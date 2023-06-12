<?php

namespace app\helpers\push;

use app\constants\AppConstant;
use app\constants\CodeConstant;
use app\constants\CommonConstant;
use app\exception\PushHandlerException;
use app\helpers\PrintHelper;
use app\models\KafkaPushMessage;
use app\models\PushHandlerResult;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use yii\helpers\ArrayHelper;

class ApplePushHelper extends PushHelper
{
    const SEND_CONFIG_KEY = 'apple_push';

    // curl并发数量
    const CURL_CONCURRENCY = 50;

    const PROD_PUSH_URL = 'https://api.push.apple.com/3/device/';
    const DEV_BUNDLE = 'com.comjia.comjiasearch-DailyBuild';
    const DEV_MJB_201002_BUNDLE = 'com.comjia.mjbsearch-DailyBuild';
    const PROD_BUNDLE = 'com.comjia.comjiasearch';
    const PROD_MJB_201004_BUNDLE = 'com.comjia.comjiasimplify';
    const PROD_MJB_201002_BUNDLE = 'com.comjia.comjiashadow';
    const MAX_BATCH_MESSAGE_NUM = 100;
    const MAX_BATCH_REG_ID_NUM = 500;

    /**
     * 构造器
     * ApplePushHelper constructor.
     * @param $julive_app_id
     */
    public function __construct($julive_app_id)
    {
        parent::__construct($julive_app_id);

        if (!defined('CURL_HTTP_VERSION_2_0')) {
            define('CURL_HTTP_VERSION_2_0', 3);
        }
    }

    /**
     * 获取guzzle客户端
     * @return mixed
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/19 6:42 下午
     * @throws PushHandlerException
     */
    public function getPushGuzzleClient()
    {
        $provider_cert_url = ArrayHelper::getValue($this->config, 'provider_cert_pem', '');
        $provider_cert_pem = \Yii::getAlias($provider_cert_url);
        if (empty($provider_cert_pem)) {
            throw new PushHandlerException('未配置到apns证书');
        }


        if (empty($this->client)) {
            $client_params = [
                'timeout' => 10,
                'cert' => $provider_cert_pem,
                'version' => 2.0,
                'verify' => false
            ];

            //增加http2 心跳选项
            if (!defined('CURLOPT_UPKEEP_INTERVAL_MS')) {
                define('CURLOPT_UPKEEP_INTERVAL_MS', 281);
            }

            $client_params['curl'] = [
                CURLOPT_UPKEEP_INTERVAL_MS => 30000,
                CURLOPT_DNS_CACHE_TIMEOUT => 30,
                //CURLMOPT_PIPELINING => CURLPIPE_MULTIPLEX,
            ];
            $this->client = new Client($client_params);
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
            $pool_index = 0;
            foreach ($this->batch_message as $push_index => $push_message) {
                try {
                    $data = $this->formatData($push_message);
                } catch (Exception $e) {
                    PrintHelper::printError("消息处理异常 {$push_message}, {$e->getMessage()}");
                    continue;
                }

                // 苹果的push一次只能发送一个
                foreach ($push_message->reg_id_arr as $unique_id => $reg_id) {
                    $uri    = $this->getPushUrl($reg_id);
                    $header = [
                        'Content-Type' => 'application/json',
                        'apns-topic'   => $this->getBundleId($data['app_id']),
                        'apns-collapse-id' => $this->getApnsCollapseId($push_message),
                        'Connection'   => 'Keep-Alive'
                    ];
                    // 记录请求index和push_index的对应关系
                    $this->pool_index_map[$pool_index++] = [
                        'push_index' => $push_index,
                        'unique_id'  => $unique_id,
                    ];
                    $post_data                           = [
                        'aps'      => $data['aps'],
                        'jump_url' => $data['jump_url'],
                    ];
                    yield new Request('post', $uri, $header, json_encode($post_data), 2.0);
                }

            }
        };
    }

    /**
     * 获取apns的重复发送id
     * @param KafkaPushMessage $push_message
     * @return string
     * Author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020-12-23 09:57
     */
    public function getApnsCollapseId(KafkaPushMessage $push_message)
    {
        return $push_message->request_id;
    }

    /**
     *
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/24 5:53 上午
     * @param $index
     * @return mixed|null
     */
    public function getPoolPushMessage($index)
    {
        // 索引
        $push_index = $this->pool_index_map[$index]['push_index'];
        $unique_id  = $this->pool_index_map[$index]['unique_id'];
        $reg_id     = $this->batch_message[$push_index]->reg_id_arr[$unique_id];

        // 数据构造
        $push_message                = clone($this->batch_message[$push_index]);
        $push_message->unique_id_arr = [$unique_id];
        $push_message->reg_id_arr    = [$unique_id => $reg_id];

        return $push_message;
    }

