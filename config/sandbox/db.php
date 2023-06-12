<?php

$config = [
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'charset' => 'utf8',
            'dsn' => 'mysql:host=172.22.209.25;dbname=julive_system',
            'username' => 'julive_system',
            'password' => 'C4ohgook6Ei%Vu6ox',
            'enableSchemaCache' => true,
            'schemaCacheDuration' => 86400,//缓存一天
            'schemaCache' => 'schema_cache',//缓存的位置
        ],
        'db_yingyan' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=172.22.209.25;dbname=track',
            'username' => 'comjia_sandbox',
            'password' => 'comjia_sandbox_784512',
            'charset' => 'utf8',
        ],
        //cache
        'redis_business'=>[
            'class'=>'yii\redis\Connection',
            'hostname'=>'172.22.209.25',
            'port'=>'6379',
            'database'=>15,
            'password'=>'kanjia888888',
        ],
        'db_pc_comjia' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=172.22.209.25;dbname=pc_comjia',
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