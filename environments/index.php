<?php
/**
 * 项目于配置文件
 */


// 载入主配置文件
$config = require DEPLOY_ENV . '/config.php';

// 载入Clients配置文件
$config[FX_KEY_CLIENTS] = require DEPLOY_ENV . '/clients.php';

return $config;