<?php


namespace app\helpers\push;

use app\constants\CodeConstant;
use app\constants\CommonConstant;
use app\helpers\HttpHelper;
use app\helpers\PrintHelper;
use app\models\KafkaPushMessage;
use app\models\PushHandlerResult;
use Exception;
use GuzzleHttp\Psr7\Request;
use JPush\Client;
use JPush\PushPayload;
use yii\helpers\ArrayHelper;

class JiguangPushHelper extends PushHelper
{
    const SEND_CONFIG_KEY = 'jiguang_push';

    // curl并发数量
    const CURL_CONCURRENCY = 1;

    const PUSH_URL = 'https://api.jpush.cn/v3/push';

    const TIME_WINDOW = 60;
    const TIME_WINDOW_LIMIT = 550;

    const MAX_BATCH_MESSAGE_NUM = 8;
    const MAX_BATCH_REG_ID_NUM = 500;

    /**
     * 发送消息
     * @param KafkaPushMessage $push_message
     * @return void
     * @throws \app\exception\PushMsgException
     */
    public function send($push_message) {
        //记录次数
        $push_message->addTopicGroupTypeDaySendNum();

        // 批次
        $uri    = self::PUSH_URL;

        $app_key       = ArrayHelper::getValue($this->config, 'app_key', '');
        $master_secret = ArrayHelper::getValue($this->config, 'app_secret', '');

        $authorization = base64_encode("{$app_key}:{$master_secret}");
        $header = [
            'Content-Type' => 'application/json',
            'Connection' =>  'Keep-Alive',
            'Authorization' => "Basic {$authorization}"
        ];

        try{
            $data = $this->formatData($push_message);
        }catch (Exception $e) {
            PrintHelper::printError("消息处理异常 {$push_message}, {$e->getMessage()}");
            return;
        }

        //结果
        $result  = PushHandlerResult::getDefaultResult($push_message, ['code' => 0, 'message' => '未知错误']);

        //调用 push
        try{
            $http_helper = new HttpHelper();
            $result->raw_result = $http_helper->setHeader($header)
                ->setConnectTimeOut(1)
                ->setTimeOut(1)
                ->postJson($uri, $data, false);
        }catch (Exception $e) {
            $result->code       = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message    = $e->getMessage();
            $result->need_retry = CommonConstant::COMMON_STATUS_YES;
            $push_message->afterHandlerSend($result);
            return;
        }

        // 消息
        $message = empty($http_helper->getException()) ? '未知异常' : $http_helper->getException()->getMessage();
        // 获取http结果
        $http_code = $http_helper->getStatusCode();
        $header    = $http_helper->getResponseHeader();
        if(empty($http_code) || empty($header)) {
            $result->code       = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message    = $message;
            $result->need_retry = CommonConstant::COMMON_STATUS_YES;
            $push_message->afterHandlerSend($result);
            return;
        }

        // 限速处理
        $json_header = json_encode($header);

        // 查看是否有问题
        if($header['X-Rate-Limit-Limit'] < 100) {
            // 运行id
            $env = YII_ENV;

            //公共报警信息
            $error_message =
                "### 极光推送每分钟可发送格式小于600 !!!报警：\n" .
                "------------\n\n" .
                "**开发环境**：{$env} \n\n" .
                "**当前限速**：{$header['X-Rate-Limit-Limit']}/分钟 \n\n";

            PrintHelper::printDing($error_message);
        }

        PrintHelper::printDebug("request_id:{$push_message->request_id}发送结果\nresult:{$result}\nheader:{$json_header}\n");
        PrintHelper::printDebug("本时间段剩余：{$header['X-Rate-Limit-Remaining']} : {$header['X-Rate-Limit-Reset']}秒后重置次数");
        $limit_push_num = $header['X-Rate-Limit-Remaining'];
        if($limit_push_num < 1) {
            PrintHelper::printError("停止运行：{$header['X-Rate-Limit-Reset']}秒");
            sleep($header['X-Rate-Limit-Reset']);
        }

        // 如果成功就返回
        if(in_array($http_code, [200])) {
            $result->code           = CodeConstant::SUCCESS_CODE;
            $result->message        = '发送成功';
            $push_message->afterHandlerSend($result);
            return;

        }
        if (in_array($http_code, [400]) && strpos($message, '"code":1003') !== false) {
            $result->code           = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message        = 'reg_id非法';
            $result->invalid_reg_id = array_values($push_message->reg_id_arr);
            $push_message->afterHandlerSend($result);
            return;
        }

        if (in_array($http_code, [400]) && strpos($message, '"code":1011') !== false) {
            $result->code           = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message        = 'reg_id非法';
            $result->invalid_reg_id = array_values($push_message->reg_id_arr);

            $push_message->afterHandlerSend($result);
            return;
        }

        if (in_array($http_code, [429])) {
            $result->code       = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message    = '限速，需要重试';
            $result->need_retry = CommonConstant::COMMON_STATUS_YES;

            $push_message->afterHandlerSend($result);
            return;
        }

        $result->code    = CodeConstant::ERROR_CODE_SYSTEM;
        $result->message = $message;
        $push_message->afterHandlerSend($result);
    }

    /**
     * 获取guzzle客户端
     * @return mixed
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/19 6:42 下午
     */
    public function getPushGuzzleClient()
    {
        // 获取client
        $app_key       = ArrayHelper::getValue($this->config, 'app_key', '');
        $master_secret = ArrayHelper::getValue($this->config, 'app_secret', '');
        if (empty($this->client)) {
            $this->client = new \GuzzleHttp\Client(['timeout' => 1, 'auth' => [$app_key, $master_secret]]);
        }
        return $this->client;
    }


