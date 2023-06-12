<?php
namespace app\components\filters\ratelimiter;

use app\exception\PushApiException;
use Yii;
use yii\base\Model;
use yii\filters\RateLimitInterface;

class User extends Model implements RateLimitInterface
{
    const SPLIT = '|';

    /**
     * limit类型
     * @var string
     */
    private $type = 'ratelimit';

    /**
     * 支持的限速种类，每一种就是一种类型的redis key
     * 要控制这个数组，不能随意添加！
     * @var array
     */
    const RATELIMIT      = 'ratelimit';
    const UNIQUE_ID      = 'unique_id';
    const IDENTITY_ID    = 'identity_id';
    const DAILY          = 'daily';
    const MOBILE_CAPTCHA = 'mobile_captcha';

    private $type_list = [self::RATELIMIT, self::UNIQUE_ID, self::IDENTITY_ID, self::DAILY, self::MOBILE_CAPTCHA];

    /**
     * @var the IP of the user
     */
    private $ip;
    /**
     * @var the maximum number of allowed requests
     */
    private $rateLimit;
    /**
     * @var the time period for the rates to apply to
     */
    private $timePeriod;

    /**
     * Returns a surrogate user with the IP address assigned.
     * @param string $ip the IP of the client.
     * @param $rateLimit
     * @param $timePeriod
     * @return User the user component.
     */
    public static function findByIp($ip, $rateLimit, $timePeriod)
    {
        $user = new User;
        $user->ip = $ip;
        $user->rateLimit = $rateLimit;
        $user->timePeriod = $timePeriod;
        return $user;
    }

    /**
     * 设置类型
     * @param string $type
     * @return $this
     */
    public function setType(string $type)
    {
        if (!in_array($type, $this->type_list)) {
            throw new PushApiException('', "无对应限速类型");
        }
        $this->type = $type;
        return $this;
    }

    /**
     * Returns the maximum number of allowed requests and the window size.
     * @param \yii\web\Request $request the current request
     * @param \yii\base\Action $action the action to be executed
     * @return array an array of two elements. The first element is the maximum number of allowed requests,
     * and the second element is the size of the window in seconds.
     */
    public function getRateLimit($request, $action)
    {
        return [$this->rateLimit, $this->timePeriod];
    }

    /**
     * Loads the number of allowed requests and the corresponding timestamp from a persistent storage.
     * @param \yii\web\Request $request the current request
     * @param \yii\base\Action $action the action to be executed
     * @return array an array of two elements. The first element is the number of allowed requests,
     * and the second element is the corresponding UNIX timestamp.
     */
    public function loadAllowance($request, $action)
    {
        $cache = Yii::$app->redis;
        $result = explode(static::SPLIT, $cache->get("user.$this->type.ip.allowance." . $this->ip));
        return [
            intval($result[0] ? $result[0] : 0),
            intval($result[1] ? $result[1] : 0),
        ];
    }

    /**
     * Saves the number of allowed requests and the corresponding timestamp to a persistent storage.
     * @param \yii\web\Request $request the current request
     * @param \yii\base\Action $action the action to be executed
     * @param integer $allowance the number of allowed requests remaining.
     * @param integer $timestamp the current timestamp.
     */
    public function saveAllowance($request, $action, $allowance, $timestamp)
    {
        $cache = Yii::$app->redis;

        $cache->SETEX("user.$this->type.ip.allowance." . $this->ip, $this->timePeriod, "$allowance" . static::SPLIT . "$timestamp");
    }

    /**
     * 输入完验证码后删除相关限速
     */
    public function remove()
    {
        $cache = Yii::$app->redis;
        $cache->del("user.$this->type.ip.allowance." . $this->ip);
    }
}