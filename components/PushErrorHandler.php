<?php

namespace app\components;

use Yii;
use yii\web\Response;
use yii\db\Exception;
use yii\web\ErrorHandler;
use yii\web\HttpException;
use yii\base\UserException;
use app\constants\CodeConstant;
use yii\web\TooManyRequestsHttpException;

class PushErrorHandler extends ErrorHandler
{
    /**
     * Converts an exception into an array.
     * @param \Exception $exception the exception being converted
     * @return array the array representation of the exception.
     */
    protected function convertExceptionToArray($exception)
    {
        if (!YII_ENV_DEV && !YII_ENV_TEST && !$exception instanceof UserException && !$exception instanceof HttpException) {
            $exception = new HttpException(500, '', CodeConstant::ERROR_CODE_SYSTEM);
        }
        if ($exception instanceof TooManyRequestsHttpException) {
            $exception = new HttpException(500, '', CodeConstant::ERROR_CODE_REQUEST_TOO_MANY);
        }

        $code          = $exception->getCode();
        $msg           = $exception->getMessage();
        $array         = [];
        $array['code'] = isset(CodeConstant::$errMsgs[$code]) && !empty($code) ? $code : CodeConstant::ERROR_CODE_SYSTEM;

        if (YII_ENV_DEV || YII_ENV_TEST) {
            $array['errMsg']            = empty($msg) ? CodeConstant::$errMsgs[$array['code']] : $msg;
            $array['exception']['name'] = ($exception instanceof Exception || $exception instanceof ErrorException) ? $exception->getName() : 'Exception';
            if ($exception instanceof HttpException) {
                $array['exception']['status'] = $exception->statusCode;
            }
            $array['exception']['type'] = get_class($exception);
            $array['exception']['file']        = $exception->getFile();
            $array['exception']['line']        = $exception->getLine();
            $array['exception']['stack-trace'] = explode("\n", $exception->getTraceAsString());
            if ($exception instanceof Exception) {
                $array['exception']['error-info'] = $exception->errorInfo;
            }

            if (($prev = $exception->getPrevious()) !== null) {
                $array['exception']['previous'] = $this->convertExceptionToArray($prev);
            }
        } else {
            $array['errMsg'] = CodeConstant::$errMsgs[$array['code']];
        }

        Yii::$app->response->on(Response::EVENT_BEFORE_SEND, function ($event) {
            $event->sender->statusCode = 200;
        });
//        Yii::error('[EsaErrorHandler] common params-->' . var_export(Yii::$app->request->apiCommon(), true));
//        Yii::error('[EsaErrorHandler] post params-->' . var_export(Yii::$app->request->post(), true));
        $array['data'] = (object)[];
        return $array;
    }
}