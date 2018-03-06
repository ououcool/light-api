<?php
namespace LightApi;

use LightApi\Helpers\LogHelper;

/**
 * Application
 * @author ououcool(ouyangjiaohui@gmail.com)
 */
class Application{

    /**
     * 初始化应用
     */
    private static function init(){
        // 注册异常时的捕获和处理器
        set_exception_handler(__CLASS__ . '::exceptionCallback');

        // 注册退出时的捕获和处理器
        register_shutdown_function(__CLASS__ . '::shutdownCallback');
        
        // 注册错误时的捕获和处理器
        set_error_handler(__CLASS__ . '::errorCallback', error_reporting());
        
        // 初始化类加载器
        require_once FX_PATH . 'ClassLoader.php';
        ClassLoader::addNamespace(__NAMESPACE__, FX_PATH);
        ClassLoader::addNamespace(API_DIR_NAME, API_PATH);
        ClassLoader::addNamespace(CLIAPP_DIR_NAME, CLIAPP_PATH);
        ClassLoader::addNamespace(COMMON_DIR_NAME, COMMON_PATH);
        spl_autoload_register(__NAMESPACE__ . '\ClassLoader::import', $throw = true);
        
        // 加载框架自带函数库
        require FX_COMMON_PATH . 'functions.php';

        // 加载框架惯例配置
        $GLOBALS[FX_KEY_CONFIG] = require FX_CONFIG_PATH . 'config.php';
        
        // 加载用户函数库
        require COMMON_PATH . 'functions.php';
        
        // 加载并合并用户配置到全局变量中
        $GLOBALS[FX_KEY_CONFIG] = array_merge($GLOBALS[FX_KEY_CONFIG], require CONFIG_PATH . 'index.php');
    }

    /**
     * 应用启动入口
     */
    public static function start(){
        self::init();
        
        $response = self::{"startWith" . ucfirst(APP_MODE)}();
        
        $response = self::{"renderResponseWith" . ucfirst(APP_MODE)}($response);
        
        // $response = self::{"sendResponseWith" . ucfirst(APP_MODE)}($response);
        die($response);
    }

    private static function startWithApi(){
        /**
         * 定义当前客户端的请求IP常量
         */
        define('REQUEST_CLIENT_IP', get_client_ip($type = 0, $adv = false));
        
        // 进行全局IP限制检查
        $authed = self::checkClientIPLimit(REQUEST_CLIENT_IP);
        if($authed == false) {
            $response = array(
                    'response' => null,
                    'status' => '-1',
                    'message' => 'illegal request. your ip is not allowd.'
           );
            return $response;
        }

        // 检查当前请求是否包含有效Client标识
        $ua = isset($_REQUEST['ua']) ? $_REQUEST['ua'] : $_SERVER['HTTP_USER_AGENT'];
        if(isset($GLOBALS[FX_KEY_CONFIG][FX_KEY_CLIENTS][$ua]) == false) {
            $response = array(
                    'response' => null,
                    'status' => '-2',
                    'message' => 'illegal request. client ua is not correct.'
           );
            return $response;
        }
        
        // 获取当前Client的相关配置项
        $clientConfig = $GLOBALS[FX_KEY_CONFIG][FX_KEY_CLIENTS][$ua];
        
        // 进行Client的请求IP限制检查
        $authed = self::checkClientIPLimit(REQUEST_CLIENT_IP, $clientConfig['ClientIPBlackList'], $clientConfig['ClientIPBind']);
        if($authed == false) {
            $response = array(
                    'response' => null,
                    'status' => '-3',
                    'message' => 'illegal request. you ip is client limited.'
           );
            return $response;
        }
        
        // 开始解析请求参数, 并进行基础检查
        $call = isset($_REQUEST['call']) ? $_REQUEST['call'] : null;
        $args = isset($_REQUEST['args']) ? $_REQUEST['args'] : null;
        $sign = isset($_REQUEST['sign']) ? $_REQUEST['sign'] : null;
        if(empty($call) || empty($args) || empty($sign)) {
            $response = array(
                    'response' => null,
                    'status' => '-4',
                    'message' => 'illegal request. some arguments is not correct.'
           );
            return $response;
        }
        
        // 检查请求签名
        $authed = self::checkRequestSign($call, $args, $sign, $ua, $clientConfig['RequestSignKey']);
        if($authed == false) {
            $response = array(
                    'response' => null,
                    'status' => '-5',
                    'message' => 'illegal request. sign auth failed.'
           );
            return $response;
        }
        
        // 检查API授权列表
        $authed = self::checkAllowedApi($call, $clientConfig['AllowedInterface']);
        if($authed == false) {
            $response = array(
                    'response' => null,
                    'status' => '-6',
                    'message' => 'illegal request. you are not allowd to call this api.'
           );
            return $response;
        }
        
        /**
         * 本次请求的客户端UA
         */
        define('REQUEST_UA', $ua);
        /**
         * 本次请求的Api名称为常量
         */
        define('REQUEST_API', $call);
     
        // 调用请求的API处理对象
        $response = BaseApi::invokeRequestApi($call, $args);
        return $response;
    }

    private static function renderResponseWithApi($response){
        if(is_array($response)) {
            $response = json_encode($response, JSON_UNESCAPED_UNICODE);
        }
        return $response;
    }

