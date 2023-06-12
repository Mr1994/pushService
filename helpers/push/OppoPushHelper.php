<?php

namespace app\helpers\push;

use app\constants\CodeConstant;
use app\constants\CommonConstant;
use app\exception\PushHandlerException;
use app\exception\PushMsgException;
use app\helpers\HttpHelper;
use app\helpers\PrintHelper;
use app\helpers\PusherHelper;
use app\models\KafkaPushMessage;
use app\models\PushHandlerResult;
use Exception;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Yii;
use yii\helpers\ArrayHelper;

class OppoPushHelper extends PushHelper
{
    /**
     * 居理app_id
     * @var
     */
    const SEND_CONFIG_KEY = 'oppo_push';

    // curl并发数量
    const CURL_CONCURRENCY = 2;

    const URL_REFRESH_TOKEN = 'https://api.push.oppomobile.com/server/v1/auth';
    const URL_TOKEN_EXPIRE = 3600;

    const URL_PUSH_MESSAGE_UNICAST = 'https://api.push.oppomobile.com/server/v1/message/notification/unicast'; // 单推
    const URL_PUSH_MESSAGE_BROADCAST = 'https://api.push.oppomobile.com/server/v1/message/notification/broadcast'; // 批量推送
    const URL_PUSH_UNICAST_BATCH = 'https://api.push.oppomobile.com/server/v1/message/notification/unicast_batch';
    const URL_PUSH_MESSAGE_SAVE_MESSAGE = 'https://api.push.oppomobile.com/server/v1/message/notification/save_message_content';

    const URL_PUSH_FEED_BACK_INVALID_REG_ID = 'https://feedback.push.oppomobile.com/server/v1/feedback/fetch_invalid_regidList';

