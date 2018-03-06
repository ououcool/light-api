<?php
namespace LightApi\Helpers;

/**
 * 数据库操作辅助类
 * @author ououcool(ouyangjiaohui@gmail.com)
 */
class DBHelper{
	
    /**
     * 默认数据库配置标识/读操作标识
     */
    const DEFAULT_DBCONFIG_FLAG_READ = '__MASTER_READ__';
    
    /**
     * 默认数据库配置标识/写操作标识
     */
    const DEFAULT_DBCONFIG_FLAG_WRITE = '__MASTER_WRITE__';
    
    /**
     * 数据库访问对象池
     * @var \System\DbAccessObject
     */
    private static $daoPool = array();
    
    /**
     * 默认数据库配置, 未初始化时为null
     * @var array
     */
    private static $defaultDbConfig = null;
    
    /**
     * 第三方应用数据库, 未初始化时为null
     * @var array
     */
    private static $otherDbConfig = null;
    
    /**
     * 当前是否处于主库Only模式(不读写分离/不负载均衡)
     * @var bool
     */
    private static $inMasterOnlyMode = false;
    
    /**
     * 当前是否正处在事务中
     * @var bool
     */
    private static $inTransaction = false;
    
    /**
     * 当前事务嵌套深度; 默认0
     * @var int
     */
    private static $curTransactionDepth = 0;
    
    /**
     * 上一个活动的DAO对象标识
     * @var string
     */
    private $lastActiveDAO = null;

    /**
     * 构造函数
     * @param array $_config    数据库相关配置, 如果不指定该参数, 则会加载默认配置文件进行配置初始化
     */
    public function __construct( $_config = null ){
        // 如果尚未进行过配置初始化, 则开始进行初始化
        if( self::$defaultDbConfig === null && self::$otherDbConfig === null ) {
            $this->initDBConfig( $_config ); // 初始化配置项
            
            self::$otherDbConfig = self::$otherDbConfig === null ? array() : self::$otherDbConfig; // 初始化为非null值, 以防再次重复进行初始化
            self::$defaultDbConfig = self::$defaultDbConfig === null ? array() : self::$defaultDbConfig; // 初始化为非null值, 以防再次重复进行初始化
        }
    }

    /**
     * 初始化数据库的配置数据
     * @param array $configArray    数据库相关配置, 如果不指定该参数, 则会加载默认配置文件进行配置初始化
     * @throws \InvalidArgumentException
     */
    private function initDBConfig( $configArray = null ){
        //如果没有, 传入数据库相关配置数组, 则根据配置文件进行初始化 
        if($configArray===null){
            //初始化主域名以外的第三方数据库系统配置
            if(defined('DBHELPER_CONFIG_PATH') && file_exists(DBHELPER_CONFIG_PATH)){
                $dbConfigPath = DBHELPER_CONFIG_PATH;
            }else{
                $dbConfigPath = dirname(__FILE__);
            }
            $dbConfigPath = $dbConfigPath.DIRECTORY_SEPARATOR.'DBHelper.Config.php';
            $configArray = require $dbConfigPath ;
        }
        
        // 如果配置了第三方的其他数据库, 则进行初始化, 并unset掉
        if( isset( $configArray['OTHER'] ) ) {
            self::$otherDbConfig = $configArray['OTHER'];
            unset( $configArray['OTHER'] );
        }
        // 如果没有配置主数据库的host/port/user/pwd/name, 则报错退出
        if( isset( $configArray['MASTER'] ) == false || isset( $configArray['MASTER']['HOST'] ) == false || isset( $configArray['MASTER']['PORT'] ) == false || isset( $configArray['MASTER']['USER'] ) == false || isset( $configArray['MASTER']['PWD'] ) == false || isset( $configArray['MASTER']['NAME'] ) == false ){
            throw new \InvalidArgumentException( '数据库配置错误！缺少最基本的主数据库连接参数' );
        }
            
        // 如果未设置MASTER_BEAR_READ参数, 则重置为默认false
        $configArray['MASTER_BEAR_READ'] = isset( $configArray['MASTER_BEAR_READ'] ) ? $configArray['MASTER_BEAR_READ'] : false;
        // 如果未设置DB_TABLE_PREFIX参数, 则重置为默认''
        $configArray['DB_TABLE_PREFIX'] = isset( $configArray['DB_TABLE_PREFIX'] ) ? $configArray['DB_TABLE_PREFIX'] : '';
        
        self::$defaultDbConfig = $configArray; // 存储配置项到静态变量内
    }

