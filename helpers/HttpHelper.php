<?php

namespace app\helpers;

use Yii;
use DateTime;
use DateTimeZone;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\TransferStats;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\ResponseInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Http请求统一处理类
 * 
 * $option自定义可参考：https://guzzle-cn.readthedocs.io/zh_CN/latest/request-options.html
 *
 */
class HttpHelper
{
    //访问日志
    private $access_logger;

    //异常日志
    private $error_logger;

    //调试开关
    private $debug = false;

    //是否重试
    private $retry = false;

    //最大重试次数
    private $max_retry = 5;

    //服务器响应超时最大秒数,默认5秒
    private $connect_timeout = 5;

    //请求超时的秒数,默认30秒
    private $timeout = 30;

    //受保护的选项,这几个属于不允许做自定义设置，设置了也不处理
    private $protected_option = [
        RequestOptions::DEBUG,
        RequestOptions::PROXY,
        RequestOptions::VERIFY,
        RequestOptions::CONNECT_TIMEOUT,
        RequestOptions::TIMEOUT,
        RequestOptions::COOKIES,
        RequestOptions::ON_STATS,
        RequestOptions::HTTP_ERRORS,
        RequestOptions::QUERY,
        RequestOptions::BODY,
        RequestOptions::FORM_PARAMS,
        RequestOptions::MULTIPART,
        RequestOptions::JSON,
        'save_to'
    ];

    //自定义请求参数
    private $option = [];

    //请求头参数
    private $header = [];

    //代理
    private $proxy;

    //cookie值
    private $cookie;

    //返回状态码
    private $status_code;

    //返回头信息
    private $respose_headers;

    //捕获到的异常
    private $exception;

    public function __construct()
    {
        //设置是否debug
//        $this->debug = Yii::$app->params['is_debug'];

        $this->cookie = new CookieJar();

        //定义日志输出格式
        $format = new LineFormatter("%context%\n");

        //初始化access_logger
        $this->access_logger = new Logger('');
        $stream_handler = new StreamHandler(Yii::$app->params['server']['log']['access_log_path'], Logger::INFO);
        $stream_handler->setFormatter($format);
        $this->access_logger->pushHandler($stream_handler);

        //初始化error_logger
        $this->error_logger = new Logger('');
        $stream_handler = new StreamHandler(Yii::$app->params['server']['log']['error_log_path'], Logger::INFO);
        $stream_handler->setFormatter($format);
        $this->error_logger->pushHandler($stream_handler);
    }

    /**
     * 获取当前时间(精确到毫秒数)
     *
     * @return void
     */
    private function getTime()
    {
        $timezone = new DateTimeZone('PRC');
        $time = new DateTime(null, $timezone);
        return $time->format('Y-m-d H:i:s.u');
    }

    /**
     * 构造请求客户端
     * @param string $option 附加选项
     * @return Client
     */
    private function buildClient($option)
    {
        $_option = [
            RequestOptions::DEBUG => $this->debug, //调试开关
            RequestOptions::VERIFY => false,
            RequestOptions::CONNECT_TIMEOUT => $this->connect_timeout,
            RequestOptions::TIMEOUT => $this->timeout,
            RequestOptions::COOKIES => $this->cookie,
            RequestOptions::ON_STATS => function (TransferStats $stats) use ($option) {
                $response = $stats->getResponse();
                $request = $stats->getRequest();
                //获取不到response的时候什么都不用做
                if (empty($response)) {
                    return;
                }
                //记录状态码
                $this->status_code = $response->getStatusCode();
                //记录header
                $headers = $response->getHeaders();
                $respose_headers = [];
                foreach ($headers as $name => $values) {
                    if (count($values) == 1) {
                        $respose_headers[$name] = $values[0];
                    } else {
                        $respose_headers[$name] = $values;
                    }
                }
                $this->respose_headers = $respose_headers;


                //记录请求日志
                $this->debug && $this->access_logger->info('', [
                    'time' => $this->getTime(),
                    'method' => $request->getMethod(),
                    'host' => $request->getUri()->getHost(),
                    'url' => $request->getUri()->__toString(),
                    'pramas' => $this->requestDataFormat($option),
                    'status_code' => $response->getStatusCode(),
                    'response_time' => $stats->getTransferTime()
                ]);
            }
        ];
        //处理拓展选项
        if (!empty($this->option)) {
            foreach ($this->option as $key => $val) {
                if (in_array($key, $this->protected_option)) {
                    continue;
                }
                $_option[$key] = $val;
            }
        }
        //处理请求头
        if (!empty($this->header)) {
            $_option[RequestOptions::HEADERS] = $this->header;
        }
        //处理代理
        if (!empty($this->proxy)) {
            $_option[RequestOptions::PROXY] = $this->proxy;
        }
        return new Client($_option);
    }

