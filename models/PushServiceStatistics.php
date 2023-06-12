<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "push_service_statistics".
 *
 * @property integer $id
 * @property integer $statistics_date_type
 * @property string  statistics_date
 * @property integer $statistics_source_type
 * @property string  $statistics_source
 * @property integer $group_by_type
 * @property integer $group_by
 * @property string  $statistics_desc
 * @property integer $statistics_type
 * @property string  $statistics_value
 * @property integer $create_datetime
 * @property integer $update_datetime
 */
class PushServiceStatistics extends ActiveRecord
{
    //统计时间类型
    const STATISTICS_DATE_TYPE_DAY   = 1;
    const STATISTICS_DATE_TYPE_WEEK  = 2;
    const STATISTICS_DATE_TYPE_MONTH = 3;
    const STATISTICS_DATE_TYPE_HOUR  = 4;
    const STATISTICS_DATE_TYPE = [
        self::STATISTICS_DATE_TYPE_DAY   => '日',
        self::STATISTICS_DATE_TYPE_WEEK  => '周',
        self::STATISTICS_DATE_TYPE_MONTH => '月',
        self::STATISTICS_DATE_TYPE_HOUR  => '小时',
    ];

    //数据源
    const STATISTICS_SOURCE_TYPE_DB    = 1;
    const STATISTICS_SOURCE_TYPE_REDIS = 2;
    const STATISTICS_SOURCE = [
        self::STATISTICS_SOURCE_TYPE_DB    => '数据表',
        self::STATISTICS_SOURCE_TYPE_REDIS => 'REDIS',
    ];

    //数据分组依据
    const GROUP_BY_TYPE_NO                    = 1;
    const GROUP_BY_TYPE_APP_ID                = 2;
    const GROUP_BY_TYPE_KAFKA_GROUP           = 3;
    const GROUP_BY_TYPE_KAFKA_GROUP_SEND_TYPE = 4;
    const GROUP_BY_TYPE = [
        self::GROUP_BY_TYPE_NO                    => '无分组依据',
        self::GROUP_BY_TYPE_APP_ID                => 'appid',
        self::GROUP_BY_TYPE_KAFKA_GROUP           => 'topic:group',
        self::GROUP_BY_TYPE_KAFKA_GROUP_SEND_TYPE => 'topic:group:sendtype',
    ];

    //统计数据类型
    const STATISTICS_TYPE_APP_ID_USER_SEND_NUM       = 1;
    const STATISTICS_TYPE_API_CALL_NUM               = 2;
    const STATISTICS_TYPE_KAFKA_TOPIC_GROUP_SEND_NUM = 3;
    const STATISTICS_TYPE_KAFKA_SEND_TYPE_SEND_NUM   = 4;
    const STATISTICS_TYPE = [
        self::STATISTICS_TYPE_APP_ID_USER_SEND_NUM       => 'appid下的用户接受消息数量',
        self::STATISTICS_TYPE_API_CALL_NUM               => 'push服务的调用次数',
        self::STATISTICS_TYPE_KAFKA_TOPIC_GROUP_SEND_NUM => 'kafka消息的发送次数',
        self::STATISTICS_TYPE_KAFKA_SEND_TYPE_SEND_NUM   => 'kafka消息的不同渠道的发送次数',
    ];

    public static function getDb() {
        return Yii::$app->db_yingyan;
    }
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'push_service_statistics';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'statistics_date_type', 'statistics_source_type', 'group_by_type', 'statistics_type', 'create_datetime', 'update_datetime'], 'integer'],
            [['statistics_value', 'statistics_date'], 'string', 'max' => 64],
            [['statistics_desc', 'statistics_source', 'group_by'], 'string', 'max' => 256],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'statistics_date_type'   => '统计日期类型 1日 2周 3月 4小时',
            'statistics_date'        => '统计日期 数字 或者 汉字',
            'statistics_source_type' => '数据源类型1 数据表 2redis',
            'statistics_source'      => '数据源名',
            'group_by_type'          => '数据拆分依据类型 1所有数据 2appid 3topic-group 4topic-group-sendtype',
            'group_by'               => '数据拆分依据拼接',
            'statistics_desc'        => '统计类型描述',
            'statistics_type'        => '统计类型 1appid下的用户接受消息数量 2push服务的调用次数 3kafka消息的发送次数 4kafka消息的不同渠道的发送次数',
            'statistics_value'       => '统计数值',
            'create_datetime'        => '创建时间',
            'update_datetime'        => '更新时间',
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