    /**
     * 返回请求闭包
     * @return \Closure
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/22 10:15 上午
     */
    public function getRequestsClosure()
    {
        return function () {
            // 批次
            $uri    = self::PUSH_URL;
            $header = [
                'Content-Type: application/json',
                'Connection: Keep-Alive'
            ];
            $pool_index = 0;
            foreach ($this->batch_message as $push_index => $push_message) {
                try {
                    $data = $this->formatData($push_message);
                }catch (Exception $e) {
                    PrintHelper::printError("消息处理异常 {$push_message}, {$e->getMessage()}");
                    continue;
                }

                // 限速
                self::rateLimit($this->push_handler_id, self::TIME_WINDOW, self::TIME_WINDOW_LIMIT, 1);


                // 记录请求index和push_index的对应关系
                $this->pool_index_map[$pool_index++] = $push_index;
                yield new Request('post', $uri, $header, json_encode($data));
            }
        };
    }

    /**
     * 拒绝处理
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/22 9:56 上午
     * @param KafkaPushMessage $push_message
     * @param $reason
     * @return bool
     * @throws \app\exception\PushMsgException
     */
    public function afterReject($push_message, $reason)
    {
        $request_id = $push_message->request_id;

        // 结果处理
        $code = $reason->getCode();
        $message = $reason->getMessage();
        PrintHelper::printInfo("请求失败， 消息id：{$request_id}, 失败码：{$code}, 失败原因：{$message} \n");

        //初始化返回值
        $result = PushHandlerResult::getDefaultResult($push_message, []);
        $result->code    = CodeConstant::ERROR_CODE_SYSTEM;
        $result->message = $message;

        //reg_id非法
        if (in_array($code, [400]) && strpos($message, '"code":1011,') !== false) {
            $result->code           = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message        = 'reg_id非法';
            $result->invalid_reg_id = array_values($push_message->reg_id_arr);
            //消息收尾处理
            return $push_message->afterHandlerSend($result);
        }

        //极光限速
        if (in_array($code, [429])) {
            $result->code       = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message    = '限速，需要重试';
            $result->need_retry = CommonConstant::COMMON_STATUS_YES;
            //消息收尾处理
            return $push_message->afterHandlerSend($result);
        }

        //消息收尾处理
        return $push_message->afterHandlerSend($result);
    }


    /**
     * 格式化数据
     * @param KafkaPushMessage $push_message
     * @return array
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/15 10:29 上午
     */
    public function formatData(KafkaPushMessage $push_message)
    {
        //待发送用户
        $reg_id_arr = array_values($push_message->reg_id_arr);
        $reg_id_arr = array_unique(array_filter($reg_id_arr));

        $pusher = $this->getPusher();
        $pusher->setPlatform('all');
        // 格式化推送目标
        self::_formatAudience($pusher, ['registration_id' => $reg_id_arr]);

        //设置选项
        $options = [
            'apns_production' => true,
            'time_to_live'    => ArrayHelper::getValue($push_message->push_params, 'time_to_live', 86400),
        ];
        $pusher->options($options);

        // 通知参数
        $jump_url = self::getSchemeUrl($push_message);

        $extra = [];
        if (!empty($jump_url)) {
            $extra['jump_url'] = $jump_url;
        }

        // ios消息体
        $ios_notification = [
            'alert'           => [
                'title'    => $push_message->title,
                'subtitle' => '',
                'body'     => $push_message->notification,
            ],
            'extras'          => $extra,
            'sound'           => 'default',
            'badge'           => 1,
            'mutable-content' => true //需要加上这个参数，才有消息送达统计
        ];
        $pusher->iosNotification($ios_notification['alert'], $ios_notification);
        // 安卓消息体
        $android_notification = [
            'title'  => $push_message->title,
            'alert'  => $push_message->notification,
            'extras' => $extra,
        ];

        if (isset($push_message->push_config['business_push'])) {
            $pusher->message($push_message->notification, [
                'title'        => empty($push_message->title) ? '居理新房' : $push_message->title,
                'content_type' => 'text',
                'extras'       => $extra
            ]);
        } else {
            $pusher->androidNotification($android_notification['alert'], $android_notification);
        }


        return $pusher->build();
    }

    /**
     * 获取基础类
     * @return PushPayload|null
     * creater: 卫振家
     * create_time: 2020/5/7 下午7:12
     */
    private function getPusher()
    {
        return (new Client($this->config['app_key'], $this->config['app_secret'], null))->push();
    }

    /**
     * 格式化推送目标
     * @param PushPayload $pusher
     * @param array $push_target
     * @return bool [type] [description]
     */
    private static function _formatAudience(&$pusher, $push_target)
    {
        if (empty($push_target)) {
            $pusher->addAllAudience();
            return true;
        }
        if (!empty($push_target['registration_id'])) {
            $pusher->addRegistrationId($push_target['registration_id']);
        }
        if (!empty($push_target['tag'])) {
            $pusher->addTag($push_target['tag']);
        }
        if (!empty($push_target['tag_and'])) {
            $pusher->addTagAnd($push_target['tag_and']);
        }
        if (!empty($push_target['tag_not'])) {
            $pusher->addTagNot($push_target['tag_not']);
        }
        return true;
    }


}