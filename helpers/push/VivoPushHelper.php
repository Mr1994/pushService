<?php

namespace app\helpers\push;

use app\constants\CodeConstant;
use app\constants\CommonConstant;
use app\exception\PushHandlerException;
use app\exception\PushMsgException;
use app\helpers\HttpHelper;
use app\helpers\PrintHelper;
use app\models\KafkaPushMessage;
use app\models\PushHandlerResult;
use Exception;
use GuzzleHttp\Psr7\Request;
use Yii;
use yii\helpers\ArrayHelper;

class VivoPushHelper extends PushHelper
{
    const SEND_CONFIG_KEY = 'vivo_push';

    const CURL_CONCURRENCY = 2;

    const URL_REFRESH_TOKEN = 'https://api-push.vivo.com.cn/message/auth';
    const URL_TOKEN_EXPIRE = 86400;

    const URL_PUSH_MESSAGE_UNICAST = 'https://api-push.vivo.com.cn/message/send';
    const URL_PUSH_MESSAGE_BROADCAST = 'https://api-push.vivo.com.cn/message/pushToList';
    const URL_PUSH_MESSAGE_ALL = 'https://api-push.vivo.com.cn/message/all';
    const URL_PUSH_MESSAGE_SAVE_MESSAGE = 'https://api-push.vivo.com.cn/message/saveListPayload';

    //最大次数约束
    const MAX_BATCH_MESSAGE_NUM = 100;
    const MAX_BATCH_REG_ID_NUM  = 5000;

    private $real_batch_message = [];
    private $real_format_data   = [];

    /**
     * 返回请求闭包
     * @return \Closure
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/22 10:15 上午
     */
    public function getRequestsClosure()
    {
        return  function () {
            //构造消息体
            foreach ($this->batch_message as $push_index => $push_message) {
                $this->formatData($push_message);
            }

            //将真实的push_message和format进行处理
            $this->batch_message = $this->real_batch_message;
            $format_arr          = $this->real_format_data;
            $this->real_batch_message = $this->real_format_data = [];

            foreach($format_arr as $format_index => $format_data) {
                try {
                    $token = $this->getToken();
                }catch (Exception $e) {
                    continue;
                }
                $uri = $this->getPushUrl($format_data);
                $header = [
                    'Content-Type' => 'application/json',
                    'authToken' => $token
                ];

                // 记录请求index和push_index的对应关系
                $this->pool_index_map[$format_index] = $format_index;

                //请求
                yield new Request('post', $uri, $header, json_encode($format_data));
            }
        };
    }

    /**
     * vivo异常处理
     * @param KafkaPushMessage $push_message
     * @param $response
     * @return bool author: 卫振家
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/21 9:42 下午
     * @throws \app\exception\PushMsgException
     */
    public function afterFulfilled($push_message, $response)
    {
        // 结果处理
        $status     = $response->getStatusCode();
        $raw_result = $response->getBody()->getContents();
        PrintHelper::printInfo("请求成功， 消息id：{$push_message->request_id}, 状态码：{$status}，结果：$raw_result \n");
        $raw_result = empty($raw_result) ? [] : json_decode($raw_result, true);

        // 结果处理
        $result = PushHandlerResult::getDefaultResult($push_message, $raw_result);
        $result->code    = CodeConstant::ERROR_CODE_SYSTEM;
        $result->message = empty($raw_result['desc']) ? '未知错误' : $raw_result['desc'];

        if (!isset($raw_result['result'])) {
            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = '推送失败';
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($raw_result['result'], ['0'])) {
            $result->code = CodeConstant::SUCCESS_CODE;
            $result->message = '推送成功';
            $result->invalid_reg_id = isset($raw_result['invalidUsers']) ? array_column($raw_result['invalidUsers'], 'userid') : [];
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($raw_result['result'], ['10000'])) {
            $this->is_refresh_token = true;
            //reg_id非法
            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = '无权限请求';
            $result->need_retry = PushHandlerResult::NEED_RETRY_YES;
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($raw_result['result'], ['10302'])) {
            //reg_id非法
            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = 'regId 不合法';
            $result->invalid_reg_id = array_values($push_message->reg_id_arr);
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($raw_result['result'], ['10072'])) {
            //超频
            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = '超频';
            $result->need_retry = PushHandlerResult::NEED_RETRY_YES;
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($raw_result['result'], ['10070'])) {
            $this->setBatchLimit();
            $this->setSingleLimit();

            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = '超频';
            return $push_message->afterHandlerSend($result);
        }

        return $push_message->afterHandlerSend($result);
    }


