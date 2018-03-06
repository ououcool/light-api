<?php
namespace LightApi;

/**
 * Api基类
 * @author ououcool(ouyangjiaohui@gmail.com)
 */
class BaseApi{

    public function __construct( ){}

    /**
     * 获取当前客户端相关配置
     * @param string $ua
     *        客户端标识; 可选
     * @return array
     */
    public function getClientConfig( $ua = null ){
        $ua = empty( $ua ) ? REQUEST_UA : $ua;
        return $GLOBALS[FX_KEY_CONFIG][FX_KEY_CLIENTS][$ua];
    }

    /**
     * 调用其他API
     * @param string $call
     *        要调用的API名称
     * @param string $args
     *        API调用参数
     */
    public function invokeApi( $call, $args = [] ){
        return self::invokeRequestApi( $call, $args );
    }
    
    // 调用请求的API处理对象
    public static function invokeRequestApi( $call, $args ){
        // 请求分发前预处理
        $call = explode( '.', $call );
        if( sizeof( $call ) != 3 ) {
            $response = array( 
                    'response' => null,
                    'status' => '-7',
                    'message' => 'illegal request. call api format is not correct.'
            );
            return $response;
        }
        
        $requestMethod = $call[2]; // 请求方法名
        $requestClass = API_DIR_NAME . "\\{$call[0]}\\{$call[1]}"; // 请求类名

        ClassLoader::import( $requestClass );
        if( class_exists( $requestClass, false ) == false || method_exists( $requestClass, $requestMethod ) == false ) {
            $response = array( 
                    'response' => null,
                    'status' => '-8',
                    'message' => 'illegal request. the api you called is not exists.'
            );
            return $response;
        }
        
        // 初始化Api类，并调用对应Api方法
        $requestClassInstance = new $requestClass( );
        if( is_string( $args ) ) {
            $args = json_decode( $args, true );
            if( $args === null ) {
                $response = array( 
                        'response' => null,
                        'status' => '-9',
                        'message' => 'illegal request. the api arguments is not a good json string. '
                );
                return $response;
            }
        }
        $response = $requestClassInstance->$requestMethod( $args );
        return $response;
    }

    
    /**
     * 结束并给出响应结果
     * @param string $response
     *        响应的数据
     * @param number $status
     *        请求处理状态
     * @param string $message
     *        请求处理消息
     * @return array
     */
    public function endResponse( $response = null, $status = 1, $message = 'success' ){
        $response = array( 
                'response' => $response,
                'status' => $status,
                'message' => $message
        );
        return $response;
    }
}