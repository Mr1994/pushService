<?php

namespace app\controllers;

use app\components\filters\ratelimiter\InternalAPIRateLimiter;
use common\components\filters\jwt\JwtFilter;
use Yii;
use yii\web\Response;
use yii\web\Controller;
use app\constants\InternalAPI;
use app\exception\RequestException;

class InternalAPIController extends Controller
{
    public $enableCsrfValidation = false;
    public $client_ip = null;

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        //修改内容
        $behaviors[] = [
            'class' => 'yii\filters\ContentNegotiator',
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
                // 'text/html' => Response::FORMAT_JSON,
            ],
        ];

        if (isset($this->client_ip) && ! self::isBigDataCall()) {
            $behaviors['rateLimiter'] = [
                'class'                  => InternalAPIRateLimiter::className(),
                'client_ip'              => $this->client_ip,
                'enableRateLimitHeaders' => false,
                'rule'                   => [],

            ];
        }
        return $behaviors;
    }

    public static function checkIP()
    {
        //只有生成环境开启白名单验证
        if (!YII_ENV_PROD) {
            return true;
        }
        $ip = self::getUserIp();
        foreach (InternalAPI::WHITE_LIST as $rule) {
            if (strpos($rule, '/') === false && $ip == $rule) {
                return true;
            } else if (static::ip_in_network($ip, $rule)) {
                return true;
            }
        }
        Yii::error("InternalAPIController::checkIP失败: " . $ip);
        return false;
    }

    private static function ip_in_network($ip, $network)
    {
        $ip = (float) (sprintf("%u", ip2long($ip)));
        $s = explode('/', $network);
        $network_start = (float) (sprintf("%u", ip2long($s[0])));
        $network_len = pow(2, 32 - $s[1]);
        $network_end = $network_start + $network_len - 1;

        if ($ip >= $network_start && $ip <= $network_end) {
            return true;
        }
        return false;
    }

    private static function getUserIp()
    {
        //strcasecmp 比较两个字符，不区分大小写。返回0，>0，<0。
        if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $clientIp = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ips = explode(',', getenv('HTTP_X_FORWARDED_FOR'));
            array_filter($ips);
            if (count($ips) > 0 && !empty($ips[0])) {
                $clientIp = $ips[0];
            }
        } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $clientIp = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $clientIp = $_SERVER['REMOTE_ADDR'];
        }
        if (empty($clientIp) || $clientIp == "none") {
            $clientIp = null;
        }
        return $clientIp;
    }

    private static function getcheckData(string $type)
    {
        switch ($type) {
            case InternalAPI::REQUEST_TYPE_JSON:
                return Yii::$app->request->getRawBody();
                break;
            case InternalAPI::REQUEST_TYPE_FORM:
                $data = Yii::$app->request->post();
                return json_encode($data, true);
                break;
            default:
                throw new RequestException("julive-internal-api-type error!");
        }
    }

    /**
     * 处理数据
     * @param \yii\base\Action $action
     * @return bool
     * @throws RequestException
     * @throws \yii\web\BadRequestHttpException
     * creater: 卫振家
     * create_time: 2020/6/9 下午12:09
     */
    public function beforeAction($action)
    {
        parent::beforeAction($action);

        //大数据调用不校验
        if(self::isBigDataCall()) {
            return true;
        }

        //return true;
        //白名单校验
        if (!self::checkIP()) {
            throw new RequestException("ip white list check failed!");
        }
        $type = Yii::$app->request->headers->get('julive-internal-api-type');
        if (empty($type)) {
            throw new RequestException("julive-internal-api-type is empty");
        }
        $key = Yii::$app->request->headers->get("julive-internal-api-key");
        if (empty($key)) {
            throw new RequestException("julive-internal-api-key is empty");
        }
        $data = static::getcheckData($type);
        if (empty($data)) {
            throw new RequestException("check data is empty");
        }
        $check = md5($data);
        if ($check != $key) {
            throw new RequestException("julive-internal-api-key check failed");
        }
        return true;
    }

    /**
     * 返回处理成功
     *
     * @param string $message 返回数据
     * @return void
     */
    public function InternalCallOk($data = [])
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (is_array($data) && empty($data)) {
            $data = (object) array();
        }
        return [
            'code' => InternalAPI::OK,
            'errMsg' => 'ok',
            'data' => $data
        ];
    }

    /**
     * 返回处理异常
     *
     * @param int $code 错误码
     * @param string $errMsg 错误信息
     * @return void
     */
    public function InternalCallError(int $code, string $errMsg)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return [
            'code' => $code,
            'errMsg' => $errMsg,
            'data' => (object) array()
        ];
    }


    /**
     * 检查白名单
     * @return bool
     * creater: 卫振家
     * create_time: 2020/6/9 上午11:34
     */
    public static function isBigDataCall()
    {
        //只有生成环境开启白名单验证
        if (!YII_ENV_PROD) {
            return true;
        }
        $ip = self::getUserIp();
        foreach (InternalAPI::BIG_DATA_CALL_IPS as $rule) {
            if (strpos($rule, '/') === false && $ip == $rule) {
                return true;
            } else if (static::ip_in_network($ip, $rule)) {
                return true;
            }
        }
        Yii::info("InternalAPIController::大数据调用: " . $ip);
        return false;
    }

}
