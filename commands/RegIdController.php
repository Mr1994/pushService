<?php


namespace app\commands;


use app\constants\AppConstant;
use app\constants\PushConstant;
use app\constants\YwPushRegIdConstant;
use app\helpers\HttpHelper;
use app\helpers\PrintHelper;
use app\helpers\push\OppoPushHelper;
use app\models\YwPushRegId;
use Exception;
use Yii;

class RegIdController extends BaseConsoleController
{

    const REDIS_NO_DATA_SLEEP_TIME = 10;
    const REDIS_POP_SLEEP_TIME = 50;
    /**
     * 删除reg_id
     * creater: 卫振家
     * create_time: 2020/8/21 上午10:10
     */
    public function actionDeleteInvalidRegId()
    {
        while(true) {
            //数据存量检测
            $list_len = Yii::$app->redis_business->llen(PushConstant::REDIS_KEY_INVALID_PUSH_REG_ID);
            PrintHelper::printInfo('数据长度：'.$list_len);
            if($list_len < 1) {
                sleep(self::REDIS_NO_DATA_SLEEP_TIME);
                continue;
            }

            $app_unique_ids_arr = [];
            for($int = 0; $int < 100; $int ++) {
                //获取非法的返回值
                $invalid_unique_id  = Yii::$app->redis_business->rpop(PushConstant::REDIS_KEY_INVALID_PUSH_REG_ID);
                PrintHelper::printInfo('非法数据：'. $invalid_unique_id);
                $invalid_unique_arr = empty($invalid_unique_id) ? [] : json_decode($invalid_unique_id, true);
                if(isset($invalid_unique_arr['app_id']) && isset($invalid_unique_arr['unique_id'])) {
                    $app_unique_ids_arr[$invalid_unique_arr['app_id']][] = $invalid_unique_arr['unique_id'];
                    usleep(self::REDIS_POP_SLEEP_TIME);
                }else{
                    break;
                }
            }

            if(empty($app_unique_ids_arr)) {
                continue;
            }

            foreach ($app_unique_ids_arr as $app_id => $app_unique_ids) {
                $app_unique_ids = array_unique($app_unique_ids);
                YwPushRegId::updateAll(['valid_status' => YwPushRegIdConstant::VALID_STATUS_NO],[
                    'app_id'    => $app_id,
                    'unique_id' => $app_unique_ids,
                ]);
                PrintHelper::printInfo("删除非法uniqu_id; app_id:{$app_id} unique_id:" . implode(',', $app_unique_ids));
            }
        }
    }

    /**
     *
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/11/2 10:54 上午
     */
    public function actionDeleteOppoInvalidUser()
    {
        $oppo_pusher = new OppoPushHelper(AppConstant::APP_COMJIA_ANDROID);


        while(true) {
            $start_time = time();
            try{
                $token = $oppo_pusher->getToken();
                $header = [
                    'auth_token'    => $token
                ];
                $result = (new HttpHelper())
                    ->setConnectTimeOut(10)
                    ->setTimeOut(10)
                    ->setHeader($header)
                    ->get(OppoPushHelper::URL_PUSH_FEED_BACK_INVALID_REG_ID, true);
            }catch (Exception $e) {
                PrintHelper::printError("请求http异常，{$e->getMessage()}");
                self::try_sleep($start_time);
                continue;
            }

            PrintHelper::printInfo("非法用户信息" . json_encode($result));
            if(! isset($result['code'])) {
                self::try_sleep($start_time);
                continue;
            }

            //如果结果为11， 重置token；
            if(in_array($result['code'], [11])) {
                $oppo_pusher->is_refresh_token = true;
                PrintHelper::printError("token无效, 重置token");
                self::try_sleep($start_time);
                continue;
            }


            //非法用户处理
            if(in_array($result['code'], [0]) && isset($result['data']) && ! empty($result['data']['registration_ids'])) {
                $invalid_reg_ids = $result['data']['registration_ids'];

                //反插unique_id
                $unique_ids = YwPushRegId::find()->select(['unique_id'])
                    ->where(['reg_id' => $invalid_reg_ids, 'type' => PushConstant::SEND_TYPE_OPPO])
                    ->column();
                if(empty($unique_ids)) {
                    PrintHelper::printInfo("未找到对应的unique_id");
                    continue;
                }


                YwPushRegId::updateAll(['valid_status' => YwPushRegIdConstant::VALID_STATUS_NO],[
                    'app_id'    => AppConstant::APP_COMJIA_ANDROID,
                    'unique_id' => $unique_ids,
                    'type'      => [PushConstant::SEND_TYPE_OPPO, PushConstant::SEND_TYPE_JIGUANG],
                ]);
                PrintHelper::printInfo("删除OPPO非法用户成功");
            }

            PrintHelper::printInfo("处理完成");
            self::try_sleep($start_time);
        }
    }
}