    /**
     * 获取原声数据
     * @param KafkaPushMessage $push_message
     * @throws PushMsgException author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/13 12:01 下午
     */
    public function formatData($push_message)
    {
        if (count($push_message->reg_id_arr) == 1 && ! $this->overSingleLimit()) {
            $single_message = $this->pushSingle($push_message);
            $this->real_batch_message[] = $push_message;
            $this->real_format_data[]   = $single_message;
            return;
        }

        if(! $this->overBatchLimit()) {
            //批量消息处理
            $batch_message = $this->pushCommon($push_message);
            if(! empty($batch_message)) {
                $this->real_batch_message[] = $push_message;
                $this->real_format_data[]   = $batch_message;
            }
            return;
        }


        // 批量消息超过数量就是用单推
        if($this->overBatchLimit() && ! $this->overSingleLimit()) {
            foreach ($push_message->reg_id_arr as $unique_id => $reg_id) {
                $single_push_message = clone $push_message;
                $single_push_message->reg_id_arr = [$unique_id => $reg_id];
                $single_push_message->unique_id_arr = [$unique_id];
                $single_message = $this->pushSingle($single_push_message);
                $this->real_batch_message[] = $single_push_message;
                $this->real_format_data[]   = $single_message;
            }
            return;
        }

    }


    /**
     * @param KafkaPushMessage $push_message
     * @return mixed
     * @throws PushMsgException
     * creater: 卫振家
     * create_time: 2020/5/14 下午12:53
     */
    private function pushSingle($push_message)
    {
        $reg_id_arr = array_values($push_message->reg_id_arr);
        $reg_id = $reg_id_arr[0];

        $jump_url = self::getSchemeUrl($push_message);


        $data = [
            "regId" => $reg_id,
            "notifyType" => 1, // 通知类型 1:无，2:响铃，3:振动，4:响铃和振动
            "title" => $this->getTitle($push_message),
            "content" => $push_message->notification,
            "skipType" => 1, // 点击跳转类型 1：打开APP首页 2：打开链接 3：自定义 4:打开app内指定页面
            'requestId' => self::getVivoRequestId($push_message),
            "classification" => 0, // 消息类型 0：运营类消息，1：系统类消息。不填默认为0 运营1天只能5个
            "clientCustomMap" => ['jump_url' => $jump_url],
            "pushMode" => 0,//self::getPushModel(), // 1测试 0线上
            "extra" => self::getExtra($push_message),
        ];
        return $data;

    }

    /**
     * 推送消息
     * @param KafkaPushMessage $push_message
     * @return mixed
     * creater: 卫振家
     * create_time: 2020/5/14 下午1:57
     */
    private function pushCommon($push_message)
    {
        // 创建消息共同体
        try{
            $task_id = $this->saveListBody($push_message);
        }catch (Exception $e) {
            return [];
        }
        // 请求头部信息
        // reg_id
        $reg_id = array_values($push_message->reg_id_arr);

        $jump_url = self::getSchemeUrl($push_message);

        $data = [
            "regIds" => $reg_id,
            "taskId" => $task_id,
            "notifyType" => 1, // 通知类型 1:无，2:响铃，3:振动，4:响铃和振动
            "title" => $this->getTitle($push_message),
            "content" => $push_message->notification,
            "skipType" => 1, // 点击跳转类型 1：打开APP首页 2：打开链接 3：自定义 4:打开app内指定页面
            'requestId' => self::getVivoRequestId($push_message),
            "classification" => 0, // 消息类型 0：运营类消息，1：系统类消息。不填默认为0 运营1天只能5个
            "clientCustomMap" => ['jump_url' => $jump_url],
            "pushMode" => self::getPushModel(),// 1测试 0线上
            "extra" => self::getExtra($push_message),

        ];
        return $data;

    }