    /**
     * 请求统一方法
     *
     * @param string $method 请求方法
     * @param string $url 请求地址
     * @param array $option 附加选项
     * @return void
     */
    private function do($method, $url, $option = [])
    {
        try {
            $promise = $this->buildClient($option)->requestAsync($method, $url, $option)->then(
                function (ResponseInterface $response) {
                    $code = $response->getStatusCode();
                    if ($code == 200) {
                        return $response->getBody()->getContents();
                    } else {
                        return false;
                    }
                },
                function ($e) {
                    throw $e;
                }
            );
            return $promise->wait();
        } catch (\Exception $e) {
            //记录捕获到的异常
            $this->exception = $e;
            //记录异常日志
            $this->error_logger->info('', [
                'time' => $this->getTime(),
                'method' => $method,
                'url' => $url,
                'pramas' => $this->requestDataFormat($option),
                'type' => get_class($e),
                'error' => $e->getMessage()
            ]);
            //记录yii日志
            PrintHelper::printError("Http::Error接口调用异常：". $url ."参数：" . json_encode($this->requestDataFormat($option)) . " 错误信息：".$e->getMessage());
            return false;
        }
    }

    /**
     * 发送请求方法(带重试机制)
     *
     * @param string $method 请求方法
     * @param string $url 请求地址
     * @param array $option 附加选项
     * @return void
     */
    private function request($method, $url, $option = [])
    {
        if ($this->retry) {
            $try_count = 0;
            $result = $this->do($method, $url, $option);
            while (!$result && $try_count < $this->max_retry) {
                $try_count += 1;
                $result = $this->do($method, $url, $option);
            }
            return $result;
        } else {
            return $this->do($method, $url, $option);
        }
    }

