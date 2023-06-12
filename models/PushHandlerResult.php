<?php


namespace app\models;

use app\constants\CodeConstant;
use app\helpers\PrintHelper;
use Yii;
use yii\base\Model;

/**
 * This is the model class for kafka push message.
 *
 * @property string $request_id
 * @property int    $need_retry
 * @property int    $code
 * @property string $message
 * @property array $raw_params
 * @property array $raw_result
 * @property array $invalid_reg_id
 * @property int   $create_time
 */
class PushHandlerResult extends Model
{
    public $request_id;
    public $need_retry;
    public $code;
    public $message;
    public $raw_params;
    public $raw_result;
    public $invalid_reg_id;
    public $create_time;

    const NEED_RETRY_YES = 1;
    const NEED_RETRY_NO  = 2;

    public function attributes()
    {
        return [
            'request_id', 'code', 'message', 'raw_result', 'raw_params', 'need_retry','create_time', 'invalid_reg_id'
        ];
    }

    public function rules()
    {
        return [
            [['code', 'message', 'raw_params', 'raw_result', 'request_id', 'create_time'], 'required'],
            [['code', 'need_retry'], 'integer'],
            [['raw_params', 'raw_result', 'invalid_reg_id'], 'default', 'value' => []],
            [['message'], 'string'],
        ];
    }

    /**
     * 转换为json
     * @return false|string
     * creater: 卫振家
     * create_time: 2020/5/8 下午2:35
     */
    public function __toString()
    {
        $raw_result = json_encode($this->raw_result, JSON_UNESCAPED_UNICODE);
        $raw_param  = json_encode($this->raw_params, JSON_UNESCAPED_UNICODE);
        return "request_id:{$this->request_id},message:{$this->message},result:{$raw_result},request:{$raw_param}";
    }

    /**
     * 增加记录处理中间方法
     * creater: 卫振家
     * create_time: 2020/5/15 上午10:20
     */
    public function yiiLogError()
    {
        PrintHelper::printError("{$this->message} id:{$this->request_id}");
    }

    /**
     * 增加记录处理中间方法 todo修改为小驼峰
     * creater: 卫振家
     * create_time: 2020/5/15 上午10:19
     */
    public function yiiLogInfo()
    {
        PrintHelper::printInfo("{$this->message} id:{$this->request_id}");
    }

    ################################# API的返回结果开始 ################################
    /**
     * 获取api的output
     * @param $request_id
     * @param $code
     * @param $message
     * @param $data
     * @return PushHandlerResult
     * creater: 卫振家
     * create_time: 2020/5/12 上午10:45
     */
    public static function getApiOutputResult($request_id, $code, $message, $data)
    {
        //构造一个空的result;
        $result             = new PushHandlerResult();
        $result->code       = $code;
        $result->message    = $message;
        $result->request_id = $request_id;
        $result->need_retry = PushHandlerResult::NEED_RETRY_NO;
        $result->raw_params = [
            'ip'   => Yii::$app->request->userIP,
            'url'  => Yii::$app->request->url,
            'data' => Yii::$app->request->post()
        ];
        $result->raw_result = $data;
        $result->invalid_reg_id = [];
        $result->create_time = time();
        return $result;
    }

    /**
     * api异常结果记录
     * @param $request_id
     * @param $exception_code
     * @param $exception_message
     * @return PushHandlerResult
     * creater: 卫振家
     * create_time: 2020/5/12 上午10:05
     */
    public static function getApiExceptionResult($request_id, $exception_code, $exception_message)
    {
        //构造一个空的result;
        $result             = new PushHandlerResult();
        $result->code       = $exception_code;
        $result->message    = $exception_message;
        $result->request_id = $request_id;
        $result->need_retry = PushHandlerResult::NEED_RETRY_NO;
        $result->raw_params = [
            'ip'   => Yii::$app->request->userIP,
            'url'  => Yii::$app->request->url,
            'data' => Yii::$app->request->post()
        ];
        $result->raw_result = ['code' => $exception_code, 'message' => $exception_message];
        $result->invalid_reg_id = [];
        $result->create_time = time();
        return $result;
    }

    ################################# API的返回结果结束 ################################

    /**
     * 消息异常结果记录
     * @param KafkaPushMessage  $push_message
     * @param $exception_code
     * @param $exception_message
     * @return PushHandlerResult
     * creater: 卫振家
     * create_time: 2020/5/12 上午10:05
     */
    public static function getMsgExceptionResult($push_message, $exception_code, $exception_message)
    {
        //构造一个空的result;
        $result             = new PushHandlerResult();
        $result->code       = $exception_code;
        $result->message    = $exception_message;
        $result->request_id = $push_message->request_id;
        $result->need_retry = PushHandlerResult::NEED_RETRY_NO;
        $result->raw_params = $push_message->toArray();
        $result->raw_result = [];
        $result->invalid_reg_id = [];
        $result->create_time = time();
        return $result;
    }

    /**
     * 消息异常结果记录
     * @param KafkaPushMessage $push_message
     * @param string $message
     * @param array $act_result
     * @return PushHandlerResult
     * creater: 卫振家
     * create_time: 2020/5/12 上午10:05
     */
    public static function getMsgActResult($push_message, $message = '操作成功', $act_result = [])
    {
        //构造一个空的result;
        $result             = new PushHandlerResult();
        $result->code       = CodeConstant::SUCCESS_CODE;
        $result->message    = $message;
        $result->request_id = $push_message->request_id;
        $result->need_retry = PushHandlerResult::NEED_RETRY_NO;
        $result->raw_params = $push_message->toArray();
        $result->raw_result = $act_result;
        $result->invalid_reg_id = [];
        $result->create_time = time();
        return $result;
    }

    ################################# 消息异常结果开始  ################################

    /**
     * 获取默认结果集
     * @param KafkaPushMessage $push_message
     * @param array $push_result
     * @return PushHandlerResult
     * creater: 卫振家
     * create_time: 2020/5/11 下午9:30
     */
    public static function getDefaultResult($push_message, $push_result = [])
    {
        if(empty($push_result)) {
            $push_result = ['code' => CodeConstant::ERROR_CODE_SYSTEM, 'message' => '接口响应为空'];
        }

        //构造一个空的result;
        $result             = new PushHandlerResult();
        $result->code       = CodeConstant::ERROR_CODE_SYSTEM;
        $result->message    = '接口响应为空';
        $result->request_id = $push_message->request_id;
        $result->need_retry = PushHandlerResult::NEED_RETRY_NO;
        $result->raw_params = $push_message->toArray();
        $result->raw_result = $push_result;
        $result->invalid_reg_id = [];
        $result->create_time = time();
        return $result;
    }

    ################################# 消息异常结果结束 ################################
}

