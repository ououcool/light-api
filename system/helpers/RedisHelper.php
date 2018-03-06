<?php
namespace LightApi\Helpers;

use LightApi\Config;

/**
 * Redis帮助类
 * 提供的所有操作基于Redis扩展.
 * 具体使用方法可参考以下链接
 *  https://github.com/phpredis/phpredis 介绍
 *
 * @author ououcool(ouyangjiaohui@gmail.com)
 *
 *
 * @method static boolean exists(string $key)
 * @method static array keys(string $keyPattern)
 * @method static integer ttl(string $key)
 * @method static integer pttl(string $key)
 * @method static boolean persist(string $key)
 * @method static boolean expire(string $key, integer $ttl)
 * @method static boolean pexpire(string $key, integer $ttl)
 * @method static boolean setTimeout(string $key, integer $ttl)
 * @method static boolean expireAt(string $key, integer $unixTimestamp)
 * @method static boolean pexpireAt(string $key, integer $unixTimestamp)
 * @method static integer delete(string $key1, string $key2, ...)
 *
 * @method static mixed get(string $key)
 * @method static boolean set(string $key, string $value, integer $expire)
 * @method static boolean mSet(array $keyValues)
 * @method static boolean setNx(string $key, string $value)
 * @method static boolean mSetNx(array $keyValues)
 * @method static boolean setEx(string $key, integer $expire, string $value)
 * @method static integer incr(string $key)
 * @method static integer incrBy(string $key, integer incrValue)
 * @method static float incrByFloat(string $key, float incrValue)
 * @method static integer decr(string $key)
 * @method static integer decrBy(string $key, integer decrValue)
 *
 * @method static mixed hGet(string $key, string $hashKey)
 * @method static array hMGet(string $key, array $hashKeys)
 * @method static boolean hSetNx(string $key, string $hashKey, string $value)
 * @method static boolean hSet(string $key, string $hashKey, string $value)
 * @method static boolean hMSet(string $key, array $keyValues)
 * @method static mixed hLen(string $key)
 * @method static mixed hDel(string $key, string $hashKey1, string $hashKey2, ...)
 * @method static array hKeys(string $key)
 * @method static array hVals(string $key)
 * @method static array hGetAll(string $key)
 * @method static boolean hExists(string $key, string $hashKey)
 * @method static integer hIncrBy(string $key, string $hashKey, integer $incrValue)
 * @method static float hIncrByFloat(string $key, string $hashKey, float $incrValue)
 */
class RedisHelper
{
    private static $redis = null;

    private static $hostname = '127.0.0.1';

    private static $port = 6379;

    private static $database = 0;

    private static $connectTimeout = 3;

    private static $auth = null;

    private static function prepare()
    {
        self::$redis = new \Redis();
        $config = Config::get(self::aliasName());
        foreach ($config as $key => $value)
            self::$$key = $value;

        self::open();
    }

    public static function open()
    {
        self::$redis->connect(self::$hostname, self::$port, self::$connectTimeout);
        if (self::$auth !== null)
            self::$redis->auth(self::$auth);
        self::$redis->select(self::$database);
    }

    public static function close()
    {
        self::$redis->close();
    }

    public static function getDSN(){
        return Config::get(self::aliasName());
    }

    public static function reactive(){
        if (self::$redis === null){
            self::prepare();
        }

        try {
            $flag = self::$redis->echo('ALIVE');
            if(empty($flag) || $flag!='ALIVE'){
                throw new \Exception("redis server ".self::$hostname.':'.self::$port." is down.");
            }
        } catch (\Exception $ex) {
            self::prepare();
        }

        $flag = self::$redis->echo('ALIVE');
        if(empty($flag) || $flag!='ALIVE'){
            throw new \Exception("redis server ".self::$hostname.':'.self::$port." is down and can't reconnect.");
        }
    }

    public static function __callStatic($method, $params)
    {
        if (self::$redis === null){
            self::prepare();
        }

        //调用Redis扩展的对应操作
        $result = call_user_func_array(array(self::$redis, $method), $params);

        // 当使用pconnect时不自动对链接进行关闭, 可提高性能和程序稳定性
        //self::close();

        return $result;
    }

    public static function aliasName()
    {
        return 'RedisHelper';
    }

    /**
     * 获取锁
     * @param  String $key 锁标识
     * @param  Int $expire 锁过期时间
     * @return Boolean true 可执行 false 不可执行
     */
    public static function lock($key, $expire = 3)
    {
        $key ='poa_l:' . $key;
        $is_lock = self::setnx($key, time() + $expire);
        self::expire($key, $expire);
        // 不能获取锁
        if (!$is_lock) {
            // 判断锁是否过期
            $lock_time = self::get($key);
            // 锁已过期，删除锁，重新获取
            if (time() > $lock_time) {
                self::unlock($key);
                $is_lock = self::setnx($key, time() + $expire);
                self::expire($key, $expire);
            }
        }
        return $is_lock ? true : false;
    }

    /**
     * 释放锁
     * @param  String $key 锁标识
     * @return Boolean
     */
    public static function unlock($key)
    {
        $key ='poa_l:' . $key;
        return self::del($key);
    }

}