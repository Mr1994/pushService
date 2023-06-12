<?php
/**
 * Created by PhpStorm.
 * User: sun
 * Date: 2020/5/7
 * Time: 15:29
 */
return [
    'server' => [
        'log' => [
            'access_log_path' => '/alidata/log/push_service/http/access_'.date('Ymd',time()).'.log',
            'error_log_path' => '/alidata/log/push_service/http/error_'.date('Ymd',time()).'.log',
        ],
        'api_client_server'   => 'http://testserver.apiclient.comjia.com/server',
    ],
    'is_debug' => true,
];