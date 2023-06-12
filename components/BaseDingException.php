<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\components;

use app\helpers\CommonHelper;
use app\helpers\PrintHelper;
use Yii;
use Exception;
use yii\base\Component;

/**
 * FileTarget records log messages in a file.
 *
 * The log file is specified via [[logFile]]. If the size of the log file exceeds
 * [[maxFileSize]] (in kilo-bytes), a rotation will be performed, which renames
 * the current log file by suffixing the file name with '.1'. All existing log
 * files are moved backwards by one place, i.e., '.2' to '.3', '.1' to '.2', and so on.
 * The property [[maxLogFiles]] specifies how many history files to keep.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class BaseDingException extends Component
{
    const JULIVE_EXCEPTION_QUEUE = 'exception:julive_exception_queue'; // 异常信息队列

    const JULIVE_EXCEPTION_QUEUE_NUM = 'exception:queue_num_'; // 异常加入队列次数

    // 钉钉需要艾特的手机号码
    public $at_mbile;

    // 钉钉机械人地址
    public $ding_url;

    //需排除的报警
    public $except;

    //是否@所有人
    public $is_at_all;

    // 加入队列总数限制
    const EXCEPTION_QUEUE_TOTAL = 1000;

    /**
     * 钉钉Exception
     */
    public function dingMsg()
    {
        try {
            // 排除掉不必要的警告
            $except = $this->_except(Yii::$app->errorHandler->exception, $this->except);
            if ($except) {
                return;
            }

            if (isset(Yii::$app->errorHandler->exception)) {
                // 异常数据组装
                $exception_data = $this->getExceptionData();
                // 将数据放入队列中
                $this->pushExceptionQueue($exception_data);
            }
        } catch (Exception $e) {
            PrintHelper::printError('钉钉请求异常,异常信息 - ' . json_encode($e) . '系统信息' . $e->getMessage());
        }
    }

    /**
     * 异常信息入队
     * @author      赵薇
     * @date        2020-01-03
     * ---------------------------
     * @param   array   $exception_data  异常信息
     * @return array
     */
    private function pushExceptionQueue( array $exception_data ) {
        $exception_cache_key = self::JULIVE_EXCEPTION_QUEUE;

        $exception_total_num = Yii::$app->redis_business->llen($exception_cache_key);

        // 加入队列次数限制， 队列中超过 1000个异常则抛弃掉
        if ( $exception_total_num > self::EXCEPTION_QUEUE_TOTAL ) {
            return [];
        }

        // 当前异常次数 cache key
        $exception_num_cache_key = self::JULIVE_EXCEPTION_QUEUE_NUM . $exception_data['md5_str'];
        // 异常次数 +1
        Yii::$app->redis_business->incr($exception_num_cache_key);

        $exception_data['ding_url'] = $this->ding_url;

//        var_dump(Yii::$app->redis_business->set($exception_num_cache_key, 0));
        if ( Yii::$app->redis_business->get($exception_num_cache_key) <= 1 ) {
            Yii::$app->redis_business->rpush($exception_cache_key, json_encode($exception_data));
        }
    }

    /**
     * 获取报警数据
     * @author      赵薇
     * @date        2020-01-03
     * ---------------------------
     * @var         exception
     * @return      array
     */
    public function getExceptionData() {
        $data = [];

        // 获取异常报警信息
        $exception = Yii::$app->errorHandler->exception;
        
        // 异常信息
        $data['exception']['message'] = $exception->getMessage();
        $data['exception']['line'] = $exception->getLine();
        $data['exception']['file'] = $exception->getFile();
        $data['exception']['code'] = $exception->getCode();
        $data['exception']['text'] = $exception->getTraceAsString();
        $data['exception']['class'] = get_class(Yii::$app->errorHandler->exception);
        $data['exception']['route'] = Yii::$app->request->getPathInfo();     

        // 获取请求参数
        if (Yii::$app->request->isGet) {
            $args = Yii::$app->request->get();
        } else {
            $args = Yii::$app->request->post();
        }   

        // 中文不转为unicode ，对应的数字 256
        $args = json_encode($args, JSON_UNESCAPED_UNICODE);
        // 获取可用的请求头信息
        $headers = $this->getHeader();       

        //是否@all
        if (isset($this->is_at_all)) {
            $isAtAll = true;
        }else{
            $isAtAll = false;
        }

        // 其他信息
        $data['project_id'] = YII::$app->id; // 项目id
        $data['args'] = $args;
        $data['ding_mobile'] = array_unique($this->at_mbile); // 获取@用户
        $data['str_mobile'] = $this->_atMobile($data['ding_mobile']);
        $data['headers_str'] = $this->getFormatHeaderData($headers); //格式化header数据
        $data['is_at_all'] = $isAtAll;
        // 第一行敏感异常数据加粗
        $data['file'] = str_replace(WWW_PATH, '', $exception->getFile());
        $data['env'] = YII_ENV;
        $data['ip']  = Yii::$app->request->userIP;
        $data['server_ip'] = CommonHelper::get_server_ip();
        /**
         * 异常信息md5值
         * 项目ID + 错误描述 + 行号
         */
        $data['md5_str'] = md5($data['project_id'] . $exception->getMessage() . $exception->getLine());

        return $data;
    }

    /**
     * 排除报警信息
     * @param $exception
     * @param $except
     * @return bool
     */
    protected function _except($exception, $except)
    {
        foreach ($except as $k => $v) {
            if ($exception instanceof $v) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取header头信息
     * @return string
     */
    protected function getHeader()
    {

    }

    /**
     * 格式化header信息
     * @param array $headers header头信息
     * @return string
     */
    protected function getFormatHeaderData($headers = [])
    {
        $headers_str = '';

        if (!empty($headers)) {
            foreach ($headers as $k => $v) {
                $headers_str .= "> **{$k}**: {$v} \n\n";
            }
        }

        return $headers_str;
    }

    /**
     * 获取需要@的同事
     * @param $at_mobile 电话号码数组
     * @return string
     */
    protected function _atMobile($at_mobile)
    {
        // @所有数组里面电话
        $str_mobile = '';
        if (!empty($at_mobile)) {
            foreach ($at_mobile as $k => $v) {
                $str_mobile .= '@' . $v;
            }
        }

        return $str_mobile;
    }
}