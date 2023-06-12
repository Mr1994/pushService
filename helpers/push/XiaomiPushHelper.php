<?php

namespace app\helpers\push;

use app\constants\AppConstant;
use app\constants\CodeConstant;
use app\constants\PushConstant;
use app\helpers\PrintHelper;
use app\models\KafkaPushMessage;
use app\models\PushHandlerResult;
use Rookiejin\Xmpush\Builder;
use Rookiejin\Xmpush\Constants;
use Rookiejin\Xmpush\IOSBuilder;
use Rookiejin\Xmpush\PushRequestPath;
use Rookiejin\Xmpush\Result;
use Rookiejin\Xmpush\Sender;
use Rookiejin\Xmpush\ServerSwitch;
use yii\helpers\ArrayHelper;
use GuzzleHttp\Psr7\Request;

class XiaomiPushHelper extends PushHelper
{
    const SEND_CONFIG_KEY = 'xiaomi_push';
    const CURL_CONCURRENCY = 2;

    const TIME_WINDOW = 60;
    const TIME_WINDOW_LIMIT = 800;

    //最大次数约束
    const MAX_BATCH_MESSAGE_NUM = 100;
    const MAX_BATCH_REG_ID_NUM  = 5000;

    public function __construct($julive_app_id)
    {
        parent::__construct($julive_app_id);

        //初始化处理
        Constants::setBundleId($this->getBundleId());
        Constants::setPackage($this->getPackage());
        Constants::setSecret($this->getSecret());
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
            $uri = $this->getPushUrl();
            $header = $this->getPushHeader();

            $pool_index = 0;
            foreach ($this->batch_message as $push_index => $push_message) {
                // 空数据处理
                $data = $this->formatData($push_message);
                if(empty($data)) {
                    continue;
                }

                //限速
                self::rateLimit($this->push_handler_id, self::TIME_WINDOW, self::TIME_WINDOW_LIMIT, 1);

                // 记录请求index和push_index的对应关系
                $this->pool_index_map[$pool_index++] = $push_index;

                yield new Request('post', $uri, $header, http_build_query($data));
            }
        };
    }

    /**
     * 异常处理
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

        // 构造结果
        $xm_result = new Result($raw_result);
        $result = PushHandlerResult::getDefaultResult($push_message, $xm_result->getRaw());
        $result->message = empty($result->raw_result['reason']) ? $result->raw_result['description'] : $result->raw_result['reason'];

        //成功处理
        if (in_array($xm_result->getErrorCode(), [0])) {
            $result->code = CodeConstant::SUCCESS_CODE;
            $result->message = '推送成功';
            $result->invalid_reg_id = empty($result->raw_result['data']['bad_regids']) ? [] : explode(',', $result->raw_result['data']['bad_regids']);
            return $push_message->afterHandlerSend($result);
        }

        if (in_array($xm_result->getErrorCode(), [10002, 10003, 10027, 10037])) {
            //可以重试的代码
            $result->code = CodeConstant::ERROR_CODE_REQUEST_TOO_MANY;
            $result->need_retry = PushHandlerResult::NEED_RETRY_YES;
            return $push_message->afterHandlerSend($result);
        }
        if (in_array($xm_result->getErrorCode(), [20301])) {
            //非法id
            $result->code = CodeConstant::ERROR_CODE_REQUEST_TOO_MANY;
            $result->invalid_reg_id = array_filter(array_values($push_message->reg_id_arr));
            return $push_message->afterHandlerSend($result);
        }
        if (in_array($xm_result->getErrorCode(), [200001])) {
            //日超频约束
            $this->setBatchLimit();

            $result->code = CodeConstant::ERROR_CODE_REQUEST_TOO_MANY;
            $result->invalid_reg_id = array_filter(array_values($push_message->reg_id_arr));
            return $push_message->afterHandlerSend($result);
        }

        //兜底处理
        return $push_message->afterHandlerSend($result);
    }

    /**
     * 获取请求链接
     * @return string
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/21 9:45 下午
     */
    public function getPushUrl()
    {
        $requestPath = PushRequestPath::V3_REGID_MESSAGE();
        $server = ServerSwitch::getInstance()->selectServer($requestPath);

        return Constants::$HTTP_PROTOCOL . "://" . $server->getHost() . $requestPath->getPath();
    }

    /**
     * 获取请求header头
     * @return mixed
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/21 9:45 下午
     */
    public function getPushHeader()
    {
        $header['Authorization'] = "key=" . $this->getSecret();
        $header['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
        $header['X-PUSH-HOST-LIST'] = true;
        $header['X-PUSH-SDK-VERSION'] = Constants::SDK_VERSION;
        return $header;
    }

    /**
     * 格式化数据
     * @param KafkaPushMessage $push_message
     * @return array
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/21 9:43 下午
     */
    public function formatData($push_message)
    {
        // 日发送约束
        if($this->overBatchLimit()) {
            PrintHelper::printInfo("{$push_message->request_id}, 本日预消息处理已超出频率，小米官方要求不再调用");
            return [];
        }

        //用户过滤
        $regIdList = array_filter(array_values($push_message->reg_id_arr));

        $jump_url = self::getSchemeUrl($push_message);

        $message = new Builder();
        // 如果是业务数据。修改成透传发送
        if (isset($push_message->push_config['business_push']) && isset($push_message->push_config['passThrough']) && $push_message->push_config['passThrough'] == PushConstant::PASSTHROUGH) {
            $message->passThrough(1);  // 这是一条通知栏消息，如果需要透传，把这个参数设置成1,同时去掉title和descption两个参数
        } else {
            $message->passThrough(0);  // 这是一条通知栏消息，如果需要透传，把这个参数设置成1,同时去掉title和descption两个参数
        }
        $message->title($push_message->title);  // 通知栏的title
        $message->description($push_message->notification); // 通知栏的descption
        $payload = is_scalar($push_message->push_params) ? $push_message->push_params : json_encode($push_message->push_params);
        $message->payload($payload); // 携带的数据，点击后将会通过客户端的receiver中的onReceiveMessage方法传入。
        $message->extra(Builder::notifyForeground, 1); // 应用在前台是否展示通知，如果不希望应用在前台时候弹出通知，则设置这个参数为0
        $message->extra('jump_url', $jump_url); //定义事件是自定义事件
        $message->extra('channel_id', $push_message->getChanleId()); //定义事件是自定义事件
//        $message->extra('notification_style_type', 1); //定义事件是自定义事件
//        $message->extra('notification_large_icon_uri', 'https://c01.gaitubao.net/gaitubao_FrHkNsQ62bdlq20cijt-7TbVHaGp.png?imageMogr2/quality/95'); //定义事件是自定义事件


        /**
         * String（可选字段）, 支持以下状态的回执类型：
         * 1：消息送达。
         * 2：消息点击。
         * 3：消息送达或点击。
         * 16：目标设备无效（设备超过90天未联网；alias/user account/regID不正确；App未注册或已卸载；发送目标的区域有误等原因）。
         * 32：客户端调用了disablePush接口禁用Push。
         * 64：目标设备不符合过滤条件（包括网络条件不符合、地理位置不符合、App版本不符合、机型不符合、地区语言不符合等）。
         * 128：当日推送总量超限，限制规则请参见“消息限制说明”。
         * 如果不设置，默认类型是3。
         *
         * 说明：
         *
         * 对于当前设备不在线、透传消息而App未启动、非小米手机App未启动、消息有效期TTL过期等原因导致的本次消息无法下发，不返回回执。
         * 如果需要返回多种状态的回执，将type数值相加即可。例如：既需要收到送达回执，也需要收到目标设备无效的回执，请将callback.type设置为17（即1+16）；如果需要收到消息送达、消息点击和目标设备无效三种状态的回执，请将callback.type设置为19（1+2+16）。
         * 最佳实践：建议针对上述状态中的16类型做过滤处理，减少对这些用户的无效推送。
         */
        $message->extra('callback', $this->getCallBackUrl());
        $message->extra('callback.x', $push_message->request_id);
        $message->extra('callback.type', 19);// 3：消息送达或点击。 16：目标设备无效（

        $message->notifyId(2); // 通知类型。最多支持0-4 5个取值范围，同样的类型的通知会互相覆盖，不同类型可以在通知栏并存
        $message->build();

        //指定regId列表群发
        $fields = $message->getFields();
        $jointRegIds = '';
        foreach ($regIdList as $regId) {
            if (isset($regId)) {
                $jointRegIds .= $regId . Constants::$comma;
            }
        }
        $fields['registration_id'] = $jointRegIds;
        return $fields;
    }

    /**
     * ios推送
     * @param KafkaPushMessage $push_message
     * @return Result
     * creater: 卫振家
     * create_time: 2020/5/7 下午5:05
     */
    public function iosPushByRegids($push_message)
    {
        $user_account_list = array_filter(array_values($push_message->reg_id_arr));

        $message = new IOSBuilder();
        $message->title($push_message->title);
        $message->description($push_message->notification);
        $message->soundUrl('default');
        $message->badge('4');
        $payload = '{"test":1,"ok":"It\'s a string"}';
        $message->extra('payload', $payload);
        $message->build();

        $sender = new Sender();
        return $sender->sendToIds($message, $user_account_list);
    }

    /**
     * 发送安卓推送
     * @param KafkaPushMessage $push_message 推送任务
     * @return Result
     */
    public function androidPushByRegids($push_message)
    {
        //用户过滤
        $user_account_list = array_filter(array_values($push_message->reg_id_arr));

        $jump_url = self::getSchemeUrl($push_message);

        $sender = new Sender();
        $message = new Builder();
        $message->title($push_message->title);  // 通知栏的title
        $message->description($push_message->notification); // 通知栏的descption
        $message->passThrough(0);  // 这是一条通知栏消息，如果需要透传，把这个参数设置成1,同时去掉title和descption两个参数
        $message->payload($push_message->push_params); // 携带的数据，点击后将会通过客户端的receiver中的onReceiveMessage方法传入。
        $message->extra(Builder::notifyForeground, 1); // 应用在前台是否展示通知，如果不希望应用在前台时候弹出通知，则设置这个参数为0

        $message->extra('jump_url', $jump_url); //定义事件是自定义事件
        $message->notifyId(2); // 通知类型。最多支持0-4 5个取值范围，同样的类型的通知会互相覆盖，不同类型可以在通知栏并存
        $message->build();
        $result = $sender->sendToIds($message, $user_account_list);
        return $result;
    }

    /**
     * 根据APP_ID，获取APP类型 1:ios,2:android
     * @return int
     */
    public function getType()
    {
        $ios = [
            AppConstant::APP_COMJIA_IOS,
        ];
        if (in_array($this->julive_app_id, $ios)) {
            return AppConstant::DEVICE_TYPE_IOS;
        }
        return AppConstant::DEVICE_TYPE_ANDROID;
    }

    /**
     * 获取handler
     * @param $app_id
     * @return string
     * creater: 卫振家
     * create_time: 2020/5/7 下午3:45
     */
    public function getBundleId()
    {//方便扩张，APP_ID,对应不同的BundleId
        return 'this is BundleId';
    }

    /**
     * 获取小米密钥
     * @return mixed
     * creater: 卫振家
     * create_time: 2020/5/7 下午3:46
     */
    public function getSecret()
    {//方便扩张，如果APP_ID,对应不同的Secret
        return ArrayHelper::getValue($this->config, 'app_secret', '');
    }

    /**
     * 获取包名
     * @return mixed
     * creater: 卫振家
     * create_time: 2020/5/7 下午3:48
     */
    public function getPackage()
    {//方便扩张，如果APP_ID,对应不同的Package
        return ArrayHelper::getValue(AppConstant::$app_ids, $this->julive_app_id, '');
    }

    /**
     * xiao
     * @return string
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/28 2:40 下午
     */
    public function getCallBackUrl()
    {
        if(YII_ENV_PROD) {
            return "https://pushservice.julive.com/push-notify/xiaomi-notify";
        }

        //return "https://pushservice.julive.com/push-notify/xiaomi-notify";

        return "http://test.pushservice.julive.com/push-notify/xiaomi-notify";
    }


}