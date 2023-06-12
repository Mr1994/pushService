<?php

namespace app\models;

use app\constants\CommonConstant;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "push_auth_config".
 *
 * @property integer $id
 * @property string $app_desc
 * @property string $app_key
 * @property string $app_secret
 * @property string $julive_app_id
 * @property integer $auth_status
 * @property integer $create_datetime
 * @property integer $update_datetime
 */
class PushAuthConfig extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'push_auth_config';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'auth_status', 'create_datetime', 'update_datetime'], 'integer'],
            [['app_desc'], 'string', 'max' => 256],
            [['app_key'], 'string', 'max' => 32],
            [['app_secret'], 'string', 'max' => 64],
            [['julive_app_id'], 'string', 'max' => 64],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'          => '自增ID',
            'app_desc'  => '应用描述',
            'app_key'     => 'app_key',
            'app_secret'   => 'app_secret',
            'julive_app_id' => '支持的app_id',
            'auth_status'   => '授权状态 1正常 2无权限',
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
     * @return PushAuthConfig
     */
    public static function getPushAuthByAppKey($app_key)
    {
        $app_key = trim($app_key);
        if(empty($app_key)) {
            return null;
        }
        return self::findOne(['app_key' => $app_key, 'auth_status' => CommonConstant::COMMON_STATUS_YES]);
    }
}
