<?php
namespace LightApi\Helpers;

use LightApi\Config;

class LogHelper
{

    const LOG_EMERG = 0;

    const LOG_ALERT = 1;

    const LOG_CRIT = 2;

    const LOG_ERR = 3;

    const LOG_WARNING = 4;

    const LOG_NOTICE = 5;

    const LOG_INFO = 6;

    const LOG_DEBUG = 7;

    const LOG_TYPE_FILE = 'file';

    const LOG_TYPE_SYSLOG = 'syslog';
    
    /**
     * 日志级别映射表
     *
     * @var array
     */
    private static $LOG_LEVEL_IDS = array(
        self::LOG_EMERG => 'EMERG',
        self::LOG_ALERT => 'ALERT',
        self::LOG_CRIT => 'CRIT',
        self::LOG_ERR => 'ERROR',
        self::LOG_WARNING => 'WARN',
        self::LOG_NOTICE => 'NOTICE',
        self::LOG_INFO => 'INFO',
        self::LOG_DEBUG => 'DEBUG'
    );

    private static $logType;
    
    private static $logLevel;

    /**
     * 日志设施标记
     * 此属性仅用于syslog模式
     * 
     * @var integer 建议取值范围: LOG_LOCAL0 ~ LOG_LOCAL7
     */
    private static $logFacility;

    /**
     * 上一次使用到的日志标签
     * 此属性仅用于syslog模式
     * 
     * @var string
     */
    private static $lastUsedTag;

    /**
     * log日志文件保存路径
     *
     * @var string
     */
    private static $logFileSavePath;

    /**
     * 是否按照错误级别分割文件
     * 
     * @var boolean
     */
    private static $splitFileByLevel = true;
    
    /**
     * 日志输出资源是否已经初始化
     * 
     * @var boolean
     */
    private static $isOutputResInited = false;

    private function __construct()
    {}
    
    private static function init(){
        $selfAlias = self::aliasName();
        $config = Config::get($selfAlias);
        
        // 支持枚举和级别控制
        self::$logLevel = $config['logLevel'];
        if(self::$logLevel===null){
            throw new \Exception("{$selfAlias} logLevel can not be null. ");
        }
        
        if(!is_int(self::$logLevel) && !is_array(self::$logLevel)){
            throw new \Exception("{$selfAlias} logLevel only support integer or array. ");
        }
        
        self::$logType = $config['logType'];
        // syslog 模式相关配置
        if( self::$logType == self::LOG_TYPE_SYSLOG ){
            if(IS_WIN){
                throw new \Exception("{$selfAlias} only allow Unix like OS use syslog mode.");
            }
            if(isset($config['logFacility'])==false){
                throw new \Exception("{$selfAlias} need logFacility config when use syslog mode.");
            }
            self::$logFacility = $config['logFacility'];
        }else
            
       // file 模式相关配置
        if( self::$logType == self::LOG_TYPE_FILE ){
            if(isset($config['splitFileByLevel'])==false){
                throw new \Exception("{$selfAlias} need splitFileByLevel config when use file mode.");
            }
            if(isset($config['logFileSavePath'])==false){
                throw new \Exception("{$selfAlias} need logFileSavePath config when use file mode.");
            }
            self::$splitFileByLevel = $config['splitFileByLevel'];
            self::$logFileSavePath = $config['logFileSavePath'];
        }
        // 其他非 file 和 syslog 类型的则直接报错
        else{
            throw new \Exception("{$selfAlias} does not support the [" . self::$logType . "] log type yet. please change it.");
        }
    }

    /**
     * 写调试信息
     *
     * @param mixed $message            
     * @param string $tag            
     */
    public static function debug($message, $tag)
    {
        self::log($message, self::LOG_DEBUG, $tag);
    }

    /**
     * 写消息类信息
     *
     * @param mixed $message            
     * @param string $tag            
     */
    public static function info($message, $tag)
    {
        self::log($message, self::LOG_INFO, $tag);
    }

    /**
     * 写提醒类信息
     *
     * @param mixed $message            
     * @param string $tag            
     */
    public static function notice($message, $tag)
    {
        self::log($message, self::LOG_NOTICE, $tag);
    }

