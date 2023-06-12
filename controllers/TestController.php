<?php


namespace app\controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\RequestOptions;
use http\Client\Request;
use Psr\Http\Message\ResponseInterface;
use Yii;

/**
 * push服务提供
 * Class PushServerController
 * @package app\controllers
 */
class TestController extends PushBaseController
{


    /**
     * 消息发送状态
     * creater: 卫振家
     * create_time: 2020/5/6 上午11:53
     */
    public function actionDetail()
    {
        $post   = Yii::$app->request->post();
        $header = Yii::$app->request->getHeaders();
        yii::error(json_encode($post));
        //获取消息id
        $request_id = Yii::$app->request->post('request_id');


        //记录消息id和记录信息


        //查询消息消费日志
        return $this->output($request_id);
    }


    public function actionApple2()
    {


        $client   = new Client();
        $total    = 100;
        $requests = function ($total) {


            $uri = 'https://api.push.oppomobile.com/server/v1/message/notification/unicast';
            $uri = 'http://www.push.com/test/detail';
            for ($i = 0; $i < $total; $i++) {

                $token = '23f744ff-8fc0-432a-ab5b-54018b376629';

                $notify = [
                    'style'             => 1,
                    'title'             => 'ceshi',
                    'sub_title'         => '23112', //副标题
                    'content'           => "content",
                    'click_action_type' => '5',
                    'click_action_url'  => "push://app.comjia.com/oppopush?jump_url=www.baidu.com",
                    //"call_back_url"     => "http://test.pushservice.julive.com/index.php/push-notify/oppo-notify"

                ];


                $data      = [
                    'target_type'  => 2, // 使用registration_id推送 2,别名推送alias_name 3
                    'target_value' => 'CN_5ca8fc9ed7917c1484bf93cf696c21ff', // 推送目标用户
                    'notification' => $notify,
                ];
                $body      = [
                    'body'    => $data,
                    'headers' => 1,
                ];
                $urlParams = [
                    'message' => json_encode($data),
                ];

                yield new Request('post', $uri, ['auth_token' => $token], $urlParams);
            }
        };

        $pool = new Pool($client, $requests($total), [
            'concurrency' => 5,
            'fulfilled'   => function ($response, $index) {
                $body = $response->getBody();

                $remainingBytes = $body->getContents();
                var_dump($remainingBytes);
                // this is delivered each successful response
            },
            'rejected'    => function ($reason, $index) {
                var_dump($reason);

                // this is delivered each failed request
            },
        ]);

// Initiate the transfers and create a promise
        $promise = $pool->promise();

// Force the pool of requests to complete.
        $promise->wait();


    }


    public function actionTest1()
    {
        $a = time();
        if (!defined('CURL_HTTP_VERSION_2_0')) {
            define('CURL_HTTP_VERSION_2_0', 3);
        }
        $pem_file       = "/www/julive/push/config/test/cert/apns_product.pem";
        $client_parms =[
            'timeout' => 10,
            'cert' => $pem_file,
            'version'=>2.0,
        ];

        $client = new Client($client_parms);
        $device_token   = '927c3c1fde9859f8bb59c415f0c25e8d120434f48685b6962f9e8964201dd57d';
        $uri = "https://api.push.apple.com/3/device/$device_token";
        $apns_topic     = 'com.comjia.comjiasearch-DailyBuild';


        $options['headers'] = [
            'Content-Type' => 'application/json',
            'apns-topic'    => $apns_topic,
        ];
        $data = array(
            "aps"=>array(
                'alert'=>'这是推送标题',
                "sound"=>"dial.caf",
                "badge"=>0,
            ),
            'app'=>array(
                "title"=>"这是展示标题内容",
                "content"=>"这是自定义内容",
            ),
        );
        $options['body'] = json_encode($data);



        for ($i= 0; $i<1; $i++) {
            $promise = $client->requestAsync('POST', $uri, $options);
            $promise->then(
                function (ResponseInterface $res) {
//                    echo $res->getBody()->getContents();
                    echo $res->getStatusCode() . "\n";
//                    echo $res->getReasonPhrase() . "\n";
                },
                function (RequestException $e) {
                    echo $e->getMessage() . "\n";die;
//                    echo $e->getRequest()->getMethod();
//                    echo 2;
                }
            );
            $promise->wait();

        }

        echo $a . "\n";
        echo time() . "\n";


    }


    public function actionPt(){
        // FA2F4FF65196B8FE7B2A9796B696368A 华为
        // 14D661E52B6AD9EAB0690B90530C52D8 小米
        // 03722D8B08311C3B17E15059F58E5A00  oppo
        // BABF0149066F49679CF2B1358049CA61  vivo
        //      "5284C976-CB79-4D51-88DC-84B5EF52075A"
        //    "F5076E7F281D2FBB24287FA3C206AE33"

//        "F5076E7F281D2FBB24287FA3C206AE33",
//    "DF6DEF6F68F38C4F32606BEF5CFE1CA2",
//    "BABF0149066F49679CF2B1358049CA61"

//        "app_key": "GT0xyVBsHBCsn7C4E8cZ6xdcjmVRIk7d",
//    "signature": "b4306ae5fcf05227a7f2923689de9951",
        $a = '{
    "app_key": "GT0xyVBsHBCsn7C4E8cZ6xdcjmVRIk7d",
    "signature": "055583f059edee84cc6a83718ab255b0",
    "unique_id_arr": [
 
    "242D003A-7ED0-42F6-8700-652BA997B08E"
 
    ],
    "julive_app_id": 201,
    "title": "sunwenke",
    "notification": "hah",
    "priority": "1",
    "push_params": {
        "scheme_url": "comjia://app.comjia.com/spot_project?data={\"project_ids\":[\"3\",\"5\"],\"recall_id\":\"123\",\"recall_type\":\"2\",\"batch\":\"202008231400\",\"strategy\":\"999\"}",
        "image_url": ""
    },
    "send_type": 0,
     "push_config":{"push_now":"1"}

    }';

        var_dump($a);
        $client = new Client(['timeout' => 10]);
        $uri    = 'http://internal-pushservice.julive.com/push-server/add';
//        $uri    = 'http://www.push.com/push-server/add';


        $a = json_decode($a,true);


        for ($i= 0; $i<1; $i++) {
            $a['title'] = '';
            $a['title'] = '测试编号'.$i;
            $options['body'] = json_encode($a);

            $options['headers'] = [
                'Content-Type' => 'application/json',
                'julive-internal-api-type'    => 'json',
                'julive-internal-api-key'    => '6bedf5071694a84048cb4051134e3234',
            ];
            $promise = $client->requestAsync('POST', $uri, $options);
            $promise->then(
                function (ResponseInterface $res) {
                    echo $res->getBody()->getContents();
//                    echo $res->getStatusCode() . "\n";
//                    echo $res->getReasonPhrase() . "\n";
                },
                function (RequestException $e) {
                    echo $e->getMessage() . "\n";
//                    echo $e->getRequest()->getMethod();
//                    echo 2;
                }
            );
            $promise->wait();

        }

    }



}