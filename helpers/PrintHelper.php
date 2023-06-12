<?php


namespace app\helpers;


use Exception;
use Yii;
use yii\helpers\Console;

class PrintHelper
{
    /**
     * 打印错误信息
     * @param $data
     * creater: 卫振家
     * create_time: 2020/6/2 上午11:28
     */
    public static function printError($data)
    {
        $new_line = str_repeat("\n", strlen($data) - strlen(ltrim($data, "\n")));
        $message  = $new_line .  Date('Y-m-d H:i:s') . ' ' . ltrim($data) . "\n";

        if(Yii::$app->controller instanceof yii\console\Controller) {
            Yii::$app->controller->stderr($message, Console::FG_RED, Console::UNDERLINE);
        }else {
            Yii::error($message);
        }
    }

    /**
     * 打印普通信息信息
     * @param $data
     * creater: 卫振家
     * create_time: 2020/6/2 上午11:29
     */
    public static function printInfo($data)
    {
        $new_line = str_repeat("\n", strlen($data) - strlen(ltrim($data, "\n")));
        $message  = $new_line . Date('Y-m-d H:i:s') . ' ' . ltrim($data) . "\n";

        if(Yii::$app->controller instanceof yii\console\Controller) {
            Yii::$app->controller->stdout("$message", Console::FG_GREEN, Console::UNDERLINE);
        }else {
            Yii::info($message);
        }
    }

    /**
     * 打印debug信息
     * @param $data
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/26 7:53 下午
     */
    public static function printDebug($data)
    {
        if(! Yii::$app->params['is_debug']) {
            return;
        }
        $new_line = str_repeat("\n", strlen($data) - strlen(ltrim($data, "\n")));
        $message  = $new_line . Date('Y-m-d H:i:s') . ' ' . ltrim($data) . "\n";

        if(Yii::$app->controller instanceof yii\console\Controller) {
            Yii::$app->controller->stdout("$message", Console::FG_GREEN, Console::UNDERLINE);
        }else {
            Yii::info($message);
        }
    }


    /**
     * 发送钉钉消息
     * @param $ding_message
     * @param string $title
     * @param string $msg_type
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/20 6:35 下午
     */
    public static function printDing($ding_message, $title = '异常报警', $msg_type = 'markdown')
    {
        $ding_exception_sender =  Yii::$app->DingException;

        // 设置参数
        $ding_mobile = $ding_exception_sender->at_mbile;
        $ding_url    = $ding_exception_sender->ding_url;
        $is_at_all = false;
        if (isset($ding_exception_sender->is_at_all)) {
            $is_at_all = true;
        }

        // 整合请求的数据
        $data = array(
            'msgtype'  => 'markdown',
            'markdown' => [
                'title' => $title,
                'text'  => $ding_message,
            ],
            'at'       => [
                'atMobiles' => $ding_mobile,
                'isAtAll'   => $is_at_all
            ],
        );

        try {
            $http = new HttpHelper();
            $result = $http->setConnectTimeOut(1)->setTimeOut(5)->postJson($ding_url, $data);
        }catch (Exception $e) {
            PrintHelper::printError("发送钉钉失败{$e->getMessage()}");
            return;
        }

        $result = is_scalar($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE);
        PrintHelper::PrintInfo('钉钉发送异常成功 - ' . $result);
    }
}