    /**
     * 写警告信息
     *
     * @param mixed $message            
     * @param string $tag            
     */
    public static function warning($message, $tag)
    {
        self::log($message, self::LOG_WARNING, $tag);
    }

    /**
     * 写错误信息
     *
     * @param mixed $message            
     * @param string $tag            
     */
    public static function error($message, $tag)
    {
        self::log($message, self::LOG_ERR, $tag);
    }

    /**
     * 写危急类信息
     *
     * @param mixed $message            
     * @param string $tag            
     */
    public static function critical($message, $tag)
    {
        self::log($message, self::LOG_CRIT, $tag);
    }

    /**
     * 写警报类信息
     *
     * @param mixed $message            
     * @param string $tag            
     */
    public static function alert($message, $tag)
    {
        self::log($message, self::LOG_ALERT, $tag);
    }

    /**
     * 写警急类信息
     *
     * @param mixed $message            
     * @param string $tag            
     */
    public static function emergency($message, $tag)
    {
        self::log($message, self::LOG_EMERG, $tag);
    }

    /**
     * 写入日志
     *
     * @param mixed $message
     *            日志内容
     * @param int $level
     *            日志级别
     * @param string $tag
     *            日志标签
     */
    public static function log($message, $level, $tag)
    {
        if( empty(self::$logType) ){
            self::init();
        }
        
        // 如果要写入日志的级别低于配置级别, 则直接丢弃
        if(is_int(self::$logLevel) && $level>self::$logLevel){
            return ;
        }
        // 如果要写入日志的级别不再枚举的日志级别范围内, 则直接丢弃
        if(is_array(self::$logLevel) && !in_array($level, self::$logLevel) ){
            return ;
        }
        
        if (self::$logType == self::LOG_TYPE_FILE) {
            return self::writeLogToFile($message, $level, $tag);
        }
        
        if (self::$logType == self::LOG_TYPE_SYSLOG) {
            return self::writeLogToSyslog($message, $level, $tag);
        }
    }

    private static function parseMessageAsString($message)
    {
        if (is_string($message)) {
            return $message;
        }
        if (is_array($message)) {
            return json_encode($message, JSON_UNESCAPED_UNICODE);
        }
        
        if ($message instanceof \Exception || $message instanceof \Error) {
            $exception = $message;
            // 记录异常日志
            $message = 'GET=' . json_encode($_GET, JSON_UNESCAPED_UNICODE) . '; POST=' . json_encode($_POST, JSON_UNESCAPED_UNICODE);
            $message .= ";\r\nMessage: " . $exception->getMessage() . '; Code: ' . $exception->getCode();
            $message .= ";\r\nTrace: \r\n" . $exception->getTraceAsString();
        }
        return $message;
    }

    private static function writeLogToFile($message, $level, $tag)
    {
        if (self::$isOutputResInited==false){
            if (file_exists(self::$logFileSavePath) == false) {
                mkdir(self::$logFileSavePath, 0775, true);
            }
            self::$isOutputResInited = true;
        }
        
        $dateNow = date("Y-m-d H:i:s");
        $logFilePath = self::$splitFileByLevel ? '.'.strtolower(self::$LOG_LEVEL_IDS[$level]) : "";
        $logFilePath = self::$logFileSavePath . "{$tag}{$logFilePath}.".str_replace('-', '', substr($dateNow, 0, 10)).".log";
        
        $message = self::$LOG_LEVEL_IDS[$level] . "\t" . self::parseMessageAsString($message);
        file_put_contents($logFilePath, "\r\n{$dateNow} {$message}", FILE_APPEND);
    }

    private static function writeLogToSyslog($message, $level, $tag)
    {
        if(self::$isOutputResInited==false || self::$lastUsedTag!=$tag){
            if (! openlog($tag, LOG_ODELAY, self::$logFacility)) {
                throw new \Exception(self::aliasName()." can't open syslog for ident {$tag} and facility " . self::$logFacility);
            }
            self::$lastUsedTag = $tag;
            self::$isOutputResInited = true;
        }
        syslog($level, self::parseMessageAsString($message));
    }
    
    public static function aliasName()
    {
        return 'LogHelper';
    }
}