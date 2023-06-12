<?php


namespace app\constants;


class KafkaConstant
{
    //推送适应的kafka topic
    //const KAFKA_TOPIC_PUSH_SERVICE_APP2C = 'Julive_Queue_Push_Service_APP2C';
    //const KAFKA_TOPIC_PUSH_SERVICE_APP2B = 'Julive_Queue_Push_Service_APP2B';

    //渠道topic
    const KAFKA_TOPIC_PUSH_SERVICE_XIAOMI   = "Julive_Queue_Push_Service_XIAOMI";
    const KAFKA_TOPIC_PUSH_SERVICE_HUAWEI   = "Julive_Queue_Push_Service_HUAWEI";
    const KAFKA_TOPIC_PUSH_SERVICE_OPPO     = "Julive_Queue_Push_Service_OPPO";
    const KAFKA_TOPIC_PUSH_SERVICE_VIVO     = "Julive_Queue_Push_Service_VIVO";
    const KAFKA_TOPIC_PUSH_SERVICE_APNS     = "Julive_Queue_Push_Service_APNS";
    const KAFKA_TOPIC_PUSH_SERVICE_JIGUANG  = "Julive_Queue_Push_Service_JIGUANG";

    const QueueTopicList = [
    ];

    const AsyncTopicList = [
        // 旧topic
        //self::KAFKA_TOPIC_PUSH_SERVICE_APP2C,
        //self::KAFKA_TOPIC_PUSH_SERVICE_APP2B,

        // 新topic
        self::KAFKA_TOPIC_PUSH_SERVICE_XIAOMI,
        self::KAFKA_TOPIC_PUSH_SERVICE_HUAWEI,
        self::KAFKA_TOPIC_PUSH_SERVICE_OPPO,
        self::KAFKA_TOPIC_PUSH_SERVICE_VIVO,
        self::KAFKA_TOPIC_PUSH_SERVICE_APNS,
        self::KAFKA_TOPIC_PUSH_SERVICE_JIGUANG,
    ];

    //推送使用的kafka customer group id;
    const KAFKA_CUSTOMER_GROUP_HIGH_PRIORITY   = 'group_JULIVE_QUEUE_PUSH_SERVICE_high_priority';
    const KAFKA_CUSTOMER_GROUP_MIDDLE_PRIORITY = 'group_JULIVE_QUEUE_PUSH_SERVICE_middle_priority';
    const KAFKA_CUSTOMER_GROUP_LOW_PRIORITY    = 'group_JULIVE_QUEUE_PUSH_SERVICE_low_priority';
    const KAFKA_CUSTOMER_GROUP_CLOW_PRIORITY   = 'group_JULIVE_QUEUE_PUSH_SERVICE_clow_priority';

    const KAFKA_PUSH_CUSTOMER_GROUP = [
        self::KAFKA_CUSTOMER_GROUP_HIGH_PRIORITY,
        self::KAFKA_CUSTOMER_GROUP_MIDDLE_PRIORITY,
        self::KAFKA_CUSTOMER_GROUP_LOW_PRIORITY,
        self::KAFKA_CUSTOMER_GROUP_CLOW_PRIORITY,
    ];

    //写入数据库使用的kafka customer group id
    const KAFKA_CUSTOMER_GROUP_RECORD = 'group_JULIVE_QUEUE_PUSH_SERVICE_record';
}