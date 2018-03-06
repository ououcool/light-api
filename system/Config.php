<?php

namespace LightApi;

/**
 * 配置操作类
 *
 * @author ououcool(ouyangjiaohui@gmail.com)
 */
class Config {
	
	/**
	 * 读取配置项
	 *
	 * @param string $configKey
	 *        	要读取的配置项键名
	 * @param mixed $defaultValue
	 *        	不存在配置项时返回的默认值
	 */
	public static function get($configKey, $defaultValue = null) {
		if (array_key_exists ( $configKey, $GLOBALS [FX_KEY_CONFIG] ))
			return $GLOBALS [FX_KEY_CONFIG] [$configKey];
		return $defaultValue;
	}
	
	/**
	 * 设置配置项的值
	 *
	 * @param mixed $configKey
	 *        	单个设置时此参数为要设置的配置项键名， 批量设置时此参数为key-value table数组
	 * @param mixed $configValue
	 *        	仅用于单个设置时设置配置项的值
	 */
	public static function set($configKey, $configValue = null) {
		if (is_string ( $configKey ) && $configValue != null) {
			$GLOBALS [FX_KEY_CONFIG] [$configKey] = $configValue;
			return true;
		}
		if (is_array ( $configKey ) && $configValue == null) {
			foreach ( $configKey as $key => $value )
				self::set ( $key, $value );
			return true;
		}
		throw new \Exception ( "调用参数有误！请检查方法使用说明。" );
	}
}