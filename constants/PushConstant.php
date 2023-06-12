<?php


namespace app\constants;


class PushConstant
{
    //300秒有限期
    const SIGNATURE_EXPIRE      = 300;

    //send_type记录
    const SEND_TYPE_AUTO        = 0; //自动选择
    const SEND_TYPE_XIAOMI      = 1; // 小米
    const SEND_TYPE_HUAWEI      = 2; // 华为
    const SEND_TYPE_JIGUANG     = 3; // 免费极光
    const SEND_TYPE_VIVO        = 4; // vivo push
    const SEND_TYPE_OPPO        = 5; // oppo push
    const SEND_TYPE_JIGUANG_VIP = 7; // 免费极光
    const SEND_TYPE_APPLE       = 8; // 苹果apns



    const BUSINESS_PUSH = 1;
    const SEND_TYPE = [
        self::SEND_TYPE_XIAOMI      => '小米',
        self::SEND_TYPE_HUAWEI      => '华为',
        self::SEND_TYPE_VIVO        => 'VIVO',
        self::SEND_TYPE_OPPO        => 'OPPO',
        self::SEND_TYPE_JIGUANG     => '免费极光',
        self::SEND_TYPE_JIGUANG_VIP => '极光VIP',
    ];

    const XIAOMI_CHANLE_ID = 'julive_channel_id_02'; // 小米渠道
    const HUAWEI_CHANLE_ID = 'julive_channel_id_01'; // 华为渠道

    const IOS_SOUND = 'popcorn.caf';

    // 渠道列表
    public static $CHANLE_ID_LIST = [
        self::SEND_TYPE_XIAOMI => self::XIAOMI_CHANLE_ID,
        self::SEND_TYPE_HUAWEI => self::HUAWEI_CHANLE_ID,
    ];

    public static $IOS_SOUND_LIST = [
        self::SEND_TYPE_APPLE => self::IOS_SOUND,
    ];


    public static $SEND_TYPE_SORT = [
        PushConstant::SEND_TYPE_XIAOMI => 1,
        PushConstant::SEND_TYPE_HUAWEI => 2,
        PushConstant::SEND_TYPE_VIVO => 3,
        PushConstant::SEND_TYPE_OPPO => 4,
        PushConstant::SEND_TYPE_APPLE => 5,
        PushConstant::SEND_TYPE_JIGUANG_VIP => 6,
        PushConstant::SEND_TYPE_JIGUANG => 7,
    ];

    public static $BUSINESS_SEND_TYPE_SORT = [
        PushConstant::SEND_TYPE_XIAOMI => 1,
        PushConstant::SEND_TYPE_HUAWEI => 2,
        PushConstant::SEND_TYPE_APPLE => 3,
        PushConstant::SEND_TYPE_JIGUANG_VIP => 4,
        PushConstant::SEND_TYPE_JIGUANG => 5,
        PushConstant::SEND_TYPE_OPPO => 6,
        PushConstant::SEND_TYPE_VIVO => 7,
    ];

    //api和kafka的对应关系
    public static $KAFKA_TOPIC_APP_PUSH = [
        //居理app 安卓的使用2b topic 等级 1 2 3 给 极光  3 4 给小米  5 ，6 给华为， 5 ，6给小米华为 ， ios的使用2c的topic  1-6都给苹果
        AppConstant::APP_COMJIA_ANDROID => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_JIGUANG,
        AppConstant::APP_COMJIA_IOS     => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_APNS,

        //咨询师app
        AppConstant::APP_COMJIA_ANDROID_EMPLOYEE => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_JIGUANG,
        AppConstant::APP_COMJIA_IOS_EMPLOYEE     => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_JIGUANG,

        //经纪人app
        AppConstant::APP_REALTOR_ANDROID => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_JIGUANG,
        AppConstant::APP_REALTOR_IOS     => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_JIGUANG,
        AppConstant::APP_COMJIA_ANDROID_EMPLOYEE_PAD => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_JIGUANG,

        //esa
        AppConstant::APP_COMJIA_ANDROID_ESA => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_JIGUANG,
        AppConstant::APP_KFS_ANDROID_ESA    => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_JIGUANG,
    ];

    public static $KAFKA_SEND_TYPE_TOPIC = [
        self::SEND_TYPE_XIAOMI      => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_XIAOMI,
        self::SEND_TYPE_HUAWEI      => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_HUAWEI,
        self::SEND_TYPE_VIVO        => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_VIVO,
        self::SEND_TYPE_OPPO        => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_OPPO,
        self::SEND_TYPE_JIGUANG     => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_JIGUANG,
        self::SEND_TYPE_JIGUANG_VIP => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_JIGUANG,
        self::SEND_TYPE_APPLE       => KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_APNS,
    ];

    //每个用户推送
    public static $APP_PUSH_NUM_PER_UNIQUE_ID_LIMIT = [
        //居理app
        AppConstant::APP_COMJIA_ANDROID => 6,
        AppConstant::APP_COMJIA_IOS     => 6,
    ];

