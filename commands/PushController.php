<?php


namespace app\commands;

use app\constants\KafkaConstant;
use app\constants\PushConstant;
use app\exception\PushMsgException;
use app\helpers\HttpHelper;
use app\helpers\PrintHelper;
use app\helpers\push\XiaomiPushHelper;
use app\helpers\QueueHelper;
use app\models\KafkaPushMessage;
use app\models\TimingPushMessage;
use Exception;
use yii\console\ExitCode;

class PushController extends BaseConsoleController
{
    /**
     * 发送推送
     * creater: 卫振家
     * create_time: 2020/5/7 下午2:54
     * @param $topic_name
     * @param $group_id
     * @return int
     * @throws \Exception
     */
    public function actionSend($topic_name = '', $group_id = '')
    {
        $user_topic = empty($topic_name) ? [] : [$topic_name];
        $group_id   = empty($group_id) ? KafkaConstant::KAFKA_CUSTOMER_GROUP_LOW_PRIORITY : $group_id;//不同优先级使用不同group
        $common_queue = new QueueHelper();
        PrintHelper::printInfo("开始发送kafka push消息\n");

        $valid_topic_name = array_intersect($user_topic, PushConstant::$KAFKA_SEND_TYPE_TOPIC);
        if (empty($valid_topic_name)) {
            PrintHelper::printError("本topic配置不存在\n");
            return true;
        }
        $valid_topic_name = count($valid_topic_name) === 1 ? $valid_topic_name[0] : $valid_topic_name;

        //从kafka获取得推送消息
        $common_queue->reciveMessage($valid_topic_name, $group_id, function ($data) use ($group_id) {
            if(empty($data)) {
                return true;
            }

            //获取消息
            $kafka_push_message = new KafkaPushMessage();
            $kafka_push_message->setAttributes($data);
            PrintHelper::printInfo("\n\n开始处理 id:{$kafka_push_message->request_id}");
            PrintHelper::printDebug("消息体:".json_encode($data, JSON_UNESCAPED_UNICODE));

            //发送推送
            return $kafka_push_message->send();
        }, QueueHelper::FORMAT_JSON);

        return ExitCode::OK;
    }