    //最大次数约束
    const MAX_BATCH_MESSAGE_NUM = 100;
    const MAX_BATCH_REG_ID_NUM  = 5000;

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
            $pool_index = 0;
            foreach ($this->batch_message as $push_index => $push_message) {
                // 获取token
                try {
                    $token = $this->getToken();
                }catch (Exception $e) {
                    PrintHelper::printError("消息处理异常 {$push_message}, {$e->getMessage()}");
                    continue;
                }

                //获取消息题
                $data = $this->formatData($push_message);
                if(empty($data)) {
                    continue;
                }

                $uri = $this->getPushUrl($data);
                $header = [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'auth_token' => $token
                ];

                // 记录请求index和push_index的对应关系
                $this->pool_index_map[$pool_index++] = $push_index;
                yield new Request('post', $uri, $header, http_build_query($data));
            }
        };
    }


    /**
     * 异常处理
     * @param KafkaPushMessage $push_message
     * @param ResponseInterface $response
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

        //生成结果对象
        $result = PushHandlerResult::getDefaultResult($push_message, $raw_result);
        $result->code   = CodeConstant::ERROR_CODE_SYSTEM;
        $result->message = empty($raw_result['message']) ? '请求失败' : $raw_result['message'];

        //无结果处理
        if (!isset($raw_result['code'])) {
            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = '推送失败';
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($raw_result['code'], ['0'])) {
            $result->code = CodeConstant::SUCCESS_CODE;
            $result->message = '推送成功';
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($raw_result['code'], ['10000'])) {
            //reg_id非法
            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = 'regId 不合法';
            $result->invalid_reg_id = array_values($push_message->reg_id_arr);
            return $push_message->afterHandlerSend($result);
        }

        if(in_array($raw_result['code'], [11])) {
            //刷新token
            $this->is_refresh_token = true;

            //返回信息
            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = '无权限请求';
            $result->need_retry = PushHandlerResult::NEED_RETRY_YES;
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($raw_result['code'], ['13'])) {
            //分钟超频
            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = '超频';
            $result->need_retry = PushHandlerResult::NEED_RETRY_YES;
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($raw_result['code'], ['33'])) {
            //日超频
            $this->setBatchLimit();
            $this->setSingleLimit();

            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = '超频';
        }

        //消息收尾处理
        return $push_message->afterHandlerSend($result);
    }

    /**
     * 获取原声数据
     * @param $push_message
     * @return array|mixed
     * @throws PushHandlerException
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/13 12:01 下午
     */
    public function formatData($push_message)
    {
        if (count($push_message->reg_id_arr) == 1) {
            return $this->pushSingle($push_message);
        } else {
            return $this->pushCommon($push_message);
        }
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
        $app_key = ArrayHelper::getValue($this->config, 'app_key', '');
        $master_secret = ArrayHelper::getValue($this->config, 'master_secret', '');
        $timestamp = time() * 1000;
        $sign = hash("sha256", $app_key . $timestamp . $master_secret);

        $post = [
            'app_key' => $app_key,
            'sign' => $sign,
            'timestamp' => $timestamp,
        ];

        $cache_key = 'oppo:' . $post['app_key'];
        if(! $this->is_refresh_token) {
            $token_in_redis = Yii::$app->redis_business->get($cache_key);
            if (!empty($token_in_redis)) {
                return $token_in_redis;
            }
        }
        try{
            $http_helper = new HttpHelper();
            $arr_result  = $http_helper
                ->setConnectTimeOut(1)->setTimeOut(1)
                ->postForm(self::URL_REFRESH_TOKEN, $post, true);
        }catch (Exception $e) {
            throw new PushHandlerException("Oppo push refreshToken Exception {$e->getMessage()}");
        }

        //http异常处理
        $http_code = $http_helper->getStatusCode();
        $http_exp  = $http_helper->getException();
        if(empty($http_code)) {
            throw new PushHandlerException("Oppo push refreshToken Exception:  curl请求失败");
        }
        if(! empty($http_exp)) {
            throw new PushHandlerException("Oppo push refreshToken Exception:  code码:{$http_code}, 异常信息：{$http_exp->getMessage()}");
        }

        // 正常处理
        if (empty($arr_result['data']) || empty($arr_result['data']['auth_token'])) {
            $message = json_encode($arr_result, JSON_UNESCAPED_UNICODE);
            throw new PushHandlerException("Oppo push refreshToken Exception {$message}");
        }

        Yii::$app->redis_business->setex($cache_key, self::URL_TOKEN_EXPIRE / 2, $arr_result['data']['auth_token']); //提前100秒过期
        $this->is_refresh_token = false;
        return $arr_result['data']['auth_token'];
    }


    /**
     * @param KafkaPushMessage $push_message
     * @return mixed
     * creater: 卫振家
     * create_time: 2020/5/14 下午12:53
     */
    private function pushSingle($push_message)
    {
        // 获取本日的限速情况
        if($this->overSingleLimit()) {
            PrintHelper::printInfo("request_id:{$push_message->request_id}, 本日预消息处理已超出频率，oppo官方要求不再调用");
            return [];
        }

        // 获取权限令牌
        $reg_id_arr = array_values($push_message->reg_id_arr);
        $reg_id = $reg_id_arr[0];

        $data = [
            'target_type' => 2, // 使用registration_id推送 2,别名推送alias_name 3
            'target_value' => $reg_id, // 推送目标用户
            'notification' => self::getNotify($push_message),
        ];
        $urlParams = [
            'message' => json_encode($data),
        ];
        return $urlParams;

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
        if($this->overBatchLimit()) {
            PrintHelper::printInfo("request_id:{$push_message->request_id}, 本日预消息处理已超出频率，oppo官方要求不再调用");
            return [];
        }

        // 创建消息共同体
        try{
            $message_id = $this->saveListBody($push_message);
        }catch (Exception $e) {
            return [];
        }


        // reg_id 数组
        $reg_id_arr = array_values($push_message->reg_id_arr);

        // 请求参数
        $url_params = [
            'message_id' => $message_id,
            'target_type' => 2,
            'target_value' => implode(';', $reg_id_arr),
            'notification' => self::getNotify($push_message)
        ];

        return $url_params;

    }

    /**
     * @param $data
     * @return string
     * @autor: julive sunwenke@julive.com
     * @create_time: 2020/9/18 11:44 上午
     * 获取push发送地址
     */
    public function getPushUrl($data)
    {
        if (isset($data['message_id'])) {
            $url = self::URL_PUSH_MESSAGE_BROADCAST; // 批量
        } else {
            $url = self::URL_PUSH_MESSAGE_UNICAST; // 单推
        }
        return $url;
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
        $url_params = self::getNotify($push_message);

        // 请求头部信息
        $header['auth_token'] = $this->getToken();

        // 发送请求
        try{
            $http_helper = new HttpHelper();
            $raw_result = $http_helper
                ->setTimeOut(3)
                ->setConnectTimeOut(3)
                ->setHeader($header)
                ->postForm(self::URL_PUSH_MESSAGE_SAVE_MESSAGE, $url_params, true);
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

        // 正常处理
        if (empty($raw_result['data']) || empty($raw_result['data']['message_id'])) {
            $message = json_encode($raw_result, JSON_UNESCAPED_UNICODE);

            if(in_array($raw_result['code'], [33])) {
                $this->setBatchLimit();
            }
            if(in_array($raw_result['code'], [11])) {
                $this->is_refresh_token = true;
                PrintHelper::printError("token无效");
                $push_message->enqueue(true);
            }
            throw new PushMsgException($push_message, "预消息处理生成失败:  {$message}");
        }
        return $raw_result['data']['message_id'];
    }

    /**
     * 获取提醒消息体
     * @param KafkaPushMessage $push_message
     * @return array
     * creater: 卫振家
     * create_time: 2020/5/14 下午2:12
     */
    private static function getNotify($push_message)
    {

        $scheme_url = self::getSchemeUrl($push_message);

        if(YII_ENV_PROD) {
            $call_back = "https://pushservice.julive.com/push-notify/oppo-notify";
        }else {
            $call_back = 'http://test.pushservice.julive.com/index.php/push-notify/oppo-notify';
        }

        $notify = [
            'style' => 1,
            'title' => $push_message->title,
            'sub_title' => '居理新房', //副标题
            'content' => $push_message->notification,
            'click_action_type' => '5',
            'click_action_url' => "push://app.comjia.com/oppopush?jump_url={$scheme_url}",
            "call_back_url" => $call_back,
            "call_back_parameter" => $push_message->request_id,

        ];


        /**
         * if (array_keys($param)[0] == 'intent') {
         * // 打开自定义APP页面
         * $actionType = 5;
         * $actionKey  = 'click_action_url';
         * $actionVal  = $param['intent'];
         * } elseif (array_keys($param)[0] == 'url') {
         * // 打开指定url
         * $actionType = 2;
         * $actionKey  = 'click_action_url';
         * $actionVal  = $param['url'];
         * } else {
         * // 打开APP首页
         * $actionType = 0;
         * $actionKey  = '';
         * $actionVal  = '';
         * }**/

        return $notify;
    }
}