    private static function startWithCli(){
        if($_SERVER['argc'] < 2) {
            $response = array(
                    'response' => null,
                    'status' => '-4',
                    'message' => 'illegal request. you call usage is not correct. eg. php cli.php Example.Demo.run'
           );
            return $response;
        }
        $call = trim($_SERVER['argv'][1]);
        
        $args = empty($_SERVER['argv'][2]) ? null : $_SERVER['argv'][2];
        
        /**
         * 记录本次请求的CliApp名称为常量
         */
        define('REQUEST_CLIAPP', $call);
        
        // 调用请求的CliApp处理对象
        $response = BaseCliApp::invokeRequestCliApp($call, $args);
        return $response;
    }

    private static function renderResponseWithCli($response){
        if(is_array($response)) {
            $response = json_encode($response, JSON_UNESCAPED_UNICODE);
        }
        return $response;
    }

    public static function shutdownCallback(){
        $error = error_get_last();
        if($error !== null)
            self::errorCallback($error['type'], $error['message'], $error['file'], $error['line'], null);
    }

    public static function errorCallback($errno, $errstr, $errfile, $errline, $errcontext){
        $canContinue = $errno == E_WARNING || $errno == E_NOTICE || $errno == E_DEPRECATED || $errno == E_STRICT || $errno == E_USER_WARNING || $errno == E_USER_NOTICE || $errno == E_USER_DEPRECATED;
        $noNeedLog = $errno == E_NOTICE || $errno == E_USER_NOTICE || $errno == E_DEPRECATED || $errno == E_USER_DEPRECATED || $errno == E_STRICT;
        
        $errorTypeMap = array(
                E_ERROR => 'E_ERROR',
                E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
                E_WARNING => 'E_WARNING',
                E_PARSE => 'E_PARSE',
                E_NOTICE => 'E_NOTICE',
                E_STRICT => 'E_STRICT',
                E_DEPRECATED => 'E_DEPRECATED',
                E_CORE_ERROR => 'E_CORE_ERROR',
                E_CORE_WARNING => 'E_CORE_WARNING',
                E_COMPILE_ERROR => 'E_COMPILE_ERROR',
                E_COMPILE_WARNING => 'E_COMPILE_WARNING',
                E_USER_ERROR => 'E_USER_ERROR',
                E_USER_WARNING => 'E_USER_WARNING',
                E_USER_NOTICE => 'E_USER_NOTICE',
                E_USER_DEPRECATED => 'E_USER_DEPRECATED'
       );
        $errno = isset($errorTypeMap[$errno]) ? $errorTypeMap[$errno] : 'E_ERRNO_' . $errno;
        
        if(!$noNeedLog) {
            $logMessage = "Type: {$errno}; Message: {$errstr}; File: {$errfile}; Line: {$errline}";
            if($errno == 'E_WARNING' || $errno == 'E_USER_WARNING') {
                LogHelper::warning($logMessage, 'fx_warning');
            }else {
                LogHelper::error($logMessage, 'fx_error');
            }
        }
        if($canContinue) {
            return true;
            /* Don't execute PHP internal error handler */
        }
        
        $response = array(
                'response' => null,
                'status' => '-9999',
                'message' => "server occur a runtime error. message: errno[{$errno}], errstr[{$errstr}], errfile[{$errfile}], errline[{$errline}]"
       );
        die(json_encode($response, JSON_UNESCAPED_UNICODE));
    }

    public static function exceptionCallback($exception){
        // 记录异常日志
        LogHelper::error($exception, 'fx_exception');
        
        $response = array(
                'response' => null,
                'status' => '-999',
                'message' => 'server occur an unexpected exception. message: ' . $exception->getMessage()
       );
        die(json_encode($response, JSON_UNESCAPED_UNICODE));
    }
    
    // 检查IP限定
    private static function checkClientIPLimit($clientIP = null, $IPBlackList = null, $IPBindList = null){
        $clientIP = $clientIP == null ? get_client_ip() : $clientIP;
        
        // IP黑名单不为空, 且当前IP在黑名单内, 则
        $IPBlackList = $IPBlackList == null ? $GLOBALS[FX_KEY_CONFIG]['ClientIPBlackList'] : $IPBlackList;
        if(is_array($IPBlackList) && in_array($clientIP, $IPBlackList))
            return false; // 认证不通过, 返回false
                              
        // IP白名单为空, 或者当前IP在白名单内, 则
        $IPBindList = $IPBindList == null ? $GLOBALS[FX_KEY_CONFIG]['ClientIPBind'] : $IPBindList;
        if(is_array($IPBindList) && (sizeof($IPBindList) < 1 || in_array($clientIP, $IPBindList)))
            return true; // 认证通过, 返回true
        
        return false; // 配置有问题, 或者不在白名单内
    }
    
    // 检查请求签名
    private static function checkRequestSign($call, $args, $sign, $ua, $signKey){
        $signKey = "{$ua}{$signKey}{$ua}";
        $signKey = md5("{$signKey}{$call}{$signKey}{$args}{$signKey}");
        return $sign == $signKey;
    }
    
    // 检查允许调用的Api列表
    private static function checkAllowedApi($call, $allowedApiList){
        // 如果不是数组 或者 数组元素为空, 则
        if(is_array($allowedApiList) == false || sizeof($allowedApiList) == 0)
            return false; // 认为不通过
                              
        // 如果数组只有一个元素 且 为 *, 则
        if(sizeof($allowedApiList) == 1 && $allowedApiList[0] == '*')
            return true;
            
            // 遍历所有的Api列表, 进行通配符比较
        foreach($allowedApiList as $apiExp) {
            $apiExp = rtrim($apiExp, '*');
            if(str_starts_with($call, $apiExp) == true)
                return true;
        }
        return false;
    }
}