    /**
     * 增加数据
     * @throws PushMsgException
     * @throws \app\exception\PushApiException
     * creater: 卫振家
     * create_time: 2020/5/12 上午9:07
     */
    public function actionTest()
    {
        date_default_timezone_set('Asia/Shanghai');//'Asia/Shanghai'   亚洲/上海
        $json = '{"request_id":"6858AB99-4973-90A1-F51B-3099E904FE20","julive_app_id":201,"unique_id_arr":["35B3C719-CE96-4572-B7AC-E01EAB735C03"],"reg_id_arr":{"35B3C719-CE96-4572-B7AC-E01EAB735C03":"1d8df6b80fa6dd564a6ae3e6345bcbf285294cf1adf6da81e228d2d05af201d1"},"title":"您的订单有更新了","notification":"您的订单有更新了","batch":"6858AB99-4973-90A1-F51B-3099E904FE20","push_config":{"push_now":"1"},"push_params":{"scheme_url":"comjia:\/\/app.comjia.com\/v4\/qa_list","image_url":""},"push_time":1604386514,"push_count":1,"priority":2,"has_filter":2,"create_time":1604386514,"kafka_topic":"Julive_Queue_Push_Service_APNS","kafka_group_id":"group_JULIVE_QUEUE_PUSH_SERVICE_low_priority","send_type":8}';
        $json = '{"request_id":"6858AB99-4973-90A1-F51B-3099E904FE20","julive_app_id":101,"unique_id_arr":["14D661E52B6AD9EAB0690B90530C52D8"],"reg_id_arr":{"14D661E52B6AD9EAB0690B90530C52D8":"6TWYix0PyHmepGxofQftjILqJiixzYQKOCiUAZOVlOlaNxO8HJxTu36NfpykAakt"},"title":"哈哈你好啊奥术大师大所哈","notification":"嗯哈发的发送发送按时发达上发大水发好的啊","batch":"6858AB99-4973-90A1-F51B-3099E904FE20","push_config":{"push_now":"1"},"push_params":{"scheme_url":"comjia:\/\/app.comjia.com\/v4\/qa_list","image_url":""},"push_time":1604386514,"push_count":1,"priority":2,"has_filter":2,"create_time":1604386514,"kafka_topic":"Julive_Queue_Push_Service_APNS","kafka_group_id":"group_JULIVE_QUEUE_PUSH_SERVICE_low_priority","send_type":1}';
//        $json = '{"request_id":"6858AB99-4973-90A1-F51B-3099E904FE20","julive_app_id":101,"unique_id_arr":["21B0451D7E5B2FC920837ED38B8CE3A8"],"reg_id_arr":{"21B0451D7E5B2FC920837ED38B8CE3A8":"RbXRf63uiatT760Jjvot/g6iORzQZemxlm8VZ0HD6NaQdtlmfW8q/Z2tRR5S2T+E"},"title":"您的订单有更新了","notification":"您的订单有更新了","batch":"6858AB99-4973-90A1-F51B-3099E904FE20","push_config":{"push_now":"1"},"push_params":{"scheme_url":"comjia:\/\/app.comjia.com\/v4\/qa_list","image_url":""},"push_time":1604386514,"push_count":1,"priority":2,"has_filter":2,"create_time":1604386514,"kafka_topic":"Julive_Queue_Push_Service_XIAOMI","kafka_group_id":"group_JULIVE_QUEUE_PUSH_SERVICE_low_priority","send_type":1}';
//        $json = '{"request_id":"6858AB99-4973-90A1-F51B-3099E904FE20","julive_app_id":101,"unique_id_arr":["FA2F4FF65196B8FE7B2A9796B696368A"],"reg_id_arr":{"FA2F4FF65196B8FE7B2A9796B696368A":"IQAAAACy0OmBAABrCofQE2GfCQFlp2I2E4vPmQRsMpg68rOKj4EJ1EzVgTenEWBfD8F4kKNTHDxxt-dgRg_rcEdD7ii92yoMFnTQB9WWFLvJ3gcsXQ"},"title":"您的订单有更新了","notification":"您的订单有更新了","batch":"6858AB99-4973-90A1-F51B-3099E904FE20","push_config":{"push_now":"1"},"push_params":{"scheme_url":"comjia:\/\/app.comjia.com\/v4\/qa_list","image_url":"https://t7.baidu.com/it/u=1595072465,3644073269&fm=193&f=GIF"},"push_time":1604386514,"push_count":1,"priority":2,"has_filter":2,"create_time":1604386514,"kafka_topic":"Julive_Queue_Push_Service_XIAOMI","kafka_group_id":"group_JULIVE_QUEUE_PUSH_SERVICE_low_priority","send_type":2}';
        $json= '{"request_id":"EAA9AECB-0BD9-B139-E58B-EA961082001A","julive_app_id":101,"unique_id_arr":["BABF0149066F49679CF2B1358049CA61"],"reg_id_arr":{"BABF0149066F49679CF2B1358049CA61":"15985112220221500102621"},"title":"ceshi","notification":"ceshi2","batch":"EAA9AECB-0BD9-B139-E58B-EA961082001A","push_config":{"push_now":1},"push_params":{"scheme_url":"www.baidu.com"},"push_time":1625470542,"push_count":1,"priority":1,"has_filter":2,"create_time":1625470542,"kafka_topic":"Julive_Queue_Push_Service_VIVO","kafka_group_id":"group_JULIVE_QUEUE_PUSH_SERVICE_high_priority","send_type":4}';
        $json = '{"request_id":"70F348D5-89D1-EA8D-A073-EB35E4F3D51C","julive_app_id":101,"unique_id_arr":["7C210772AA250216EB3E4C5979738F0B"],"reg_id_arr":{"7C210772AA250216EB3E4C5979738F0B":"IQAAAACy0OmBAABeWwHQRHwN6dD4nPCpMsKpKLnF2solmD5yIKnTrhi8PKwfZ6GSmI77DHv50ZUPfHVrfntgM7C1j4SOMX0FtQLvpELsTmgGIw4jBg"},"title":"您关注的36氪发了一条新内容","notification":"数据展示回传效果push数据","batch":"70F348D5-89D1-EA8D-A073-EB35E4F3D51C","push_config":{"push_now":1},"push_params":{"scheme_url":"comjia:\/\/app.comjia.com\/qa_detail?data=%7B%22qa_id%22%3A%22355%22%7D"},"push_time":1626404887,"push_count":1,"priority":1,"has_filter":2,"create_time":1626404887,"kafka_topic":"Julive_Queue_Push_Service_HUAWEI","kafka_group_id":"group_JULIVE_QUEUE_PUSH_SERVICE_high_priority","send_type":2}';
        $json = '{"request_id":"70F348D5-89D1-EA8D-A073-EB35E4F3D51C","julive_app_id":101,"unique_id_arr":["BABF0149066F49679CF2B1358049CA61"],"reg_id_arr":{"BABF0149066F49679CF2B1358049CA61":"16036846691001500150121"},"title":"您关注的36氪发了一条新内容","notification":"数据展示回传效果push数据","batch":"70F348D5-89D1-EA8D-A073-EB35E4F3D51C","push_config":{"push_now":1},"push_params":{"scheme_url":"comjia:\/\/app.comjia.com\/qa_detail?data=%7B%22qa_id%22%3A%22355%22%7D"},"push_time":1626404887,"push_count":1,"priority":1,"has_filter":2,"create_time":1626404887,"kafka_topic":"Julive_Queue_Push_Service_HUAWEI","kafka_group_id":"group_JULIVE_QUEUE_PUSH_SERVICE_high_priority","send_type":4}';
        $json = '{"request_id":"8B55B811-C823-2B77-7487-DE11C2BF69ED","julive_app_id":101,"unique_id_arr":["3841231663411858A562556B9E3FD6D0"],"reg_id_arr":{"3841231663411858A562556B9E3FD6D0":"AQAAAACy0OmBAAArpJW92zI3m1OITHBWwa4MkirBqb3HhOnuz-82usZr3vpKuNtayktrlW5DT8Eyc1RMJMe29I0A-voVHyh3mWd2w7jnfMlCD4fiyw"},"title":"系统消息","notification":"亲爱的房友，你的头像经核实含广告内容，系统将自动替换为默认头像，请尽快修改噢，社区小伙伴们等着和你一起玩耍呢~","batch":"8B55B811-C823-2B77-7487-DE11C2BF69ED","push_config":{"user_id":20519144,"operation_user_id":"","commend_id":"","information_id":"","level":"","title":"系统消息","notification":"亲爱的房友，你的头像经核实含广告内容，系统将自动替换为默认头像，请尽快修改噢，社区小伙伴们等着和你一起玩耍呢~","behavior":3,"push_type":1,"type":4,"topic_type":"api_user_audit_fail","passThrough":2,"business_push":1},"push_params":{"scheme_url":"comjia:\/\/app.comjia.com\/usernews_system_list"},"push_time":1631176847,"push_count":1,"priority":1,"has_filter":2,"create_time":1631176847,"kafka_topic":"Julive_Queue_Push_Service_HUAWEI","kafka_group_id":"group_JULIVE_QUEUE_PUSH_SERVICE_high_priority","send_type":2}';
        $data = json_decode($json,true);
        $kafka_push_message = new KafkaPushMessage();
        $kafka_push_message->setAttributes($data);
        $validate = $kafka_push_message->validate();
        if($validate === false) {
            $error = $kafka_push_message->getErrors();
            $error_message = '数据格式错误,error:'.  json_encode($error, JSON_UNESCAPED_UNICODE);
            throw new PushMsgException($kafka_push_message, $error_message);
        }

//        $kafka_push_message->enqueueWithAlloc();
        //如果写入数据库
        if($kafka_push_message->push_time > time()) {
            $kafka_push_message->toTimingPushMessage();
            return true;
        }
        $kafka_push_message->send();
        return true;
    }

