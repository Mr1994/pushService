<?php


namespace app\models;

use app\constants\CommonConstant;
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
 * @property string $batch
 * @property int $priority
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
class PushHandlerMessage extends Model
{
    public $request_id;

    public $julive_app_id;
    public $unique_id_arr;
    public $reg_id_arr;
    public $send_type;

    public $title;
    public $notification;
    public $batch;
    public $priority;
    public $push_config;
    public $push_params;
    public $push_time;
    public $create_time;

    public $push_count;
    public $has_filter;

    public $kafka_topic;
    public $kafka_group_id;

    public function attributes()
    {
        return [
            'request_id', 'julive_app_id', 'unique_id_arr', 'reg_id_arr','send_type',
            'title', 'notification', 'batch', 'priority',
            'push_config', 'push_params',
            'push_time', 'push_count', 'has_filter','create_time',
            'kafka_topic','kafka_group_id',
        ];
    }

    public function rules()
    {
        return [
            [['request_id', 'julive_app_id', 'title', 'notification', 'push_time', 'priority', 'has_filter', 'create_time'], 'required'],
            [['julive_app_id', 'push_time', 'push_count', 'send_type', 'priority', 'has_filter'], 'integer'],
            [['request_id', 'title', 'notification','batch', 'kafka_topic', 'kafka_group_id'], 'string'],
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
     * @return bool
     */
    public function toKafkaPushMessage()
    {
        $kafka_push_message = new KafkaPushMessage();
        $kafka_push_message->setAttributes($this->toArray());
        return $kafka_push_message->enqueue(true);
    }
}

