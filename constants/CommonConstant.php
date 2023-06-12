<?php


namespace app\constants;


class CommonConstant
{
    //通用状态
    const COMMON_STATUS_YES = 1;
    const COMMON_STATUS_NO = 2;
    const COMMON_STATUS = [
        self::COMMON_STATUS_YES => '是',
        self::COMMON_STATUS_NO  => '否',
    ];

    //平台表示
    const PLAT_TYPE_JULIVE = 1;
    const PLAT_TYPE_KFS = 2;
    const PLAT_TYPE_XPT = 3;
    const PLAT_TYPE = [
        self::PLAT_TYPE_JULIVE => '居理新房',
        self::PLAT_TYPE_KFS    => '开发商',
        self::PLAT_TYPE_XPT    => '新平台',
    ];

    //分页表示
    const PAGE_SIZE = 20; // app默认分页数量
}