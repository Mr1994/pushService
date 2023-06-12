<?php
namespace app\constants;

use Yii;


class CodeConstant {
    // 成功
    const SUCCESS_CODE              = 0;

    //操作成功后返回上一页
    const SUCCESS_GO_BACK           = 2;
    //操作成功后刷新页面
    const SUCCESS_RELOAD            = 3;
    //成功，需要提示信息
    const SUCCESS_PROMPT            = 4;
    const SUCCESS                   =200;
    const CHECKDATA                 =100;
    const CHECKDATAOTHER            =101;
    const CHECKDATALIVEDATE         =102;

    // 错误
    const ERROR_CODE_MSG              = 1;
    const ERROR_CODE_SYSTEM         = 1001;
    const ERROR_CODE_PARAM          = 1002;
    const ERROR_CODE_NO_LICATION    = 1003;
    const ERROR_CODE_NO_MODIFY      = 1004;
    const ERROR_CODE_RATE_LIMIT     = 1005;
    const ERROR_CODE_UNAUTHORIZED   = 1006;


    //数据异常
    const ERROR_CODE_DATA_THROW          = 1007;
    //收藏超出100条提示
    const ERROR_CODE_DATA_FAVORITE_MORE  = 1008;
    const ERROR_CODE_NO_AUTHORITY        = 1009;

    const ERROR_CODE_NOTFOUND            = 1010;
    //字段必填
    const ERROR_CODE_FIELD_REQUIRED      = 1011;
    // 没有权限
    const ERROR_CODE_UNPOWER             = 1012;
    //权限为空
    const ERROR_CODE_POWER_NULL          = 1013;
    //菜单为空
    const ERROR_CODE_MENU_NULL           = 1014;
    //未知角色
    const ERROR_CODE_UNKNOWN_ROLE        = 1015;
    //版本过低
    const ERROR_CODE_LOW_VERSION         = 1016;
    // 系统升级中，请稍后再试
    const ERROR_CODE_SYSTEM_MAINTENANCE  = 1017;
    //请求频繁
    const ERROR_CODE_REQUEST_TOO_MANY    = 1018;

    const ERROR_CODE_REQUEST_VALUE_NULL  = 1019;

    //解密失败
    const ERROR_CODE_DECRYPT_FAIL        = 1020;

    //一键登录失败,客户端重新获取token
    const ERROR_CODE_FLASH_LOGIN         = 1021;

    //强制升级提示
    const UPDATE_VERSION                 = 1099;

    //需解密的秘串必须是三个.连接的字符串
    const ERROR_CODE_DECRYPT_STRING_FAIL = 1021;
    // 订单创建失败
    const ERROR_CODE_ORDER_CREATE_FAIL   = 1022;

    //第三方错误
    const ERROR_CODE_THIRD_PARTY         = 3019;
    // 已经点赞
    const ERROR_CODE_ALREADY_CLICK       = 3020;
    // 记录已经存在
    const ERROR_CODE_ALREADY_EXIST       = 3021;

    const ERROR_CODE_MOURNING        = 4030; //哀悼日code


    //登陆注册相关常量
    const ERROR_CODE_PWD              = 1101;
    const ERROR_CODE_MOBILE_EMPTY     = 1102;
    const ERROR_CODE_MOBILE_ERROR     = 1103;
    const ERROR_CODE_CAPTCHA_ERROR    = 1104;
    const ERROR_CODE_PASS_LENGTH      = 1105;
    const ERROR_CODE_NO_REGISTER      = 1106;
    const ERROR_CODE_NO_LOGIN         = 1107;
    const ERROR_CODE_FORMAT_PWD       = 1108;
    const ERROR_CODE_CAPTCHA_SENDED   = 1109;
    const ERROR_CODE_USER_PWD         = 1110;
    const ERROR_CODE_NOT_LOGIN        = 1111;
    const ERROR_CODE_WECHAT_NOT_BIND  = 1112;
    const ERROR_CODE_WECHAT_HASE_BIND_OTHER = 1113;
    const ERROR_CODE_REPEAT_POINT     = 1114;

    //咨询跳转列表
    const ERRPR_CODE_HEADER_JUMP_LIST = 1115;
    //图片验证码
    const ERROR_CODE_CAPTCHA_IMG      = 1116;
    const ERROR_CODE_CAPTCHA_IMG_ERR  = 1117;
    const ERROR_CODE_UNSAFE_PWD       = 1118;
    const ERROR_CODE_NEED_AUTH        = 1119;



