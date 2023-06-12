<?php

namespace app\helpers\push;

use app\constants\CodeConstant;
use app\constants\PushConstant;
use app\exception\PushHandlerException;
use app\helpers\HttpHelper;
use app\helpers\PrintHelper;
use app\helpers\PusherHelper;
use app\models\KafkaPushMessage;
use app\models\PushHandlerResult;
use Exception;
use GuzzleHttp\Psr7\Request;
use Yii;
use yii\helpers\ArrayHelper;

class HuaweiPushHelper extends PushHelper
{
    /**
     * 居理app_id
     * @var
     */
    const SEND_CONFIG_KEY = 'huawei_push';

    // curl并发数量
    const CURL_CONCURRENCY = 2;

    const URL_REFRESH_TOKEN = 'https://login.cloud.huawei.com/oauth2/v2/token';
    const URL_REFRESH_TOKEN_V3 = 'https://oauth-login.cloud.huawei.com/oauth2/v3/token';

    const URL_PUSH_MESSAGE = 'https://api.push.hicloud.com/pushsend.do';
    const URL_PUSH_MESSAGE_V2 = 'https://push-api.cloud.huawei.com/v1/';

    const TIME_WINDOW = 1;
    const TIME_WINDOW_LIMIT = 3000;

    const MAX_BATCH_MESSAGE_NUM = 100;
    const MAX_BATCH_REG_ID_NUM = 1000;

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
                try {
                    $uri   = $this->getPushUrlV2();
                    $data  = $this->formatData($push_message);
                    $token = $this->getToken();
                    $header = [
                        'Content-Type' => 'application/json',
                        'Authorization' => $token
                    ];
                }catch (Exception $e) {
                    PrintHelper::printError("消息处理异常 {$push_message}, {$e->getMessage()}");
                    continue;
                }

                //批量限速
                //self::rateLimitByRegId($this->push_handler_id, self::TIME_WINDOW, self::TIME_WINDOW_LIMIT, count($push_message->reg_id_arr));

                // 记录请求index和push_index的对应关系
                $this->pool_index_map[$pool_index++] = $push_index;
                yield new Request('post', $uri, $header, json_encode($data));
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
     * @throws PushHandlerException
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
        $result->message = empty($raw_result['msg']) ? '请求失败' : $raw_result['msg'];

        //无结果处理
        if (!isset($raw_result['code'])) {
            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = '推送失败';
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($raw_result['code'], ['80000000'])) {
            $result->code = CodeConstant::SUCCESS_CODE;
            $result->message = '推送成功';
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($raw_result['code'], ['80200001','80200003'])) {
            //设置需要更新token
            $this->is_refresh_token = true;

            //reg_id非法
            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = '无权限请求';
            $result->need_retry = PushHandlerResult::NEED_RETRY_YES;
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($raw_result['code'], ['80100000'])) {
            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = '部分Token发送成功，返回的illegal_tokens为不合法而发送失败的Token。';

            $msg_obj = empty($raw_result['msg']) ? [] : json_decode($raw_result['msg'], true);
            $result->invalid_reg_id = empty($msg_obj['illegal_tokens']) ? [] : $msg_obj['illegal_tokens'];
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($raw_result['code'], ['80300007'])) {
            $result->code = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message = '所有Token都是无效的';
            $result->invalid_reg_id = array_values($push_message->reg_id_arr);
            return $push_message->afterHandlerSend($result);
        }

