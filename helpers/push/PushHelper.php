<?php


namespace app\helpers\push;

use app\constants\CodeConstant;
use app\constants\CommonConstant;
use app\exception\PushHandlerException;
use app\helpers\PrintHelper;
use app\models\KafkaPushMessage;
use app\models\PushHandlerResult;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;
use Yii;
use yii\helpers\ArrayHelper;

Abstract Class PushHelper
{

    /**
     * @var int 居理应用id
     */
    const SEND_CONFIG_KEY = '';

    // curl并发数量
    const CURL_CONCURRENCY = 1;

    //限速redis
    const RDKEY_PUSH_RATE_LIMIT_BY_CURL_NUM   = 'push_rate_limit:curl:%s';

    //最大发送次数约束
    const MAX_BATCH_MESSAGE_NUM = 10;
    const MAX_BATCH_REG_ID_NUM = 3000;

    // 配置相关
    protected $julive_app_id;
    protected $config;
    protected $push_handler_id;

    //handler
    private static $handlers = [];


    //推送相关信息
    protected $client         = null ;

    /**
     * @var array [KafkaPushMessage]
     */
    protected $batch_message      = [];
    protected $pool_index_map     = [];


    //批量发送调用限制
    public $batch_limit_status = CommonConstant::COMMON_STATUS_NO;
    public $batch_limit_day   = 0;

    //单发送调用限制
    public $single_limit_status = CommonConstant::COMMON_STATUS_NO;
    public $single_limit_day   = 0;

    public $is_refresh_token = false;

    /**
     * 构造器
     * JiguangPushHelper constructor.
     * @param $julive_app_id
     */
    public function __construct($julive_app_id)
    {
        $this->julive_app_id = $julive_app_id;
        $push_config  = ArrayHelper::getValue( Yii::$app->params['push'], [static::SEND_CONFIG_KEY], []);
        $this->config = ArrayHelper::getValue( $push_config, $this->julive_app_id, []);
        $this->push_handler_id = static::SEND_CONFIG_KEY . md5(json_encode($this->config));
    }

    /**
     * 获取配置
     * @param string $key
     * @return mixed|null
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/14 3:53 下午
     */
    public function getConfig($key = '')
    {
        if(empty($key)) {
            return $this->config;
        }
        if(! isset($this->config[$key])) {
            return '';
        }
        return $this->config[$key];
    }

    /**
     * 返回单例
     * @param $julive_app_id
     * @return PushHelper
     * creater: 卫振家
     * create_time: 2020/5/11 下午4:13
     * @throws PushHandlerException
     */
    public static function instance($julive_app_id)
    {
        $push_config = ArrayHelper::getValue( Yii::$app->params['push'], static::SEND_CONFIG_KEY, []);
        $send_config = ArrayHelper::getValue( $push_config, $julive_app_id, []);
        if(empty($send_config)){
            throw new PushHandlerException('未获取到对应推送类型的配置');
        }
        return new static($julive_app_id);
    }

    /**
     * 发送消息
     * @param KafkaPushMessage $push_message
     * @return void
     */
    public function send($push_message) {
        //记录次数
        $push_message->addTopicGroupTypeDaySendNum();
        $push_message->addTopicGroupDaySendNum();

        //发送
        $this->batch_message[] = $push_message;
        if (! $this->readyForPushMessageList()) {
            return;
        }

        $this->batchSend();
    }

    /**
     * 批量发送
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/21 9:46 下午
     */
    public function batchSend()
    {
        // 获取客户端
        $client = $this->getPushGuzzleClient();

        // 获取链接生成器
        $requests = $this->getRequestsClosure();

        // 构造链接池子
        $pool = new Pool($client, $requests(), [
//            'concurrency' => static::CURL_CONCURRENCY,
            'fulfilled' => function (ResponseInterface $response, $index) {
                $this->afterFulfilled($this->getPoolPushMessage($index), $response);
            },
            'rejected' => function ($reason, $index) {
                $this->afterReject($this->getPoolPushMessage($index), $reason);
            },
        ]);

        // Initiate the transfers and create a promise
        $promise = $pool->promise();

        // Force the pool of requests to complete.
        $promise->wait();

        $this->batch_message  = [];
        $this->pool_index_map = [];
    }

    /**
     * 获取guzzle客户端
     * @return mixed
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/19 6:42 下午
     */
    public function getPushGuzzleClient()
    {
        if (empty($this->client)) {
            $this->client = new Client([
                'verify' => false,
                'timeout' => 1
            ]);
        }
        return $this->client;
    }

    /**
     * 获取待发送的消息
     * @return bool author: 卫振家
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/19 6:38 下午
     */
    public function readyForPushMessageList()
    {
        // 获取push_config
        if (empty($this->batch_message)) {
            return false;
        }
        $message_num = count($this->batch_message);
        PrintHelper::printDebug(static::SEND_CONFIG_KEY . "当前累计个数:{$message_num}\n");

        //如果数量足够就发送
        if ($message_num >= static::MAX_BATCH_MESSAGE_NUM) {
            return true;
        }

        // 根据最新的push配置判断是否数据是否正确
        $last_message = end($this->batch_message);
        reset($this->batch_message);
        if(isset($last_message->push_config['business_push']) || isset($last_message->push_config['push_now'])) {
            return true;
        }

        // 设置push_message_为空
        return false;
    }

    /**
     * 通过异步发送索引获取消息本身
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/24 5:53 上午
     * @param $index
     * @return mixed|null
     */
    public function getPoolPushMessage($index)
    {
        // 获取push索引
        $push_index   = $this->pool_index_map[$index];
        $push_message = $this->batch_message[$push_index];

        // 返回push消息
        return $push_message;
    }

    /**
     * 获取请求闭包
     * @return \Closure
     * @return mixed
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/22 10:21 上午
     */
    abstract public function getRequestsClosure();

    /**
     * vivo异常处理
     * @param KafkaPushMessage $push_message
     * @param $response
     * @return bool author: 卫振家
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/21 9:42 下午
     */
    public function afterFulfilled($push_message, $response) {
        // 锚定消息
        $request_id = $push_message->request_id;

        // 结果处理
        $status     = $response->getStatusCode();
        $raw_result = $response->getBody()->getContents();
        PrintHelper::printInfo("请求成功， 消息id：{$request_id}, 状态码：{$status}，结果：$raw_result \n");
        $raw_result =  empty($raw_result) ? [] : json_decode($raw_result, true);

        // 结果处理
        $result = PushHandlerResult::getDefaultResult($push_message, $raw_result);
        $result->code    = CodeConstant::SUCCESS_CODE;
        $result->message = '推送成功';
        return $push_message->afterHandlerSend($result);
    }

    /**
     * 拒绝处理
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/22 9:56 上午
     * @param KafkaPushMessage $push_message
     * @param $reason
     * @return bool
     */
    public function afterReject($push_message, $reason) {
        // 锚定消息
        $request_id = $push_message->request_id;

        // 结果处理
        $code = $reason->getCode();
        $message = $reason->getMessage();
        PrintHelper::printInfo("请求失败， 消息id：{$request_id}, 失败码：{$code}, 失败原因：{$message} \n");

        //默认结果
        $result = PushHandlerResult::getDefaultResult($push_message, []);
        $result->code    = CodeConstant::ERROR_CODE_SYSTEM;
        $result->message = $message;

        //curl请求失败
        if (in_array($code, [0])) {
            $result->code       = CodeConstant::ERROR_CODE_SYSTEM;
            $result->message    = $message;
            $result->need_retry = CommonConstant::COMMON_STATUS_YES;

            //强制刷新新的curl客户端
            $this->client = null;

            //消息收尾处理
            return $push_message->afterHandlerSend($result);
        }

        return $push_message->afterHandlerSend($result);
    }

    /**
     * 获取跳转链接
     * @param $push_message
     * @return mixed
     * creater: 卫振家
     * create_time: 2020/5/14 下午6:31
     */
    public static function getSchemeUrl(KafkaPushMessage $push_message)
    {
        $scheme_url = ArrayHelper::getValue($push_message->push_params, 'scheme_url', '');
        if (empty($scheme_url) && is_string($push_message->push_params)) {
            $scheme_url = ArrayHelper::getValue(json_decode($push_message->push_params, true), 'scheme_url', '');
        }
        $scheme_url = stripslashes($scheme_url);
        if(! empty($scheme_url) && strpos($scheme_url, 'comjia://app.comjia.com/project_info?data=') !== false) {
            $scheme_url = self::buildExtShowSchemeUrl($scheme_url, $push_message);
        }
        return $scheme_url;
    }

    /**
     * 获取承接scheme_url
     * @param string $scheme_url
     * @param KafkaPushMessage $push_message
     * @return string|string
     * Author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020-12-15 11:50
     */
    public static function buildExtShowSchemeUrl($scheme_url, KafkaPushMessage $push_message)
    {
        //获取url的参数
        $url_data = parse_url($scheme_url);
        parse_str($url_data['query'], $query_data);
        if(empty($query_data['data'])) {
            return $scheme_url;
        }

        //获取scheme中的新data
        $scheme_data = json_decode($query_data['data'], true);
        if(empty($scheme_data)) {
            $scheme_data = json_decode(urldecode($query_data['data']), true);
        }
        if(empty($scheme_data)) {
            return $scheme_url;
        }

        //构造新data
        $scheme_data['title']       = $push_message->title;
        $scheme_data['notification'] = $push_message->notification;
        $query_data['data'] =  json_encode($scheme_data);
        //构造链接
        $query_string  = http_build_query($query_data);
        return "{$url_data['scheme']}://{$url_data['host']}{$url_data['path']}?{$query_string}";
    }

    /**
     * 获取跳转链接
     * @param $push_message
     * @return mixed
     * creater: 卫振家
     * create_time: 2020/5/14 下午6:31
     */
    public static function getImageUrl($push_message)
    {
        $image_url = ArrayHelper::getValue($push_message->push_params, 'image_url', '');
        if (empty($image_url) && is_string($push_message->push_params)) {
            $image_url = ArrayHelper::getValue(json_decode($push_message->push_params, true), 'image_url', '');
        }
        return str_replace('http:', 'https:', $image_url);;
    }


    /**
     * 按照请求次数限速
     * @param string $push_handler_id 发送器类型， 1小米 2华为 3极光
     * @param int $time_window 发送窗口大小, 单位秒
     * @param int $limit_num 窗口内最大发送数量，单位个数， 允许的数量可以用 $limit_num/$time_window表示
     * @param int $curl_num 当前需要发送的数量，单位个数
     * @return bool
     */
    public static function rateLimit($push_handler_id, $time_window, $limit_num, $curl_num)
    {
        $push_handler_id = trim($push_handler_id);
        $time_window     = intval($time_window);
        $limit_num       = intval($limit_num);
        $curl_num        = intval($curl_num);

        $get_lock_result = self::getRedisRateLimitLock($push_handler_id, $time_window, $limit_num, $curl_num);
        while(! $get_lock_result) {
            $get_lock_result = self::getRedisRateLimitLock($push_handler_id, $time_window, $limit_num, $curl_num);
        }

        return true;
    }

    /**
     * 获取redis时间窗口锁
     * @param $push_handler_id
     * @param $time_window
     * @param $limit_num
     * @param $use_num
     * @return bool
     */
    public static function getRedisRateLimitLock($push_handler_id, $time_window, $limit_num, $use_num)
    {
        $redis_key       = sprintf(self::RDKEY_PUSH_RATE_LIMIT_BY_CURL_NUM, $push_handler_id);

        $current_time = time();

        $redis_key    = trim($redis_key);
        $time_window  = intval($time_window);
        $limit_num    = intval($limit_num);
        $use_num      = intval($use_num);


        //获取计数的key
        $time_poll_key = Yii::$app->redis_business->get($redis_key);
        if(empty($time_poll_key)) {
            $default_time_poll_key = "{$redis_key}:{$time_window}:{$current_time}";
            if(Yii::$app->redis_business->setnx($redis_key, $default_time_poll_key)) {
                $time_poll_key = $default_time_poll_key;
                do{
                    Yii::$app->redis_business->expire($redis_key, $time_window);
                } while( Yii::$app->redis_business->ttl($redis_key) < 0);
            }else {
                $time_poll_key = Yii::$app->redis_business->get($redis_key);
            }
        }


        //增加需要的调用的次数
        $new_use_num   = Yii::$app->redis_business->incrby($time_poll_key, $use_num);

        //获取锁失败
        if($new_use_num > $limit_num) {

            //沉睡时间 等待下一个时间窗口时间的开始时间
            $sleep_time = Yii::$app->redis_business->ttl($redis_key);

            PrintHelper::printError("限速 redis-key: {$time_poll_key} ，需要等待时间: {$sleep_time} ,当前时间: {$current_time}");

            if($sleep_time > 0 ) {
                sleep($sleep_time);
            }else {
                Yii::$app->redis_business->del($redis_key);
                Yii::$app->redis_business->del($time_poll_key);
            }
            return false;
        }

        //如果是第一次获取锁，则设置超时时间为时间窗口
        if($new_use_num == $use_num) {
            //设置超时时间
            do{
                Yii::$app->redis_business->expire($time_poll_key, $time_window + 1);
            } while( Yii::$app->redis_business->ttl($time_poll_key) < 0);
        }
        PrintHelper::printDebug("获锁成功 redis-key: {$time_poll_key} ，当前令牌数量：{$new_use_num}, 获取锁成功，当前时间: {$current_time}");
        return true;
    }



    /**
     * 设置单发限制
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/11/5 7:11 下午
     */
    public function setSingleLimit()
    {
        $this->single_limit_status = CommonConstant::COMMON_STATUS_YES;
        $this->single_limit_day = date('Ymd');
    }

    /**
     * 单个是否被限制
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/11/5 7:09 下午
     */
    public function overSingleLimit()
    {
        $is_limit = $this->single_limit_status === CommonConstant::COMMON_STATUS_YES && date('Ymd') === $this->single_limit_day;
        if(! $is_limit) {
            $this->single_limit_status = CommonConstant::COMMON_STATUS_NO;
            $this->single_limit_day = date('Ymd');
        }
        return $is_limit;
    }

    /**
     * 设置群发限制
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/11/5 7:11 下午
     */
    public function setBatchLimit()
    {
        $this->batch_limit_status = CommonConstant::COMMON_STATUS_YES;
        $this->batch_limit_day = date('Ymd');
    }


    /**
     * 群发是否被限制
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/11/5 7:09 下午
     */
    public function overBatchLimit()
    {
        $is_limit = $this->batch_limit_status === CommonConstant::COMMON_STATUS_YES && date('Ymd') === $this->batch_limit_day;
        if(! $is_limit) {
            $this->batch_limit_status = CommonConstant::COMMON_STATUS_NO;
            $this->batch_limit_day = date('Ymd');
        }
        return $is_limit;
    }

}