    // 咨询师APP错误常量 3000-6000
    const ERROR_CODE_LOGIN_ROLE_ERROR = 3001;
    const ERROR_CODE_CONFIRM_DISTRIBUTE_ERROR = 3002;
    const ERROR_CODE_EMPLOYEE_PWD = 3003;
    const ERROR_CODE_REPORT_EXIST = 3004;
    const ERROR_CODE_USER_SYS_NUMBER_BINDING = 3005;
    const ERROR_CODE_MANAGER_SET_STOP_VISIT  = 3006;
    const ERROR_CODE_USER_MOBILE_EXIST = 3007;
    const ERROR_CODE_REPORT = 3008;
    const ERROR_CODE_ORDER_NOT_CONFIRM = 3009;
    const ERROR_CODE_ORDER_REMIND=3010;

    const ERROR_CODE_POPUP=3011;
    const ERROR_CODE_REQUEST_METHOD_TYPE = 40001; //请求类型失败

    // 服务号错误常量 6001-9000
    const ERROR_CODE_NO_JOIN = 6001;
    const ERROR_CODE_SHARE_FAIL = 6002;
    const ERROR_CODE_NO_LOGIN_IN_INSURE   = 6003;
    const ERROR_CODE_NO_LOGIN_IN_BEIDOU   = 6006;
    const ERROR_CODE_NO_SIGN_SUCCESS = 6004;
    const ERROR_CODE_MOBILE_SUCCESS = 6005;//手机号有活跃订单,user_id没有活跃订单

    // 留电异常,但是文案是提示成功
    const ERROR_CODE_SAVE_NUMBER_FAIL = 7001;
    // 留点异常,用户设置了免打扰
    const ERROR_CODE_SET_DISTURB = 7002;

    // 小程序错误常量定义
    const ERROR_CODE_NO_OPENID = 8001; // 未获得openid需要客户端重新install
    //支撑系统
    const ERROR_CODE_NO_LOGIN_VPN = 20001;//没有登录vpn
    const ERROR_CODE_INVALID_SALES_STATUS = 20002;  // 无效的销售状态
    const ERROR_CODE_WAINING_PRICE = 20003;  // 价格超限提示
    const ERROR_CODE_IS_EXIST     = 20004;   //居理楼盘名称已存在
    const ERROR_CODE_BUILDING_STATUS     = 20005;   //楼栋自洽状态错误
    const ERROR_CODE_PROJECT_STATUS     = 20006;   //楼盘自洽状态错误

    //楼盘户型状态自洽
    const ERROR_CJ_PROJECT_ALL_SALE = 20007;  //楼盘售罄报错
    const ERROR_CJ_PROJECT_WAIT_SALE = 20008;  //楼盘待售报错
    const ERROR_CJ_PROJECT_PARAM_NOT_ENOUGH = 20009;  //楼盘动态信息录入不足30%
    const ERROR_CJ_HOUSE_TYPE_IS_VALIDATE = 20010;  //户型单价总价自洽
    const ERROR_CJ_PROJECT_ALL_SALE_DB = 20011;//楼盘售罄报错（取数据库值判断）
    const ERROR_CJ_PROJECT_WAIT_SALE_DB = 20012;//楼盘待售报错（取数据库值判断）

    //HR 人力系统
    //提交离职交接 未提交离职申请审批
    const ERROR_CODE_NO_SUBMIT_DIMISSION=9001;
    //bbs
    const ERROR_DEL            = 2000;
    const ERROR_CODE_ERROR_TYPE = 2001;
    const ERROR_CODE_POST_DO_NOT_EXIST = 2002;
    const ERROR_CODE_SAVE = 2003;
    const ERROR_CODE_POST_ALREADY_COMMENT = 2004;

    //放量计划app楼栋
    const ERROR_CODE_DATA_VOLUME_BUILD     = 1601;

