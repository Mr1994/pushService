<?php


namespace app\controllers;

use app\constants\CodeConstant;
use app\constants\CommonConstant;
use app\constants\PushConstant;
use app\exception\PushApiException;
use app\helpers\QueueHelper;
use app\models\KafkaPushMessage;
use app\models\TimingPushMessage;
use Yii;
use yii\helpers\ArrayHelper;
/**
 * push服务提供
 * Class PushServerController
 * @package app\controllers
 */
class PushServerController extends PushBaseController
{
    /**
     * 发送push
     * creater: 卫振家
     * create_time: 2020/5/6 上午11:49
     * @throws PushApiException
     * @throws \app\exception\PushMsgException
     */
    public function actionAdd()
    {
        //获取消息 - request 按照json结果解析，仿照api_client
        $post          = Yii::$app->request->post();
        $julive_app_id = ArrayHelper::getValue($post, 'julive_app_id', '');
        $request_id    = (new QueueHelper())->getUUID();
        $push_count    = 1;

        //构造消息体
        $kafka_push_message                = new KafkaPushMessage();
        $kafka_push_message->request_id    = $request_id;
        $kafka_push_message->julive_app_id = $julive_app_id;
        $kafka_push_message->push_count    = $push_count;
        $kafka_push_message->has_filter    = CommonConstant::COMMON_STATUS_NO;
        $kafka_push_message->create_time   = ArrayHelper::getValue($post, 'timestamp', time());
        $kafka_push_message->create_time   = empty($kafka_push_message->create_time) ? $request_id : $kafka_push_message->create_time;

        //用户信息
        $kafka_push_message->unique_id_arr = ArrayHelper::getValue($post, 'unique_id_arr', []);
        $kafka_push_message->reg_id_arr    = ArrayHelper::getValue($post, 'reg_id_arr', []);

        //发送相关配置
        $kafka_push_message->title         = ArrayHelper::getValue($post, 'title', '');
        $kafka_push_message->notification  = ArrayHelper::getValue($post, 'notification', '');
        $kafka_push_message->batch         = ArrayHelper::getValue($post, 'batch', $request_id);
        $kafka_push_message->batch         = empty($kafka_push_message->batch) ? $request_id : $kafka_push_message->batch;
        $kafka_push_message->push_config   = ArrayHelper::getValue($post, 'push_config', []);
        $kafka_push_message->push_params   = ArrayHelper::getValue($post, 'push_params', []);

        //扩展信息
        $kafka_push_message->push_time = ArrayHelper::getValue($post, 'push_time', time());
        $kafka_push_message->push_time = empty($kafka_push_message->push_time) ? time() : $kafka_push_message->push_time;
        $kafka_push_message->priority  = ArrayHelper::getValue($post, 'priority', PushConstant::PRIORITY_LEVEL_1);
        $kafka_push_message->priority  = empty($kafka_push_message->priority) ? PushConstant::PRIORITY_LEVEL_1 : $kafka_push_message->priority;
        $kafka_push_message->send_type = intval(ArrayHelper::getValue($post, 'send_type', PushConstant::SEND_TYPE_AUTO));
        $kafka_push_message->send_type = empty($kafka_push_message->send_type) ? PushConstant::SEND_TYPE_AUTO : $kafka_push_message->send_type;

        //用户校验
        if (empty($kafka_push_message->unique_id_arr)) {
            throw new PushApiException($request_id, "未设置推送用户");
        }

        //kafka链接修正
        $kafka_push_message->setSchemeUrl();
        $kafka_push_message->setTopic();
        $kafka_push_message->setGroup();


        //验证
        $valid = $kafka_push_message->validate();
        if (empty($valid)) {
            $message = json_encode($kafka_push_message->getErrors(), JSON_UNESCAPED_UNICODE);
            throw new PushApiException($kafka_push_message->request_id, "数据异常：{$message}");
        }

        //将消息写入kafka
        $kafka_push_message->enqueueWithAlloc();

        //记录次数
        $kafka_push_message->addTopicGroupDayApiCallNum();
        return $this->output($request_id);
    }

