<?php


namespace app\models;

use app\constants\AppConstant;
use app\constants\CommonConstant;
use app\constants\KafkaConstant;
use app\constants\PushConstant;
use app\constants\YwPushRegIdConstant;
use app\exception\PushApiException;
use app\exception\PushHandlerException;
use app\exception\PushMsgException;
use app\helpers\PrintHelper;
use app\helpers\push\ApplePushHelper;
use app\helpers\push\HuaweiPushHelper;
use app\helpers\push\JiguangPushHelper;
use app\helpers\push\OppoPushHelper;
use app\helpers\push\PushHelper;
use app\helpers\push\VivoPushHelper;
use app\helpers\push\XiaomiPushHelper;
use app\helpers\PusherHelper;
use app\helpers\QueueHelper;
use Exception;
use Yii;
use yii\base\Model;

/**
 * This is the model class for kafka push message.
 *
 * @property string $request_id
 *
 * @property int $julive_app_id
 * @property int $send_type
 * @property array $unique_id_arr
 * @property array $reg_id_arr
 * @property string $title
 * @property string $notification
 * @property int $priority
 * @property string $batch
 * @property array $push_config
 * @property array $push_params
 * @property int $push_time
 * @property int $create_time
 *
 * @property int $push_count
 * @property int $has_filter
 *
 * @property string $kafka_topic
 * @property string $kafka_group_id
 */
class KafkaPushMessage extends Model
{
    //请求id
    public $request_id;

    //传入的消息信息
    public $julive_app_id;
    public $unique_id_arr;
    public $reg_id_arr;
    public $title;
    public $notification;
    public $batch;
    public $priority;
    public $push_config;
    public $push_params;
    public $push_time;
    public $send_type;
    public $create_time;

    //初始化的消息信息
    public $push_count;
    public $has_filter;

    //kafka相关信息
    public $kafka_topic;
    public $kafka_group_id;

    //  现有的数据怎么处理 没有分表
    public function attributes()
    {
        return [
            'request_id', 'julive_app_id', 'unique_id_arr','reg_id_arr',
            'title', 'notification', 'batch',
            'push_config', 'push_params',
            'push_time', 'push_count', 'priority','has_filter','create_time',
            'kafka_topic','kafka_group_id','send_type'
        ];
    }

