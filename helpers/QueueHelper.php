<?php

namespace app\helpers;

use app\constants\KafkaConstant;
use app\constants\PushConstant;
use app\helpers\time\Timer;
use app\models\KafkaPushMessage;
use RdKafka\TopicPartition;
use Yii;
use DateTime;
use DateTimeZone;
use Exception;
use RdKafka\Conf;
use RdKafka\TopicConf;
use RdKafka\Producer;
use RdKafka\Consumer;
use RdKafka\KafkaConsumer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

/**
 * 队列处理请求统一类
 */
class QueueHelper
{

    /**消费偏移量
     * earliest 当各分区下有已提交的offset时，从提交的offset开始消费；无提交的offset时，从头开始消费
     * latest 当各分区下有已提交的offset时，从提交的offset开始消费；无提交的offset时，消费新产生的该分区下的数据
     */
    const OFFSET_EARLIEST = 'earliest';

    const OFFSET_LATEST = 'latest';

    //纯文本格式
    const FORMAT_PAW = 'paw';

    //json格式
    const FORMAT_JSON = 'json';

    //PHP序列化格式
    const FORMAT_SERIALIZE = 'serialize';

    // 消费group名前缀
    const GROUP_PREFIX = 'group_';

    //访问日志
    private $produce_logger;

    //访问日志
    private $consume_logger;

    //异常日志
    private $error_logger;

    //默认的消费者的开始偏移量
    //private $offset = 'smallest';
    private $offset = self::OFFSET_EARLIEST;

    //默认的消费者group_id
    private $group_id = "";

    //消费者偏移量提交（写入）到存储的频率,默认2秒
    private $auto_commit_interval = 2000;

    //消息超时时间
    private $message_time_out = 5000;

    //调试开关
    private $debug = false;

    //服务器地址 
    private $brokers;

    public static $consumer;

    public static $current_group_id ;

    const   CUSTOMER_LOW_COUNT_MAX = 1000;
    private $customer_low_count    = 0;

    /**
     * 获取当前时间(精确到毫秒数)
     *
     * @return void
     */
    private function getTime()
    {
        $timezone = new DateTimeZone('PRC');
        $time     = new DateTime(null, $timezone);
        return $time->format('Y-m-d H:i:s.u');
    }

