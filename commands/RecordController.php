<?php


namespace app\commands;

use app\constants\KafkaConstant;
use app\constants\PushConstant;
use app\helpers\PrintHelper;
use app\helpers\QueueHelper;
use app\models\KafkaPushMessage;
use app\models\PushServiceUseRecord;
use app\services\StatisticsService;
use yii\console\ExitCode;

class RecordController extends BaseConsoleController
{
    /**
     * 发送推送
     * creater: 卫振家
     * create_time: 2020/5/7 下午2:54
     * @param $topic_name
     * @return int
     * @throws \Exception
     */
    public function actionUserMessage($topic_name = '')
    {
        $topic_name = KafkaConstant::AsyncTopicList;

        $common_queue = new QueueHelper();
        $group_id     = KafkaConstant::KAFKA_CUSTOMER_GROUP_RECORD;
        PrintHelper::printInfo("开始发送记录用户 push数据\n");

        $topic_name = array_intersect($topic_name, PushConstant::$KAFKA_SEND_TYPE_TOPIC);
        if (empty($topic_name)) {
            PrintHelper::printError("本topic {$topic_name}配置不存在\n");
        }

        $mysql = true;
        //从kafka获取得推送消息
        $common_queue->reciveMessage($topic_name, $group_id, function ($data) {
            if (empty($data)) {
                return true;
            }

            //获取消息
            $kafka_push_message = new KafkaPushMessage();
            $kafka_push_message->setAttributes($data);
            PrintHelper::printInfo("\n\n开始记录 request_id:{$kafka_push_message->request_id},消息体:" . json_encode($data, JSON_UNESCAPED_UNICODE));

            //校验
            $validate = $kafka_push_message->validate();
            if ($validate === false) {
                $error         = $kafka_push_message->getErrors();
                $error_message = json_encode($error, JSON_UNESCAPED_UNICODE);
                PrintHelper::printError("数据格式错误 request_id:{{$kafka_push_message->request_id}:{$error_message}");
                return true;
            }
            //定时任务跳过
            if ($kafka_push_message->push_time > time()) {
                PrintHelper::printInfo("不到发送时间跳过 request_id:{$kafka_push_message->request_id}:发送时间{$kafka_push_message->push_time}");
                return true;
            }
            if ($kafka_push_message->push_count > 1) {
                PrintHelper::printInfo(" request_id:{$kafka_push_message->request_id}:重试数据不记录");
                return true;
            }


            // 表示app业务push推送,用于在app中显示
            if (!isset($kafka_push_message->push_config['business_push'])) {
                //写入数据库
                PushServiceUseRecord::setSuffix(time());
                foreach ($kafka_push_message->unique_id_arr as $unique_id) {

                    PrintHelper::printInfo("开始记录数据 request_id:{$kafka_push_message->request_id}，unique_id:{$unique_id}");
                    $sql_data[] = [
                        $kafka_push_message->julive_app_id,
                        $kafka_push_message->request_id,
                        $kafka_push_message->batch,
                        $kafka_push_message->title,
                        $kafka_push_message->notification,
                        json_encode($kafka_push_message->push_params, JSON_UNESCAPED_UNICODE),
                        $kafka_push_message->push_time,
                        $unique_id
                    ];
                }
                //更新字符集
                $sql = PushServiceUseRecord::getDb()->createCommand()->batchInsert(PushServiceUseRecord::tableName(),
                    ['app_id', 'request_id', 'batch', 'title', 'notification', 'push_params', 'send_time', 'unique_id'], $sql_data)->execute();
                if ($sql) {
                    PrintHelper::printInfo("记录数据成功 request_id:{$kafka_push_message->request_id}");
                } else {
                    PrintHelper::printInfo("记录数据失败 request_id:{$kafka_push_message->request_id}");
                }
            }




            //发送推送
            return true;
        }, QueueHelper::FORMAT_JSON);

        return ExitCode::OK;
    }