    /**
     * 添加一个其他数据库配置
     * @param string $configFlag
     *        在后续的查询等操作时候会用到
     * @param array $dbConfig
     *        如: array('HOST'=>'127.0.0.1','PORT'=>3306,'USER'=>'root','PWD'=>'','NAME'=>'test','CHARSET'=>'utf8');
     * @throws \InvalidArgumentException
     */
    public function addOtherDbConfig( $configFlag, $dbConfig ){
        if( is_array( $dbConfig ) && isset( $dbConfig['HOST'] ) && isset( $dbConfig['PORT'] ) && isset( $dbConfig['USER'] ) && isset( $dbConfig['PWD'] ) && isset( $dbConfig['NAME'] ) && isset( $dbConfig['CHARSET'] ) ){
            self::$otherDbConfig[$configFlag] = $dbConfig;
        }else{
            throw new \InvalidArgumentException( "要添加的连接配置参数错误! 请仔细检查" );
        }
    }

    /**
     * 移除已设置的数据库连接配置参数
     * @param string $configFlag
     *        要移除的配置名称, 如果不指定, 则默认移除全部
     */
    public function removeOtherDbConfig( $configFlag = null ){
        if( $configFlag === null ){
            self::$otherDbConfig = array(); // 清除所有配置
        }else{
            unset( self::$otherDbConfig[$configFlag] ); // 清除指定的配置
        }
    }

    /**
     * 初始化默认数据库的读写操作对象
     */
    private function initDefaultDAO( ){
        $dbWriteConf = self::$defaultDbConfig['MASTER'];
        
        self::$daoPool[self::DEFAULT_DBCONFIG_FLAG_WRITE] = new DbAccessObject( $dbWriteConf );
        
        // 计算可以承受读的服务器数量
        $canReadDBCount = isset( self::$defaultDbConfig['SLAVE'] ) ? sizeof( self::$defaultDbConfig['SLAVE'] ) : 0;
        // 如果配置了主服务器承担读任务, 或者没有配置任何从服务器, 则 对可承受读操作的服务器数量+1
        if( self::$defaultDbConfig['MASTER_BEAR_READ'] == true || $canReadDBCount == 0 ){
            $canReadDBCount++;
        }
        
        $dbReadConf = null;
        // 随机选择本次要承担读操作的数据库配置索引
        $currentUseDBIndex = mt_rand( 0, $canReadDBCount - 1 );
        // 如果主库负载读压力 且 本次使用的索引为, 总配置数-1, 则使用主服务器
        if( self::$defaultDbConfig['MASTER_BEAR_READ'] == true  && $currentUseDBIndex == $canReadDBCount - 1 ){
            $dbReadConf = self::$defaultDbConfig['MASTER'];
        }else{
            $dbReadConf = self::$defaultDbConfig['SLAVE'][$currentUseDBIndex];
        }
        self::$daoPool[self::DEFAULT_DBCONFIG_FLAG_READ] = new DbAccessObject( $dbReadConf );
    }

    /**
     * 获取用于指定数据库的读操作数据访问对象
     * @param string $dbConfigFlag
     *        则使用主数据库的配置
     * @return DbAccessObject
     */
    private function getReadDAO( $dbConfigFlag = null ){
        if( empty($dbConfigFlag) ){
            $dbConfigFlag = self::DEFAULT_DBCONFIG_FLAG_READ;
            //如果当前操作未指定数据库链接标识, 
            //且  当前处于MasterOnly模式 或者 事务环境中 则 将数据库链接标识 指定为 主库
            if( self::$inMasterOnlyMode==true || self::$inTransaction==true){
                $dbConfigFlag = self::DEFAULT_DBCONFIG_FLAG_WRITE;
            }
        }
        return $this->getDAO( $dbConfigFlag );
    }

    /**
     * 获取用于指定数据库的写操作数据访问对象
     * @param string $dbConfigFlag
     *        则使用主数据库的配置
     * @return DbAccessObject
     */
    private function getWriteDAO( $dbConfigFlag = null ){
        if( empty($dbConfigFlag) ){
            $dbConfigFlag = self::DEFAULT_DBCONFIG_FLAG_WRITE;
        }
        return $this->getDAO( $dbConfigFlag );
    }

    /**
     * 获取指定数据库配置的数据库访问对象
     * @param string $dbConfigFlag        
     * @throws \Exception
     * @return DbAccessObject
     */
    private function getDAO( $dbConfigFlag ){
        $this->lastActiveDAO = $dbConfigFlag;
        
        // if the dao exist, direct return
        if( isset( self::$daoPool[$dbConfigFlag] ) ){
            return self::$daoPool[$dbConfigFlag];
        }
        
        if( $dbConfigFlag == self::DEFAULT_DBCONFIG_FLAG_READ || $dbConfigFlag == self::DEFAULT_DBCONFIG_FLAG_WRITE ) {
            $this->initDefaultDAO( );
            return self::$daoPool[$dbConfigFlag];
        }
        
        if( isset( self::$otherDbConfig[$dbConfigFlag] ) == false ){
            throw new \Exception( "指定标识[{$dbConfigFlag}]的数据库连接配置不存在" );
        }
            
        // init other db config DAO
        self::$daoPool[$dbConfigFlag] = new DbAccessObject( self::$otherDbConfig[$dbConfigFlag] );
        
        return self::$daoPool[$dbConfigFlag];
    }

