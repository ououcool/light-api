<?php
/**
 * Api客户端认证相关配置
 */
//@formatter:off
return array(
    // 本地开发调试用认证身份
    'LOCAL_DEV_CLIENT' => array(
        // Client请求签名密钥
        'RequestSignKey'    => 'LOCAL_DEV_REQUEST_SIGN_KEY',
        
        // Client请求IP限制
        // 如果数组留空(不对IP限制), 则该Client在所有IP均可连接. 
        // 如果数组不为空, 则该Client仅允许在列表内配置的IP进行连接.
        'ClientIPBind'      => array(),
        
        // Client禁入IP限制(黑名单)
        // 当ClientIPBind为空时, 需对某些IP进行限制, 则可以将这些IP配置到该项中来达到禁止访问的目的．
        'ClientIPBlackList' => array(),
        
        // Client接口调用授权
        // 配置支持通配符, 规则如下:
        // 如果允许所有Api接口被调用(不做限制), 则使用 *
        // 如果允许AModule下所有Class提供的Api, 则使用 AModule.*
        // 如果允许AModule下BClass提供的所有Api, 则使用 AModule.BClass.*
        // 如果仅允许指定的Api被该Client访问, 则配置该Api的全路径到列表中即可.
        'AllowedInterface'  => array(
            '*'
        )
    ),
);