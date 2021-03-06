<?php
use LightApi\Helpers\LogHelper;

/**
 * 系统惯例配置
 * 内部所有配置项, 均可以被用户项目配置文件覆盖
 */
return array(
    //客户端IP绑定, 此限制为全局限制, 不针对不同Client. 
    //如果不做绑定限制(数组留空), 则所有IP均请求. 如果配置了任意IP, 则整个网站仅列表内的IP可以请求.
    'ClientIPBind'     =>array(),
    //客户端禁入IP限制(黑名单), 此限制为全局限制, 不针对不同类型Client.
    //如果将任何IP配置在该列表内, 则该列表内的所有IP都会被限制无法访问整个网站.
    'ClientIPBlackList'     =>array(),
    
    // Redis相关配置
    'RedisHelper'		=>array(
        'hostname' 	=> '127.0.0.1',
        'port' 		=> 6379,
        'database' 	=> 0,
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
        'logLevel' => [LogHelper::LOG_EMERG, LogHelper::LOG_ALERT, LogHelper::LOG_CRIT, LogHelper::LOG_ERR],
        
        // 以下为syslog模式示例配置
        // 'logType' 	=> 'syslog',            // 日志类型; 可选值: file / syslog
        // 'logFacility' => LOG_LOCAL0,         // 日志来源设施; 建议取值范围: LOG_LOCAL0 ~ LOG_LOCAL7; Win系统仅支持 LOG_USER
    
        // 以下为file模式示例配置
        'logType' 	=> 'file',                  // 日志类型; 可选值: file / syslog
        'splitFileByLevel' 	=> false,          // 日志是否按照级别单独文件存放; 仅限 file 类型日志
        'logFileSavePath'   => RUNTIME_PATH.'Logs'.DIRECTORY_SEPARATOR,  //日志文件存储目录, 必须对PHP可写, 且以/结尾; 仅限 file 类型日志
    ),
    
    //数据库链接相关配置
    'DBHelper'          => array(
        'DB_TABLE_PREFIX'   => '',      //数据库表前缀
        'MASTER_BEAR_READ'  => true,    //主服务器是否负担读操作
        'MASTER'    => array(   //主服务器配置
            'HOST'      => '127.0.0.1',     // 服务器地址
            'PORT'      => 3306,            // 端口
            'USER'      => 'root',          // 用户名
            'PWD'       => '',              // 密码
            'NAME'      => 'test',          // 数据库名
            'CHARSET'   => 'utf8',          // 数据库编码默认采用utf8
            'PERSIST'   => false          // 数据库是否使用永久链接
        ),
       'SLAVE'      => array(   //从服务器配置
           //从服务器1,2,3.....n
           //array('HOST' => '127.0.0.1', 'PORT' => 3306, 'USER' => 'root', 'PWD' => '', 'NAME' => 'mydb', 'CHARSET' => 'utf8'),
           //array('HOST' => '127.0.0.1', 'PORT' => 3306, 'USER' => 'root', 'PWD' => '', 'NAME' => 'mydb', 'CHARSET' => 'utf8')
        ),
        'OTHER'     => array(   //其他第三方数据库配置
            //'TEST' => array('HOST' => '127.0.0.1', 'PORT' => 3306, 'USER' => 'root', 'PWD' => '', 'NAME' => 'test', 'CHARSET' => 'utf8'),
            //'MySQL' => array('HOST' => '127.0.0.1', 'PORT' => 3306, 'USER' => 'root', 'PWD' => '', 'NAME' => 'mydb', 'CHARSET' => 'utf8'),
            //'SQLITE' => array('PATH' => '/your/path/to/db')
        )
    ),
);