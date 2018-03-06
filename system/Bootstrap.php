<?php
/**
 * 框架所在文件夹名称
 */
defined('FX_DIR_NAME')          or define('FX_DIR_NAME', 'system');

/**
 * API所在文件夹名称
 * 备注: 所有API类的根命名空间必须和此相同
 */
defined('API_DIR_NAME')         or define('API_DIR_NAME', 'api');

/**
 * CliApp所在文件夹名称
 * 备注: 所有CliApp类的根命名空间必须和此相同
 */
defined('CLIAPP_DIR_NAME')      or define('CLIAPP_DIR_NAME', 'cliapp');

/**
 * 环境配置文件所在文件夹名称
 */
defined('CONFIG_DIR_NAME')      or define('CONFIG_DIR_NAME', 'environments');

/**
 * 公共类库所在文件夹名称
 * 备注: 所有公共类库中的类的根命名空间必须和此相同
 */
defined('COMMON_DIR_NAME')      or define('COMMON_DIR_NAME', 'common');

/**
 * 当前应用根目录
 */
defined('ROOT_PATH')            or define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

/**
 * 系统框架目录
 */
defined('FX_PATH')              or define('FX_PATH', ROOT_PATH . FX_DIR_NAME. DIRECTORY_SEPARATOR);

/**
 * API类文件存放目录
 */
defined('API_PATH')             or define('API_PATH', ROOT_PATH . API_DIR_NAME. DIRECTORY_SEPARATOR);

/**
 * CliApp类文件存放目录
 */
defined('CLIAPP_PATH')          or define('CLIAPP_PATH', ROOT_PATH . CLIAPP_DIR_NAME. DIRECTORY_SEPARATOR);

/**
 * 应用配置文件存放目录
 * 要求包含:
 *      config.php          系统配置文件
 */
defined('CONFIG_PATH')          or define('CONFIG_PATH', ROOT_PATH . CONFIG_DIR_NAME . DIRECTORY_SEPARATOR);

/**
 * 应用公共类库和函数库目录
 * 要求包含:
 *      functions.php       函数库文件
 */
defined('COMMON_PATH')          or define('COMMON_PATH', ROOT_PATH . COMMON_DIR_NAME . DIRECTORY_SEPARATOR);

/**
 * 运行时文件存放目录
 * 该目录必须对当前PHP运行用户可写
 */
defined('RUNTIME_PATH')         or define('RUNTIME_PATH', ROOT_PATH . 'runtime' . DIRECTORY_SEPARATOR);

/**
 * 系统惯例配置存放目录
 * 要求包含:
 *      config.php          系统惯例配置文件
 */
defined('FX_CONFIG_PATH')       or define('FX_CONFIG_PATH', FX_PATH . 'config' . DIRECTORY_SEPARATOR);


/**
 * 系统公共类库和函数库目录
 * 包含:
 *      functions.php       系统函数库文件
*/
defined('FX_COMMON_PATH')       or define('FX_COMMON_PATH', FX_PATH . 'common' . DIRECTORY_SEPARATOR);

/**
 * 定义配置文件(COMMON_PATH/config.php)内返回的配置数组在$GLOBALS全局变量内的存储key名
*/
defined('FX_KEY_CONFIG')        or define('FX_KEY_CONFIG', '__CONFIG__');

/**
 * 定义配置文件(COMMON_PATH/{DEPLOY_ENV}/clients.php)内返回的配置数组在主Configs变量内的存储key名
*/
defined('FX_KEY_CLIENTS')       or define('FX_KEY_CLIENTS', '__CLIENTS__');


/**
 * 定义当前程序运行的模式
 * 取值:
 *  api / cli
 */
defined('APP_MODE')             or define('APP_MODE', 'api');

/**
 * 当前程序是否以CLI模式运行
 * @var boolean
 */
define('IS_CLI',                PHP_SAPI=='cli' ? true : false);

/**
 * 当前服务器OS环境是否为Windows
 * @var boolean
 */
define('IS_WIN',                strstr(PHP_OS, 'WIN') ? true : false);

if( !in_array(APP_MODE, array('api', 'cli')) ){
    die( 'LightApi dose not support ' . APP_MODE . ' yet !' );
}

if( APP_MODE=='cli' && !IS_CLI ){
    die( 'This script can only run with cli app mode.' );
}

if(!IS_CLI){
    /**
     * Ajax请求的标识符
     * 只要GET或者POST包含此参数, 则认为是ajax请求
     */
    defined('AJAX_IDENTIFIER')  or define('AJAX_IDENTIFIER', '__isAjax');
    
    // 定义当前请求相关的HTTP常量
    define('REQUEST_TIME',      $_SERVER['REQUEST_TIME']);
    define('REQUEST_METHOD',    $_SERVER['REQUEST_METHOD']);
    define('REQUEST_IS_GET',    REQUEST_METHOD =='GET' ? true : false);
    define('REQUEST_IS_POST',   REQUEST_METHOD =='POST' ? true : false);
    define('REQUEST_IS_PUT',    REQUEST_METHOD =='PUT' ? true : false);
    define('REQUEST_IS_DELETE', REQUEST_METHOD =='DELETE' ? true : false);
    define('REQUEST_IS_OPTIONS',REQUEST_METHOD =='OPTIONS' ? true : false);
    define('REQUEST_IS_AJAX',   ( (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || (!empty($_POST[AJAX_IDENTIFIER]) || !empty($_GET[AJAX_IDENTIFIER]) ) ) );
}

/**
 * 是否本地开发环境
 */
defined('ENV_IS_LOCAL') or define('ENV_IS_LOCAL', DEPLOY_ENV == 'local');
/**
 * 是否研发共用环境
 */
defined('ENV_IS_DEV')   or define('ENV_IS_DEV', DEPLOY_ENV == 'dev');

/**
 * 是否测试使用环境
 */
defined('ENV_IS_TEST')  or define('ENV_IS_TEST', DEPLOY_ENV == 'test');
/**
 * 是否线上生产环境
 */
defined('ENV_IS_PROD')  or define('ENV_IS_PROD', DEPLOY_ENV == 'prod');

/**
 * 环境调试开关
 */
defined('ENV_DEBUG')    or define('ENV_DEBUG', ENV_IS_PROD == false);

require 'Application.php';
LightApi\Application::start();