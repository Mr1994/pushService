<?php
namespace app\helpers\push;

use app\helpers\PrintHelper;
use app\models\KafkaPushMessage;
use Exception;
use GuzzleHttp\Psr7\Request;
use yii\helpers\ArrayHelper;

class MeizuPushHelper extends PushHelper
{

    const URL_PUSH_MESSAGE_PUSH_ID   = 'https://server-api-push.meizu.com/garcia/api/server/push/varnished/pushByPushId';

    /**
     * 居理app_id
     * @var
     */
    const SEND_CONFIG_KEY = 'meizu_push';

    /**
     * 返回请求闭包
     * @return \Closure
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/22 10:15 上午
     */
    public function getRequestsClosure()
    {
        return  function () {
            $pool_index = 0;
            foreach ($this->batch_message as $push_index => $push_message) {
                try {
                    $data = $this->formatData($push_message);
                }catch (Exception $e) {
                    PrintHelper::printError("消息处理异常 {$push_message}, {$e->getMessage()}");
                    continue;
                }
                $uri  = self::URL_PUSH_MESSAGE_PUSH_ID;
                $header = [
                    'Content-Type' => 'application/json',
                ];

                // 记录请求index和push_index的对应关系
                $this->pool_index_map[$pool_index++] = $push_index;

                //请求
                yield new Request('post', $uri, $header, json_encode($data));
            }
        };
    }

    /**
     * 格式化数据
     * @param $push_message
     * @return array
     * author: 卫振家
     * Email: weizhenjia@julive.com
     * Date: 2020/10/22 11:29 上午
     */
    public function formatData($push_message)
    {
        $app_id = ArrayHelper::getValue($this->config, 'app_id', '');
        $post = [
            'appId'       => $app_id,
            'pushIds'     => implode(',', array_values($push_message->reg_id_arr)),
            'messageJson' => self::getMessage($push_message),
        ];
        $post['sign'] = $this->signPush($post);
        return $post;
    }

    /**
     * 魅族验证签名生成
     * @param array
     * @return
     */
    private function signPush($arr = [])
    {
        ksort($arr);
        $data = '';
        foreach ($arr as $key => $value) {
            $data .= $key . "=" . $value;
        }

        $master_secret = ArrayHelper::getValue($this->config, 'master_secret', '');
        return  md5($data . $master_secret, false);
    }

    /**
     * 公用的推送消息
     * @param KafkaPushMessage $push_message
     * @return false|string
     */
    private static function getMessage($push_message)
    {
        // 请求参数
        $msg = [
            'noticeBarInfo'    => [
                'noticeBarType' => 0, //通知栏样式(0, 标准) int 非必填，值为0
                'title'         => $push_message->title,        //推送标题,  string 必填，字数限制1~32
                'content'       => $push_message->notification, //推送内容,  string 必填，字数限制1~100
            ],
            'noticeExpandInfo' => [
                'noticeExpandType' => 0, //展开方式 (0, 标准),(1, 文本) int 非必填，值为0、1
                // 'noticeExpandContent' => '', //展开内容,  string noticeExpandType为文本时，必填
            ],
            'clickTypeInfo'    => self::getClickActionArr($push_message),
            'pushTimeInfo'     => [
                'offLine' => 1, //是否进离线消息(0 否 1 是[validTime])  int 非必填，默认值为1
                // 'validTime' => 12, //有效时长 (1 72 小时内的正整数)  int offLine值为1时，必填，默认24
            ],
            'advanceInfo'      => [
                // 'suspend' => 1,    //是否通知栏悬浮窗显示 (1 显示  0 不显示)  int 非必填，默认1
                // 'clearNoticeBar' => '', //是否可清除通知栏 (1 可以  0 不可以)  int 非必填，默认1
                // 'isFixDisplay' => '', //是否定时展示 (1 是  0 否)  int 非必填，默认0
                // 'fixStartDisplayTime' => '', //定时展示开始时间(yyyy-MM-dd HH:mm:ss)  str 非必填
                // 'fixEndDisplayTime' => '', //定时展示结束时间(yyyy-MM-dd HH:mm:ss)  str 非必填
                'notificationType' => [
                    'vibrate' => 1, //震动 (0关闭  1 开启) ,   int 非必填，默认1
                    // 'lights' => '', //闪光 (0关闭  1 开启),  int 非必填，默认1
                    // 'sound' => '', //声音 (0关闭  1 开启),  int 非必填，默认1
                ],
            ],
            "extra" => [
                //"callback"       => "http://flyme.callback",//String(必填字段), 第三方接收回执的Http接口, 最大长度128字节
                //"callback.param" => "param",//String(可选字段), 第三方自定义回执参数, 最大长度64字节
                //"callback.type"  => 3 //int(可选字段), 回执类型(1-送达回执, 2-点击回执, 3-送达与点击回执), 默认3
            ]
        ];
        return json_encode($msg);
    }

    /**
     *
     * @param KafkaPushMessage $push_message
     * @return array
     * creater: 卫振家
     * create_time: 2020/5/14 下午2:12
     */
    private static function getClickActionArr($push_message)
    {

        /**
        // 打开自定义APP内页
        if (array_keys($push_params)[0] == 'intent') {
            // 打开自定义APP页面
            $actionType = 1;
            $actionVal  = $push_params['intent'];
        } elseif (array_keys($push_params)[0] == 'url') {
            // 打开指定url
            $actionType = 2;
            $actionVal  = $push_params['url'];
        } else {
            // 打开APP首页
            $actionType = 0;
            $actionVal  = '';
        }
         * **/

        $scheme_url = self::getSchemeUrl($push_message);
        if(empty($scheme_url)) {
            return [
                'clickType' => 0,
            ];
        }

        if(strpos($scheme_url, 'http') === 0) {
            return [
                'clickType' => 2,
                'url'  => "launcher://app?data=" . json_encode(['url' => $scheme_url], JSON_UNESCAPED_UNICODE),
            ];
        }
        return [
            'clickType' => 1,
            'url'  => "launcher://app?data=" . json_encode(['intent' => $scheme_url], JSON_UNESCAPED_UNICODE),
        ];
    }

}