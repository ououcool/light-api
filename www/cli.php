<?php
/**
 * 使用说明
 *  启动一个CliApp
 *  php cli.php ConsumeTask.SMSConsumer.start  1
 * 
 *  停止一个CliApp
 *  php cli.php stop ConsumeTask.SMSConsumer.start
 */

// 读取php.ini中预定义的环境变量
$env = get_cfg_var('env');
$env = $env ? $env : 'dev';
/**
 * 环境变量可选值
 *      local        个人本地开发环境
 *      dev          开发团队共用的环境
 *      test         测试团队使用的环境
 *      prod         线上生产使用的正式
 */
defined('DEPLOY_ENV')   or define('DEPLOY_ENV', $env);


ini_set('date.timezone','Asia/Shanghai');

// 生产环境错误配置
// error_reporting(E_ERROR);
// ini_set('display_errors', 'off');

// 开发环境错误配置
ini_set('display_errors', 'on');
error_reporting(E_ALL & ~ E_NOTICE & ~ E_STRICT & ~ E_DEPRECATED);

// 定义当前运行模式为CLI
define('APP_MODE', 'cli');

//载入框架启动文件，开始执行
require dirname (__DIR__) .'/lightapi/Bootstrap.php';