<?php
use LightApi\Helpers\LogHelper;

/**
 * 开发环境
 * 项目配置文件
 */
//@formatter:off
return array(
    // 客户端请求IP限制
    // 此限制为全局限制, 优先级高于针对不同Client的配置. 
    // 如果数组留空(不限制IP), 则所有IP均可连接.
    // 如果数组不为空, 则仅允许列表内配置的IP连接.
    'ClientIPBind'     	=>array(),
    
    // 客户端禁入IP限制(黑名单)
    // 此限制为全局限制, 优先级高于针对不同Client的配置. 
    // 当ClientIPBind为空时, 需对某些IP进行限制, 则可以将这些IP配置到该项中来达到禁止访问的目的．
    'ClientIPBlackList'	=>array(),
    
	// Redis相关配置
    'RedisHelper'		=>array(
        'hostname' 	=> '192.168.39.214',
        'port' 		=> 6379,
        'database' 	=> 11,
        'connectTimeout'	=> 3,
    ),

    // 日志相关配置
    // 非Unix系统只支持file模式
    // 建议Unix系统优先使用syslog模式
    'LogHelper'		    =>array(
        // 日志级别控制
        // 支持两种模式配置:
        // 1: 级别控制模式，记录所有低于指定级别的日志
        // 2: 枚举控制模式，记录所有被枚举了的级别日志
        // 记录LOG_DEBUG及以上级别所有日志;
        // 即: LOG_EMERG / LOG_ALERT / LOG_CRIT / LOG_ERR / LOG_WARNING / LOG_NOTICE / LOG_INFO / LOG_DEBUG
        'logLevel' => LogHelper::LOG_DEBUG,
        // 只记录 LOG_EMERG / LOG_ALERT / LOG_CRIT / LOG_ERR 级别的日志
        // 'logLevel' => [LogHelper::LOG_EMERG, LogHelper::LOG_ALERT, LogHelper::LOG_CRIT, LogHelper::LOG_ERR],
        
        // 以下为syslog模式示例配置
        // 'logType' 	=> 'syslog',            // 日志类型; 可选值: file / syslog
        // 'logFacility' => LOG_LOCAL0,         // 日志来源设施; 建议取值范围: LOG_LOCAL0 ~ LOG_LOCAL7; Win系统仅支持 LOG_USER
    
        // 以下为file模式示例配置
        'logType' 	=> 'file',                  // 日志类型; 可选值: file / syslog
        'splitFileByLevel' 	=> false,          // 日志是否按照级别单独文件存放; 仅限 file 类型日志
        'logFileSavePath'   => RUNTIME_PATH.'logs'.DIRECTORY_SEPARATOR,  //日志文件存储目录, 必须对PHP可写, 且以/结尾; 仅限 file 类型日志
    ),
);