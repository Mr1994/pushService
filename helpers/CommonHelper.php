<?php


namespace app\helpers;


class CommonHelper
{
    public static function matchIp($ip)
    {
        $white_list = [
            '172.18.24.208',
            '39.107.88.66',
            '58.135.84.55',
            '127.0.0.1',
            '117.121.0.98',
            '124.90.33.34',
            '124.90.33.38',
            '124.160.62.*',
            '123.57.174.225',
            '36.110.71.*',
            '1.203.112.150',
            '192.168.10.*',
            '36.110.71.28',
            '192.168.10.18',

        ];
        foreach ($white_list as $rule) {
            if ($rule === '*' || $rule === $ip || (($pos = strpos($rule, '*')) !== false && !strncmp($ip, $rule, $pos))) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取用户ip
     * @return array|false|mixed|string|null
     * creater: 卫振家
     * create_time: 2020/7/3 上午9:14
     */
    public static function getUserIp()
    {
        //strcasecmp 比较两个字符，不区分大小写。返回0，>0，<0。
        if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $clientIp = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ips = explode(',', getenv('HTTP_X_FORWARDED_FOR'));
            array_filter($ips);
            if (count($ips) > 0 && !empty($ips[0])) {
                $clientIp = $ips[0];
            }
        } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $clientIp = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $clientIp = $_SERVER['REMOTE_ADDR'];
        }
        if (empty($clientIp) || $clientIp == "none") {
            $clientIp = null;
        }
        return $clientIp;
    }

    /**
     * 获取服务器ip
     * @return bool|mixed
     * creater: 卫振家
     * create_time: 2020/7/3 上午9:14
     */
    public static function get_server_ip()
    {
        if (!empty($_SERVER['SERVER_ADDR']))
            return $_SERVER['SERVER_ADDR'];
        $result = shell_exec("/sbin/ifconfig");
        if (preg_match_all("/addr:(\d+\.\d+\.\d+\.\d+)/", $result, $match) !== 0) {
            foreach ($match[0] as $k => $v) {
//                  if($match[1][$k] != "127.0.0.1")
                return $match[1][$k];
            }
        }
        return false;
    }
}