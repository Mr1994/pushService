<?php


namespace app\services;


use app\constants\KafkaConstant;
use app\constants\PushConstant;
use app\helpers\PrintHelper;
use app\models\PushServiceStatistics;
use app\models\PushServiceUseRecord;
use Yii;

class StatisticsService
{
    /**
     * 统计app的用户每天收到的数据量
     * creater: 卫振家
     * create_time: 2020/6/11 上午8:33
     * @param $statistics_time
     * @return bool
     */
    public static function statisticsAppUserReceiveNum($statistics_time)
    {
        PrintHelper::printInfo("开始app推送次数统计");
        $statistics_date = date('Ymd', $statistics_time);
        PushServiceUseRecord::setSuffix($statistics_time);
        $send_num_list = PushServiceUseRecord::find()
            ->select(['app_id', 'count(*) as send_num'])
            ->groupBy(['app_id'])
            ->asArray()
            ->all();

        if (empty($send_num_list)) {
            PrintHelper::printInfo("{$statistics_date}无app推送数据");
        }
        //单用户单日发送量
        foreach ($send_num_list as $send_num_row) {
            $push_service_statistics = PushServiceStatistics::findOne([
                'statistics_date_type'   => PushServiceStatistics::STATISTICS_DATE_TYPE_DAY,
                'statistics_date'        => $statistics_date,
                'statistics_source_type' => PushServiceStatistics::STATISTICS_SOURCE_TYPE_DB,
                'statistics_source'      => PushServiceUseRecord::tableName(),
                'group_by_type'          => PushServiceStatistics::GROUP_BY_TYPE_APP_ID,
                'group_by'               => $send_num_row['app_id'],
                'statistics_type'        => PushServiceStatistics::STATISTICS_TYPE_APP_ID_USER_SEND_NUM,
            ]);
            if (empty($push_service_statistics)) {
                $push_service_statistics = new PushServiceStatistics();
            }
            $push_service_statistics->statistics_date_type   = PushServiceStatistics::STATISTICS_DATE_TYPE_DAY;
            $push_service_statistics->statistics_date        = $statistics_date;
            $push_service_statistics->statistics_source_type = PushServiceStatistics::STATISTICS_SOURCE_TYPE_DB;
            $push_service_statistics->statistics_source      = PushServiceUseRecord::tableName();
            $push_service_statistics->group_by_type          = PushServiceStatistics::GROUP_BY_TYPE_APP_ID;
            $push_service_statistics->group_by               = $send_num_row['app_id'];
            $push_service_statistics->statistics_type        = PushServiceStatistics::STATISTICS_TYPE_APP_ID_USER_SEND_NUM;
            $push_service_statistics->statistics_desc        = '单日app每个用户收到推送数量的加和';
            $push_service_statistics->statistics_value       = $send_num_row['send_num'];;

            if ($push_service_statistics->save()) {
                PrintHelper::printInfo("统计过推送次数成功 日期:{$statistics_date}, app:{$send_num_row['app_id']}, 次数:{$send_num_row['send_num']}");
            } else {
                PrintHelper::printInfo("统计过推送次数失败 日期:{$statistics_date}, app:{$send_num_row['app_id']}, 次数:{$send_num_row['send_num']}" . json_encode($push_service_statistics->getErrors(), JSON_UNESCAPED_UNICODE));
            }
        }

        return true;
    }