    /**
     * 唯一ID生成函数
     */
    public function getUUID()
    {
        mt_srand((float)microtime() * 10000);
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45); // "-"
        $uuid   = substr($charid, 0, 8) . $hyphen
            . substr($charid, 8, 4) . $hyphen
            . substr($charid, 12, 4) . $hyphen
            . substr($charid, 16, 4) . $hyphen
            . substr($charid, 20, 12);
        return $uuid;
    }

    /**
     * 检查配置文件函数
     *
     * @return void
     */
    private function checkConfig()
    {
        if (empty(Yii::$app->params['kafka'])) {
            throw new Exception("common_queue params is empty!");
        }
        if (empty(Yii::$app->params['kafka']['common_queue'])) {
            throw new Exception("common_queue params is empty!");
        }
        if (empty(Yii::$app->params['kafka']['common_queue']['produce_log_path'])) {
            throw new Exception("common_queue produce_log_path params is empty!");
        }
        if (empty(Yii::$app->params['kafka']['common_queue']['consume_log_path'])) {
            throw new Exception("common_queue consume_log_path params is empty!");
        }
        if (empty(Yii::$app->params['kafka']['common_queue']['brokers'])) {
            throw new Exception("common_queue brokers params is empty!");
        } else {
            $this->brokers = Yii::$app->params['kafka']['common_queue']['brokers'];
        }
    }

    /**
     * 检查主题是否合法
     *
     * @param string 主题名称
     * @return bool
     */
    private function checkTopic($topic)
    {
        $topic = is_array($topic) ? $topic : [$topic];
        $queue_topic = array_intersect(KafkaConstant::QueueTopicList, $topic);
        $async_topic = array_intersect(KafkaConstant::AsyncTopicList, $topic);
        if(empty($queue_topic) && empty($async_topic)) {
            return false;
        }
        return true;

        /**
        if (in_array($topic, KafkaConstant::QueueTopicList)) {
            return true;
        } else if (in_array($topic, KafkaConstant::AsyncTopicList)) {
            return true;
        }
        return false;
         **/
    }

    /**
     * 打包数据
     *
     * @param string $data 数据
     * @param string $format 格式
     * @return mixed
     */
    private function packMessage($data, $format)
    {
        if ($format == self::FORMAT_JSON) {
            return json_encode($data);
        } else if ($format == self::FORMAT_SERIALIZE) {
            return serialize($data);
        } else {
            return $data;
        }
    }

    /**
     * 解包数据
     *
     * @param string $data 数据
     * @param string $format 格式
     * @return mixed
     */
    private function unPackMessage($data, $format)
    {
        if ($format == self::FORMAT_JSON) {
            return json_decode($data, true);
        } else if ($format == self::FORMAT_SERIALIZE) {
            return unserialize($data);
        } else {
            return $data;
        }
    }

    /**
     * 异常日志
     *
     * @param string $type 错误类别
     * @param string $error 错误信息
     * @param array $extend 拓展信息
     * @return void
     */
    private function error($type, $error, $extend = null)
    {
        $body = [
            'time'    => $this->getTime(),
            'brokers' => $this->brokers,
            'type'    => $type,
            'error'   => $error
        ];
        if (!empty($extend)) {
            $body['extend'] = $extend;
        }
        $this->error_logger->info('', $body);
    }

    /**
     * 初始化生产者相关配置
     *
     * @return Producer
     */
    private function getProducer()
    {
        $conf = new Conf();
        $conf->set('metadata.broker.list', $this->brokers);
        $conf->setDrMsgCb(function ($kafka, $message) {
            if (!empty($message->err)) {
                $this->error('delivery_message_err', rd_kafka_err2str($message->err), [
                    'topic' => $message->topic_name,
                    'data'  => $message->payload
                ]);
                throw new Exception(rd_kafka_err2str($message->err));
            } else {
                //记录生产者日志
                $this->debug && $this->produce_logger->info('', [
                    'time'      => $this->getTime(),
                    'brokers'   => $this->brokers,
                    'topic'     => $message->topic_name,
                    'partition' => $message->partition,
                    'offset'    => $message->offset,
                    'key'       => $message->key,
                    'data'      => $message->payload
                ]);
            }
        });
        $conf->setErrorCb(function ($kafka, $err, $reason) {
            $this->error('librdkafka_err', $reason);
            throw new Exception($reason);
        });
        return new Producer($conf);
    }

    /**
     * 初始化消费者相关配置
     *
     * @param bool $auto_commit 是否自动提交偏移量，默认为true
     * @return KafkaConsumer
     */
    private function getConsumer()
    {
        $conf = new Conf();
        $conf->set('group.id', $this->group_id);
        $conf->set('metadata.broker.list', $this->brokers);
        $conf->set('socket.keepalive.enable', 1);
        $conf->set('auto.commit.interval.ms', $this->auto_commit_interval);
        $conf->set('auto.offset.reset', $this->offset);
        $conf->setRebalanceCb(function ($kafka, $err, $partitions) {

            switch ($err) {
                case RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                    $kafka->assign($partitions);
                    break;
                case RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                    $kafka->assign(NULL);
                    break;
                default:
                    $this->error('rebalance_err', rd_kafka_err2str($err));
                    throw new Exception(rd_kafka_err2str($err));
            }
        });
        $conf->setOffsetCommitCb(function ($kafka, $err, $partitions) {
            if ($err != 0) {
                $this->error('offset_commit_err', rd_kafka_err2str($err), [
                    'partitions' => $partitions
                ]);
                throw new Exception(rd_kafka_err2str($err));
            }
        });
        $conf->setErrorCb(function ($kafka, $err, $reason) {
            $this->error('librdkafka_err', $reason);
            throw new Exception($reason);
        });
        return new KafkaConsumer($conf);
    }

    public function __construct()
    {
        //检查kafka拓展是否正常
        if (!extension_loaded('rdkafka')) {
            throw new Exception("rdkafka is need!");
        }
        //设置是否debug
        $this->debug = Yii::$app->params['is_debug'];

        //检查配置是否正确
        $this->checkConfig();
        //定义日志输出格式
        $format = new LineFormatter("%context%\n");
        //初始化produce_logger
        $this->produce_logger = new Logger('');
        $stream_handler       = new StreamHandler(Yii::$app->params['kafka']['common_queue']['produce_log_path'], Logger::INFO);
        $stream_handler->setFormatter($format);
        $this->produce_logger->pushHandler($stream_handler);
        //初始化consume_logger
        $this->consume_logger = new Logger('');
        $stream_handler       = new StreamHandler(Yii::$app->params['kafka']['common_queue']['consume_log_path'], Logger::INFO);
        $stream_handler->setFormatter($format);
        $this->consume_logger->pushHandler($stream_handler);
        //初始化error_logger
        $this->error_logger = new Logger('');
        $stream_handler     = new StreamHandler(Yii::$app->params['kafka']['common_queue']['error_log_path'], Logger::INFO);
        $stream_handler->setFormatter($format);
        $this->error_logger->pushHandler($stream_handler);
    }

    /**
     * 设置GroupID,订阅模式下才需要使用此方法
     *
     * @param string $val 需要设置的名称
     * @param string $default_prefix 添加默认前缀
     * @return void
     */
    protected function setGroupID($val, $default_prefix = true)
    {
        if ($default_prefix) {
            $this->group_id = static::GROUP_PREFIX . $val;
        } else {
            $this->group_id = $val;
        }
        return $this;
    }

    /**
     * 设置brokers
     */
    protected function setBroker($broker)
    {
        $this->brokers = $broker;
        return $this;
    }

    /**
     * 更改auto.offset.reset模式
     */
    public function changeOffset()
    {
        $this->offset = self::OFFSET_EARLIEST;
        return $this;
    }

    /**
     * 获取Metadata
     *
     * @param 队列主题 $topic
     * @return void
     */
    public function getMetadata($topic)
    {
        $producer = $this->getProducer();
        $topic    = $producer->newTopic($topic);
        $metadata = $producer->getMetadata(false, $topic, 60e3);
        return $metadata;
    }

    /**
     * 获取主题水位长度（并非是未消费的概念）
     *
     * @param 队列主题 $topic
     * @return void
     */
    public function getMessageCount($topic)
    {
        $this->setGroupID($topic);
        $count = 0;
        $conf  = new Conf();
        $conf->set('group.id', $this->group_id);
        $conf->set('metadata.broker.list', $this->brokers);
        $consumer = new Consumer($conf);
        $metadata = $consumer->metadata(false, $consumer->newTopic($topic), 60e3);
        $topics   = $metadata->getTopics();
        if (count($topics) > 0) {
            foreach ($topics as $_topic) {
                $partitions = $_topic->getPartitions();
                foreach ($partitions as $partition) {
                    $id   = $partition->getId();
                    $low  = 0;
                    $high = 0;
                    $consumer->queryWatermarkOffsets($topic, $id, $low, $high, -1);
                    $count += $high - $low;
                }
            }
        }
        return $count;
    }

    /**
     * 生产消息
     *
     * @param string 队列主题 $topic_name
     * @param mixed 消息内容 $data
     * @param string 消息体格式 $format
     * @return bool
     */
    public function pushMessage($topic_name, $data, $format = self::FORMAT_PAW)
    {
        try {
            if (!$this->checkTopic($topic_name)) {
                throw new Exception("topic name illegal");
            }
            $topic_conf = new TopicConf();
            $topic_conf->set('message.timeout.ms', $this->message_time_out);
            $topic_conf->setPartitioner(RD_KAFKA_MSG_PARTITIONER_RANDOM);
            $producer  = $this->getProducer();
            $topic     = $producer->newTopic($topic_name, $topic_conf);
            $partition = RD_KAFKA_PARTITION_UA;
//            $partition = $this->setPartition($data);
//            var_dump($partition);
            $topic->produce($partition, 0, $this->packMessage($data, $format), $this->getUUID());
            $producer->poll(0);
            $producer->flush(-1);
            return true;
        } catch (Exception $e) {
            $this->error('push_message_err', $e->getMessage(), [
                'topic' => $topic_name,
                'data'  => $data
            ]);
            return false;
        }
    }

    /**
     * 消费消息
     *
     * @param $topic_name
     * @param $group_id
     * @param $callback
     * @param string $format
     * @return bool
     */
    public function reciveMessage($topic_name, $group_id, $callback, $format = self::FORMAT_PAW)
    {
        if (!$this->checkTopic($topic_name)) {
            throw new Exception("topic name illegal");
        }

        //定时操作
        $timer = Timer::getIntervalTimer(60, function(){
            PrintHelper::printDebug('定时消息开始');
            if(empty(KafkaPushMessage::$handlers)) {
                return;
            }
            foreach (KafkaPushMessage::$handlers as $send_type => $app_handlers) {
                foreach ($app_handlers as $app_id => $app_handler) {
                    if (! method_exists($app_handler, 'batchSend')) {
                        continue;
                    }
                    $app_handler->batchSend();
                }
            }
            PrintHelper::printDebug('定时消息结束');
        });

        while (true) {
            //运行定时器
            $timer->run();

            // 获取消息
            try {
                $message = $this->changeGroup($topic_name, $group_id);
            } catch (Exception $e) {
                $this->error('recive_message_err', $e->getMessage(), [
                    'topic' => $topic_name
                ]);

                // 记录kafka消费异常
                PrintHelper::printError('kafka消费消息异常：' . $e->getMessage());

                // 如果连接超时，则直接重新生成customer
                if(strpos($e->getMessage(), 'Disconnected') !== false) {
                    unset(self::$consumer[self::$current_group_id]);
                }

                //再次循环
                continue;
            }

            // 消息处理
            if ($message->err == RD_KAFKA_RESP_ERR_NO_ERROR) {
                try { //回调
                    $callback($this->unPackMessage($message->payload, $format), $message);
                    //记录消费日志
                    $this->debug && $this->consume_logger->info('', [
                        'time'      => $this->getTime(),
                        'brokers'   => $this->brokers,
                        'topic'     => $message->topic_name,
                        'group_id'  => $this->group_id,
                        'partition' => $message->partition,
                        'offset'    => $message->offset,
                        'key'       => $message->key,
                        'data'      => $message->payload
                    ]);
                } catch (Exception $e) {
                    $this->error('callback_err', $e->getMessage(), [
                        'topic' => $topic_name,
                        'data'  => $message->payload
                    ]);
                }
            } else if ($message->err != RD_KAFKA_RESP_ERR__TIMED_OUT && $message->err != RD_KAFKA_RESP_ERR__PARTITION_EOF) {
                // 异常消息处理
                $this->error('consume_message_err', rd_kafka_err2str($message->err));
            }
        }
    }

    /**
     * 订阅消息
     *
     * @param string $topic 队列主题
     * @param string $group_id 消费组ID
     * @param function $callback 消费函数
     * @param string $format 消息体格式
     * @return void
     */
    public function subscribeMessage($topic, $group_id, $callback, $format = self::FORMAT_PAW)
    {
        return $this->setGroupID($group_id, false)->reciveMessage($topic, $callback, $format);
    }


    /**
     * 设置分区策略
     * 请求的数据，根据请求的数据进行分区
     */
    public function setPartition($data)
    {
        if (isset($data['priority']) && ($data['priority'] <= 2 && $data['priority'] >= 0)) {
            return intval($data['priority']);
        } else {
            // 默认写到1分区
            return PushConstant::PRIORITY_LEVEL_1;
        }

    }


    /**
     *
     */
    public function getPartition($group_id)
    {
        if (PushConstant::$PARTITION_GROUP_ID[$group_id]) {
            return PushConstant::$PARTITION_GROUP_ID[$group_id];
        } else {
            return PushConstant::PRIORITY_LEVEL_1;
        }
    }

    /**
     * @autor: julive sunwenke@julive.com
     * @create_time: 2020/10/19 4:15 下午
     * 根据请求参数修改发送优先级,存过存在高优先级数据，优先发送高优先级数据
     */
    public function changeGroup($topic_name,$group_id)
    {
        // 非优先级发送队列
        if(!in_array($group_id,[KafkaConstant::KAFKA_CUSTOMER_GROUP_LOW_PRIORITY,KafkaConstant::KAFKA_CUSTOMER_GROUP_HIGH_PRIORITY])){
            $consumer = $this->setConsumer($topic_name, $group_id);
            return $consumer->consume($this->message_time_out);
        }

        // 优先级处理队列
        if($this->customer_low_count > 0) {
            $consumer = $this->setConsumer($topic_name, KafkaConstant::KAFKA_CUSTOMER_GROUP_LOW_PRIORITY);
            $message  = $consumer->consume($this->message_time_out);
            PrintHelper::printInfo("从低优先级消费数据，还可以消费{$this->customer_low_count}次");
            $this->customer_low_count--;
            if($message->len == null){
                PrintHelper::printInfo("从低优先级未消费到数据，清空低优先级次数， 消费高优先级");
                $this->customer_low_count = 0;
            }
            return $message;
        }

        //如果还有没有低优先级消费数量就消费高优先级
        $consumer  = $this->setConsumer($topic_name, KafkaConstant::KAFKA_CUSTOMER_GROUP_HIGH_PRIORITY);
        $message  = $consumer->consume($this->message_time_out);
        if($message->len == null) {
            $this->customer_low_count = self::CUSTOMER_LOW_COUNT_MAX;
            PrintHelper::printInfo("从高优先级消费数据未消费到消息， 开始消费低优先级，消费{$this->customer_low_count}次");
        }else {
            PrintHelper::printInfo("从高优先级消费到消费数据");
        }

        return $message;
    }

    /**
     * 设置使用的custumer
     * @param $topic_name
     * @param $group_id
     * @return Consumer
     * @throws Exception
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/20 3:28 下午
     */
    public function setConsumer($topic_name, $group_id)
    {
        //设置使用的group_id
        $this->group_id         = $group_id;
        self::$current_group_id = $group_id;
        //获取consumer
        if (!empty(self::$consumer[$group_id])) {
            PrintHelper::printInfo('未重建consumer');
            $consumerClient = self::$consumer[$group_id];
        } else {
            PrintHelper::printInfo('重新创建consumer');

            $subscribe_topic = is_array($topic_name) ? $topic_name : [$topic_name];
            $consumer = $this->getConsumer();
            $consumer->subscribe($subscribe_topic);

            $topic_name_arr = implode(',', $subscribe_topic);
            PrintHelper::printInfo("重新建立kafka连接，topic:{$topic_name_arr}, group:{$group_id}");
//                $partition = $this->getPartition($group_id);
//                $consumer->assign([
//                    new TopicPartition($topic_name, $partition),
//                ]);
            $consumerClient = self::$consumer[$group_id] = $consumer;
        }


        return $consumerClient;
    }
}