    // 不限制每天发送次数的用户
    public static $UN_LIMIT_PUSH_NUM_UNIQUE_ID_ARR = [
        AppConstant::APP_COMJIA_ANDROID => [
            '7C210772AA250216EB3E4C5979738F0B',
            'F5076E7F281D2FBB24287FA3C206AE33',
            'FA2F4FF65196B8FE7B2A9796B696368A',
            'BABF0149066F49679CF2B1358049CA61',
            '31745AB99584748E4A2ADF37A7AAA3F4',
            '14D661E52B6AD9EAB0690B90530C52D8',
            '03722D8B08311C3B17E15059F58E5A00',
            'DF6DEF6F68F38C4F32606BEF5CFE1CA2',
            '37929015E67CE4BD139585D5381ACEE0',
            'F63BE66FE925A2D81CE289092CEC06B1',
            'C996E5DAB2F3A762E98230FC10B8CDD5',
        ],
        AppConstant::APP_COMJIA_IOS     => [
            '5284C976-CB79-4D51-88DC-84B5EF52075A',
            'B5273145-49F7-14E1-315D-937C43E035DF',
            '127A777A-F995-4ED1-A8A5-C5802FBEF0A1',
            'D48EB86E-6D26-4800-9E0F-95CAB9E70A6E',
            '69841E6F-AA8C-0999-F86C-7BF0514F7F50',
            '80C6D32-F872-4803-92CE-F089ECAA4B0E'
        ],
        AppConstant::APP_COMJIA_ANDROID_MAJIA_006 => [
            '7D5FD07F652CB9703ACF1E8B41F0DA49',
            'DF6DEF6F68F38C4F32606BEF5CFE1CA2',
            'A50BCFB6B65A39DC49933A1EE113DFB5',
        ],
        AppConstant::APP_COMJIA_IOS_MAJIA_004 => [

        ],
    ];

    //最大重试次数
    const PUSH_MAX_COUNT = 3;

    //推送消息的优先级
    const PRIORITY_LEVEL_1 = 1;
    const PRIORITY_LEVEL_2 = 2;
    const PRIORITY_LEVEL_3 = 3;
    const PRIORITY_LEVEL_4 = 4;
    const PRIORITY_LEVEL_5 = 5;
    const PRIORITY_LEVEL_6 = 6;
    const PRIORITY_LEVEL_7 = 7;

    //消息约束
    public static $PRIORITY_LEVELS = [
        self::PRIORITY_LEVEL_1 => self::PRIORITY_LEVEL_1,
        self::PRIORITY_LEVEL_2 => self::PRIORITY_LEVEL_2,
        self::PRIORITY_LEVEL_3 => self::PRIORITY_LEVEL_3,
        self::PRIORITY_LEVEL_4 => self::PRIORITY_LEVEL_4,
        self::PRIORITY_LEVEL_5 => self::PRIORITY_LEVEL_5,
        self::PRIORITY_LEVEL_6 => self::PRIORITY_LEVEL_6,
        self::PRIORITY_LEVEL_7 => self::PRIORITY_LEVEL_7,


    ];

    //不同group_id支持的优先级数组
    public static $PRIORITY_MAP_GROUP_ID = [
        PushConstant::PRIORITY_LEVEL_1 => KafkaConstant::KAFKA_CUSTOMER_GROUP_HIGH_PRIORITY,
        PushConstant::PRIORITY_LEVEL_2 => KafkaConstant::KAFKA_CUSTOMER_GROUP_LOW_PRIORITY,
        PushConstant::PRIORITY_LEVEL_3 => KafkaConstant::KAFKA_CUSTOMER_GROUP_LOW_PRIORITY,
        PushConstant::PRIORITY_LEVEL_4 => KafkaConstant::KAFKA_CUSTOMER_GROUP_LOW_PRIORITY,
        PushConstant::PRIORITY_LEVEL_5 => KafkaConstant::KAFKA_CUSTOMER_GROUP_LOW_PRIORITY,
        PushConstant::PRIORITY_LEVEL_6 => KafkaConstant::KAFKA_CUSTOMER_GROUP_LOW_PRIORITY,
        PushConstant::PRIORITY_LEVEL_7 => KafkaConstant::KAFKA_CUSTOMER_GROUP_LOW_PRIORITY,
    ];

    /**
     * @var int[]
     * group对应的分区
     */
    public static $PARTITION_GROUP_ID = [
        KafkaConstant::KAFKA_CUSTOMER_GROUP_HIGH_PRIORITY  => PushConstant::PRIORITY_LEVEL_1,
        KafkaConstant::KAFKA_CUSTOMER_GROUP_LOW_PRIORITY => PushConstant::PRIORITY_LEVEL_2,
//        KafkaConstant::KAFKA_CUSTOMER_GROUP_LOW_PRIORITY => PushConstant::PRIORITY_LEVEL_3,
    ];

    const PASSTHROUGH = 1;//透传
    //推送记录api
    const REDIS_KEY_TOPIC_GROUP_DAY_SEND_NUM     = "push_statistics:topic_group_send_num:%s:%s:%s";
    const REDIS_KEY_TOPIC_SEND_DAY_TYPE_SEND_NUM  = "push_statistics:topic_group_type_send_num:%s:%s:%s:%s";
    const REDIS_KEY_TOPIC_GROUP_DAY_API_CALL_NUM = "push_statistics:topic_group_api_call_num:%s:%s:%s";

    //非法的reg_id
    const REDIS_KEY_INVALID_PUSH_REG_ID = 'push_process:reg_id_process:invalid_reg_id';
    const REDIS_KEY_HOUR_SLOW_PUSH_WARNING = 'push_process:hour_slow_push_warning:%s';
}