<?php


if (!defined('ENV_STATE')) {
    $env_state = get_cfg_var('switch.environment');
    define('ENV_STATE', $env_state === false ? 'dev' : $env_state);
    defined('YII_ENV') or define('YII_ENV', ENV_STATE);
}
//defined('YII_ENV') or define('YII_ENV', 'dev');
defined('WWW_PATH') or define('WWW_PATH', __DIR__);
defined('YII_DEBUG') or define('YII_DEBUG', (YII_ENV !== 'prod'));

// comment out the following two lines when deployed to production
//defined('YII_DEBUG') or define('YII_DEBUG', true);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';



(new yii\web\Application($config))->run();
