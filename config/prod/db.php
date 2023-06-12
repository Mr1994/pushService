<?php


$config = [
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'charset' => 'utf8',
            'dsn' => 'mysql:host=123.57.229.36;dbname=julive_system',
            'username' => 'julive_system',
            'password' => 'C4ohgook6Ei%Vu6ox',
            'enableSchemaCache' => true,
            'schemaCacheDuration' => 86400,//缓存一天
            'schemaCache' => 'schema_cache',//缓存的位置
        ],
        'db_yingyan' => [
            'class' => 'yii\db\Connection',
            'charset' => 'utf8',
            'dsn' => 'mysql:host=123.57.229.36;dbname=track',
            'username' => 'comjia002_wukong',
            'password' => 'Je6Quo!jei-ph9Esfo$iFee2',
        ],
        //cache
        'redis_business'=>[
            'class'=>'yii\redis\Connection',
            'hostname'=>'123.57.229.36',
            'port'=>'6379',
            'database'=>15,
            'password'=>'kanjia888888',
        ],
        'db_pc_comjia' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=172.22.14.211;dbname=pc_comjia',
            'username' => 'comjia_sandbox',
            'password' => 'comjia_sandbox_784512',
            'charset' => 'utf8',
            'enableSchemaCache' => true,
            'schemaCacheDuration' => 86400,//缓存一天
            'schemaCache' => 'schema_cache',//缓存的位置
        ],
    ]
];
return $config;