    /**
     * 增加测试方法
     * @return bool
     * creater: 卫振家
     * create_time: 2020/6/10 下午1:29
     */
    public function actionTest()
    {
        $data               = json_decode('{"request_id":"749A972C-FEFA-63BA-2A52-6E2F6CBEB57A","julive_app_id":101,"unique_id_arr":["AE04782FE110395B3C0FDD2F84BAD3C6"],"title":"测试推sdfjs送","notification":"消息fsfdjksdfjasdkfjdsaklfm内容","batch":"749A972C-FEFA-63BA-2A52-6E2F6CBEB57A","push_config":[],"push_params":[],"push_time":0,"push_count":1,"priority":1,"has_filter":2,"create_time":1591687910,"kafka_topic":"Julive_Queue_Push_Service_APP2C","kafka_group_id":"group_JULIVE_QUEUE_PUSH_SERVICE_high_priority","send_type":0}', 1);
        $kafka_push_message = new KafkaPushMessage();
        $kafka_push_message->setAttributes($data);

        //校验
        $validate = $kafka_push_message->validate();
        if ($validate === false) {
            $error         = $kafka_push_message->getErrors();
            $error_message = json_encode($error, JSON_UNESCAPED_UNICODE);
            PrintHelper::printError("数据格式错误 id:{{$kafka_push_message->request_id}:{$error_message}");
            return true;
        }

        //定时任务跳过
        if ($kafka_push_message->push_time > time()) {
            PrintHelper::printInfo("不到发送时间跳过 request_id:{$kafka_push_message->request_id}:发送时间{$kafka_push_message->push_time}");
        }

        //写入数据库
        PushServiceUseRecord::setSuffix(time());
        foreach ($kafka_push_message->unique_id_arr as $unique_id) {
            PrintHelper::printInfo("开始记录数据 request_id:{$kafka_push_message->request_id}，unique_id:{$unique_id}");
            $user_record = PushServiceUseRecord::findOne([
                "request_id" => $kafka_push_message->request_id,
                "unique_id"  => $unique_id
            ]);
            if (!empty($user_record)) {
                PrintHelper::printInfo("已过记录数据 request_id:{$kafka_push_message->request_id}, unique_id{$unique_id}成功，id：{$user_record->id}");
                continue;
            }
            $user_record               = new PushServiceUseRecord();
            $user_record->app_id       = $kafka_push_message->julive_app_id;
            $user_record->request_id   = $kafka_push_message->request_id;
            $user_record->batch        = $kafka_push_message->batch;
            $user_record->title        = $kafka_push_message->title;
            $user_record->notification = $kafka_push_message->notification;
            $user_record->push_params  = json_encode($kafka_push_message->push_params, JSON_UNESCAPED_UNICODE);
            $user_record->send_time    = $kafka_push_message->push_time;
            $user_record->unique_id    = $unique_id;
            if ($user_record->save()) {
                PrintHelper::printInfo("记录数据成功 request_id:{$kafka_push_message->request_id}, unique_id:{$unique_id}成功，id：{$user_record->id}");
            } else {
                PrintHelper::printInfo("记录数据失败 request_id:{$kafka_push_message->request_id}, unique_id:{$unique_id}成功，" . json_encode($user_record->getErrors(), JSON_UNESCAPED_UNICODE));
            }
        }
        return true;
    }

    /**
     * 统计用户信息
     * creater: 卫振家
     * create_time: 2020/6/9 上午10:37
     */
    public static function actionStatistics()
    {
        PrintHelper::printInfo("开始统计");

        //统计今天的数据
        $today = time();
        //单用户单日发送量
        StatisticsService::statisticsAppUserReceiveNum($today);
        //api调用次数统计
        StatisticsService::statisticsApiCallNum($today);
        //group发送次数统计
        StatisticsService::statisticsGroupSendNum($today);
        //group下的send_type发送次数统计
        //group 分渠道发送次数统计
        StatisticsService::statisticsSendTypeSendNum($today);
        PrintHelper::printInfo("今日结束统计");

        //统计昨天的数据
        $yesterday = strtotime('-1 days');
        //单用户单日发送量
        StatisticsService::statisticsAppUserReceiveNum($yesterday);
        //api调用次数统计
        StatisticsService::statisticsApiCallNum($yesterday);
        //group发送次数统计
        StatisticsService::statisticsGroupSendNum($yesterday);
        //group下的send_type发送次数统计
        //group 分渠道发送次数统计
        StatisticsService::statisticsSendTypeSendNum($yesterday);
        PrintHelper::printInfo("结束统计");
    }
}