        return $push_message->afterHandlerSend($result);
    }

    /**
     * @param $push_message
     * @return array[]
     * @autor: julive sunwenke@julive.com
     * @create_time: 2020/8/18 3:28 下午
     * 格式化华为push发送
     */
    private function formatData($push_message)
    {
        //待发送用户
        $reg_id_arr = array_values($push_message->reg_id_arr);
        $device_token_arr = array_unique(array_filter($reg_id_arr));

        if (isset($push_message->push_config['business_push']) && (isset($push_message->push_config['passThrough']) && $push_message->push_config['passThrough'] == PushConstant::PASSTHROUGH)) {
            $data = $this->getTransmission($push_message, $device_token_arr);
        } else {
            $data = $this->getNotification($push_message, $device_token_arr);
        }
        return $data;
    }

    /**
     * @param $push_message
     * @return array
     * @autor: julive sunwenke@julive.com
     * @create_time: 2020/9/28 10:30 上午
     * 发送透传
     */
    public function getTransmission($push_message, $device_token_arr)
    {
        $jump_url = self::getSchemeUrl($push_message);

        return [
            'message' => [
                'token' => $device_token_arr,
                'data' => json_encode([['jump_url' => $jump_url]])
            ],
//            'validate_only'=>true
        ];

    }

    /**
     * @param KafkaPushMessage $push_message
     * @param $device_token_arr
     * @return array[]
     * @autor: julive sunwenke@julive.com
     * @create_time: 2020/9/28 10:28 上午
     * 获取push通知栏
     */
    public function getNotification($push_message, $device_token_arr)
    {
        $jump_url = self::getSchemeUrl($push_message);
        $image_url = self::getImageUrl($push_message);

        $data = [
            'message' => [
                'token'        => $device_token_arr,
                'notification' => ['title' => $push_message->title, 'body' => $push_message->notification],
                'android'      => [
                    'notification' =>
                        [
                            'title'         => $push_message->title,
                            'body'          => $push_message->notification,
                            'channel_id'    => $push_message->getChanleId(),
                            'default_sound' => $push_message->getDefaultSound(),
                            'notify_id'     => crc32($push_message->request_id),
                            'importance' =>'HIGH',
                            'click_action'  => [
                                'type'   => 1,
                                'intent' => "intent://app.comjia.com/huaweipush?jump_url={$jump_url}#Intent;scheme=push;launchFlags=0x20000000;end"
                            ],
                            'badge'         => [
                                'add_num' => 1,
                                'class'   => 'com.comjia.kanjiaestate.home.view.activity.SplashActivity',
                                'set_num' => 0
                            ],
                            'image' => $image_url,

                        ],
                ]
            ],
//            'validate_only'=>true
        ];
        return $data;
    }

    /**
     * 获取token,将token缓存在redis里面
     * @return string
     * @throws PushHandlerException
     * @throws \app\exception\PushMsgException
     */
    public function getToken()
    {
        $post = [
            'grant_type' => 'client_credentials',
        ];

        $post['client_id'] = ArrayHelper::getValue($this->config, 'app_id', '');
        $post['client_secret'] = ArrayHelper::getValue($this->config, 'app_secret', '');

        // 如果要不需要刷新token，则从缓存中获取token
        $key = 'hw_token_' . $post['client_id'];
        if(! $this->is_refresh_token) {
            $token_in_redis = Yii::$app->redis_business->get($key);
            if (!empty($token_in_redis)) {
                return $token_in_redis;
            }
        }

        // 获取token
        try{
            $http_helper = new HttpHelper();
            $arr_result  = $http_helper
                ->setConnectTimeOut(1)
                ->setTimeOut(1)
                ->postForm(self::URL_REFRESH_TOKEN_V3, $post, true);
        }catch (Exception $e) {
            throw new PushHandlerException("Huawei push refreshToken Exception {$e->getMessage()}");
        }

        //http异常处理
        $http_code = $http_helper->getStatusCode();
        $http_exp  = $http_helper->getException();
        if(empty($http_code)) {
            throw new PushHandlerException( "Huawei push refreshToken Exception:  curl请求失败");
        }
        if(! empty($http_exp)) {
            throw new PushHandlerException( "Huawei push refreshToken Exception:  code码:{$http_code}, 异常信息：{$http_exp->getMessage()}");
        }

        //http正常处理
        if(empty($arr_result['access_token'])) {
            $message = json_encode($arr_result, JSON_UNESCAPED_UNICODE);
            throw new PushHandlerException( "Huawei push refreshToken Exception $message");
        }

        // 缓存token
        Yii::$app->redis_business->setex($key, $arr_result['expires_in'] / 2, $arr_result['access_token']); //提前100秒过期

        // 设置不需要刷新缓存
        $this->is_refresh_token = false;

        return ArrayHelper::getValue($arr_result, 'access_token', 'xxxxxxxxxxxxxx');
    }

    /**
     * 获取透传消息
     * @param $push_message
     * @return false|string
     */
    public function getPayLoad($push_message)
    {
        $jump_url = self::getSchemeUrl($push_message);

        $payload = [
            'hps' => [
                'msg' => [
                    'type' => 3,
                    'body' => ['title' => $push_message->title, 'content' => $push_message->notification],
                    'action' => [
                        'type' => 1,
                        'param' => ['intent' => "intent://app.comjia.com/huaweipush?jump_url={$jump_url}#Intent;scheme=push;launchFlags=0x20000000;end"]
                    ],
                    'BadgeNotification' => [
                        'add_num' => 1,
                        'class' => 'com.comjia.kanjiaestate.debug.SplashActivity',
//                        'set_num' => 1
                    ]


                ],
                'ext' => [
                    'customize' => [['jump_url' => $jump_url]]
                ]
                ,
                'BadgeNotification' => [
                    'add_num' => 1,
                    'class' => 'com.example.hmstest.MainActivity',
                    'set_num' => '1'
                ]
            ]
        ];
        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }


    private function getPushUrlV2()
    {
        //获取url
        $app_id = ArrayHelper::getValue($this->config, 'app_id', '');
        return self::URL_PUSH_MESSAGE_V2 . $app_id . '/messages:send';
    }

}