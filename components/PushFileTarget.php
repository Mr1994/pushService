<?php


namespace app\components;


use yii\helpers\VarDumper;
use yii\log\FileTarget;
use yii\log\Logger;

class PushFileTarget extends FileTarget
{
    public function formatMessage($message)
    {
        list($text, $level, $category, $timestamp) = $message;
        $level = Logger::getLevelName($level);
        if (!is_string($text)) {
            // exceptions may not be serializable if in the call stack somewhere is a Closure
            if ($text instanceof \Throwable || $text instanceof \Exception) {
                $text = (string) $text;
            } else {
                $text = VarDumper::export($text);
            }
        }
        $traces = [];
        if (isset($message[4])) {
            foreach ($message[4] as $trace) {
                $traces[] = "in {$trace['file']}:{$trace['line']}";
            }
        }

        $text_arr     = json_decode($text, 1);
        $message_data = [
            'log_timestamp' => $this->getTime($timestamp),
        ];
        if(empty($text_arr)) {
            $message_data['log_msg'] = $text;
        }else{
            $message_data = array_merge($message_data, $text_arr);
        }
        return json_encode($message_data, JSON_UNESCAPED_UNICODE);
    }
}