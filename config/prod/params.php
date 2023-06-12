<?php


$params['params'] = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/params/push.php', //push业务
    require __DIR__ . '/params/server.php', //api请求
    require __DIR__ . '/params/kafka.php' //kafka请求
);
return $params;