    public static $errMsgs = [
        // 成功
        self::SUCCESS_CODE                        => '操作成功',
        self::UPDATE_VERSION                      => '请先升级至最新版本，在我的页面中点击检查新版本按钮即可升级',
        // 未修改
        self::ERROR_CODE_NO_MODIFY                => '未修改',
        // 错误
        self::ERROR_CODE_SYSTEM                   => '服务器异常',
        self::ERROR_DEL                           => '删除失败',
        self::ERROR_CODE_SAVE                     => '保存失败',
        self::ERROR_CODE_PARAM                    => '参数错误',
        self::ERROR_CODE_NO_LICATION              => '定位失败',
        self::ERROR_CODE_RATE_LIMIT               => '操作过于频繁',
        self::ERROR_CODE_UNAUTHORIZED             => '网络请求失败，请重试',// token错误
        self::ERROR_CODE_DATA_THROW               => '数据异常',
        self::ERROR_CODE_REQUEST_VALUE_NULL       => '数据为空',
        self::ERROR_CODE_DECRYPT_FAIL             => '解密失败',
        self::ERROR_CODE_FLASH_LOGIN              => '一键登录失败',
        self::ERROR_CODE_DECRYPT_STRING_FAIL      => '需解密的秘串必须是两个.连接的字符串',
        self::ERROR_CODE_ORDER_CREATE_FAIL        => '请重新提交订单！如有问题还可联系客服400-625-0061',
        self::ERROR_CODE_ERROR_TYPE               => '分类不存在',
        self::ERROR_CODE_POST_DO_NOT_EXIST        => '帖子不存在',
        self::ERROR_CODE_POST_ALREADY_COMMENT     => '该帖子已经被评过',
        self::ERROR_CODE_DATA_FAVORITE_MORE       => '收藏失败',
        self::ERROR_CODE_NO_AUTHORITY             => '您无操作权限',
        self::ERROR_CODE_NOTFOUND                 => '页面未找到',
        self::ERROR_CODE_FIELD_REQUIRED           => '字段必填',
        self::ERROR_CODE_UNPOWER                  => '对不起，您现在还没获此操作的权限',
        self::ERROR_CODE_POWER_NULL               => '当前角色，权限为空',
        self::ERROR_CODE_MENU_NULL                => '当前角色，菜单为空',
        self::ERROR_CODE_UNKNOWN_ROLE             => '未知角色',
        self::ERROR_CODE_LOW_VERSION              => '当前版本过低，请卸载重装',
        self::ERROR_CODE_SYSTEM_MAINTENANCE       => '系统升级中，请稍后。。。',
        self::ERROR_CODE_REQUEST_TOO_MANY         => '您的请求过于频繁，请稍后重试',
        self::ERROR_CODE_THIRD_PARTY              => '第三方错误',
        self::ERROR_CODE_ALREADY_CLICK            => '此视频已经赞过了哦~',
        self::ERROR_CODE_ALREADY_EXIST            => '此条记录已经存在',

        // 登录注册用户相关
        self::ERROR_CODE_PWD                      => '密码错误',
        self::ERROR_CODE_MOBILE_EMPTY             => '手机号不存在',
        self::ERROR_CODE_MOBILE_ERROR             => '手机号不正确',
        self::ERROR_CODE_CAPTCHA_ERROR            => '验证码不正确',
        self::ERROR_CODE_PASS_LENGTH              => '密码必须大于6位',
        self::ERROR_CODE_NO_REGISTER              => '您还没有注册，请先注册再登录',
        self::ERROR_CODE_NO_LOGIN                 => '用户未登陆',
        self::ERROR_CODE_FORMAT_PWD               => '密码格式错误',
        self::ERROR_CODE_CAPTCHA_SENDED           => '验证码已发送,请勿重复请求',
        self::ERROR_CODE_CAPTCHA_IMG              => '请输入图片验证码',
        self::ERROR_CODE_CAPTCHA_IMG_ERR          => '图片验证码输入错误',
        self::ERROR_CODE_USER_PWD                 => '用户名密码错误',
        self::ERROR_CODE_NOT_LOGIN                => '请登录',
        self::ERROR_CODE_WECHAT_NOT_BIND          => '未绑定微信用户',
        self::ERROR_CODE_WECHAT_HASE_BIND_OTHER   => '您的微信账号已绑定其他用户',
        self::ERROR_CODE_REPEAT_POINT             => '不能重复领取积分',
        self::ERRPR_CODE_HEADER_JUMP_LIST         => '当前咨询师不存在',
        self::ERROR_CODE_UNSAFE_PWD               => '密码安全校验未通过',

        // 咨询师APP
        self::ERROR_CODE_LOGIN_ROLE_ERROR         => '账号不存在或密码错误',
        self::ERROR_CODE_CONFIRM_DISTRIBUTE_ERROR => '确认收到分配异常',
        self::ERROR_CODE_EMPLOYEE_PWD             => '账号不存在或密码错误',
        self::ERROR_CODE_USER_SYS_NUMBER_BINDING  => '用户系统号绑定错误',
        self::ERROR_CODE_MANAGER_SET_STOP_VISIT   => '主管设置成了停访，不能接上户',
        self::ERROR_CODE_USER_MOBILE_EXIST        => '该号码已被注册，请更换其他手机号',
        self::ERROR_CODE_REPORT                   => '报备错误',
        self::ERROR_CODE_ORDER_NOT_CONFIRM        => '有订单未确认',
        self::ERROR_CODE_REQUEST_METHOD_TYPE        => '请求方法的类型错误',

        // 服务号
        self::ERROR_CODE_NO_JOIN                  => '无资格参数活动',
        self::ERROR_CODE_SHARE_FAIL               => '分享失败',

        self::ERROR_CODE_SAVE_NUMBER_FAIL         => '留电异常',

        self::ERROR_CODE_NO_OPENID                => '未获得openid',

        // HR人力系统
        self::ERROR_CODE_NO_SUBMIT_DIMISSION      =>'离职进行交接之前先发起离职申请审批！离职申请通过之后再发起离职交接审批！',

        self::ERROR_CODE_MOURNING                  =>'亲爱的用户，评论通道暂时关闭，系统升级后开启，谢谢您的理解'
    ];
}