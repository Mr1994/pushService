<?php

require __DIR__  . '/bootstrap.php'; // 数据库配置

// 项目配置
$config = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/' . YII_ENV . '/db.php', // 数据库配置
    require __DIR__ . '/' . YII_ENV . '/params.php'
);
// 配置文件参数
//$params = require __DIR__ . '/' . YII_ENV . '/params.php' ;
//$config['components']['params'] = $params;

return $config;