    public function actionTest2()
    {
        $json = '{"app_key":"GT0xyVBsHBCsn7C4E8cZ6xdcjmVRIk7d","signature":"055583f059edee84cc6a83718ab255b0","unique_id_arr":["B5273145-49F7-14E1-315D-937C43E035DF"],"julive_app_id":201,"title":"650--😍PUSH--🤑-1😘","notification":"🥰哈哈😘😙HAH ","push_config":{"business_push":"1"},"push_params":[],"send_type":8,"priority":2,"push_time":1600000000}';
        $data = json_decode($json, true);
        $host = YII_ENV_TEST || YII_ENV_DEV ? 'testpushservice.comjia.com' : 'internal-pushservice.julive.com';
        $uri  = "http://{$host}/push-server/add";
        var_dump((new HttpHelper())->setTimeOut(2)->setConnectTimeOut(1)->postJson($uri, $data, true));
    }

    public function actionBatchTest($num = 100, $type = 1)
    {
        for($i = 0; $i < $num; $i ++){
            if($type == 1) {
                $this->actionTest2();
            }else {
                $this->actionTest();
            }
        }
    }

    public function actionLegalCheck()
    {
        // oppo 获取非法用户
        /**
        $oppo_pusher = new OppoPushHelper(101);
        $token = $oppo_pusher->getToken();
        $header = [
        'auth_token'    => $token
        ];
        $result = (new HttpHelper())
        ->setConnectTimeOut(10)
        ->setTimeOut(10)
        ->setHeader($header)
        ->get('https://feedback.push.oppomobile.com/server/v1/feedback/fetch_invalid_regidList', true);
         * **/

        // vivo获取非法用户
        /**
        $vivo_pusher = new VivoPushHelper(101);
        $token = $vivo_pusher->getToken();
        $header = [
            'Content-Type' => 'application/json',
            'authToken' => $token,
        ];
        $data = [
            "userType" => 1,
            "userIds"  => [
                '15985112220221500102621',
                '15985112220221500102620',
            ]
        ];
        $http_helper = (new HttpHelper())->setConnectTimeOut(10)->setTimeOut(10)->setHeader($header);
        $result = $http_helper->postJson("https://api-push.vivo.com.cn/invalidUser/check", $data, true);
         * **/

        //华为获取非法用户
        /**
        $huawei_pusher = new HuaweiPushHelper(101);
        $huawei_app_id = $huawei_pusher->getConfig('app_id');
        $token = $huawei_pusher->getToken();
        $header    = [
            'Content-Type' => 'application/json',
            'Authorization'    => $token
        ];
        $data = [
            'token' => 'FA2F4FF65196B8FE7B2A9796B696368A',
        ];
        $http_helper = (new HttpHelper())->setConnectTimeOut(10)->setTimeOut(10)->setHeader($header);
        $result = $http_helper->postJson("https://push-api.cloud.huawei.com/v1/{$huawei_app_id}/token:query", $data, true);
        **/

        //小米获取非法用户
        $xiaomi_pusher = new XiaomiPushHelper(101);
        $url = 'https://api.xmpush.xiaomi.com/v1/trace/messages/status';
        $header = $xiaomi_pusher->getPushHeader();
        $data = [
            'begin_time' => '1409820131002',
            'end_time'   => '1409820283941',
        ];

        $http_helper = (new HttpHelper())->setConnectTimeOut(10)->setTimeOut(10)->setHeader($header);
        $result = $http_helper->get($url, true, $data);
        var_dump($result);



    }

    /**
     * 定时获取
     * creater: 卫振家
     * create_time: 2020/5/11 下午5:59
     */
    public function actionTickSend()
    {
        PrintHelper::printInfo("开始处理定时消息");

        while(true){
            $start_time = time();
            $push_message_list = TimingPushMessage::find()
                ->where(['push_status' => TimingPushMessage::PUSH_STATUS_WAITING])
                ->andWhere(['<=', 'push_time', time()])
                ->orderBy(['push_time' => SORT_ASC])
                ->limit(10)
                ->all();
            foreach($push_message_list as $push_message) {
                try{
                    $push_message->toKafkaPushMessage();
                    $push_message->delete();
                }catch(Exception $e) {
                    continue;
                } catch (\Throwable $e) {
                    continue;
                }
                PrintHelper::printInfo("定时消息处理完成{$push_message->request_id}");
            }

            self::try_sleep($start_time);
        }
    }

}