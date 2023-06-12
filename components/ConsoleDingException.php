<?php


namespace app\components;


use app\exception\PushMsgException;
use app\helpers\CommonHelper;
use Exception;
use Yii;

class ConsoleDingException extends BaseDingException
{
    /**
     * 获取报警数据
     * @return      array
     * @author      赵薇
     * @date        2020-01-03
     * ---------------------------
     * @var         exception
     */
    public function getExceptionData() {
        $data = [];

        // 获取异常报警信息
        $exception = Yii::$app->errorHandler->exception;


        // 异常信息
        $data['exception']['message'] = $exception->getMessage();
        $data['exception']['line']    = $exception->getLine();
        $data['exception']['file']    = str_replace(WWW_PATH, '',  $exception->getFile());
        $data['exception']['code']    = $exception->getCode();
        $data['exception']['text']    = $exception->getTraceAsString();
        $data['exception']['class']   = get_class(Yii::$app->errorHandler->exception);
        $data['exception']['route']   = "./yii " . implode(' ', Yii::$app->request->params);

        // 中文不转为unicode ，对应的数字 256
        $args = '';
        if(($exception instanceof PushMsgException)) {
            $args = "{$exception->push_message}";
        }

        // 获取可用的请求头信息
        $headers = $this->getHeader();

        //是否@all
        if (isset($this->is_at_all)) {
            $isAtAll = true;
        }else{
            $isAtAll = false;
        }

        // 其他信息
        $data['project_id'] = YII::$app->id; // 项目id
        $data['args'] = $args;
        $data['ding_mobile'] = array_unique($this->at_mbile); // 获取@用户
        $data['str_mobile'] = $this->_atMobile($data['ding_mobile']);
        $data['headers_str'] = $this->getFormatHeaderData($headers); //格式化header数据
        $data['is_at_all'] = $isAtAll;

        // 第一行敏感异常数据加粗
        $data['file'] = str_replace(WWW_PATH, '', $exception->getFile());
        $data['env'] = YII_ENV;
        $data['ip']  = CommonHelper::get_server_ip();
        $data['server_ip'] = CommonHelper::get_server_ip();

        /**
         * 异常信息md5值
         * 项目ID + 错误描述 + 行号
         */
        $data['md5_str'] = md5($data['project_id'] . $exception->getMessage() . $exception->getLine());

        return $data;
    }
}