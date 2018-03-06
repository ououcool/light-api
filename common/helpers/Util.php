<?php
namespace helpers;

class Util
{
    /**
     * 判断参数的类型是否合法
     * @param array $params 参数名称
     * @param array $keys 要检查的键值
     * @param string $type 参数类型 目前只支持string/array
     * @param bool $allowEmpty 是否允许为空 注意 数值0 将被视为非empty
     * @return array 返回不合法的键值
     * @throws \Exception
     */
    public static function checkParamsType($params, $keys, $type, $allowEmpty)
    {
        $invalid_keys = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $params)) {
                $invalid_keys[] = $key;
                continue;
            } else if (!$allowEmpty) {
                if (empty($params[$key]) && $params[$key] !== 0) {
                    $invalid_keys[] = $key;
                    continue;
                }
            }
            switch ($type) {
                case 'string'://把非数组 非对象都视为
                    if (!is_string($params[$key]) && !is_numeric($params[$key])) {
                        $invalid_keys[] = $key;
                    }
                    break;
                case 'strNull'://字符串可为null
                    if (!is_string($params[$key]) && !is_numeric($params[$key]) && !is_null($params[$key])) {
                        $invalid_keys[] = $key;
                    }
                    break;
                case 'numeric':
                    if (!is_numeric($params[$key])) {
                        $invalid_keys[] = $key;
                    }
                    break;
                case 'integer':
                    if (!is_int($params[$key])) {
                        $invalid_keys[] = $key;
                    }
                    break;
                case 'array':
                    if (!is_array($params[$key])) {
                        $invalid_keys[] = $key;
                    }
                    break;
                default:
                    throw new \Exception('未设定的参数判断类型' . $type);
            }
        }
        return $invalid_keys;
    }

    /**
     * 检查是否已传必传参数
     */
    protected static function checkParamsNotNull($params = [], $fields = [])
    {
        if (empty($params) || empty($fields)) {
            return true;
        }

        foreach ($fields as $key) {
            if (!isset($params[$key])) {
                throw new \Exception("缺少参数{$key}");
            }
        }
    }

    /**
     * 用户真实IP
     */
    public static function getUserRealIP($type = 1)
    {
        if (isset($_SERVER)) {
            if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) && $_SERVER["HTTP_X_FORWARDED_FOR"]) {
                $realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
            } else if (isset($_SERVER["HTTP_X_REAL_IP"]) && $_SERVER["HTTP_X_REAL_IP"]) {
                $realip = $_SERVER["HTTP_X_REAL_IP"];
            } else if (isset($_SERVER["HTTP_CLIENT_IP"]) && $_SERVER["HTTP_CLIENT_IP"]) {
                $realip = $_SERVER["HTTP_CLIENT_IP"];
            } else {
                $realip = $_SERVER["REMOTE_ADDR"];
            }
        } else {
            if (getenv("HTTP_X_FORWARDED_FOR")) {
                $realip = getenv("HTTP_X_FORWARDED_FOR");
            } else if (getenv("HTTP_X_REAL_IP")) {
                $realip = getenv("HTTP_X_REAL_IP");
            } else if (getenv("HTTP_CLIENT_IP")) {
                $realip = getenv("HTTP_CLIENT_IP");
            } else {
                $realip = getenv("REMOTE_ADDR");
            }
        }
        if ($type && strstr($realip, ",")) {//含有逗号
            $a = explode(",", $realip);
            return trim($a[1]);
        }
        return $realip;
    }

}