    /**
     * 获取主数据库的表前缀
     * @return string 表前缀
     */
    public function getMasterTablePrefix( ){
        return self::$defaultDbConfig['DB_TABLE_PREFIX'];
    }

    /**
     * 获取当前是否处于MasterOnly模式
     * @return boolean
     */
    public function isInMasterOnlyMode(){
        return self::$inMasterOnlyMode;
    }
    
    /**
     * 开启MasterOnly模式(不读写分离/不负载均衡)
     */
    public function startMasterOnlyMode(){
    	self::$inMasterOnlyMode = true;
    }
    
    /**
     * 关闭MasterOnly模式(根据配置进行读写分离和负载均衡)
     */
    public function stopMasterOnlyMode(){
    	self::$inMasterOnlyMode = false;
    }
    
    /**
     * 获取当前是否处于事务环境
     * @return boolean
     */
    public function isInTransaction(){
    	return self::$inTransaction;
    }
    
    /**
     * 检查并重新激活DAO的db连接
     * @param string $dbConfigFlag
     *        默认为处理主+从数据库, 如需处理指定数据库,则传入对应的配置名称
     */
    public function reactiveDAO( $dbConfigFlag = null ){
        /**
         * 分两种情况
         *      $dbConfigFlag != null 处理 指定flag对应dao 
         *      $dbConfigFlag == null 处理 default-read + default-write
         */
         // 处理 default-read + default-write
        if(empty($dbConfigFlag)){
            $defaultReadDao = $this->getReadDAO();
            $defaultReadDao->checkAndReconnectDB();
            $defaultWriteDao = $this->getWriteDAO();
            $defaultWriteDao->checkAndReconnectDB();
            return ;
        }
        //处理 指定flag对应dao 
        $dao = $this->getDAO($dbConfigFlag);
        $dao->checkAndReconnectDB();
    }
    
    /**
     * 开始数据库事务
     * @param string $dbConfigFlag
     *        默认为主数据库, 如需切换至第三方, 需要先添加, 然后传入对应的配置名称
     * @return void
     */
    public function startTransaction( $dbConfigFlag = null ){
        // 如果已开启事务，则仅递增事务嵌套深度
        if(self::$inTransaction){
            self::$curTransactionDepth++;            
            return ;
        }
        
        $this->getWriteDAO( $dbConfigFlag )->startTrans( );
        
        // 设置事务状态相关变量
        self::$inTransaction = true;
        self::$curTransactionDepth = 1;
    }

    /**
     * 提交数据库事务
     * @param string $dbConfigFlag
     *        默认为主数据库, 如需切换至第三方, 需要先添加, 然后传入对应的配置名称
     * @return void
     */
    public function commitTransaction( $dbConfigFlag = null ){
        if(!self::$inTransaction){
            return ; 
            // throw new \Exception( '事务提交失败！Message: 没有找到已开启的事务.' );
        }
        
        // 如果没有达到最外层事务, 则仅递减事务嵌套深度
        if(self::$curTransactionDepth > 1){
            self::$curTransactionDepth--;
            return ;
        }
        
        $this->getWriteDAO( $dbConfigFlag )->commit( );
        
        // 重置事务状态相关变量为关闭状态
        self::$inTransaction = false;
        self::$curTransactionDepth = 0;
    }

    /**
     * 回滚数据库事务
     * @param string $dbConfigFlag
     *        默认为主数据库, 如需切换至第三方, 需要先添加, 然后传入对应的配置名称
     * @return void
     */
    public function rollbackTransaction( $dbConfigFlag = null ){
        if(!self::$inTransaction){
            return ; 
            // throw new \Exception( '事务回滚失败！Message: 没有找到已开启的事务.' );
        }
        // 回滚时, 不递减事务嵌套层数
        // 直接进行回滚, 并重置事务状态到关闭状态
        $this->getWriteDAO( $dbConfigFlag )->rollback( );
        
        // 重置事务状态相关变量为关闭状态
        self::$inTransaction = false;
        self::$curTransactionDepth = 0;
    }

