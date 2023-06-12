<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "cj_user_push_news".
 *
 * @property int $id
 * @property int $user_id
 * @property string $notification
 * @property string $push_params
 * @property string $unique_id
 * @property integer $create_datetime
 * @property integer $is_read
 * @property integer $push_type
 * @property string $title
 */
class CjUserPushNews extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'cj_user_push_news';
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
            [['id', 'user_id', 'create_datetime', 'is_read'], 'integer'],
            [['notification', 'push_params', 'unique_id'], 'string', 'max' => 255],
        ];
    }


    public function behaviors()
    {
        return [
            [
                'class'      => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['create_datetime'],
                ],
            ],
        ];
    }
}
