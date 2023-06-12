<?php


namespace app\constants;


class AppConstant
{
    // 居理新房
    const APP_COMJIA_ANDROID = 101;
    const APP_COMJIA_IOS = 201;

    // 马甲包
    const APP_COMJIA_ANDROID_MAJIA_006 = 101006;
    const APP_COMJIA_IOS_MAJIA_004 = 201004;
    const APP_COMJIA_IOS_MAJIA_002 = 201002;

    // 咨询师APP
    const APP_COMJIA_ANDROID_EMPLOYEE = 102;
    const APP_COMJIA_IOS_EMPLOYEE = 202;

    // 经纪人app
    const APP_REALTOR_ANDROID = 807;
    const APP_REALTOR_IOS = 808;

    // 咨询师pad
    const APP_COMJIA_ANDROID_EMPLOYEE_PAD = 103;

    // esa APPID
    const APP_COMJIA_ANDROID_ESA = 104;

    // 开发商安卓esa
    const APP_KFS_ANDROID_ESA = 705;


    //esa 小米升级APPID
    const APP_COMJIA_ANDROID_ESA_XIAOMI = 988;

    //安卓系统得APP_ID,用于升级Android系统
    const APP_COMJIA_ANDROID_OS = 901;

    const APP_COMJIA_SMART_PROGRAM = 401;

    public static $DEVICE_TYPE = [
        self::APP_COMJIA_ANDROID => '安卓',
        self::APP_COMJIA_IOS     => 'IOS',
    ];

    //M站
    const APP_COMJIA_M = 301;

    //支付宝小程序
    const APP_COMJIA_ALIPAY_MINI = 501;

    public static $all_app_id = [
        self::APP_COMJIA_ANDROID,
        self::APP_COMJIA_IOS,
        self::APP_COMJIA_ANDROID_EMPLOYEE,
        self::APP_COMJIA_IOS_EMPLOYEE,
        self::APP_COMJIA_ANDROID_EMPLOYEE_PAD,
        self::APP_COMJIA_ANDROID_ESA,
        self::APP_COMJIA_ANDROID_OS,
        self::APP_COMJIA_SMART_PROGRAM,
        self::APP_COMJIA_ANDROID_ESA_XIAOMI,
        self::APP_KFS_ANDROID_ESA,
    ];

    // 安卓设备端
    public static $android_app_id = [
        self::APP_COMJIA_ANDROID,
        self::APP_COMJIA_ANDROID_EMPLOYEE,
        self::APP_COMJIA_ANDROID_EMPLOYEE_PAD,
        self::APP_COMJIA_ANDROID_ESA
    ];

    public static $ios_app_id = [
        self::APP_COMJIA_IOS,
        self::APP_COMJIA_IOS_EMPLOYEE
    ];

    //bundleId设置
    public static $app_ids = [
        self::APP_COMJIA_ANDROID              => '居理新房',
        self::APP_COMJIA_IOS                  => '居理新房iOS',
        self::APP_COMJIA_ANDROID_EMPLOYEE     => '咨询师APP Android',
        self::APP_COMJIA_ANDROID_EMPLOYEE_PAD => '咨询师APP Android Pad',
        self::APP_COMJIA_IOS_EMPLOYEE         => '咨询师APP iOS',
    ];


    const DEVICE_TYPE_IOS     = 'ios';
    const DEVICE_TYPE_ANDROID = 'android';

    /*************************协议配置***************************/

    // 情报站推荐列表
    const COMJIA_INFORMATION_RECOMMEND = 'comjia://app.comjia.com/intelligence?data={"type":"2"}';
    // 问答列表
    const COMJIA_QUESTION = 'comjia://app.comjia.com/v4/qa_list';
    // 信息流列表
    const COMJIA_INFORMATION = 'comjia://app.comjia.com/intelligence';
    // 问答收藏列表
    const COMJIA_MY_QA = 'comjia://app.comjia.com/v4/qa_list';
    // 信息流详情页
    const COMJIA_INFORMATION_DETAIL = 'comjia://app.comjia.com/information/detail?data={"information_id":"*"}';
    // 咨询师点评详情页
    const COMJIA_INTELLIGENCE_INTELLIGENCE = 'comjia://app.comjia.com/intelligence/review_detail?data={"review_id":"*"}';
    // 问答详情
    const COMJIA_QUESTION_DETAIL = 'comjia://app.comjia.com/qa_detail?data={"qa_id":"*"}';

    public static $app_h5_scheme_url = [
        self::APP_COMJIA_ANDROID              => 'comjia://app.comjia.com/h5?data=%s',
        self::APP_COMJIA_IOS                  => 'comjia://app.comjia.com/h5?data=%s',
    ];

    /*************************协议配置***************************/

    const API_ERROR = 'api_error'; // api的错误日志
    const PAY_LOG   = 'pay_log'; // api的错误日志
    const ORDER_LOG = 'order_log'; // 记录订单相关信息
    const PUSH_LOG  = 'push_log'; // 记录订单相关信息


    const CALLBACK_LOG_HUAWEI  = 'huawei_callback';
    const CALLBACK_LOG_XIAOMI  = 'xiaomi_callback';
    const CALLBACK_LOG_VIVO    = 'vivo_callback';
    const CALLBACK_LOG_OPPO    = 'oppo_callback';
    const CALLBACK_LOG_JIGUANG = 'jiguang_callback';
    const CALLBACK_LOG_APPLE   = 'apple_callback';
}