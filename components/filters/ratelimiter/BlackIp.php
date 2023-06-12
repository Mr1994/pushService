<?php
/**
 *
 * redis存储IP黑名单
 * @Author yuanjinliang, <yuanjinliang@julive.com>.
 * Date: 2019-06-06 09:59
 */

namespace app\components\filters\ratelimiter;

use yii\base\Object;

class BlackIp extends Object
{

    private $redis;
    private $key  = "user:black:";
    public  $type = ''; // 针对某个action的限制需要设置。如果是全局的不需要设置

    public $timeout = 86400; // 检测黑名单生效时间
    public $expire  = 0; // 封禁时长

    public $ip; //必传。黑名单标识，一般为IP
    public $hit_num; // 触发几次后加黑名单

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->redis = \Yii::$app->redis_business;
    }

    public function setType($action)
    {
        $this->type = $action;
        return $this;
    }

    public function getKey()
    {
        return $this->key . $this->type . ":" . $this->ip;
    }

    public function check()
    {
        $result = false;
        if ($this->redis->exists($this->getKey())) {
            $res = $this->redis->get($this->getKey());
            $result = ((int) $res) <= 0;
        }
        return $result;
    }

    public function update()
    {
        $key = $this->getKey();
        if ($this->redis->exists($key)) {
            $after = $this->redis->decr($this->getKey());
            if (($after - 1) <= 0) {
                $this->redis->setex($this->getKey(), $this->expire, 0);
            }
        } else {
            $this->redis->setex($this->getKey(), $this->timeout, $this->hit_num);
        }
    }
}