    /**
     * 统计api调用次数
     * creater: 卫振家
     * create_time: 2020/6/11 上午8:36
     * @param $statistics_time
     * @return bool
     */
    public static function statisticsApiCallNum($statistics_time)
    {
        $statistics_date = date('Ymd', $statistics_time);

        foreach (KafkaConstant::AsyncTopicList as $topic) {
            //统计group
            foreach (KafkaConstant::KAFKA_PUSH_CUSTOMER_GROUP as $group) {

                //统计api调用次数
                $api_call_num_key = sprintf(
                    PushConstant::REDIS_KEY_TOPIC_GROUP_DAY_API_CALL_NUM,
                    $topic,
                    $group,
                    $statistics_date
                );
                $api_call_num     = trim(intval(Yii::$app->redis_business->get($api_call_num_key)));
                $group_by         = "{$topic}:{$group}";

                //写入数据库
                $push_service_statistics = PushServiceStatistics::findOne([
                    'statistics_date_type'   => PushServiceStatistics::STATISTICS_DATE_TYPE_DAY,
                    'statistics_date'        => $statistics_date,
                    'statistics_source_type' => PushServiceStatistics::STATISTICS_SOURCE_TYPE_REDIS,
                    'statistics_source'      => $api_call_num_key,
                    'group_by_type'          => PushServiceStatistics::GROUP_BY_TYPE_KAFKA_GROUP,
                    'group_by'               => $group_by,
                    'statistics_type'        => PushServiceStatistics::STATISTICS_TYPE_API_CALL_NUM,
                ]);
                if (empty($push_service_statistics)) {
                    $push_service_statistics = new PushServiceStatistics();
                }
                $push_service_statistics->statistics_date_type   = PushServiceStatistics::STATISTICS_DATE_TYPE_DAY;
                $push_service_statistics->statistics_date        = $statistics_date;
                $push_service_statistics->statistics_source_type = PushServiceStatistics::STATISTICS_SOURCE_TYPE_REDIS;
                $push_service_statistics->statistics_source      = $api_call_num_key;
                $push_service_statistics->group_by_type          = PushServiceStatistics::GROUP_BY_TYPE_KAFKA_GROUP;
                $push_service_statistics->group_by               = $group_by;
                $push_service_statistics->statistics_type        = PushServiceStatistics::STATISTICS_TYPE_API_CALL_NUM;
                $push_service_statistics->statistics_desc        = 'api调用次数统计';
                if(! empty($push_service_statistics->statistics_value) && $push_service_statistics->statistics_value > $api_call_num) {
                    Yii::$app->redis_business->incr($api_call_num_key, $push_service_statistics->statistics_value);
                    $push_service_statistics->statistics_value += $api_call_num;
                }else {
                    $push_service_statistics->statistics_value       = $api_call_num;
                }

                if ($push_service_statistics->save()) {
                    PrintHelper::printInfo("api调用次数统计成功 日期:{$statistics_date}, group:{$group_by}, 次数:{$api_call_num}");
                } else {
                    PrintHelper::printInfo("api调用次数统计失败 日期:{$statistics_date}, app:{$group_by}, 次数:{$api_call_num}" . json_encode($push_service_statistics->getErrors(), JSON_UNESCAPED_UNICODE));
                }
            }
        }

        return true;
    }

    /**
     * 记录topic_group的发送次数
     * creater: 卫振家
     * create_time: 2020/6/11 上午8:37
     * @param $statistics_time
     * @return bool
     */
    public static function statisticsGroupSendNum($statistics_time)
    {
        $statistics_date = date('Ymd', $statistics_time);

        //group发送次数统计
        foreach (KafkaConstant::AsyncTopicList as $topic) {
            //统计group
            foreach (KafkaConstant::KAFKA_PUSH_CUSTOMER_GROUP as $group) {

                //统计api调用次数
                $group_send_num_key = sprintf(
                    PushConstant::REDIS_KEY_TOPIC_GROUP_DAY_SEND_NUM,
                    $topic,
                    $group,
                    $statistics_date
                );
                $group_send_num     = trim(intval(Yii::$app->redis_business->get($group_send_num_key)));
                $group_by           = "{$topic}:{$group}";

                //写入数据库
                $push_service_statistics = PushServiceStatistics::findOne([
                    'statistics_date_type'   => PushServiceStatistics::STATISTICS_DATE_TYPE_DAY,
                    'statistics_date'        => $statistics_date,
                    'statistics_source_type' => PushServiceStatistics::STATISTICS_SOURCE_TYPE_REDIS,
                    'statistics_source'      => $group_send_num_key,
                    'group_by_type'          => PushServiceStatistics::GROUP_BY_TYPE_KAFKA_GROUP,
                    'group_by'               => $group_by,
                    'statistics_type'        => PushServiceStatistics::STATISTICS_TYPE_KAFKA_TOPIC_GROUP_SEND_NUM
                ]);
                if (empty($push_service_statistics)) {
                    $push_service_statistics = new PushServiceStatistics();
                }
                $push_service_statistics->statistics_date_type   = PushServiceStatistics::STATISTICS_DATE_TYPE_DAY;
                $push_service_statistics->statistics_date        = $statistics_date;
                $push_service_statistics->statistics_source_type = PushServiceStatistics::STATISTICS_SOURCE_TYPE_REDIS;
                $push_service_statistics->statistics_source      = $group_send_num_key;
                $push_service_statistics->group_by_type          = PushServiceStatistics::GROUP_BY_TYPE_KAFKA_GROUP;
                $push_service_statistics->group_by               = $group_by;
                $push_service_statistics->statistics_type        = PushServiceStatistics::STATISTICS_TYPE_KAFKA_TOPIC_GROUP_SEND_NUM;
                $push_service_statistics->statistics_desc        = 'group发送次数统计';

                //数据调整
                if(! empty($push_service_statistics->statistics_value) && $push_service_statistics->statistics_value > $group_send_num) {
                    Yii::$app->redis_business->incr($group_send_num_key, $push_service_statistics->statistics_value);
                    $push_service_statistics->statistics_value += $group_send_num;
                }else {
                    $push_service_statistics->statistics_value  = $group_send_num;
                }

                if ($push_service_statistics->save()) {
                    PrintHelper::printInfo("group发送次数统计成功 日期:{$statistics_date}, group:{$group_by}, 次数:{$group_send_num}");
                } else {
                    PrintHelper::printInfo("group发送次数统计失败 日期:{$statistics_date}, app:{$group_by}, 次数:{$group_send_num}" . json_encode($push_service_statistics->getErrors(), JSON_UNESCAPED_UNICODE));
                }
            }
        }

        return true;
    }