    /**
     * 在指定或默认的数据库内执行一条查询sql
     * @param string $sql
     *        如: select * from user where id=:id
     * @param string $args
     *        如: array('id'=>3)
     * @param string $dbConfigFlag
     *        默认为主数据库, 如需切换至第三方, 需要先添加, 然后传入对应的配置名称
     * @return mixed | null
     */
    public function executeQuery( $sql, $args = null, $dbConfigFlag = null ){
        return $this->getReadDAO( $dbConfigFlag )->query( $sql, $args );
    }

    /**
     * 在指定或默认的数据库内执行一条非查询类SQL, 如DELETE/UPDATE/INSERT
     * @param string $sql
     *        如: select * from user where id=:id
     * @param string $args
     *        如: array('id'=>3)
     * @param string $dbConfigFlag
     *        默认为主数据库, 如需切换至第三方, 需要先添加, 然后传入对应的配置名称
     * @return false | integer
     */
    public function executeNonQuery( $sql, $args = null, $dbConfigFlag = null ){
        return $this->getWriteDAO( $dbConfigFlag )->execute( $sql, $args );
    }

    /**
     * 执行单表数据创建
     * @param string $tableName        
     * @param array $data
     *        如: array('userName'=>'tomhans', 'password'=>'css7876ew76f86sa', 'cretetime'=>137545445);
     * @param string $dbConfigFlag
     *        默认为主数据库, 如需切换至第三方, 需要先添加, 然后传入对应的配置名称
     * @return integer | false
     */
    public function executeSingleTableCreate( $tableName, $data, $dbConfigFlag = null ){
        if( is_array( $data ) == false || sizeof( $data ) == 0 ){
            throw new \InvalidArgumentException( '要保存的字段数据不能为空! 请检查' );
        }
        
        $fields = array();
        $params = array();
        $values = array();
        foreach( $data as $k=>$v ){
            $fields[] = $k;
            $params[] = ":{$k}";
            $values[$k] = $v;
        }
        $fields = implode( ',', $fields );
        $params = implode( ',', $params );
            
        $sql = "INSERT INTO {$tableName}({$fields}) VALUES({$params}) ";
        return $this->executeNonQuery( $sql, $values, $dbConfigFlag );
    }

    
    /**
     * 执行单表数据删除
     * @param string $tableName        
     * @param array $filters        
     * @param mixed $orderBy
     *        数组或者字符串, 默认: 不进行排序. 例如: 'id desc', array('name', 'score'=>'desc', 'age'=>'asc')
     * @param integer $limit
     *        默认为null. 不限制数量的话可以输入null
     * @param string $dbConfigFlag
     *        默认为主数据库, 如需切换至第三方, 需要先添加, 然后传入对应的配置名称
     * @return integer | false
     */
    public function executeSingleTableDelete( $tableName, $filters, $orderBy = null, $limit = null, $dbConfigFlag = null ){
        $sql = "DELETE FROM {$tableName} WHERE ";
        
        $filter = $this->buildWhereCondition( $filters );
        $sql .= $filter['sql'];
        
        $sql .= $orderBy === null ? '' : $this->parseOrderByParams( $orderBy ); // 处理排序语句
        $sql .= $limit === null ? '' : ' LIMIT ' . intval( $limit );
        
        return $this->executeNonQuery( $sql, $filter['args'], $dbConfigFlag );
    }

    
    /**
     * 执行单表数据更新
     * @param string $tableName        
     * @param array $updates        
     * @param array $filters        
     * @param integer $limit
     *        默认为null. 不限制数量的话可以输入null
     * @param string $dbConfigFlag
     *        默认为主数据库, 如需切换至第三方, 需要先添加, 然后传入对应的配置名称
     * @return integer | false
     */
    public function executeSingleTableUpdate( $tableName, $updates, $filters, $limit = null, $dbConfigFlag = null ){
        $setSql = '';
        $values = array();
        foreach( $updates as $k=>$v ){
            $values[$k] = $v;
            $setSql = "{$setSql} {$k}=:{$k},";
        }
        $setSql = rtrim( $setSql, ',' );
        
        $filter = $this->buildWhereCondition( $filters );
        $filterSql = $filter['sql'];
        $values = array_merge($values, $filter['args']);
        
        $limit = $limit === null ? '' : 'LIMIT ' . intval( $limit );
        
        $sql = "UPDATE {$tableName} SET {$setSql} WHERE {$filterSql} {$limit}";
        return $this->executeNonQuery( $sql, $values, $dbConfigFlag );
    }

