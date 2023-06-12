<?php


namespace app\controllers;

use app\constants\AppConstant;
use app\constants\PushConstant;
use app\helpers\PrintHelper;
use app\models\YwPushRegId;
use yii;
use yii\web\Controller;

/**
 * push回调接口
 * Class PushServerController
 * @package app\controllers
 */
class PushNotifyController extends Controller
{
    // 该回调地址关闭csrf验证
    public $enableCsrfValidation = false;

    /**
     * 华为回调
     * @return bool
     * @throws yii\base\Exception
     * @autor: julive sunwenke@julive.com
     * @create_time: 2020/10/26 5:57 下午
     * 华为异常数据回调
     * 0 成功；2 应用卸载 ;5 token不存在 ；6 通知栏不显示;10 非活跃设备;15 消息覆盖;27 进程不存在  ;102消息丢失;201消息管控
     */


    public function actionHuaweiNotify()
    {
        $post = yii::$app->request->post();
        Yii::info($post, AppConstant::CALLBACK_LOG_HUAWEI);
        if (empty($post['statuses'])) {
            return true;
        }

        $reg_ids = [];
        foreach ($post['statuses'] as $k => $v) {
            if (in_array($v['status'], [2, 5, 6, 10])) {
                $reg_ids[] = $v['token'];
            }
        }

        if (empty($reg_ids)) {
            return true;
        }

        //处理
        $unique_ids = YwPushRegId::find()->select(['unique_id'])
            ->where(['reg_id' => $reg_ids, 'type' => PushConstant::SEND_TYPE_HUAWEI])
            ->column();
        if (empty($unique_ids)) {
            return true;
        }

        $notify = YwPushRegId::updateAll(['valid_status' => 2],
            ['type' => [PushConstant::SEND_TYPE_HUAWEI, PushConstant::SEND_TYPE_JIGUANG], 'unique_id' => $unique_ids]);
        if (!$notify) {
            PrintHelper::printError("无效数据更新异常:" . json_encode($unique_ids));
        }
        return true;
    }

    /**
     * 小米回调
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/28 2:49 下午
     */
    public function actionXiaomiNotify()
    {
        $post = yii::$app->request->post();
        Yii::info($post, AppConstant::CALLBACK_LOG_XIAOMI);

        $result_json = yii::$app->request->post('data', []);
        if (empty($result_json)) {
            return true;
        }

        $result_arr = json_decode($result_json, true);
        $invalid_reg_id_arr = [];
        foreach ($result_arr as $msg_id => $result) {
            if (empty($result['type'])) {
                continue;
            }

            $message_type = intval($result['type']);
            //送达
            if ($message_type === 1) {
                continue;
            }

            //点击
            if ($message_type === 2) {
                continue;
            }

            //失效用户
            if ($message_type === 16) {
                $invalid_reg_id = explode(',', $result['targets']);
                $invalid_reg_id_arr = array_merge($invalid_reg_id_arr, $invalid_reg_id);
                continue;
            }
        }

        Yii::info('调试完成');

        if (empty($invalid_reg_id_arr)) {
            return true;
        }

        //反插unique_id
        $unique_ids = YwPushRegId::find()->select(['unique_id'])
            ->where(['reg_id' => $invalid_reg_id_arr, 'type' => PushConstant::SEND_TYPE_XIAOMI])
            ->column();
        if (empty($unique_ids)) {
            return true;
        }

        $notify = YwPushRegId::updateAll(['valid_status' => 2],
            ['type' => [PushConstant::SEND_TYPE_XIAOMI, PushConstant::SEND_TYPE_JIGUANG], 'unique_id' => $unique_ids]);
        if (!$notify) {
            PrintHelper::printError("无效数据更新异常:" . json_encode($invalid_reg_id_arr));
        }

        return true;
    }

    /**
     * Oppo回调
     * @return bool
     * Author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 1/16/21 11:01 AM
     */
    public function actionOppoNotify()
    {
        $result_json = yii::$app->request->post();
        Yii::info($result_json, AppConstant::CALLBACK_LOG_OPPO);

        return true;
    }

    /**
     * vivo回调
     * @return bool
     * Author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 1/16/21 11:01 AM
     */
    public function actionVivoNotify()
    {
        $result_json = yii::$app->request->post();
        Yii::info($result_json, AppConstant::CALLBACK_LOG_VIVO);

        return true;
    }
}