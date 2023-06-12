<?php
/**
 * Created by PhpStorm.
 * User: sun
 * Date: 2020/5/7
 * Time: 15:29
 */
return [
    'kafka' => [
        'common_queue' => [
            'brokers' => '172.22.131.242:9092',
            'produce_log_path' => '/alidata/log/push_service/queue/produce_'.date('Ymd',time()).'.log',
            'consume_log_path' => '/alidata/log/push_service/queue/consume_'.date('Ymd',time()).'.log',
            'error_log_path' => '/alidata/log/push_service/queue/error_'.date('Ymd',time()).'.log',
        ],
    ]
];