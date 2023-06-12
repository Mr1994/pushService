<?php


namespace app\helpers;

use app\constants\CommonConstant;
use app\constants\PushConstant;
use app\models\KafkaPushMessage;
use Yii;

class PusherHelper
{
    /**
     * 发送慢发送警告
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/19 5:15 下午
     * @param KafkaPushMessage $kafka_push_message
     * @return bool
     */
    public static function sendSlowPushWarning($kafka_push_message)
    {
        $now        = time();
        $delay_time = $now - $kafka_push_message->push_time;
        if ($delay_time < 7200) {
            return true;
        }

        $hour                = date('Y-m-d H');
        $slow_hour_redis_key = sprintf(PushConstant::REDIS_KEY_HOUR_SLOW_PUSH_WARNING, $hour);
        $has_hour_slow_push  = Yii::$app->redis_business->get($slow_hour_redis_key);
        if (empty($has_hour_slow_push)) {
            // 运行id
            $env = YII_ENV;
            $pid = getmypid();

            // 获取请求地址
            $params = Yii::$app->request->params;
            $route  = "./yii " . implode(' ', $params);

            //公共报警信息
            $error_message =
                "### 脚本运行过慢报警：\n" .
                "------------\n\n" .
                "**开发环境**：{$env} \n\n" .
                "**延迟时间**：{$env} \n\n" .
                "**运行脚本**：" .
                "<font color=#FF0000>{$route}</font> \n\n " .
                "**进程id**：{$pid}\n\n\n\n" .
                "**消息id**：{$kafka_push_message->request_id}\n\n" .
                "**延迟时间**：{$delay_time}\n\n" .
                "**消息体**:{$kafka_push_message}";

            PrintHelper::printDing($error_message);
            Yii::$app->redis_business->setex($slow_hour_redis_key, 3600, CommonConstant::COMMON_STATUS_YES);
        }
        return true;
    }

    /**
     * 脚本关闭回调
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/20 6:30 下午
     */
    public static function sendShutDownCall()
    {
        $env = YII_ENV;
        $pid = getmypid();

        // 获取请求地址
        $params = Yii::$app->request->params;
        $route  = "./yii " . implode(' ', $params);


        //公共报警信息
        $error_message =
            "### 脚本退出：\n" .
            "------------\n\n" .
            "**开发环境**：{$env} \n\n" .
            "**运行脚本**：" .
            "<font color=#FF0000>{$route}</font> \n\n " .
            "**进程id**：{$pid}\n\n\n\n";

        PrintHelper::printDing($error_message);
    }

    /**
     * 错误处理
     * @param $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * Author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 1/14/21 9:56 AM
     */
    public static function sendErrorDing($errno, $errstr = '', $errfile = '', $errline = 0)
    {
        $env = YII_ENV;

        // 获取请求地址
        $params = Yii::$app->request->params;
        $route  = "./yii " . implode(' ', $params);

        //公共报警信息
        $error_message =
            "### php错误：{$errstr}\n" .
            "------------\n\n" .
            "**开发环境**：{$env} \n\n" .
            "**运行脚本**：" .
            "<font color=#FF0000>{$route}</font> \n\n " .
            "**错误信息**：\n\n" .
            ">错误文件：{$errfile}\n\n".
            ">错误行：{$errline} \n\n".
            ">错误级别：{$errno} \n\n"
        ;

        PrintHelper::printDing($error_message);
    }

    /**
     * @return string
     * @autor: julive sunwenke@julive.com
     * @create_time: 2020/10/22 11:57 上午
     */
    public static function setLockPriorty($topic, $group_id)
    {
        if(is_array($topic)) {
            return  false;
        }
        $key = $topic . ":" . $group_id;
        return Yii::$app->redis_business->setex($key, 300, 1);
    }

    /**
     * @param $topic
     * @param $group_id
     * @autor: julive sunwenke@julive.com
     * @create_time: 2020/10/22 12:15 下午
     * 获取高优先级锁
     */
    public static function getLockPriorty($topic,$group_id){
        if(is_array($topic)) {
            return false;
        }
        $key = $topic . ":" . $group_id;
        return Yii::$app->redis_business->get($key);
    }

    /**
     * 增加数组判定
     * @param $topic
     * @param $group_id
     * @return bool
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/23 10:17 上午
     */
    public static function delLockPriorty($topic,$group_id){
        if(is_array($topic)) {
            return  false;
        }
        $key = $topic . ":" . $group_id;
        return Yii::$app->redis_business->del($key);
    }
}