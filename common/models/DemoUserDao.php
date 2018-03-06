<?php
namespace Model;

use LightApi\BaseModel;

/**
 * 示例模型
 * 
 * @author ououcool(ouyangjiaohui@gmail.com)
 */
class DemoUserDao extends BaseModel
{

    const TABLE_NAME = "demo_user";

    const PK_COLUMN_NAME = "user_id";

    public function getPkName()
    {
        return self::PK_COLUMN_NAME;
    }

    public function getTableName()
    {
        return $this->tablePrefix . self::TABLE_NAME;
    }

    public function init()
    {
        $sql = "
        DROP TABLE IF EXISTS `{$this->tablePrefix}demo_user`;
            
        CREATE TABLE `{$this->tablePrefix}demo_user` (
          `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `username` varchar(32) NOT NULL,
          `password` varchar(32) NOT NULL,
          PRIMARY KEY (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            
        INSERT INTO `{$this->tablePrefix}demo_user`(username, password) VALUES
        ('admin', '123123'),('tom', 'tomtom'),('lucy', '123123'), 
        ('lily', '123456'),('meimei', 'mmmmmm'),('hans', '123456'), 
        ('jerry', 'lovetom'),('anan', '123123'),('mike', 'nike');
        ";
        
        $this->executeNonQuery($sql);
        
        return 'DB INIT OK.';
    }

    public function getAllUser()
    {
        $data = $this->findAll([]);
        return $data;
    }

    public function getOldestUser($params = null)
    {
        $filters = [];
        $fields = '*';
        $orderBy = 'user_id ASC';
        $data = $this->findByProperties($filters, $fields, $orderBy);
        return $data;
    }

    public function getNewestUser($params = null)
    {
        $fileds = 'user_id, username';
        $orderBy = 'user_id DESC';
        $limit = 1;
        $data = $this->executeSingleTableQuery("{$this->tablePrefix}demo_user", $fileds, null, $orderBy, $limit);
        return $data;
    }
}