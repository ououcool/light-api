<?php
namespace LightApi;

use LightApi\Helper\DBHelper;

/**
 * Model基类
 *
 *@method void startMasterOnlyMode()
 *@method void stopMasterOnlyMode()
 *@method boolean isInMasterOnlyMode()
 *@method void reactiveDAO( $dbConfigFlag = null )
 *@method void startTransaction( $dbConfigFlag = null )
 *@method void commitTransaction( $dbConfigFlag = null )
 *@method void rollbackTransaction( $dbConfigFlag = null )
 *@method boolean isInTransaction()
 *@method array executeQuery( $sql, $args = null, $dbConfigFlag = null )
 *@method mixed executeNonQuery($sql, $args = null, $dbConfigFlag = null)
 *@method mixed executeSingleTableCreate( $tableName, $data, $dbConfigFlag = null )
 *@method mixed executeSingleTableDelete( $tableName, $filters, $orderBy = null, $limit = null, $dbConfigFlag = null )
 *@method mixed executeSingleTableUpdate( $tableName, $updates, $filters, $limit = null, $dbConfigFlag = null )
 *@method array executeSingleTableQuery( $tableName, $fields = '*', $filters = null, $orderBy = null, $limit = null, $offset = 0, $dbConfigFlag = null )
 *@method array buildWhereCondition( $args )
 *@method string getErrorInfo()
 *@method string getLastSql()
 *@method string getLastSqlParams()
 *@method int getLastInsertId()
 *@method array resetArrayIndex( $dataArray, $newIndexSource, $delimiter = ':', $unsetIndexKey = false )
 *
 * @author ououcool(ouyangjiaohui@gmail.com)
 */
abstract class BaseModel
{

    /**
     * Model子类对象池
     * @var array
     */
    private static $instances = [];
    
    /**
     * 公用DBHelper实例
     *
     * @var \LightApi\Helper\DBHelper
     */
    private static $dbhelper = null;

    /**
     * 数据库表前缀
     *
     * @var string
     */
    protected $tablePrefix = null;

    protected function __construct()
    {
        self::prepare();
        $this->tablePrefix = self::$dbhelper->getMasterTablePrefix();
    }

    /**
     * 获取当前Model表的主键
     */
    public abstract function getPkName();
    
    /**
     * 获取当前Model表的表名称
     */
    public abstract function getTableName();
    
    /**
     * 为当前类的使用做准备工作
     */
    private static function prepare(){
        if(self::$dbhelper===null){
            $config = Config::get(DBHelper::aliasName());
            self::$dbhelper = new DBHelper($config);
        }
    }
    
    public function __call($method, $params){
        if(!method_exists(self::$dbhelper, $method))
            throw new \Exception("BaseModel中不存在可调用的{$method}方法。请核查。");
    
            $result = call_user_func_array(array(self::$dbhelper, $method), $params);
             
            return $result;
    }
    
    public function save($data, $dbConfigFlag = null ) {
        return self::$dbhelper->executeSingleTableCreate($this->getTableName(), $data, $dbConfigFlag);
    }
    
    public function deleteByPk($pk, $orderBy = null, $limit = null, $dbConfigFlag = null ) {
        $filters = array(
            $this->getPkName() => $pk
        );
        return $this->deleteByProperties($filters, $orderBy, $limit, $dbConfigFlag);
    }
    
    public function deleteByProperty($propertyName, $propertyValue, $orderBy = null, $limit = null, $dbConfigFlag = null ) {
        $filters = array(
            $propertyName => $propertyValue
        );
        return $this->deleteByProperties($filters, $orderBy, $limit, $dbConfigFlag);
    }
    
    public function deleteByProperties($filters, $orderBy = null, $limit = null, $dbConfigFlag = null ) {
        return self::$dbhelper->executeSingleTableDelete($this->getTableName(), $filters, $orderBy, $limit, $dbConfigFlag);
    }
    
    public function updateByPk($pk, $updates, $limit = null, $dbConfigFlag = null ) {
        $filters = array(
            $this->getPkName() => $pk
        );
        return $this->updateByProperties($filters, $updates, $limit, $dbConfigFlag);
    }
    
    public function updateByProperty($propertyName, $propertyValue, $updates, $limit = null, $dbConfigFlag = null ) {
        $filters = array(
            $propertyName => $propertyValue
        );
        return $this->updateByProperties($filters, $updates, $limit, $dbConfigFlag);
    }
    
    public function updateByProperties($filters, $updates, $limit = null, $dbConfigFlag = null ) {
        return self::$dbhelper->executeSingleTableUpdate($this->getTableName(), $updates, $filters, $limit, $dbConfigFlag);
    }
    
    public function findByPk($pk, $fields = '*', $orderBy = null, $offset = 0, $dbConfigFlag = null ) {
        $filters = array(
            $this->getPkName() => $pk
        );
        return $this->findByProperties($filters, $fields, $orderBy, $offset, $dbConfigFlag);
    }
    
    public function findByProperty($propertyName, $propertyValue, $fields = '*', $orderBy = null, $offset = 0, $dbConfigFlag = null ) {
        $filters = array(
           $propertyName => $propertyValue
        );
        return $this->findByProperties($filters, $fields, $orderBy, $offset, $dbConfigFlag);
    }
    
    public function findByProperties($filters=null, $fields = '*', $orderBy = null, $offset = 0, $dbConfigFlag = null ) {
        $limit = 1;
        $result = $this->findAll($filters, $fields, $orderBy, $limit, $offset, $dbConfigFlag);
        return is_array($result) ? $result[0] : $result;
    }
    
    public function findAll($filters=null, $fields = '*', $orderBy = null, $limit = null, $offset = 0, $dbConfigFlag = null ) {
        return self::$dbhelper->executeSingleTableQuery($this->getTableName(), $fields, $filters, $orderBy, $limit, $offset, $dbConfigFlag);
    }
    
    public function getResultCount($filters=null, $dbConfigFlag = null ) {
        $sql = 'SELECT COUNT(0) count FROM ' . $this->getTableName();
        
        $filter = $this->buildWhereCondition( $filters );
        $sql .= " WHERE " .$filter['sql'];
        
        $result = self::$dbhelper->executeQuery($sql, $filter['args'], $dbConfigFlag);
        return $result[0]['count'];
    }
    
    /**
     * 获取系统的DBHelper实例
     * @return \LightApi\Helper\DBHelper
     */
    public static function db(){
        self::prepare();
        return self::$dbhelper;
    }
    
    /**
     * 获取当前Model的实例
     * 
     * @return \LightApi\BaseModel
     */
    public static function instance(){
        $modelClassName = get_called_class();
        if(!array_key_exists($modelClassName, self::$instances)){
            self::$instances[$modelClassName] = new $modelClassName();
        }
        return self::$instances[$modelClassName];
    }
    
}