    /**
     * 取消push
     * creater: 卫振家
     * create_time: 2020/5/6 上午11:50
     * @throws PushApiException
     */
    public function actionCancel()
    {
        //获取消息id
        $request_id = Yii::$app->request->post('request_id');
        $status     = Yii::$app->request->post('status', CommonConstant::COMMON_STATUS_YES);
        if (empty($request_id)) {
            throw new PushApiException($request_id, '更改目标或数据为空', CodeConstant::ERROR_CODE_PARAM);
        }

        //获取数据库是否存在发送时间
        $push_time   = TimingPushMessage::find()
            ->select(['push_time'])
            ->where(['request_id' => $request_id, 'push_status' => TimingPushMessage::PUSH_STATUS_WAITING])
            ->limit(1)
            ->scalar();
        $expire_time = ($push_time > time() ? $push_time - time() : 0) + KafkaPushMessage::KAFKA_PUSH_MODIFY_EXPIRE;

        //记录消息id
        $cancel_redis_key = sprintf(KafkaPushMessage::KAFKA_PUSH_CANCEL_REQUEST_ID, $request_id);
        if ($status == CommonConstant::COMMON_STATUS_YES) {
            Yii::$app->redis_business->setex($cancel_redis_key, $expire_time, CommonConstant::COMMON_STATUS_YES);
        } else {
            Yii::$app->redis_business->del($cancel_redis_key);
        }

        //返回结果
        return $this->output($request_id);
    }

    /**
     * 获取构建好的message
     * creater: 卫振家
     * create_time: 2020/6/9 上午11:14
     */
    public function actionBuild()
    {
        $request_id = (new QueueHelper())->getUUID();
        $post       = Yii::$app->request->post();
        $app_key    = 'AwWQjGCIyIC8xJJhrA7pKNXPqrsNsfYX';
        $app_secret = "xb0d@&3yk@4ufH#IKxuTvjvC%*2S%hYy2qTeAqU7D5WbZ^sY)XIZVF77VGs@j%V#";
        $timestamp  = time();
        $signature  = self::signature($app_key, $app_secret, $timestamp);

        $post['app_key']    = $app_key;
        $post['timestamp']  = $timestamp;
        $post['signature']  = $signature;
        $post['request_id'] = $request_id;

        //返回结果
        return $this->output($request_id, CodeConstant::SUCCESS_CODE, '成功', $post);
    }

    /**
     * 修改push
     * creater: 卫振家
     * create_time: 2020/5/6 上午11:51
     * @throws PushApiException
     */
    public function _actionChange()
    {
        //获取消息id
        $request_id    = Yii::$app->request->post('request_id');
        $kafka_message = Yii::$app->request->post('request_body', []);
        if (empty($request_id) || empty($kafka_message)) {
            throw new PushApiException($request_id, '更改目标或数据为空', CodeConstant::ERROR_CODE_PARAM);
        }

        //获取数据库
        //获取数据库是否存在发送时间
        $push_time   = TimingPushMessage::find()
            ->select(['push_time'])
            ->where(['request_id' => $request_id, 'push_status' => TimingPushMessage::PUSH_STATUS_WAITING])
            ->limit(1)
            ->scalar();
        $expire_time = ($push_time > time() ? $push_time - time() : 0) + 86400;

        //记录消息id和记录信息
        $change_redis_key = sprintf(KafkaPushMessage::KAFKA_PUSH_CHANGE_REQUEST_ID, $request_id);
        Yii::$app->redis_business->setex($change_redis_key, $expire_time, json_encode($kafka_message));

        //返回结果
        return $this->output($request_id);
    }

    /**
     * 消息发送状态
     * creater: 卫振家
     * create_time: 2020/5/6 上午11:53
     */
    public function actionDetail()
    {
        //获取消息id
        $request_id = Yii::$app->request->post('request_id');


        //记录消息id和记录信息


        //查询消息消费日志
        return $this->output($request_id);
    }




    public function actionApple2(){

    }

}