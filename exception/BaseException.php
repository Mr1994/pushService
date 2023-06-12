<?php


/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\exception;

use app\constants\CodeConstant;
use app\models\PushHandlerResult;
use Exception;
use Yii;

/**
 * FileTarget records log messages in a file.
 *
 * The log file is specified via [[logFile]]. If the size of the log file exceeds
 * [[maxFileSize]] (in kilo-bytes), a rotation will be performed, which renames
 * the current log file by suffixing the file name with '.1'. All existing log
 * files are moved backwards by one place, i.e., '.2' to '.3', '.1' to '.2', and so on.
 * The property [[maxLogFiles]] specifies how many history files to keep.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class BaseException extends Exception
{
    public function __construct($message = "", $code = 0, Exception $previous = null)
    {
        if (!isset(CodeConstant::$errMsgs[$code])) {
            $code = CodeConstant::ERROR_CODE_SYSTEM;
        }

        parent::__construct((empty($message) ? CodeConstant::$errMsgs[$code] : $message), $code, $previous);


        //发送钉钉
        if(Yii::$app->controller instanceof yii\console\Controller) {
            Yii::$app->errorHandler->exception = $this;
            Yii::$app->DingException->dingMsg();
            Yii::$app->errorHandler->exception = null;
        }
    }
}