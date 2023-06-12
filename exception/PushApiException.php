<?php


/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\exception;

use app\models\PushHandlerResult;
use Exception;
use app\constants\CodeConstant;
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
class PushApiException extends BaseException
{
    public function __construct($request_id, $message = "", $code = CodeConstant::ERROR_CODE_PARAM, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}