    /**
     * 执行单表数据查询
     * @param string $tableName        
     * @param mixed $fields
     *        字符串或数组. 默认: *
     * @param array $filters
     *        默认: 不进行数据过滤
     * @param mixed $orderBy
     *        数组或者字符串, 默认: 不进行排序. 例如: 'id desc', array('name', 'score'=>'desc', 'age'=>'asc')
     * @param integer $limit
     *        默认为null不限制结果集数量, 需要不限制的时候可以输入null
     * @param integer $offset
     *        默认为0. 如果$limit===null, 则此参数不生效
     * @param string $dbConfigFlag
     *        默认为主数据库, 如需切换至第三方, 需要先添加, 然后传入对应的配置名称
     */
    public function executeSingleTableQuery( $tableName, $fields = '*', $filters = null, $orderBy = null, $limit = null, $offset = 0, $dbConfigFlag = null ){
        $fields = $this->parseQueryFieldsParam( $fields );
        $sql = "SELECT {$fields} FROM {$tableName} WHERE ";
        
        $filter = $this->buildWhereCondition( $filters );
        $sql .= $filter['sql'];
        
        $sql .= $orderBy === null ? '' : $this->parseOrderByParams( $orderBy ); // 处理排序语句
        $sql .= $limit === null ? '' : " LIMIT {$limit} OFFSET {$offset}";
        return $this->executeQuery( $sql, $filter['args'], $dbConfigFlag );
    }

    /**
     * 过滤处理SQL查询的参数
     * @param mixed $v
     *        要过滤的参数, 字符串或者数字
     * @return string | number	过滤处理后的结果
     */
    public function escapeQueryParam( $v ){
        if(is_object($v)){
            throw new \InvalidArgumentException('数据库操作相关参数变量只支持基本数据类型！');
        }
        return var_export($v, true);
    }
    
    /**
     * 将预格式化的sql和参数列表进行格式化为标准sql
     * @param string $sql
     * @param array $args
     * @return string
     */
    private function prepareSQLStatement( $sql, $args ){
        $keys = array();
        $values = array();
        foreach( $args as $k=>$v ) {
            $keys[] = ":{$k}";
            $values[] = $this->escapeQueryParam( $v );
        }
        return str_replace( $keys, $values, $sql );
    }
    
    /**
     * 转换查询字段列表参数
     * @param mixed $fields
     *        用户传入的字段过滤参数, 可以是数组或者字符串
     * @throws \InvalidArgumentException
     * @return string 拼接好的字段列表字符串
     */
    protected function parseQueryFieldsParam( $fields ){
        if( is_string( $fields ) && strlen( $fields ) > 0 ){
            return $fields;
        }
        if( is_array( $fields ) && sizeof( $fields ) > 0 ) {
            $result = '';
            foreach( $fields as $v ){
                $result = "{$result}{$v},";
            }
            $result = rtrim( $result, ',' );
            return $result;
        }
        throw new \InvalidArgumentException( '字段限定参数错误! 必须为String或者Array, 且不能为空' );
    }
    
    /**
     * 转换查询结果排序参数
     * @param mixed $orderBy
     *        用户传入的排序参数, 可以是数组或者字符串.例如: 'id desc', array('name', 'score'=>'desc', 'age'=>'asc')
     * @throws \InvalidArgumentException
     * @return string 拼接好的Order By语句
     */
    protected function parseOrderByParams( $orderBy ){
        if( is_string( $orderBy ) && strlen( $orderBy ) > 0 ){
            return " ORDER BY {$orderBy} ";
        }
        if( is_array( $orderBy ) && sizeof( $orderBy ) > 0 ) {
            $result = '';
            foreach( $orderBy as $k=>$v ) {
                if( is_numeric( $k ) ){
                    // 数字索引, 则直接默认升序排列
                    $result = "{$result} {$v} ASC,";
                    continue;
                }
                // 字符串索引, 则使用k作为排序列名, v作为排序类型
                $result = "{$result} {$k} {$v},";
            }
            $result = rtrim( $result, ',' );
            return " ORDER BY {$result} ";
        }
        throw new \InvalidArgumentException( '结果排序参数错误! 必须为String或者Array, 且不能为空' );
    }
    
