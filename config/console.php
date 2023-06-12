<?php



$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'runtimePath' => '/alidata/log/push_service/console',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'flushInterval' => 1,
            'targets' => [
                [
                    'class'   => 'yii\log\FileTarget',
                    'levels'  => YII_ENV_PROD ? ['error', 'warning'] : ['error', 'warning', 'info'],
                    'logFile' => '@runtime/logs/push_console_' . date('Y-m-d-H',time()) . '.log',
                ],
                [
                    'class' => 'app\components\MessageTarget',
                    'levels' => ['info', 'error', 'warning'],
                    'categories' => ['huawei_callback'],
                    'logFile'=> '@runtime/../callback/logs/huawei_' . date('Y-m-d',time()) . '.log',
                ],
                [
                    'class' => 'app\components\MessageTarget',
                    'levels' => ['info', 'error', 'warning'],
                    'categories' => ['xiaomi_callback'],
                    'logFile'=> '@runtime/../callback/logs/xiaomi_' . date('Y-m-d',time()) . '.log',
                ],
                [
                    'class' => 'app\components\MessageTarget',
                    'levels' => ['info', 'error', 'warning'],
                    'categories' => ['oppo_callback'],
                    'logFile'=> '@runtime/../callback/logs/oppo_' . date('Y-m-d',time()) . '.log',
                ],
                [
                    'class' => 'app\components\MessageTarget',
                    'levels' => ['info', 'error', 'warning'],
                    'categories' => ['vivo_callback'],
                    'logFile'=> '@runtime/../callback/logs/vivo_' . date('Y-m-d',time()) . '.log',
                ],
                [
                    'class' => 'app\components\MessageTarget',
                    'levels' => ['info', 'error', 'warning'],
                    'categories' => ['jiguang_callback'],
                    'logFile'=> '@runtime/../callback/logs/jiguang_' . date('Y-m-d',time()) . '.log',
                ],
                [
                    'class' => 'app\components\MessageTarget',
                    'levels' => ['info', 'error', 'warning'],
                    'categories' => ['apple_callback'],
                    'logFile'=> '@runtime/../callback/logs/apple_' . date('Y-m-d',time()) . '.log',
                ],
            ],
        ],
        'errorHandler' => [
            'class' => '\app\components\PushErrorHandler'
        ],
        'DingException' => [
            'class'     => '\app\components\ConsoleDingException',
            'at_mbile' => [
                '17600240045',
                '13121789715'
            ],
            'is_at_all' => false,
            'ding_url'  => 'https://oapi.dingtalk.com/robot/send?access_token=f5d2970d7d3a975bec97ac6281ead564bd5fb652793ca752c2098579a9d04e76',
            'except' => [
            ],
        ],
        'response' => [
            'class' => 'yii\console\Response',
            'on beforeSend' => function($event) {
                if (isset(\yii::$app->components['DingException'])) {
                    \Yii::$app->DingException->dingMsg();
                }
            },
        ],
    ],
    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];
$config= yii\helpers\ArrayHelper::merge(
    $config,
    require __DIR__ . '/index.php'
);
if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;
