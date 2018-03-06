<?php
namespace LightApi;

/**
 * 类加载器
 *
 * @author ououcool(ouyangjiaohui@gmail.com)
 */
class ClassLoader
{

    /**
     * 所有注册的命名空间映射
     *
     * @var array
     */
    private static $namespace = [];

    /**
     * 注册命名空间
     *
     * @param mixed $nsName
     *            命名空间名称, 字符串或数组.
     * @param string $nsRootPath
     *            用于添加单个命名空间映射时, 指定命名空间的根路径
     */
    public static function addNamespace($nsName, $nsRootPath = '')
    {
        if (is_array($nsName)) {
            self::$namespace = array_merge(self::$namespace, $nsName);
            return;
        }
        self::$namespace[$nsName] = $nsRootPath;
    }

    /**
     * 导入类文件
     *
     * @param string $className
     *            要导入的类名称
     * @return bool 导入成功或者失败
     */
    public static function import($className)
    {
        $clzFilePath = self::getClassFilePath($className);
        if (empty($clzFilePath)) {
            return false;
            // throw new \Exception("类文件[{$className}]不存在！");
        }
        // 引入类文件
        require_once $clzFilePath;
        return true;
    }

    /**
     * 检查系统中是否存在指定类
     * 
     * @param string $className            
     * @return boolean
     */
    public static function isClassExist($className)
    {
        return !empty(self::getClassFilePath($className));
    }

    /**
     * 获取已注册命名空间中的类文件路径
     * 
     * @param string $className            
     * @return NULL|string
     */
    public static function getClassFilePath($className)
    {
        $className = explode('\\', $className);
        if (sizeof($className) < 2) {
            return null;
        }

        $nsName = array_shift($className);        
        if (array_key_exists($nsName, self::$namespace) == false) {
            return null;
        }
        
        $nsRootPath = self::$namespace[$nsName];
        $clzFilePath = $nsRootPath . implode(DIRECTORY_SEPARATOR, $className) . '.php';
      
        if (!file_exists($clzFilePath)) {
            return null;
        }
        return $clzFilePath;
    }
}