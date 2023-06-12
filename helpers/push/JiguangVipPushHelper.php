<?php


namespace app\helpers\push;

use app\constants\CodeConstant;
use app\constants\CommonConstant;
use app\helpers\PrintHelper;
use app\helpers\PusherHelper;
use app\models\KafkaPushMessage;
use app\models\PushHandlerResult;
use Exception;
use GuzzleHttp\Psr7\Request;
use JPush\Client;
use JPush\PushPayload;
use yii\helpers\ArrayHelper;

class JiguangVipPushHelper extends PushHelper
{
    const SEND_CONFIG_KEY = 'jiguang_push';

    // curl并发数量
    const CURL_CONCURRENCY = 3;

    const PUSH_URL='https://api.jpush.cn/v3/push';

    const TIME_WINDOW       = 10;
    const TIME_WINDOW_LIMIT = 80;

    const MAX_BATCH_MESSAGE_NUM = 8;
    const MAX_BATCH_REG_ID_NUM  = 5000;

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
        return  function () {
            // 批次
            $uri = self::PUSH_URL;
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

            return $push_message->afterHandlerSend($result);
        }

        if (in_array($code, [429])) {
            $result->code       = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message    = '限速，需要重试';
            $result->need_retry = CommonConstant::COMMON_STATUS_YES;
            return $push_message->afterHandlerSend($result);
        }

        return $push_message->afterHandlerSend($result);
    }


    /**
     * 格式化数据
     * @param $push_message
     * @return array
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/15 10:29 上午
     */
    public function formatData($push_message)
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
        $extra    = [];
        $jump_url = self::getSchemeUrl($push_message);
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
        $ios_data         = $pusher->iosNotification($ios_notification['alert'], $ios_notification);
        // 安卓消息体
        $android_notification = [
            'title'  => $push_message->title,
            'alert'  => $push_message->notification,
            'extras' => $extra,
        ];
        $pusher->androidNotification($android_notification['alert'], $android_notification);
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