    /**
     * 构建SQL语句中Where部分条件
     * @param array $filters
     *  key为列名, value为条件值, 重名key使用尾部空格扩展进行区分. 支持表达式查询参考参数如:<pre>
     *  $where = array(
     *        　'schoolId'=>9,
     *        　'year'=>2014,
     *        　'month'=>10,
     *        　'type'=>'student',
     *        　'class_level'=>array('in', '4,5,6'),
     *        　'age'=>array('between', '12,16'),
     *        　'or',
     *        　'age '=>array('elt', 28),
     *        　'age '=>array('gt ', 35),
     *        　'age '=>array('not in', '3,6,9'),
     *        　'and',
     *        　'name'=>array('exp', "LIKE '孙%' "),
     *  );</pre>
     * @throws \InvalidArgumentException
     * @return array
     */
    public function buildWhereCondition( $filters ){
        if( $filters === null || ( is_array( $filters ) && sizeof( $filters ) == 0 ) ) {
            return array(
                'sql' => ' 1=1 ',
                'args' => null,
            );
        }
        
        if( is_array( $filters ) == false ){
            throw new \InvalidArgumentException( '仅支持通过Array类型参数进行SQL构建! 请检查' );
        }
        
        $sql = '';          // 最终的用于WHERE查询的SQL语句
        $args = [];         // 用于WHERE查询的SQL语句命名参数集合
        $argSN = 0;         // 记录当前命名参数的序号，防止出现参数重名
        
        $isNeedLogicOper = false;
        foreach( $filters as $k=>$v ) {
            $argSN++;
            $argName = "v{$argSN}";
            
            
            // 逻辑符号处理
            if( is_numeric( $k ) ) {
                $sql = $sql . ' ' . strtoupper( $v ) . ' ';
                $isNeedLogicOper = false;
                continue;
            }
            $logicOper = $isNeedLogicOper == true ? 'AND' : '';
            
            $k = rtrim( $k, ' ' );  // 去除列名中的占位空格符号
            $conditionStr = $k;     // 拼装本次条件的列名部分
            if( is_array( $v ) ) {
                $expType = strtoupper( str_replace( ' ', '', $v[0] ) ); // 空格全部替换, 并大写
                switch( $expType ){
                    case 'EQ':
                        $args[$argName] = $v[1];
                        $conditionStr .= "=:{$argName}";
                        break;
                    case 'NEQ':
                        $args[$argName] = $v[1];
                        $conditionStr .= "<>:{$argName}";
                        break;
                    case 'GT':
                        $args[$argName] = $v[1];
                        $conditionStr .= ">:{$argName}";
                        break;
                    case 'EGT':
                        $args[$argName] = $v[1];
                        $conditionStr .= ">=:{$argName}";
                        break;
                    case 'LT':
                        $args[$argName] = $v[1];
                        $conditionStr .= "<:{$argName}";
                        break;
                    case 'ELT':
                        $args[$argName] = $v[1];
                        $conditionStr .= "<=:{$argName}";
                        break;
                    case 'LIKE':
                        $args[$argName] = $v[1];
                        $conditionStr .= " LIKE :{$argName}";
                        break;
                    case 'BETWEEN':
                        // 字符串和数组参数的兼容处理
                        $rangeArgs = is_array( $v[1] ) ? $v[1] : explode( ',', $v[1] );
                        $args["{$argName}_1"] = $rangeArgs[0];
                        $args["{$argName}_2"] = $rangeArgs[1];
                        $conditionStr .= " BETWEEN :{$argName}_1 AND :{$argName}_2";
                        break;
                    case 'NOTBETWEEN':
                        // 字符串和数组参数的兼容处理
                        $rangeArgs = is_array( $v[1] ) ? $v[1] : explode( ',', $v[1] );
                        $args["{$argName}_1"] = $rangeArgs[0];
                        $args["{$argName}_2"] = $rangeArgs[1];
                        $conditionStr .= " NOT BETWEEN :{$argName}_1 AND :{$argName}_2";
                        break;
                    case 'IN':
                        // 字符串和数组参数的兼容处理
                        $caseValueArray = is_array( $v[1] ) ? $v[1] : explode( ',', $v[1] );
                        $caseValueStr = '';
                        foreach( $caseValueArray as $key=>$caseValue ){
                            $caseValueStr .= ":{$argName}_{$key},";
                            $args["{$argName}_{$key}"] = $caseValue;
                        }
                        $caseValueStr = rtrim( $caseValueStr, ',' );
                        $conditionStr .= ' IN( ' . $caseValueStr . ')';
                        break;
                    case 'NOTIN':
                        // 字符串和数组参数的兼容处理
                        $caseValueArray = is_array( $v[1] ) ? $v[1] : explode( ',', $v[1] );
                        $caseValueStr = '';
                        foreach( $caseValueArray as $key=>$caseValue ){
                            $caseValueStr .= ":{$argName}_{$key},";
                            $args["{$argName}_{$key}"] = $caseValue;
                        }
                        $caseValueStr = rtrim( $caseValueStr, ',' );
                        $conditionStr .= ' NOT IN( ' . $caseValueStr . ')';
                        break;
                    case 'EXP': // 'exp'=>'in (3,5,8) AND a%2=0'
                        $conditionStr .= ' ' . $v[1];
                        break;
                    default:
                        throw new \InvalidArgumentException( "指定的条件查询表达式类型 {$expType} 不支持" );
                }
            }else if( is_object( $v ) == false ) {
                $conditionStr .= "=:{$argName}";
                $args[$argName] = $v;
            }else{
                throw new \InvalidArgumentException( '条件查询参数类型无法识别, 请仅使用数组以及简单类型' );
            }
            
            $sql = "{$sql} {$logicOper} {$conditionStr} ";
            $isNeedLogicOper = true;
        }
        
        return array(
            'sql' => $sql,
            'args' => $args,
        );
    }

