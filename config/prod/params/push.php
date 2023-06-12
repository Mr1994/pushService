<?php
/**
 * Created by PhpStorm.
 * User: sun
 * Date: 2020/5/7
 * Time: 15:29
 */
return [
    'push' => [
        'jiguang_push' => [
            // 居理新房APP ANDROID
            '101' => [
                'app_key'    => '************',
                'app_secret' => '************',
            ],
            // 居理新房APP IOS
            '201' => [
                'app_key'    => '************',
                'app_secret' => '************',
            ],
            // 咨询师APP ANDROID
            '102' => [
                'app_key'    => '************',
                'app_secret' => '************',
            ],
            // 经纪人app ANDROID
            '807' => [
                'app_key'    => '************',
                'app_secret' => '************',
            ],
            // 经纪人app IOS
            '808' => [
                'app_key'    => '************',
                'app_secret' => '************',
            ],
        ],
        'xiaomi_push'  => [
            // 居理新房APP ANDROID
            '101' => [
                'app_id'     => '************',
                'app_key'    => '************',
                'app_secret' => '************'
            ],
        ],
        'huawei_push'  => [
            // 居理新房APP ANDROID
            '101' => [
                'app_id'     => '************',
                'app_secret' => '************'
            ],
            // 咨询师pad ANDROID
            '103' => [
                'app_id'     => '************',
                'app_secret' => '************'
            ],
        ],
        'vivo_push'    => [
            '101' => [
                'app_id'     => '15001',
                'app_key'    => '57407078-2680-4465-81d6-7f663ec2600b',
                'app_secret' => 'adc65517-8549-4084-a836-51cc9d6f565d'
            ]
        ],
        'oppo_push'    => [
            '101' => [
                'app_key'       => '3wwyLX2Xsmww04s44WWOkK0cs',
                'master_secret' => '466eF494516B37d3219DBF5489352704'
            ]
        ],
        'apple_push'   => [
            '201' => [
                'provider_cert_pem' => '@pushConfig/cert/apns_product201.pem'
            ],
            '201004' => [
                'provider_cert_pem' => '@pushConfig/cert/apns_product201004.pem'
            ],
            '201002' => [
                'provider_cert_pem' => '@pushConfig/cert/apns_product201002.pem'
            ]
        ],
    ]
];