    /**
     * 按照发送渠道统计数据
     * creater: 卫振家
     * create_time: 2020/6/11 上午8:39
     * @param $statistics_time
     * @return bool
     */
    public static function statisticsSendTypeSendNum($statistics_time)
    {
        $statistics_date = date('Ymd', $statistics_time);

        foreach (KafkaConstant::AsyncTopicList as $topic) {
            //统计group
            foreach (KafkaConstant::KAFKA_PUSH_CUSTOMER_GROUP as $group) {
                foreach (PushConstant::SEND_TYPE as $send_type => $send_name) {
                    //统计api调用次数
                    $group_send_type_send_num_key = sprintf(
                        PushConstant::REDIS_KEY_TOPIC_SEND_DAY_TYPE_SEND_NUM,
                        $topic,
                        $group,
                        $send_type,
                        $statistics_date
                    );
                    $group_send_type_send_num     = trim(intval(Yii::$app->redis_business->get($group_send_type_send_num_key)));
                    $group_by                     = "{$topic}:{$group}:{$send_type}";

                    //写入数据库
                    $push_service_statistics = PushServiceStatistics::findOne([
                        'statistics_date_type'   => PushServiceStatistics::STATISTICS_DATE_TYPE_DAY,
                        'statistics_date'        => $statistics_date,
                        'statistics_source_type' => PushServiceStatistics::STATISTICS_SOURCE_TYPE_REDIS,
                        'statistics_source'      => $group_send_type_send_num_key,
                        'group_by_type'          => PushServiceStatistics::GROUP_BY_TYPE_KAFKA_GROUP_SEND_TYPE,
                        'group_by'               => $group_by,
                        'statistics_type'        => PushServiceStatistics::STATISTICS_TYPE_KAFKA_SEND_TYPE_SEND_NUM,
                    ]);
                    if (empty($push_service_statistics)) {
                        $push_service_statistics = new PushServiceStatistics();
                    }
                    $push_service_statistics->statistics_date_type   = PushServiceStatistics::STATISTICS_DATE_TYPE_DAY;
                    $push_service_statistics->statistics_date        = $statistics_date;
                    $push_service_statistics->statistics_source_type = PushServiceStatistics::STATISTICS_SOURCE_TYPE_REDIS;
                    $push_service_statistics->statistics_source      = $group_send_type_send_num_key;
                    $push_service_statistics->group_by_type          = PushServiceStatistics::GROUP_BY_TYPE_KAFKA_GROUP_SEND_TYPE;
                    $push_service_statistics->group_by               = $group_by;
                    $push_service_statistics->statistics_type        = PushServiceStatistics::STATISTICS_TYPE_KAFKA_SEND_TYPE_SEND_NUM;
                    $push_service_statistics->statistics_desc        = "{$send_name}发送次数统计";
                    $push_service_statistics->statistics_value       = $group_send_type_send_num;

                    if(! empty($push_service_statistics->statistics_value) && $push_service_statistics->statistics_value > $group_send_type_send_num) {
                        Yii::$app->redis_business->incr($group_send_type_send_num_key, $push_service_statistics->statistics_value);
                        $push_service_statistics->statistics_value += $group_send_type_send_num;
                    }else {
                        $push_service_statistics->statistics_value  = $group_send_type_send_num;
                    }


                    if ($push_service_statistics->save()) {
                        PrintHelper::printInfo("group分渠道发送次数统计成功 日期:{$statistics_date}, group:{$group_by}, 渠道:{$send_name}, 次数:{$group_send_type_send_num}");
                    } else {
                        PrintHelper::printInfo("group分渠道发送次数统计失败 日期:{$statistics_date}, group:{$group_by}, 渠道:{$send_name}, 次数:{$group_send_type_send_num}" . json_encode($push_service_statistics->getErrors(), JSON_UNESCAPED_UNICODE));
                    }
                }
            }
        }
        return true;
    }
}