    /**
     * 获取上一次查询触发的数据库错误信息
     * @return NULL || string
     */
    public function getErrorInfo( ){
        return $this->getDAO( $this->lastActiveDAO )->getDbError( );
    }
    
    /**
     * 返回最后执行的sql语句
     * @return string
     */
    public function getLastSql( ){
        return $this->getDAO( $this->lastActiveDAO )->getLastSql( );
    }

    /**
     * 返回最后执行的sql语句参数
     * @return array
     */
    public function getLastSqlParams( ){
        return $this->getDAO( $this->lastActiveDAO )->getLastSqlParams( );
    }
    
    /**
     * 获取上次插入语句生成的自增ID
     * @return NULL | number
     */
    public function getLastInsertId( ){
        $id = $this->getDAO( $this->lastActiveDAO )->getLastInsID( );
        return $id == 0 ? null : $id;
    }
    
    /**
     * 重置指定数组的索引为元素中的指定值, 一般用于将数据库查询获取的多条记录的数字索引改为记录主键格式
     * @param array $dataArray        
     * @param string $newIndexSource
     *        必须位于第二维, 如果是多个作为组合索引, 则传入数组即可, 如: array('id', 'type')
     * @param string $delimiter        
     * @param bool $unsetIndexKey        
     * @return array
     */
    public function resetArrayIndex( $dataArray, $newIndexSource, $delimiter = ':', $unsetIndexKey = false ){
        $resultArray = array();
        foreach( $dataArray as $k=>$v ) {
            // string格式的单key索引, 则直接赋值, 继续下一个
            if( is_string( $newIndexSource ) ) {
                $resultArray[$v[$newIndexSource]] = $v;
                if( $unsetIndexKey ){
                    unset( $v[$newIndexSource] );
                }
                continue;
            }
            // 数组格式多key组合索引处理
            $k = '';
            foreach( $newIndexSource as $index ) {
                $k .= "{$v[$index]}{$delimiter}";
                if( $unsetIndexKey ){
                    unset( $v[$index] );
                }
            }
            $k = rtrim( $k, $delimiter );
            $resultArray[$k] = $v;
        }
        return $resultArray;
    }

    public static function aliasName(){
    	return 'DBHelper';
    }
}

/**
 * 数据库访问对象
 * @author ououcool(ouyangjiaohui@gmail.com)
 */
use PDO;

class DbAccessObject {
    private $_config = null;
    private $_conn = null;
    private $_stmt = null;
    private $_connected = false;
    private $_lastSql = null;
    private $_lastSqlParams = null;
    private $_numRows = null;
    private $_lastInsertId = null;
    private $_isInTransaction = false;
    
    /**
     * Constructor
     * @param $_config  数据连接数组
     */
    public function __construct($_config) {
    	$this->_config = $_config;
    }
	
    /**
     * 开启到数据库的连接
     * 
     * @param boolean $forceReconnect
     */
    public function connect($forceReconnect=false){
        if($forceReconnect){
            $this->_connected = false;
        }
        
        if($this->_connected == true){
            return;
        }
        
		$this->_conn = new DBConnection($this->_config);
		
        $dbCharset = isset( $this->_config['CHARSET'] ) ? $this->_config['CHARSET'] : 'utf8'; // 默认使用UTF8编码
        $this->_conn->exec("SET NAMES '{$dbCharset}' ");    //设置字符集
        $this->_conn->exec('SET sql_mode="" ');             //设置 sql_mode
        
        $this->_connected = true;
    }
    
    /**
     * 为PDOStatement对象绑定参数
     * @param PDOStatement $stmt
     * @param $params 要绑定的参数数组
     */
    private function _bindParams(\PDOStatement &$stmt, $params) {
        foreach ($params as $field => $val){
            $stmt->bindValue(":{$field}", $val);
        }
    }
    
    /**
     * 执行查询语句
     * @param string $sql
     * @param array $args
     * @return array 查询结果
     */
    public function query($sql, $args=null){
            if( 0 === stripos( $sql, 'call' ) ) {// 存储过程查询支持
                $this->close( );
            }
            
            $this->connect();
            
            $this->_stmt = $this->_conn->prepare($sql);
            
            if($args!=null && sizeof($args)>0){
                $this->_bindParams($this->_stmt, $args);
            }

            $this->_lastSql = $sql;
            $this->_lastSqlParams = $args;
            
            $this->_stmt->execute();
            
            $this->_numRows = $this->_stmt->rowCount();
            
            $results = $this->_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->_stmt->closeCursor();
            
            return $results;
    }

