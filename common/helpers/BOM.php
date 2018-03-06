<?php
//程序执行保护密码
//如果该文件部署在服务器上，考虑到安全风险建议增加密码执行保护限制非法用户访问

define('DEFAULT_SCANPATH', dirname(__DIR__));   //程序默认扫描的文件路径, 当前目录的上一级下所有文件
define('EXECUTE_PASSWORD', 'hello_world');      //将 hello_world 修改为你认为安全的密码，如： 88688等

if(defined('EXECUTE_PASSWORD')==false || EXECUTE_PASSWORD=='hello_world')
    die('请在程序文件[<font color=red>'.__FILE__.'</font>]中设定程序执行保护密码！且不可以为默认值 <font color=red>hello_world</font> !!!');

$urlPrefix = "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}:{$_SERVER['SERVER_PORT']}{$_SERVER['SCRIPT_NAME']}";
if(isset($_REQUEST['passwd'])==false || empty($_REQUEST['passwd'])==true)
    die("访问当前程序必须输入程序执行保护密码！方法为: 在网址中输入{$urlPrefix}?passwd=<font color=red>您设定的程序执行保护密码</font>");

if($_REQUEST['passwd'] != EXECUTE_PASSWORD)
    die("<font color=red>您输入的程序执行保护密码错误！请重新输入!!!</font>");

$urlPrefix = "{$urlPrefix}?passwd={$_REQUEST['passwd']}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>文件夹BOM头检测和清除工具</title>
</head>
<body style="font:14px/1.5 'Microsoft Yahei',tahoma,arial,\5b8b\4f53;">
<pre>
/**
 * 文件夹BOM头检测和清除工具
 * 用法：
 *  ${HOST}/path/to/BOM.php
 *  可选参数:
 *      path        要检测并处理BOM的文件夹路径
 *      showOK      显示没有BOM头的文件以及处理信息, 默认为false，只要设定了该参数，则不论其值如何， 则均会显示
 *      remove      是否自动除去文件的BOM头，默认为false，只要设定了该参数，则不论其值如何， 则均会自动除去BOM头
 * 参考用法：
 *      <a href="<?php echo $urlPrefix; ?>"><?php echo $urlPrefix; ?></a>
 *      <a href="<?php echo $urlPrefix; ?>&remove=true"><?php echo $urlPrefix; ?>&remove=true</a>
 *      <a href="<?php echo $urlPrefix; ?>&showOk=true"><?php echo $urlPrefix; ?>&showOk=true</a>
 *      <a href="<?php echo $urlPrefix; ?>&remove=true&showOk=true"><?php echo $urlPrefix; ?>&remove=true&showOk=true</a>
 *      <?php echo $urlPrefix; ?>&remove=true&showOk=true&path=/home/www/
 * @author 刘靖(lewkinglove@gmail.com)
 */
<?php
function scanAndCheck($basedir, $autoRemove=false, $showOK=false)
{
    if ($dh = opendir($basedir)) {
        while (($file = readdir($dh)) !== false) {
            if ($file == '.' || $file=='..')
                continue;
            
            if (is_dir("{$basedir}/{$file}")==false){
                $result = checkBOM("$basedir/$file", $autoRemove);
                if($result==0 && $showOK==false)
                    continue;
                
                $result = $result==0 ? '<font color=green>BOM Not Found.</font>' : ( $result==1 ? '<font color=red>BOM found.</font>' : '<font color=red>BOM found, automatically removed.</font>');
                echo "File: {$basedir}/{$file} \t{$result}\r\n";
                continue;
            }
            
            //递归处理子文件夹
            scanAndCheck("{$basedir}/{$file}", $autoRemove, $showOK);
        }
        closedir($dh);
    }
}

function checkBOM($filename, $autoRemove=false)
{
    $contents = file_get_contents($filename);
    $charset[1] = substr($contents, 0, 1);
    $charset[2] = substr($contents, 1, 1);
    $charset[3] = substr($contents, 2, 1);
    if (ord($charset[1]) == 239 && ord($charset[2]) == 187 && ord($charset[3]) == 191) {
        if ($autoRemove == true) {
            rewriteWithOutBOM($filename, substr($contents, 3));
            return 2;
        }
        return 1;
    }
    return 0;
}

function rewriteWithOutBOM($filename, $data)
{
    $filenum = fopen($filename, "w");
    flock($filenum, LOCK_EX);
    fwrite($filenum, $data);
    fclose($filenum);
}

//环境初始化
set_time_limit(0);
ignore_user_abort(true);
error_reporting(E_ALL);
ini_set('display_errors', 'on');
date_default_timezone_set('Asia/Shanghai'); //时区配置

//默认处理当前文件夹上级目录下所有文件
$scanPath = DEFAULT_SCANPATH;

$showOK = isset($_REQUEST['showOk']) ? true : false;
$autoRemove = isset($_REQUEST['remove']) ? true : false;
$scanPath = isset($_REQUEST['path']) ? $_REQUEST['path'] : $scanPath;

echo "------------------------------------------------------------------------------------------\r\n";
echo "Scan File Path: {$scanPath}\r\n";
echo "BOM Auto Remove: ".($autoRemove?'开启':'关闭')."\t\tShow Ok File: ".($showOK?'开启':'关闭')."\r\n";
echo "------------------------------------------------------------------------------------------\r\n";

scanAndCheck($scanPath, $autoRemove, $showOK);
echo "------------------------------------------------------------------------------------------\r\n";
echo "All The File Has Been Processed.\r\n";