    /**
     * vivo异常处理
     * @param KafkaPushMessage $push_message
     * @param $response
     * @return bool author: 卫振家
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/21 9:42 下午
     */
    public function afterFulfilled($push_message, $response)
    {
        // 锚定消息
        $request_id = $push_message->request_id;
        $unique_id  = $push_message->unique_id_arr[0];
        $reg_id     = $push_message->reg_id_arr[$unique_id];

        // 结果处理
        $status     = $response->getStatusCode();
        $raw_result = $response->getBody()->getContents();
        PrintHelper::printInfo("请求成功， 消息id：{$request_id},unique_id:{$unique_id}, reg_id:{$reg_id}, 状态码：{$status}，结果：$raw_result \n");
        return true;
    }

    /**
     * 拒绝处理
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/22 9:56 上午
     * @param KafkaPushMessage $push_message
     * @param $reason
     * @return bool
     */
    public function afterReject($push_message, $reason)
    {
        // 锚定消息
        $request_id = $push_message->request_id;
        $unique_id  = $push_message->unique_id_arr[0];
        $reg_id     = $push_message->reg_id_arr[$unique_id];

        // 结果处理
        $code    = $reason->getCode();
        $message = $reason->getMessage();
        PrintHelper::printInfo("请求失败， 消息id：{$request_id},unique_id:{$unique_id}, reg_id:{$reg_id}, 失败码：{$code}, 失败原因：{$message} \n");

        //reg_id非法
        $result          = PushHandlerResult::getDefaultResult($push_message, []);
        $result->code    = CodeConstant::ERROR_CODE_SYSTEM;
        $result->message = $message;

        if (in_array($code, [410])) {
            $result->code           = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message        = 'reg_id非法';
            $result->invalid_reg_id = array_values($push_message->reg_id_arr);
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($code, [0])) {
            $result->code       = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message    = '苹果请求失败';
            $result->need_retry = CommonConstant::COMMON_STATUS_YES;

            //使用新的client，避免频繁超时
            $this->client = null;
            return $push_message->afterHandlerSend($result);
        }

        return $push_message->afterHandlerSend($result);
    }

    /**
     * @param KafkaPushMessage $push_message
     * @return array[]
     * @autor: julive sunwenke@julive.com
     * @create_time: 2020/10/12 5:37 下午
     * 格式化发送数据
     */
    public function formatData($push_message)
    {
        $scheme_url = self::getSchemeUrl($push_message);
        $image_url  = self::getImageUrl($push_message);
        return [
            "aps"      => [
                'alert' => [
                    'title' => $push_message->title,
                    'body'  => $push_message->notification,
                    'launch-image' => $image_url,
                ],
                "sound" => $push_message->getSound(),
                "badge" => 1,

            ],
            'jump_url' => $scheme_url,
            'app_id'   => $push_message->julive_app_id,
        ];
    }

    /**
     * @param $reg_id
     * @return string
     * @throws PushHandlerException
     * @autor: julive sunwenke@julive.com
     * @create_time: 2020/10/12 5:37 下午
     * 获取发送url
     */
    public function getPushUrl($reg_id)
    {
        if (empty($reg_id)) {
            throw new PushHandlerException('未查到推送token');
        }
        $uri = self::PROD_PUSH_URL . $reg_id;

        return $uri;
    }

    // 获取测试包的bundleid
    public function getBundleId($app_id)
    {

        if (YII_ENV_PROD) {
            $bundle = [
                AppConstant::APP_COMJIA_IOS           => self::PROD_BUNDLE,
                AppConstant::APP_COMJIA_IOS_MAJIA_004 => self::PROD_MJB_201004_BUNDLE,
                AppConstant::APP_COMJIA_IOS_MAJIA_002 => self::PROD_MJB_201002_BUNDLE,
            ];
        } else {
            $bundle = [
                AppConstant::APP_COMJIA_IOS           => self::DEV_BUNDLE,
                AppConstant::APP_COMJIA_IOS_MAJIA_004 => self::DEV_BUNDLE,
                AppConstant::APP_COMJIA_IOS_MAJIA_002 => self::DEV_MJB_201002_BUNDLE,
            ];
        }
        return $bundle[$app_id];
    }
}