    /**
     * 构造体
     * @param KafkaPushMessage $push_message
     * @return mixed
     * creater: 卫振家
     * create_time: 2020/5/14 下午1:49
     * @throws PushHandlerException
     * @throws PushMsgException
     */
    private function saveListBody($push_message)
    {
        $jump_url = self::getSchemeUrl($push_message);

        $url_params = [
            "notifyType" => 1, // 通知类型 1:无，2:响铃，3:振动，4:响铃和振动
            "title" => $push_message->title,
            "content" => $push_message->notification,
            "skipType" => 1, // 点击跳转类型 1：打开APP首页 2：打开链接 3：自定义 4:打开app内指定页面
            'requestId' => self::getVivoRequestId($push_message),
            "classification" => 0, // 消息类型 0：运营类消息，1：系统类消息。不填默认为0 运营1天只能5个
            "clientCustomMap" => ['jump_url' => $jump_url],
            "pushMode" => self::getPushModel(), // 1测试 0线上
        ];
        // 请求头部信息
        $header['authToken'] = $this->getToken();

        // 发送请求
        try{
            $http_helper = new HttpHelper();
            $raw_result = $http_helper
                ->setTimeOut(3)
                ->setConnectTimeOut(3)
                ->setHeader($header)
                ->postJson(self::URL_PUSH_MESSAGE_SAVE_MESSAGE, $url_params, true);
        }catch (Exception $e) {
            throw new PushMsgException($push_message, "预消息处理生成失败:  {$e->getMessage()}");
        }

        //http异常处理
        $http_code = $http_helper->getStatusCode();
        $http_exp  = $http_helper->getException();
        if(empty($http_code)) {
            throw new PushMsgException($push_message, "预消息处理生成失败:  curl请求失败");
        }
        if(! empty($http_exp)) {
            throw new PushMsgException($push_message, "预消息处理生成失败:  code码:{$http_code}, 异常信息：{$http_exp->getMessage()}");
        }

        if (empty($raw_result['taskId'])) {
            $message = json_encode($raw_result, JSON_UNESCAPED_UNICODE);

            if(in_array($raw_result['result'], [10000])) {
                $this->is_refresh_token = true;
                PrintHelper::printError("auth token无效");
                $push_message->enqueue(true);
            }
            if(in_array($raw_result['result'], [10252, 10070])) {
                PrintHelper::printError("超过日最大请求次数");
                $this->setBatchLimit();
            }
            throw new PushMsgException($push_message, "预消息处理生成失败:  {$message}");
        }

        return $raw_result['taskId'];
    }

    /**
     * 获取token,将token缓存在redis里面
     * @param KafkaPushMessage $push_message
     * @return string
     * @throws PushHandlerException
     * @throws PushMsgException
     */
    public function getToken()
    {
        $app_id = ArrayHelper::getValue($this->config, 'app_id', '');
        $app_key = ArrayHelper::getValue($this->config, 'app_key', '');
        $app_secret = ArrayHelper::getValue($this->config, 'app_secret', '');
        $timestamp = intval(microtime(true) * 1000);
        $sign = md5($app_id . $app_key . $timestamp . $app_secret);
        $post = [
            'appId' => $app_id,
            'appKey' => $app_key,
            'timestamp' => $timestamp,
            'sign' => $sign,
        ];

        $key = 'vivo:' . $post['appKey'];
        if(! $this->is_refresh_token) {
            $token_in_redis = Yii::$app->redis_business->get($key);
            if (!empty($token_in_redis)) {
                return $token_in_redis;
            }
        }

        try{
            $http_helper = new HttpHelper();
            $arr_result = $http_helper
                ->setConnectTimeOut(1)->setTimeOut(1)
                ->postJson(self::URL_REFRESH_TOKEN, $post, true);
        }catch (Exception $e) {
            throw new PushHandlerException("Vivo push refreshToken Exception {$e->getMessage()}");
        }

        $http_code = $http_helper->getStatusCode();
        $http_exp  = $http_helper->getException();
        if(empty($http_code)) {
            throw new PushHandlerException("Vivo push refreshToken Exception curl请求失败");
        }

        if(! empty($http_exp)) {
            throw new PushHandlerException("Vivo push refreshToken Exception code码:{$http_code}, 异常信息：{$http_exp->getMessage()}");
        }

        if (empty($arr_result['authToken'])) {
            $message = empty($arr_result['desc']) ? '' : $arr_result['desc'];
            throw new PushHandlerException("Vivo push refreshToken Exception {$message}");
        }

        Yii::$app->redis_business->setex($key, self::URL_TOKEN_EXPIRE / 2, $arr_result['authToken']); //提前100秒过期

        $this->is_refresh_token = false;
        return $arr_result['authToken'];
    }

