<?php

namespace app\components;

use yii\helpers\VarDumper;
use yii\log\FileTarget;

/**
 * Class MessageTarget
 * @package julive\component\log
 * 无处理日志类。不携带任何yii携带信息
 */
class MessageTarget extends FileTarget
{


    public function formatMessage($message)
    {
        list($text, $level, $category, $timestamp) = $message;

        if (is_string($text)) {
            return $text;
        } elseif (is_array($text)) {
            return json_encode($text);
        } else {
            return VarDumper::export($text);
        }
    }

    /**
     * Generates the context information to be logged.
     * The default implementation will dump user information, system variables, etc.
     * @return string the context information. If an empty string, it means no context information.
     */
    public function getContextMessage()
    {
        return '';

    }

}