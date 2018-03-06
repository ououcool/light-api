<?php
namespace common\helpers;

use LightApi\Config;
use LightApi\Helpers\LogHelper;

/**
 * Tata服务调用类 <br>
 * 使用方法：<br>
 * 　$args = []; <br>
 * 　$call = 'Demo.Demo.run'; <br>
 * 　$result = Tata::usercenter()->call($call, $args); <br>
 * 返回结果为一个数组，其中code为0表示成功，其他值表示失败
 */
class Tata
{
    private $apiEntryUrl;

    private $clientUA;

    private $clientSignkey;

    private $connectTimeout = 3;

    private $executeTimeout = 30;

    private static $requestHeaders = null;

    private function __construct() {}

    private function config($apiEntryUrl, $clientUA, $clientSignkey, $connTimeout, $execTimeout) {
        $this->apiEntryUrl = $apiEntryUrl;
        $this->clientUA = $clientUA;
        $this->clientSignkey = $clientSignkey;
        $this->connectTimeout = $connTimeout;
        $this->executeTimeout = $execTimeout;
    }

    /**
     * 请求服务端Api
     *
     * @param string $call 要请求的Api名称, 三段式, ex: Test.Info.getWelcomeMessage
     * @param array $args 请求参数, 一维数组.
     * @return array or string 请求成功返回数组, 失败返回错误信息字符串
     */
    private function request($call, $args, $httpHeaders = array())
    {
        $clientUA = $this->clientUA;
        $clientSignKey = $this->clientSignkey;
        $requstUrl = "{$this->apiEntryUrl}?call={$call}";

        $args = json_encode($args);
        $sign = "{$clientUA}{$clientSignKey}{$clientUA}";
        $sign = md5("{$sign}{$call}{$sign}{$args}{$sign}");
        $postArgs = "args={$args}&sign={$sign}&ua={$clientUA}";

        $ch = curl_init();
        // 设置请求参数
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $clientUA);
        curl_setopt($ch, CURLOPT_URL, $requstUrl);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postArgs);

        // 设置其他参数
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, false);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_TIMEOUT, $this->executeTimeout); //设置cURL允许执行的最长秒数
        //curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->executeTimeout*1000); //设置cURL允许执行的最长毫秒数
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);  //在发起连接的超时时间，如果设置为0，则无限等待
        //curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout*1000);  //在发起连接的超时时间，单位为ms. 如果设置为0，则无限等待

        // 调用接口, 同步等待接受响应数据
        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode != 200 || curl_errno($ch) !== 0 ) {
            $logMessage = "请求服务失败！\r\nURL: {$requstUrl}\r\nPOST: {$postArgs}\r\nRET:{$response}";
            $logMessage .= "\r\nHTTP STATUS: {$httpCode}; \r\nCURL_ERRNO: " . curl_errno($ch) . "; CURL_ERROR: " . curl_error($ch);
            LogHelper::error($logMessage, 'tata.error');

            curl_close($ch); // 关闭curl, 释放资源
            $result = array(
                'response' => null,
                'status' => -9999,
                'message' => '网络好像有点问题，请您稍后再试~~~'
            );
            return $result;
        }
        curl_close($ch); // 关闭curl, 释放资源

        $result = json_decode($response, true);
        if($result==null){
            $logMessage = "请求服务的返回结果处理失败！";
            $logMessage .= "\r\nURL: {$requstUrl}\r\nPOST: {$postArgs}\r\nRET: ". var_export($response, true);
            LogHelper::error($logMessage, 'tata.error');

            $result = array(
                'response' => null,
                'status' => -999,
                'message' => '网络有点问题，请您稍后再试~~~'
            );
        }
        return $result;
    }

    private static function initRequestHeaders(){
        if(self::$requestHeaders!=null){
            return self::$requestHeaders;
        }

        self::$requestHeaders = [];

        // 原始客户端IP
        self::$requestHeaders[] = "Origin-Client-IP: " . getenv("REMOTE_ADDR");
        // 原始客户端App的市场来源
        self::$requestHeaders[] = "Origin-Client-UserAgent: NULL" ;

        self::$requestHeaders[] = "Origin-Client-ChannelCode: POA";
    }

    public function call($api, $args=[]){
        self::initRequestHeaders();

        return $this->request($api, $args, self::$requestHeaders);
    }

    private function getHttpArgs($call, $args)
    {
        $clientUA = $this->clientUA;
        $clientSignKey = $this->clientSignkey;

        $args = json_encode($args);
        $sign = "{$clientUA}{$clientSignKey}{$clientUA}";
        $sign = md5("{$sign}{$call}{$sign}{$args}{$sign}");
        // $postArgs = "args={$args}&sign={$sign}&ua={$clientUA}";
        $postArgs = ['args' => $args, 'sign' => $sign, 'ua' => $clientUA];

        $post['form_params']= $postArgs;

        return $post;
    }

    /**
     * 并发请求
     * @param string $uri
     *        接口地址
     * @return array
     */
    public function callAll( $groupRequest ){
        ComposerLoader::init();

        $client = new Client();

        // Initiate each request but do not block
        $promises = [];
        foreach ($groupRequest as $key=>$task){

            $requestUrl = "{$this->apiEntryUrl}?call={$task['call']}";

            $promises[$key] = $client->requestAsync('POST', $requestUrl, $this->getHttpArgs($task['call'],$task['args']));
        }

        // Wait on all of the requests to complete.
        $results = Promise\unwrap($promises);
        foreach($results as $key=>$response){
            $code = $response->getStatusCode(); // 200
            $reason = $response->getReasonPhrase(); // OK
            if ($code != 200) {
                $groupRequest[$key] = "操作失败! 请求失败: [{$code}] [{$reason}]";
                continue;
            }

            $body = $response->getBody();
            $responseContent = trim($body->getContents());
            /**
             * @var ISMSProvider $provider
             */
            $groupRequest[$key] = $responseContent;
        }
        return $groupRequest;
    }

    private static $instances = [];

    private static function instance($svcConfig){
        $apiEntryUrl = @$svcConfig['APIEntryUrl'];
        $clientUA = @$svcConfig['ClientUA'];
        $clientSignkey = @$svcConfig['ClientSignKey'];

        $connTimeout = intval(@$svcConfig['ConnectTimeout']);
        $execTimeout = intval(@$svcConfig['ExecuteTimeout']);

        $condition = empty($apiEntryUrl) || empty($clientUA) || empty($clientSignkey);
        if($condition){
            throw new \Exception('配置不正确，存在不允许的无效配置项！请检查。');
        }

        $instance = new self();
        $instance->config($apiEntryUrl, $clientUA, $clientSignkey, $connTimeout, $execTimeout);

        return $instance;
    }

    /**
     * 调用第三方服务中心
     * @param  string $moduleKey 服务中心
     * @return Tata
     */
    private static function loadInstanceBySysKey($moduleKey){
        if(!empty(self::$instances[$moduleKey])){
            return self::$instances[$moduleKey];
        }

        $tbkey = basename(str_replace('\\', '/', __CLASS__));
        $LightApi = Config::get($tbkey);
        $svcConfig = @$LightApi[$moduleKey];

        if(empty($svcConfig)){
            throw new \Exception("没有找到可供{$tbkey}使用的{$moduleKey}服务相关配置。");
        }

        self::$instances[$moduleKey] = self::instance($svcConfig);
        return self::$instances[$moduleKey];
    }

    /**
     * 获取用于UserCenter的调用实例
     * @throws \Exception
     * @return Tata
     */
    public static function usercenter(){
        return self::loadInstanceBySysKey(__FUNCTION__);
    }

    /**
     * 获取用于MessageCenter的调用实例
     * @throws \Exception
     * @return Tata
     */
    public static function messagecenter(){
        return self::loadInstanceBySysKey(__FUNCTION__);
    }

    /**
     * 获取用于OrderCenter的调用实例
     * @throws \Exception
     * @return Tata
     */
    public static function ordercenter(){
        return self::loadInstanceBySysKey(__FUNCTION__);
    }
}
