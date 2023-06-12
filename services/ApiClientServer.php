<?php
namespace app\services;

use app\constants\PushConstant;
use app\helpers\HttpHelper;
use app\helpers\PrintHelper;
use Yii;

class ApiClientServer
{
    const API_CLIENT_SERVER_GET_REG_LIST = '/v1/push/get-reg-list';

    /**
     * 获取推送用户服务
     * @param $julive_app_id
     * @param $unique_id
     * @param int $send_type
     * @return array|void
     * creater: 卫振家
     * create_time: 2020/5/11 上午9:54
     */
    public static function getRegIdList($julive_app_id, $unique_id, $send_type = PushConstant::SEND_TYPE_AUTO)
    {
        $post = [
            'params' => [
                'julive_app_id' => $julive_app_id,
                'unique_id'     => $unique_id,
                'send_type'     => $send_type,
            ]
        ];

        $base_url = Yii::$app->params['server']['api_client_server'];
//        $base_url = 'http://api_client.api_client.jl.com/server';
        $url      = $base_url . self::API_CLIENT_SERVER_GET_REG_LIST;
        $http_helper = new HttpHelper();
        $result      = $http_helper->setTimeOut(2)->setConnectTimeOut(2)->postJson($url, $post, true);
        //记录请求信息
        $param_json  = json_encode($post, JSON_UNESCAPED_UNICODE);
        $result_json = json_encode($result, JSON_UNESCAPED_UNICODE);
        $result_status = var_export($http_helper->getStatusCode(), true);
//        $result_exception = var_export($http_helper->getException(), true);

        PrintHelper::printInfo("获取reg_id, url:{$url}, param:{$param_json}, result:{$result_json}, status:{$result_status}");
        return json_decode($result_json, true);
    }
}