    /**
     * 执行非查询语句
     * @param string $sql
     * @param array $args
     * @return integer 受影响行数
     */
    public function execute( $sql, $args=null ){
        $this->connect( );
        
        $this->_stmt = $this->_conn->prepare($sql);
        
        if($args!=null && sizeof($args)>0){
            $this->_bindParams($this->_stmt, $args);
        }
        
        $this->_lastSql = $sql;
        $this->_lastSqlParams = $args;
        
        $this->_stmt->execute();
        
        $this->_numRows = $this->_stmt->rowCount();
        
        $this->_lastInsertId = $this->_conn->lastInsertId();
        
        $this->_stmt->closeCursor();
        
        return $this->_numRows;
    }

    /**
     * 检测db连接是否正常, 如有需要, 则进行重连
     */
    public function checkAndReconnectDB(){
        $oldReportLevel = error_reporting(E_ERROR);        
        try {
            $this->execute("DO 1");         // 理论性能更高 ^_^
            // $this->query("SELECT 1");    // 更直观, 但是略浪费
        } catch (\PDOException $ex) {
            $code = $ex->getCode();
            if( $code == 'HY000' ){
                $this->connect($force=true);
            }
        }
        error_reporting($oldReportLevel);
    }
    
    /**
     * 启动事务
     * @return void
     */
    public function startTrans( ){
        $this->connect( );
        
        if( $this->_isInTransaction==true ){
            return ;    //事务已开启
        }
        
    	$result = $this->_conn->beginTransaction();
    	$this->_isInTransaction = true;    //设置事务标记
    	if( !$result ){
    	    throw new \Exception( '开启事务时出现了错误. Message:' . $this->getDbError() );
    	}
    }

    /**
     * 提交事务
     * @return void
     */
    public function commit( ){
        if( $this->_isInTransaction==true ) {
        	$result = $this->_conn->commit();
            $this->_isInTransaction = false;
            if( !$result ){
                throw new \Exception( '提交事务时出现了错误. Message:' . $this->getDbError() );
            }
        }
    }

    /**
     * 回滚事务
     * @return void
     */
    public function rollback( ){
        if( $this->_isInTransaction==true ) {
        	$result = $this->_conn->rollBack();
            $this->_isInTransaction = false;
            if( !$result ){
                throw new \Exception( '回滚事务时出现了错误. Message:' . $this->getDbError() );
            }
        }
    }
    
    /**
     * 关闭数据库
     * @return void
     */
    public function close( ){
        $this->_conn = null;
        $this->_connected = false;
    }
    
    /**
     * 返回最后插入的ID
     * @return string
     */
    public function getLastInsID( ){
        return $this->_lastInsertId;
    }

    /**
     * 返回最后执行的sql语句
     * @return string
     */
    public function getLastSql( ){
        return $this->_lastSql;
    }
    
    /**
     * 返回最后执行的sql语句参数
     * @return array
     */
    public function getLastSqlParams( ){
        return $this->_lastSqlParams;
    }
    
    /**
     * 返回数据库的错误信息
     * @return string
     */
    public function getDbError( ){
        $dbError = $this->_conn->errorInfo();
        if($dbError[1] == null && $dbError[2] == null){
            $dbError = $this->_stmt->errorInfo();
        }
        if($dbError[1] == null && $dbError[2] == null){
            return null;
        }
        return "SQLSTATE[{$dbError[0]}]: {$dbError[1]} {$dbError[2]}";
    }
}

/**
 * 数据库连接类
 * 
 * @author ououcool(ouyangjiaohui@gmail.com)
 */
class DBConnection extends PDO {

	private $_dsn = null;
	private $_attr = array();

	public function __construct($config) {
		$this->_setDSN($config);
		parent::__construct($this->_dsn, $config['USER'], $config['PWD'], $this->_attr);

		$this->setAttributes(array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	}

	private function _setDSN($config) {
		$host = isset($config['HOST']) ? $config['HOST'] : '127.0.0.1';
		$port = isset($config['PORT']) ? $config['PORT'] : '3306';
		$this->_dsn = "mysql:host={$host};dbname={$config['NAME']};port={$port}";
		
		$persistent = isset($config['PERSIST']) ? $config['PERSIST'] : false;
		if ($persistent){
		    $this->_attr[PDO::ATTR_PERSISTENT] = true;
		}
	}
	
	public function setAttributes($settings) {
		foreach ($settings as $key => $val) {
			$this->setAttribute($key, $val);
		}
	}
	
}