    /**
     * 获取vivo唯一的request_id
     * @param KafkaPushMessage $push_message
     * creater: 卫振家
     * create_time: 2020/5/14 下午4:56
     * @return string
     */
    private static function getVivoRequestId($push_message)
    {
        $push_counter = 'vivo:day_request_counter';
        $counter = Yii::$app->redis_business->incr($push_counter);
        if ($counter > 9000000) {
            Yii::$app->redis_business->set($push_counter, 0);
        }

        $rand_ext = date('Ymd') . sprintf("%07d", $counter);

        return "{$push_message->request_id }-{$rand_ext}";
    }

    /**
     * 获取跳转类型
     * @param KafkaPushMessage $push_message
     * @return int
     * creater: 卫振家
     * create_time: 2020/5/14 下午6:31
     */
    private static function getSkipType($push_message)
    {
        $scheme_url = self::getSkipContent($push_message);
        if (empty($scheme_url)) {
            return 1;
        }
        if (strpos($scheme_url, 'http') === 0) {
            return 2;
        }

        return 4;
    }

    /**
     * @param KafkaPushMessage $push_message
     * @return bool|string
     */
    private function getTitle($push_message)
    {
        return mb_substr($push_message->title, 0, 20);
    }

    /**
     * 获取跳转链接
     * @param KafkaPushMessage $push_message
     * @return mixed
     * creater: 卫振家
     * create_time: 2020/5/14 下午6:31
     */
    private static function getSkipContent($push_message)
    {
        return self::getSchemeUrl($push_message);
    }

    /**
     * @param KafkaPushMessage $push_message
     * @return array
     */
    private static function getExtra($push_message)
    {
        if(YII_ENV_PROD) {
            $call_back = "https://pushservice.julive.com/push-notify/vivo-notify";
        }else {
            $call_back = 'http://test.pushservice.julive.com/index.php/push-notify/vivo-notify';
        }

        return [
            'callback' => $call_back,
            'callback.param' => $push_message->request_id,
        ];
    }

    /**
     * @return int
     * @autor: edz sunwenke@julive.com
     * @create_time: 2020-08-03 18:58
     * 获取推送类型 0 正式 1测试
     */
    private static function getPushModel()
    {

        if (YII_ENV_PROD) {
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * 获取请求链接
     * @param $data
     * @return string
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/21 9:46 下午
     */
    public function getPushUrl($data)
    {
        if (isset($data['regId'])) {
            $url = self::URL_PUSH_MESSAGE_UNICAST;
        } else {
            $url = self::URL_PUSH_MESSAGE_BROADCAST;
        }
        return $url;
    }

    public function getRegId($data)
    {
        if (count($data['regIds']) == 1) {
            $reg_id_arr = array_values($data->reg_id_arr);
            $reg_id = $reg_id_arr[0];
        } else {
            $reg_id = array_values($data->reg_id_arr);
        }
        return $reg_id;

    }
}