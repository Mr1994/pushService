<?php
namespace app\controllers;


use app\constants\CodeConstant;
use app\constants\PushConstant;
use app\exception\ParamException;
use app\exception\RequestException;
use app\models\PushAuthConfig;
use app\models\PushHandlerResult;
use yii;
use yii\web\Response;

class PushBaseController extends InternalAPIController
{
    //不需要signature验证的route
    public $allowNoSignatureRoutes = [
        'push-server/build',
    ];

    /**
     * @param $action
     * creater: 卫振家
     * create_time: 2020/6/9 上午7:30
     * @return bool
     * @throws RequestException
     * @throws yii\web\BadRequestHttpException
     * @throws ParamException
     */
    public function beforeAction($action) {
        parent::beforeAction($action);

        //大数据调用不校验
        if(self::isBigDataCall()) {
            return true;
        }

        //如果允许不验证token那么就写入
        if (in_array(Yii::$app->controller->getRoute(), $this->allowNoSignatureRoutes)) {
            return true;
        }

        if (!self::checkSignature()) {
            throw new RequestException("check signature failed!");
        }
        return true;

    }

    /**
     * @param $request_id
     * @param int $code
     * @param string $errMsg
     * @param array $data
     * @return array
     * creater: 卫振家
     * create_time: 2020/6/9 上午8:17
     */
    public function output($request_id, $code = CodeConstant::SUCCESS_CODE, $errMsg = '', $data = [])
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (empty($errMsg) && isset(CodeConstant::$errMsgs[$code])) {
            $errMsg = CodeConstant::$errMsgs[$code];
        }

        empty($data) && $data = (object)[];

        $rst = [
            'code'       => $code,
            'request_id' => $request_id,
            'errMsg'     => $errMsg,
            'data'       => $data
        ];

        PushHandlerResult::getApiOutputResult($request_id,$code,$errMsg,$rst)->yiiLogInfo();

        return $rst;
    }


    /**
     * 校验签名
     * @return bool
     * creater: 卫振家
     * create_time: 2020/6/9 上午8:17
     * @throws RequestException
     */
    public static function checkSignature()
    {
        $app_key       = trim(Yii::$app->request->post('app_key', ''));
        $timestamp     = intval(Yii::$app->request->post('timestamp', ''));
        $signature     = trim(Yii::$app->request->post('signature', ''));
        $julive_app_id = intval(Yii::$app->request->post('julive_app_id', ''));

        if(empty($app_key) || empty($timestamp) || empty($signature) || empty($julive_app_id)) {
            throw new RequestException("signature 参数错误!");
        }

        //有效期校验
        if(time() - $timestamp > PushConstant::SIGNATURE_EXPIRE) {
            throw new RequestException("signature 过期!");
        }

        //获取配置校验
        $app_auth_config = PushAuthConfig::getPushAuthByAppKey($app_key);
        if(empty($app_auth_config)) {
            throw new RequestException("signature 获取数据库配置失败!");
        }

        //权限校验
        $auth_app_id = intval(substr($julive_app_id, 0, 3));
        if(! in_array($auth_app_id, explode(',', $app_auth_config->julive_app_id))) {
            throw new RequestException("signature 获取数据库配置失败!");
        }

        //签名校验
        if($signature != self::signature($app_key, $app_auth_config->app_secret, $timestamp)) {
            throw new RequestException("signature 密钥不一致!");
        }
        return true;

    }

    /**
     * 获取签名
     * @param $app_key
     * @param $app_secret
     * @param $timestamp
     * @return string
     * creater: 卫振家
     * create_time: 2020/6/9 上午7:56
     */
    protected static function signature($app_key, $app_secret, $timestamp)
    {
        $app_key = trim($app_key);
        $app_secret = trim($app_secret);
        $timestamp  = intval($timestamp);
        return md5($timestamp . $app_key . $app_secret);
    }
}
