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
            '101'    => [
                'app_key'    => 'dfce63ae0991470fa4d58b5a',
                'app_secret' => '22b462cdcb43636d8cf7b6fc',
            ],
            '101006' => [
                'app_key'    => '3afcffb448e5c4945dbf6051',
                'app_secret' => '1dc82a634771886ef89d1b71',
            ],
            // 居理新房APP IOS
            '201'    => [
                'app_key'    => 'dfce63ae0991470fa4d58b5a',
                'app_secret' => '22b462cdcb43636d8cf7b6fc',
            ],
            // 咨询师APP ANDROID
            '102'    => [
                'app_key'    => '1c759edefa38f463c2708129',
                'app_secret' => '08ae8e183ad2d9888e3e1d55',
            ],
            // 经纪人app ANDROID
            '807'    => [
                'app_key'    => 'fa0de7f448ba2dd60383cf74',
                'app_secret' => '18312917bcd0329a704be68c',
            ],
            // 经纪人app IOS
            '808'    => [
                'app_key'    => 'fa0de7f448ba2dd60383cf74',
                'app_secret' => '18312917bcd0329a704be68c',
            ],
        ],
        'xiaomi_push'  => [
            // 居理新房APP ANDROID
            '101'    => [
                'app_id'     => '2882303761517993077',
                'app_key'    => '5681799372077',
                'app_secret' => 'uSdIgakgztqLj7ha329xCw=='
            ],
            '101006' => [
                'app_id'     => '2882303761518153046',
                'app_key'    => '5531815344046',
                'app_secret' => 'jLUxgxfX0oyNjz+3iRIAjQ=='
            ],
        ],
        'huawei_push'  => [
            // 居理新房APP ANDROID
            '101'    => [
                'app_id'     => '100739137',
                'app_secret' => '8765f70c752cfdd534b107aea166bfc6b2ba4c010b125900312093451cd2b32d'
            ],
            '101006' => [
                'app_id'     => '101098677',
                'app_secret' => '1ebd00a4df384f2e70f57e344dbbba232a92f209d42ef44c1c456c0ed8781e3f'
            ],
            // 咨询师pad ANDROID
            '103'    => [
                'app_id'     => '101357277',
                'app_secret' => 'ea73513458e7a9cf56614a98d56f9aa6ad149309459890d312862db74af2223c'
            ],
        ],
        'vivo_push'    => [
            '101'    => [
                'app_id'     => '15001',
                'app_key'    => '57407078-2680-4465-81d6-7f663ec2600b',
                'app_secret' => 'adc65517-8549-4084-a836-51cc9d6f565d'
            ],
            '101006' => [
                'app_id'     => '102397612',
                'app_key'    => '7216e81296fc0e24a2b38b9d048fdc5d',
                'app_secret' => '8e2afb44-0f39-4b6d-bc50-35046cffb55f'
            ],
        ],
        'oppo_push'    => [
            '101'    => [
                'app_key'       => 'c447b2958bfc48e59abca2bfcb7c79d2',
                'master_secret' => 'bc10d93c55b54c4f8b85ec257303f519'
            ],
            '101006' => [
                'app_key'       => '83cc61e6fefb4c9da0f41cd81ac0810a',
                'master_secret' => 'd341c7a019f74f0a865d7d6f12628662',
            ],
        ],
        'apple_push'   => [
            '201'    => [
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