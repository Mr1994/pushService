<?php
namespace app\helpers\time;

use app\helpers\PrintHelper;
use Closure;

class Timer
{
    private $start_time;
    private $interval;
    private $last_run_time;
    private $run_num = 0;
    private $max_run_num;
    private $callback;

    private $has_async_run = false;

    const UN_LIMIT_NUM = -1;

    private static $has_declare = false;
    public function __construct($interval, Closure  $call_back, $max_run_num)
    {
        $this->start_time  = $this->last_run_time = time();
        $this->interval    = $interval;
        $this->callback    = $call_back;
        $this->max_run_num = $max_run_num;
        PrintHelper::printDebug('生成timer成功');
    }

    /**
     * 设置定时器
     * @param $interval
     * @param $call_back
     * @return Timer
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/30 6:35 下午
     */
    public static function getIntervalTimer($interval, $call_back)
    {
        return new self($interval, $call_back, self::UN_LIMIT_NUM);
    }

    /**
     * 获取超时器
     * @param $interval
     * @param $call_back
     * @return Timer
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/30 6:35 下午
     */
    public static function getTimeOutTimer($interval, $call_back)
    {
        return new self($interval, $call_back, 1);
    }


    /**
     * 获取超时器
     * @param $interval
     * @param $call_back
     * @param $max_run_num
     * @return Timer author: 卫振家
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/30 6:35 下午
     */
    public static function getTimesLimitTimer($interval, $call_back, $max_run_num)
    {
        return new self($interval, $call_back, $max_run_num);
    }

    /**
     * 声明declare
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/30 11:36 上午
     */
    private function asyncCall()
    {
        if(! self::$has_declare)  {
            declare(ticks = 100);
            self::$has_declare = true;
        }
        if( ! $this->has_async_run) {
            register_tick_function(function (){
                $this->call();
            });
            $this->has_async_run = true;
        }
    }

    /**
     * 定时消息处理器
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/30 11:45 上午
     * @param bool $async
     * @return bool
     */
    public function run($async = false)
    {
        if($async) {
            //构造一个定时执行期
            $this->asyncCall();
        }else {
            $this->call();
        }
        return true;
    }

    /**
     * 调用方法
     * @return mixed
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/30 11:27 上午
     */
    private function call()
    {
        if($this->max_run_num === 0) {
            return null;
        }
        if((time() - $this->last_run_time) < $this->interval) {
            return null;
        }

        $callback = $this->callback;
        $result   = $callback();
        $this->last_run_time = time();
        $this->run_num++;
        if($this->max_run_num > 0) {
            $this->max_run_num--;
        }
        PrintHelper::printDebug("第{$this->run_num}次运行timer 回调成功:");
        return $result;
    }
}