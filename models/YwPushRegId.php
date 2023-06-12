<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "yw_push_reg_id".
 *
 * @property string $id
 * @property string $unique_id
 * @property integer $app_id
 * @property string $brand
 * @property integer $type
 * @property string $reg_id
 * @property integer $create_datetime
 * @property integer $update_datetime
 * @property integer $valid_status
 */
class YwPushRegId extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'yw_push_reg_id';
    }

    public static function getDb()
    {
        return Yii::$app->get('db_pc_comjia');
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['app_id', 'type', 'create_datetime', 'update_datetime', 'unique_id_type', 'valid_status'], 'integer'],
            [['unique_id', 'brand'], 'string', 'max' => 50],
            [['reg_id'], 'string', 'max' => 255]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'              => '自增ID',
            'unique_id'       => 'cj_device_info.unique_id,设备的唯一标识',
            'app_id'          => '居理对app的编号',
            'brand'           => '手机品牌',
            'type'            => '1:小米,2:华为,3:极光',
            'reg_id'          => '各厂商生成的regId',
            'create_datetime' => '创建时间',
            'update_datetime' => '更新时间',
            'unique_id_type'  => '1:服务端生成,2:客端生成的',
            'valid_status'    => 'reg_id合法性 1:合法,2:不合法',
        ];
    }

    public function behaviors()
    {
        return [
            [
                'class'      => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['create_datetime', 'update_datetime'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => 'update_datetime',
                ],
            ],
        ];
    }
}
