<?php
/**
 * 请求方式:
 *      /index.php?call=Demo.Demo.test&args={}&sign=2a8a0172482d4f0b8dc3d55b2ece6f3c&ua=LOCAL_DEV_CLIENT
 *  必备参数:
 *      call:   请求的目标Api
 *      args:   Api请求参数
 *      sign:   本次请求签名
 *      ua:     客户端的UserAgent
 **/
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
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

// 定义当前运行模式为API
define('APP_MODE', 'api');

/**
 * 框架所在文件夹名称
 */
defined('FX_DIR_NAME')          or define('FX_DIR_NAME', 'system');

//载入框架启动文件，开始执行
require dirname(__DIR__) . '/' . FX_DIR_NAME .'/Bootstrap.php'; 