    /**
     * 批量发送请求方法(无重试机制)
     *
     * @param string $method 请求方法
     * @param string $url_array 请求地址
     * @return void
     */
    private function batch_request($method, $url_array)
    {
        try {
            $promises = [];
            foreach ($url_array as $key => $url) {
                $promises[$key] = $this->buildClient()->requestAsync($method, $url);
            }
            $data =  Promise\unwrap($promises);
            $results = [];
            foreach ($data as $key => $info) {
                $results[$key] = $info->getBody()->getContents();
            }
            return $results;
        } catch (\Exception $e) {
            $this->error_logger->info('', [
                'time' => $this->getTime(),
                'method' => $method,
                'urls' => $url_array,
                'type' => get_class($e),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 统一处理json解析
     *
     * @param string $data
     * @return void
     */
    private function jsonFormat($data)
    {
        if (!empty($data)) {
            return json_decode($data, true);
        } else {
            return [];
        }
    }

    /**
     * 请求参数格式化，为了方便日志输出
     * 
     * @param [type] $option
     * @return void
     */
    private function requestDataFormat($option)
    {
        if (!empty($option[RequestOptions::BODY])) {
            return $option[RequestOptions::BODY];
        } else if (!empty($option[RequestOptions::JSON])) {
            return $option[RequestOptions::JSON];
        } else if (!empty($option[RequestOptions::FORM_PARAMS])) {
            return $option[RequestOptions::FORM_PARAMS];
        } else if (!empty($option[RequestOptions::MULTIPART])) {
            $data = [];
            foreach ($option[RequestOptions::MULTIPART] as $val) {
                if (gettype($val['contents']) == "resource") {
                    $data[$val['name']] = '文件';
                } else {
                    $data[$val['name']] = $val['contents'];
                }
            }
            return $data;
        } else {
            return [];
        }
    }

    /**
     * 设置响应超时最大秒数
     *
     * @param int $val
     */
    public function setConnectTimeOut($val)
    {
        $this->connect_timeout = $val;
        return $this;
    }

    /**
     * 设置请求超时最大秒数
     *
     * @param int $val
     */
    public function setTimeOut($val)
    {
        $this->timeout = $val;
        return $this;
    }

    /**
     * 设置调试
     *
     * @param int $val
     */
    public function setDebug($val)
    {
        $this->debug = $val;
        return $this;
    }

    /**
     * 设置重试
     *
     * @param int $val
     */
    public function setRetry($val)
    {
        $this->retry = $val;
        return $this;
    }

    /**
     * 设置最大重试次数
     *
     * @param int $val
     */
    public function setMaxRetry($val)
    {
        $this->max_retry = $val;
        return $this;
    }

    /**
     * 设置请求参数
     * @param array $option 请求选项
     * @return void
     */
    public function setOption($option)
    {
        $this->option = $option;
        return $this;
    }

    /**
     * 设置请求头
     * @param array $option 头选项
     */
    public function setHeader($header)
    {
        $this->header = $header;
        return $this;
    }

    /**
     * 设置代理
     * @param array $proxy 代理选项
     */
    public function setProxy($proxy)
    {
        $this->proxy = $proxy;
        return $this;
    }

    /**
     * 设置cookie
     * @param array $cookies cookie值
     * @param string $domain 域名
     * @return void
     */
    public function setCookie(array $cookies, $domain)
    {
        $this->cookie = CookieJar::fromArray($cookies, $domain);
        return $this;
    }

    /**
     * 获取响应状态码
     */
    public function getStatusCode()
    {
        return $this->status_code;
    }

    /**
     * 获取响应头信息
     *
     */
    public function getResponseHeader()
    {
        return $this->respose_headers;
    }

    /**
     * 获取cookie信息
     *
     */
    public function getCookie()
    {
        return $this->cookie;
    }

    /**
     * 获取proxy信息
     */
    public function getProxy()
    {
        return $this->proxy;
    }

    /**
     * 获取捕获到的异常
     *
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * other请求,HEAD,DELETE,OPTIONS,PATCH,PUT等特殊请求使用此方法
     * 
     * @param string $method 请求方法
     * @param string $url 请求地址
     * @param array $option 附加选项
     */
    public function other($method, $url, $option = [])
    {
        return $this->request($method, $url, $option);
    }

    /**
     * get请求
     * 
     * 注：在 query 选项查询字符串指定，将会覆盖在请求时提供的查询字符串值
     *
     * @param string $url 请求地址
     * @param bool $json_format 是否需要自动进行json解析
     * @param array $query 请求参数
     */
    public function get($url, $json_format = false, $query = [])
    {
        if (!empty($query)) {
            $data = $this->request('GET', $url, [RequestOptions::QUERY => $query]);
        } else {
            $data = $this->request('GET', $url);
        }
        if ($json_format) {
            return $this->jsonFormat($data);
        } else {
            return $data;
        }
    }

    /**
     * get请求文件
     *
     * @param string $url 请求地址
     * @param string $path 保存文件路径
     */
    public function getFile($url, $path)
    {
        $this->request('GET', $url, ['save_to' => $path]);
    }

    /**
     * post元数据
     *
     * @param string $url 请求地址
     * @param string $data 发送数据
     * @param bool $json_format 是否需要自动进行json解析
     */
    public function postRaw($url, $data, $json_format = false)
    {
        $data = $this->request('POST', $url, [RequestOptions::BODY => $data]);
        if ($json_format) {
            return $this->jsonFormat($data);
        } else {
            return $data;
        }
    }

    /**
     * post普通表单
     *
     * @param string $url 请求地址
     * @param string $data 发送数据
     * @param bool $json_format 是否需要自动进行json解析
     *  [
     *      'foo' => 'bar',
     *      'baz' => ['hi', 'there!']
     *  ]
     */
    public function postForm($url, $data, $json_format = false)
    {
        $data = $this->request('POST', $url, [RequestOptions::FORM_PARAMS => $data]);
        if ($json_format) {
            return $this->jsonFormat($data);
        } else {
            return $data;
        }
    }

    /**
     * post复杂表单(需要上次文件时用此方法)
     *
     * @param string $url 请求地址
     * @param string $data 发送数据
     * @param bool $json_format 是否需要自动进行json解析
     *  [
     *      [
     *          'name' => 'a',		//字段名
     *          'contents' => 'aaa'	//对应的值
     *      ],
     *      [
     *          'name' => 'upload_file_name',		//文件字段名
     *          'contents' => fopen('/data/test.md', 'r') //文件资源
     *      ],
     *  ]
     */
    public function postMultiForm($url, $data, $json_format = false)
    {
        $data = $this->request('POST', $url, [RequestOptions::MULTIPART => $data]);
        if ($json_format) {
            return $this->jsonFormat($data);
        } else {
            return $data;
        }
    }

    /**
     * postJson数据
     *
     * @param string $url 请求地址
     * @param string $data 发送数据
     * @param bool $json_format 是否需要自动进行json解析
     */
    public function postJson($url, $data, $json_format = false)
    {
        $data = $this->request('POST', $url, [RequestOptions::JSON => $data]);
        if ($json_format) {
            return $this->jsonFormat($data);
        } else {
            return $data;
        }
    }

    /**
     * 批量请求
     *
     * @param array $url_array 批量请求的url
     */
    public function batchGet($url_array)
    {
        return $this->batch_request('GET', $url_array);
    }
}
