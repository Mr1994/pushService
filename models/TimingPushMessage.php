<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "timing_push_message".
 *
 * @property integer $id
 * @property string $request_id
 * @property string $message
 * @property integer $push_time
 * @property integer $push_status
 * @property integer $create_datetime
 * @property integer $update_datetime
 */
class TimingPushMessage extends ActiveRecord
{
    CONST PUSH_STATUS_WAITING  = 1;
    const PUSH_STATUS_IN_QUEUE = 2;
    const PUSH_STATUS_CANCEL   = 3;

    public static function getDb()
    {
        $db = parent::getDb();
        $db->charset = "utf8mb4";
        return $db;
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'timing_push_message';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'push_time', 'push_status', 'create_datetime', 'update_datetime'], 'integer'],
            [['request_id'], 'string', 'max' => 50],
            [['message'], 'string', 'max' => 1000]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'          => '自增ID',
            'request_id'  => '请求id',
            'message'     => '推送消息体，json',
            'push_time'   => '推送时间',
            'push_status' => '1:未进入kafka， 2已经入kafka',
            'create_datetime' => '创建时间',
            'update_datetime' => '更新时间',
        ];
    }
    
    public function behaviors(){
        return [
            [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['create_datetime', 'update_datetime'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => 'update_datetime',
                ],
            ],
        ];
    }

    /**
     * 转化为kafka消息
     * @return bool author: 卫振家
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/24 8:50 上午
     */
    public function toKafkaPushMessage()
    {
        $kafka_push_message = new KafkaPushMessage();
        $kafka_push_message->setAttributes(json_decode($this->message,true));
        return $kafka_push_message->enqueue(true);
    }
}