    public function rules()
    {
        return [
            [['request_id', 'julive_app_id', 'title', 'notification', 'push_time', 'priority', 'has_filter','create_time'], 'required'],
            [['julive_app_id', 'push_time', 'push_count', 'priority', 'has_filter','send_type'], 'integer'],
            [['request_id', 'title', 'notification', 'batch', 'kafka_topic', 'kafka_group_id'], 'string'],
            [['push_config', 'push_params', 'unique_id_arr', 'reg_id_arr'], 'default', 'value' => []],
            [['push_count'], 'default', 'value' => 1],
            [['has_filter'], 'default', 'value' => CommonConstant::COMMON_STATUS_NO],
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
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * 获取topic名
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/20 1:37 下午
     */
    public function setTopic()
    {
        if (isset(PushConstant::$KAFKA_SEND_TYPE_TOPIC[$this->send_type])) {
            $this->kafka_topic = PushConstant::$KAFKA_SEND_TYPE_TOPIC[$this->send_type];
        }else {
            $this->kafka_topic = KafkaConstant::KAFKA_TOPIC_PUSH_SERVICE_JIGUANG;
        }
    }

    /**
     * 设置默认的消费group
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/11/3 12:02 下午
     */
    public function setGroup()
    {
        $this->kafka_group_id = PushConstant::$PRIORITY_MAP_GROUP_ID[$this->priority];
    }



    /**
     * 优化Push服务
     * creater: 卫振家
     * create_time: 2020/7/6 下午3:33
     * @return KafkaPushMessage
     */
    public function setSchemeUrl()
    {
        if (empty(AppConstant::$app_h5_scheme_url[$this->julive_app_id])) {
            return $this;
        }

        if (empty($this->push_params['scheme_url'])) {
            return $this;
        }

        $scheme_url = $this->push_params['scheme_url'];
        if (strpos($scheme_url, 'http') !== 0) {
            return $this;
        }

        //修正跳转协议
        $scheme_params = urlencode(json_encode(['url' => $scheme_url]));
        $scheme_url    = sprintf(AppConstant::$app_h5_scheme_url[$this->julive_app_id], $scheme_params);
        $this->push_params['scheme_url'] = $scheme_url;
        return $this;
    }

    ################################ 作为一个实体拥有的功能 ############################
    /**
     * redis 取消发送消息
     */
    const KAFKA_PUSH_CANCEL_REQUEST_ID = 'kafka_push_message:cancel:%s';
    const KAFKA_PUSH_CHANGE_REQUEST_ID = 'kafka_push_message:change:%s';
    const KAFKA_PUSH_MODIFY_EXPIRE = 86400;

    //用户过滤redis
    const PUSH_USERS_BATCH_LIMIT = 'push_user_batch_limit:%s:%s:%s';
    const PUSH_USERS_NUM_LIMIT = 'push_user_num_limit:%s:%s';

    public static $handlers;


    ################################ API相关 ##########################

    /**
     * 将kafka消息加入队列
     * @return bool
     * @throws PushApiException
     */
    public function enqueueWithAlloc()
    {
        $reg_id_map_list = $this->getRegIdList();
        if(empty($reg_id_map_list)) {
            //throw new PushApiException($this->request_id, "未设置推送用户");

            PrintHelper::printError($this->request_id. "未找到待推送用户");
            return true;
        }
        //发送给目标用户
        foreach ($reg_id_map_list as $send_type => $reg_id_map) {
            //构造kafka发送的消息体
            $type_kafka_message = new KafkaPushMessage();
            $type_kafka_message->setAttributes($this->toArray());
            $type_kafka_message->send_type = $send_type;
            $type_kafka_message->reg_id_arr = $reg_id_map;
            $type_kafka_message->unique_id_arr = array_keys($reg_id_map);
            $type_kafka_message->setTopic();

            $type_kafka_message->enqueue();
        }
        return true;
    }


    /**
     * 按照send_type发送消息
     * @param bool $re_push
     * @return bool
     */
    public function enqueue($re_push = false)
    {
        //是否是重试
        $re_push && $this->push_count++;

        //是否发送校验
        if($this->isEnqueue() === false) {
            return false;
        }

        try {
            //将消息写入kafka
            $result = (new QueueHelper())->pushMessage($this->kafka_topic, $this->toArray(), QueueHelper::FORMAT_JSON);
        } catch (Exception $e) {
            PrintHelper::printError("加入对列{$this->kafka_topic},重试失败{$e->getMessage()} id: {$this->request_id}");
            return true;
        }

        if (empty($result)) {
            PrintHelper::printError("加入队列{$this->kafka_topic},失败 id: {$this->request_id}");
            return true;
        }

        PrintHelper::printInfo("入队{$this->kafka_topic}, 成功 id: {$this->request_id}");
        return $result;
    }


    /**
     * 获取定时对象
     * creater: 卫振家
     * create_time: 2020/5/11 下午5:54
     * @return bool
     */
    public function toTimingPushMessage()
    {
        $timing_push_message = new TimingPushMessage();
        $timing_push_message->request_id  = $this->request_id;
        $timing_push_message->push_time   = $this->push_time;

        $timing_push_message->message     = "{$this}";
        $timing_push_message->push_status = TimingPushMessage::PUSH_STATUS_WAITING;

        return $timing_push_message->save();
    }


    ################################# 发送相关 ########################
    /**
     * 发送消息
     * creater: 卫振家
     * create_time: 2020/5/7 下午3:30
     * @return bool
     * @throws PushMsgException
     */
    public function send()
    {
        //校验是否发送
        if($this->isPush() === false) {
            return true;
        }

        try {
            $this->getHandler()->send($this);
        } catch (PushHandlerException $e) {
            throw new PushMsgException($this, $e->getMessage());
        }

        PrintHelper::printInfo("推送完成 id:{$this->request_id}");
        return true;
    }

    /**
     * 是否插入队列校验
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/11/3 7:13 下午
     */
    public function isEnqueue()
    {
        return $this->validModel()
            && $this->validSendType()
            && $this->validPushCount();
    }

    /**
     * 是否要发送校验
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/27 9:55 上午
     * @return bool
     */
    public function isPush()
    {
        return $this->validModel()
            && $this->validSendType()
            && $this->validPushCount()
            && $this->validGroup()
            && $this->validCancel()
            && $this->validTime()
            && $this->validUser();
    }


    /**
     * 检查是否取消发送
     * creater: 卫振家
     * create_time: 2020/5/9 上午11:20
     * @param bool $del
     * @return bool
     */
    public function validCancel($del = true)
    {
        //获取push_message到messag_id；
        $request_id       = $this->request_id;
        $cancel_redis_key = sprintf(self::KAFKA_PUSH_CANCEL_REQUEST_ID, $request_id);
        $is_cancel        = Yii::$app->redis_business->get($cancel_redis_key);
        if ($is_cancel && $del) {
            //移除消息
            Yii::$app->redis_business->del($cancel_redis_key);
            PrintHelper::printInfo("消息已被取消：{$this->request_id}");
            return false;
        }else {
            PrintHelper::printDebug("消息未被取消：{$this->request_id}");
            return true;
        }
    }

    /**
     * 过滤用户
     * @return bool
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/11/3 11:28 上午
     */
    public function validUser()
    {
        //判断是否需要过滤用户，每条消息只过滤一次,过滤完成后将详细状态记录为已过滤
        if ($this->has_filter == CommonConstant::COMMON_STATUS_YES) {
            return ! empty($this->reg_id_arr);
        }
        //数据
        $filter_reg_id_arr = $this->reg_id_arr;
        $julive_app_id     = $this->julive_app_id;
        $batch_id          = $this->batch;
        //同批次过滤
        foreach ($filter_reg_id_arr as $unique_id => $reg_id) {
            //如果没有次数过滤，直接跳过
            if (! $this->hasAppPushCountLimit($unique_id)) {
                continue;
            }

            //批次过滤
            $batch_redis_key = sprintf(self::PUSH_USERS_BATCH_LIMIT, $julive_app_id, $batch_id, $unique_id);
            if (Yii::$app->redis_business->get($batch_redis_key)) {//改批次已经发送了push,不返回reg_id
                PrintHelper::printDebug("本批次消息已发送过 id:{$this->request_id},app_id:{$julive_app_id},批次:{$batch_id},unique_id:{$unique_id}已发送过");
                unset($filter_reg_id_arr[$unique_id]);
                continue;
            }

            //记录批次推送信息
            Yii::$app->redis_business->Setex($batch_redis_key, 3600 * 3, 1);

            $times_redis_key = sprintf(self::PUSH_USERS_NUM_LIMIT, $julive_app_id, $unique_id);
            $user_num        = Yii::$app->redis_business->get($times_redis_key);
            $push_time_limit = PushConstant::$APP_PUSH_NUM_PER_UNIQUE_ID_LIMIT[$julive_app_id];

            //如果已发送次数
            if ($user_num >= $push_time_limit) {
                PrintHelper::printDebug("该用户已到本日最大发送次数 id:{$this->request_id},app_id:{$julive_app_id},最大次数:{$push_time_limit},unique_id:{$unique_id}已达到当日最大次数");
                unset($filter_reg_id_arr[$unique_id]);
                continue;
            }

            //如果未超过，如何处理
            if ($user_num > 0 && $user_num < $push_time_limit) {
                Yii::$app->redis_business->incr($times_redis_key);
            } else {
                $expire = strtotime(date('Y-m-d 24:00:00', time())) - time();
                Yii::$app->redis_business->Setex($times_redis_key, $expire, 1);
            }
        }

        //更新用户和是否已经过滤过用户了
        $this->reg_id_arr    = $filter_reg_id_arr;
        $this->unique_id_arr = array_keys($filter_reg_id_arr);
        $this->has_filter    = CommonConstant::COMMON_STATUS_YES;

        $result = ! empty($this->reg_id_arr);
        if($result) {
            PrintHelper::printInfo("过滤批次和每日发送次数完成 id: {$this->request_id}");
        }else {
            PrintHelper::printInfo("所有用户都被过滤 id: {$this->request_id}");
        }
        return $result;
    }

    /**
     * 基础验证
     * @return bool
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/11/3 7:09 下午
     */
    public function validModel()
    {
        //校验
        $validate = $this->validate();
        if ($validate === false) {
            $error = $this->getErrors();
            $error_message = json_encode($error, JSON_UNESCAPED_UNICODE);
            PrintHelper::printError("数据格式错误 id:{{$this->request_id}:{$error_message}");
            return false;
        }
        return true;
    }

    /**
     * 校验group
     * @return bool
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/11/3 6:56 下午
     */
    public function validGroup()
    {
        if($this->kafka_group_id === QueueHelper::$current_group_id) {
            return true;
        }

        PrintHelper::printInfo("不处理 id: {$this->request_id}");
        return false;
    }

    /**
     * 发送次数校验
     * @return false
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/11/3 7:01 下午
     */
    public function validPushCount()
    {
        if($this->push_count <= PushConstant::PUSH_MAX_COUNT) {
            return true;
        }
        return false;
    }

    /**
     * 渠道类型校验
     * @return bool
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/11/3 7:02 下午
     */
    public function validSendType()
    {
        if ($this->send_type !== PushConstant::SEND_TYPE_AUTO) {
            return true;
        }
        return false;
    }

    /**
     * 时间状态合法性校验
     * @return bool
     */
    public function validTime()
    {
        //慢速报警
        PusherHelper::sendSlowPushWarning($this);

        $now = time();
        //如果写入数据库
        if($this->push_time > $now && $this->push_count == 1) {
            // 为定时消息设置高优先级
            $this->kafka_group_id = KafkaConstant::KAFKA_CUSTOMER_GROUP_HIGH_PRIORITY;
            PrintHelper::printInfo("消息加入定时任务 id: {$this->request_id}:发送时间{$this->push_time}");
            $this->toTimingPushMessage();
            return false;
        }

        //消息发送时间过期3小时以上 丢弃
        if($now - $this->push_time > 10800) {
            PrintHelper::printInfo("消息延迟超过3小时，丢弃 id: {$this->request_id}");
            return false;
        }
        return true;
    }


    /**
     * 获取用户是否有次数限制
     * @param $unique_id
     * @return bool
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/13 1:59 下午
     */
    public function hasAppPushCountLimit($unique_id)
    {
        $julive_app_id = $this->julive_app_id;
        $push_config = $this->push_config;

        if (isset($push_config['tmp_push'])) {
            return false;
        }

        //如果是业务推送那么不限制次数
        if (isset($push_config['business_push']) && $push_config['business_push'] == PushConstant::BUSINESS_PUSH) {
            return false;
        }
        //如果用户在白名单中不限制每天发送次数
        if (isset(PushConstant::$UN_LIMIT_PUSH_NUM_UNIQUE_ID_ARR[$julive_app_id]) && in_array($unique_id, PushConstant::$UN_LIMIT_PUSH_NUM_UNIQUE_ID_ARR[$julive_app_id])) {
            return false;
        }
        //如果未配置app push的次数限制，则不限制
        if (!isset(PushConstant::$APP_PUSH_NUM_PER_UNIQUE_ID_LIMIT[$julive_app_id])) {
            return false;
        }
        if (YII_ENV_DEV) {
            return false;
        }
        return true;
    }

    /**
     * 获取推送用户id
     * @return array
     * creater: 卫振家
     * create_time: 2020/5/11 上午9:54
     */
    public function getRegIdList()
    {
        $where = [
            'app_id'       => $this->julive_app_id,
            'unique_id'    => $this->unique_id_arr,
            'valid_status' => YwPushRegIdConstant::VALID_STATUS_YES,
        ];
        $andwhere = [];
        if (in_array($this->julive_app_id, [AppConstant::APP_COMJIA_ANDROID, AppConstant::APP_COMJIA_IOS])) {
            $andwhere = ["<>", "type", PushConstant::SEND_TYPE_JIGUANG];
        }
        if (!empty($this->send_type)) {
            $where['type'] = $this->send_type;
        }

        $field           = ['id', 'unique_id', 'type', 'reg_id'];
        $push_reg_list = YwPushRegId::find()
            ->select($field)
            ->where($where)
            ->andFilterWhere($andwhere)
            ->asArray()
            ->all();
        if (empty($push_reg_list)) {
            PrintHelper::printDebug("未找到用户对应的reg_id, id:{$this->request_id}, unique_id:" . json_encode($this->unique_id_arr));
            return [];
        }

        //数据排序
        $type_sort = PushConstant::$SEND_TYPE_SORT;
        usort($push_reg_list, function ($row1, $row2) use ($type_sort) {
            if (!isset($row1['type']) || !isset($type_sort[$row1['type']])) {
                return 0;
            }
            if(! isset($row2['type']) || ! isset($type_sort[$row2['type']])) {
                return 0;
            }
            if($type_sort[$row1['type']] < $type_sort[$row2['type']]) {
                return -1;
            }
            if($type_sort[$row1['type']] > $type_sort[$row2['type']]) {
                return 1;
            }
            return 0;
        });

        // group用户
        $unique_map     = [];
        $reg_id_map_list = [];
        foreach ($push_reg_list as $push_reg_row) {
            $unique_id = $push_reg_row['unique_id'];
            $type      = $push_reg_row['type'];
            $reg_id    = $push_reg_row['reg_id'];

            if(! isset($unique_map[$unique_id])) {
                $unique_map[$unique_id] = $type;
                $reg_id_map_list[$type][$unique_id] = $reg_id;
            }
        }
        return $reg_id_map_list;
    }

    /**
     * 获取对应的push handler
     * @return PushHelper
     * @throws PushHandlerException
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/11/2 2:16 下午
     */
    public function getHandler()
    {
        if (!isset(self::$handlers[$this->send_type][$this->julive_app_id])) {
            switch ($this->send_type) {
                case PushConstant::SEND_TYPE_XIAOMI:
                    $handler = XiaomiPushHelper::instance($this->julive_app_id);
                    break;
                case PushConstant::SEND_TYPE_HUAWEI:
                    $handler = HuaweiPushHelper::instance($this->julive_app_id);
                    break;
                case PushConstant::SEND_TYPE_JIGUANG:
                case PushConstant::SEND_TYPE_JIGUANG_VIP:
                    $handler = JiguangPushHelper::instance($this->julive_app_id);
                    break;
                case PushConstant::SEND_TYPE_VIVO:
                    $handler = VivoPushHelper::instance($this->julive_app_id);
                    break;
                case PushConstant::SEND_TYPE_OPPO:
                    $handler = OppoPushHelper::instance($this->julive_app_id);
                    break;

                case PushConstant::SEND_TYPE_APPLE:
                    $handler = ApplePushHelper::instance($this->julive_app_id);
                    break;
                default:
                    throw new PushHandlerException($this->julive_app_id, '未知的推送类型');
            }
            self::$handlers[$this->send_type][$this->julive_app_id] = $handler;
        }

        return self::$handlers[$this->send_type][$this->julive_app_id];
    }

    /**
     * 改变消息题
     * @param KafkaPushMessage
     * creater: 卫振家
     * create_time: 2020/5/9 上午11:20
     * @return KafkaPushMessage
     */
    public function changeMessage()
    {
        //获取push_message到messag_id；
        $request_id          = $this->request_id;
        $change_redis_key    = sprintf(self::KAFKA_PUSH_CHANGE_REQUEST_ID, $request_id);
        $target_message_json = Yii::$app->redis_business->get($change_redis_key);
        if (empty($target_message_json)) {
            return $this;
        }

        //目标信息
        $target_message = json_decode($target_message_json, true);
        unset($target_message['request_id']);
        unset($target_message['push_count']);

        //记录日志
        PrintHelper::printInfo("更改消息成功 id: {$this->request_id}");

        //替换消息内容
        $this->setAttributes($target_message);

        //删除hash
        Yii::$app->redis_business->del($change_redis_key);

        return $this;
    }

    ########################################## 收尾处理 #######################################
    /**
     * 收尾处理
     * @param PushHandlerResult $result
     * creater: 卫振家
     * create_time: 2020/8/21 上午9:37
     * @return bool
     */
    public function afterHandlerSend($result)
    {
        // 重试处理
        if ($this->push_count < PushConstant::PUSH_MAX_COUNT && $result->need_retry == PushHandlerResult::NEED_RETRY_YES) {
            $this->enqueue(true);
        }

        //非法的reg_id
        $reg_id_map         = $this->reg_id_arr;
        $invalid_reg_id_arr = empty($result->invalid_reg_id) ? [] : $result->invalid_reg_id;
        if (empty($invalid_reg_id_arr)) {
            return true;
        }
        foreach ($reg_id_map as $unique_id => $reg_id) {
            if (!in_array($reg_id, $invalid_reg_id_arr)) {
                continue;
            }
            $invalid_unique_id = [
                'app_id'     => $this->julive_app_id,
                'type'       => $this->send_type,
                'unique_id'  => $unique_id,
                'reg_id'     => $reg_id,
            ];
            $json_data = json_encode($invalid_unique_id);
            $result    = Yii::$app->redis_business->lpush(PushConstant::REDIS_KEY_INVALID_PUSH_REG_ID, $json_data);
            PrintHelper::printInfo("消息:{$this->request_id} 中的非法的reg_id:{$json_data} 入队成功，当前队列长度：{$result}");
        }


        return true;
    }

    ################################### 统计 #########################################

    /**
     * 修改kafka
     * creater: 卫振家
     * create_time: 2020/6/9 上午10:22
     */
    public function addTopicGroupDaySendNum()
    {
        //重试不记录次数
        if ($this->push_count > 1) {
            return;
        }

        //增加次数记录
        $day       = date('Ymd');
        $redis_key = sprintf(
            PushConstant::REDIS_KEY_TOPIC_GROUP_DAY_SEND_NUM,
            $this->kafka_topic,
            $this->kafka_group_id,
            $day
        );

        $num = Yii::$app->redis_business->incr($redis_key);
        if($num == 1) {
            Yii::$app->redis_business->expire($redis_key, 2 * 86400);
        }
    }

    /**
     * 修改kafka
     * creater: 卫振家
     * create_time: 2020/6/9 上午10:22
     */
    public function addTopicGroupTypeDaySendNum()
    {
        //重试不记录次数
        if ($this->push_count > 1) {
            return;
        }

        //增加次数记录
        $day       = date('Ymd');
        $redis_key = sprintf(
            PushConstant::REDIS_KEY_TOPIC_SEND_DAY_TYPE_SEND_NUM,
            $this->kafka_topic,
            $this->kafka_group_id,
            $this->send_type,
            $day
        );

        $num = Yii::$app->redis_business->incr($redis_key);
        if($num == 1) {
            Yii::$app->redis_business->expire($redis_key, 2 * 86400);
        }
    }

    /**
     * 增加topic记录的api调用次数
     * creater: 卫振家
     * create_time: 2020/6/9 上午10:28
     */
    public function addTopicGroupDayApiCallNum()
    {
        //增加次数记录
        $day       = date('Ymd');
        $redis_key = sprintf(
            PushConstant::REDIS_KEY_TOPIC_GROUP_DAY_API_CALL_NUM,
            $this->kafka_topic,
            $this->kafka_group_id,
            $day
        );

        $num = Yii::$app->redis_business->incr($redis_key);
        if($num == 1) {
            Yii::$app->redis_business->expire($redis_key, 2 * 86400);
        }
    }

    /**
     * @autor: julive sunwenke@julive.com
     * @create_time: 2020/11/17 10:52 上午
     * 获取渠道id
     */
    public function getChanleId()
    {

        if (isset($this->push_config['channle_id'])) {
            return $this->push_config['channle_id'];
        }
        if(isset(PushConstant::$CHANLE_ID_LIST[$this->send_type])){
            return PushConstant::$CHANLE_ID_LIST[$this->send_type];
        }
        return '';

    }

    /**
     * @autor: julive sunwenke@julive.com
     * @create_time: 2020/11/17 10:52 上午
     * 获取渠道铃声
     */
    public function getSound()
    {
        if (isset($this->push_config['sound'])) {
            return $this->push_config['sound'];
        }
        if(isset(PushConstant::$IOS_SOUND_LIST[$this->send_type])){
            return PushConstant::$IOS_SOUND_LIST[$this->send_type];
        }
        return 'default';
    }
    /**
     * @return bool
     * @autor: julive sunwenke@julive.com
     * @create_time: 2020/11/17 11:13 上午
     * 是否使用默认铃声
     */
    public function getDefaultSound(){
        return true;
    }
}

