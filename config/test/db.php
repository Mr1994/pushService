<?php

$config = [
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'charset' => 'utf8',
            'dsn' => 'mysql:host=172.22.131.242;dbname=julive_system',
            'username' => 'julive_system',
            'password' => 'C4ohgook6Ei%Vu6ox',
            'enableSchemaCache' => false,
            'schemaCacheDuration' => 86400,//缓存一天
            'schemaCache' => 'schema_cache',//缓存的位置
        ],
        'db_yingyan' => [
            'class' => 'yii\db\Connection',
            'charset' => 'utf8',
            'dsn' => 'mysql:host=172.22.131.242;dbname=track',
            'username' => 'comjia002_wukong',
            'password' => 'Je6Quo!jei-ph9Esfo$iFee2',
        ],
        //cache
        'redis_business'=>[
            'class'=>'yii\redis\Connection',
            'hostname'=>'172.22.131.242',
            'port'=>'6379',
            'database'=>15,
            'password'=>'kanjia888888',
        ],
        'db_pc_comjia' => [
            'class' => 'yii\db\Connection',
            'charset' => 'utf8',
            'dsn' => 'mysql:host=172.22.131.242;dbname=comjia_merge',
            'username' => 'comjia002_wukong',
            'password' => 'Je6Quo!jei-ph9Esfo$iFee2',
            'enableSchemaCache' => false,
            'schemaCacheDuration' => 86400,//缓存一天
            'schemaCache' => 'schema_cache',//缓存的位置
        ],
    ]
];
return $config;