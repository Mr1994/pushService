<?php


namespace app\commands;


use app\helpers\PusherHelper;
use yii\console\Controller;

class BaseConsoleController  extends Controller
{
    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);

        // 错误处理
        $error_handler = function ($errno, $errstr = '', $errfile = '', $errline = 0) {
            PusherHelper::sendErrorDing($errno, $errstr, $errfile, $errline);
        };
        set_error_handler($error_handler);

        // 错误处理
        $shut_down = function () {
            PusherHelper::sendShutDownCall();
        };
        register_shutdown_function($shut_down);
    }

    const PUSH_INTERVAL = 10; //单位秒

    /**
     * 休眠一下
     * @param $start_time
     * creater: 卫振家
     * create_time: 2020/5/12 下午9:02
     */
    protected static function try_sleep($start_time)
    {
        $current_time = time();
        if($start_time + self::PUSH_INTERVAL > $current_time)
        {
            $t = $start_time + self::PUSH_INTERVAL - $current_time;
            sleep($t);
        }
    }
}