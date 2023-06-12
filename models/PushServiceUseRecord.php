<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "push_service_user_record".
 *
 * @property integer $id
 * @property string $request_id
 * @property integer $app_id
 * @property string $unique_id
 * @property string $batch
 * @property string $title
 * @property string $notification
 * @property integer $send_time
 * @property string $push_params
 * @property integer $create_datetime
 * @property integer $update_datetime
 */
class PushServiceUseRecord extends ActiveRecord
{
    private static $_suffix;
    /**
     * 设置表的后缀
     * @param int $time 时间戳
     */
    public static function setSuffix($time) {
        self::$_suffix = date('Ymd', $time);
    }
    
    public static function getDb() {
        $db = Yii::$app->db_yingyan;
        $db->charset = "utf8mb4";
        return $db;
    }
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        if(empty(self::$_suffix)) {
            self::setSuffix(time());
        }
        return empty(self::$_suffix) ? 'push_service_user_record' : 'push_service_user_record_' . self::$_suffix;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['app_id', 'send_time', 'create_datetime', 'update_datetime'], 'integer'],
            [['unique_id', 'batch', 'request_id'], 'string', 'max' => 64],
            [['title', 'notification'], 'string', 'max' => 255],
            [['push_params'], 'string', 'max' => 2000]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'request_id' => 'push请求唯一id',
            'app_id' => '居理生成的app_id',
            'unique_id' => '设备ID',
            'batch' => '批次',
            'title' => '标题',
            'notification' => '推送内容',
            'send_time' => '发送时间',
            'push_params' => '推送参数',
            'create_datetime' => '创建时间',
            'update_datetime' => '更新时间',
        ];
    }

    public function behaviors()
    {
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
}
