<?php
namespace LightApi;

/**
 * CliApp基类
 *
 * @author ououcool(ouyangjiaohui@gmail.com)
 */
class BaseCliApp
{

    const CMD_STOP = 'stop';

    const CMD_STOP_ALL = 'stop_all';

    public function __construct()
    {
        set_time_limit(0);
    }

    /**
     * 检测系统中是否存在指定CliApp
     *
     * @param string $cliApp            
     * @return boolean
     */
    private function isCliAppExist($cliApp)
    {
        $call = explode('.', $cliApp);
        if ( sizeof($call) < 3 ){
            return false;
        }
        
        $requestMethod = $call[2]; // 请求方法名
        $requestClass = CLIAPP_DIR_NAME . "\\{$call[0]}\\{$call[1]}"; // 请求类名
        
        $result = ClassLoader::import($requestClass);
        if ( $result == false ){
            return false;
        }
        
        return (class_exists($requestClass, false) && method_exists($requestClass, $requestMethod));
    }

    /**
     * 当前程序是否该退出了
     *
     * @return boolean
     */
    protected function isTimeToExit()
    {
        $flgAllPath = RUNTIME_PATH . '/Exit[ALL]Now';
        $flgSelfPath = RUNTIME_PATH . '/Exit[' . REQUEST_CLIAPP . ']Now';
        return (file_exists($flgAllPath) || file_exists($flgSelfPath));
    }

    /**
     * 格式化输出消息，添加时间前缀和换行后缀
     *
     * @param string $msg
     *            要输出的消息文本
     * @param bool $addTime
     *            是否添加前缀时间信息
     * @param bool $addNL
     *            是否添加后缀换行符
     * @return string
     */
    protected static function formatMsg($msg, $addTime = true, $addNL = true)
    {
        $prefix = $addTime ? date("Y-m-d H:i:s ") : '';
        $surffix = $addNL ? "\r\n" : '';
        return "{$prefix}{$msg}{$surffix}";
    }

    /**
     * 停止正在运行中的CliApp
     *
     * @param string $stopType            
     * @param string $cliApp            
     * @return string
     */
    private static function stopRunningCliApp($stopType, $cliApp = null)
    {
        $isStopAll = $stopType == self::CMD_STOP_ALL;
        if ( ! $isStopAll ){
            if ( empty($cliApp) ){
                return self::formatMsg("Commond usage is not correct. eg. php cli.php stop Example.Demo.run");
            }
            if ( ! self::isCliAppExist($cliApp) ){
                return self::formatMsg("The cli app {$cliApp} you specified is not exist.");
            }
        }
        
        // 命令行检查标记
        $cmdChkFlag = $isStopAll ? $_SERVER['argv'][0] : $cliApp;
        ;
        // 退出信号写入的文件路径
        $signalChkFilePath = RUNTIME_PATH . ($isStopAll ? '/Exit[ALL]Now' : "/Exit[{$cliApp}]Now");
        if ( ! file_exists($signalChkFilePath) ){
            file_put_contents($signalChkFilePath, '');
        }
        
        $cmd = "tasklist -V | findstr \"{$cmdChkFlag}\"";
        $cmd = IS_WIN ? $cmd : "ps aux | grep \"{$cmdChkFlag}\"";
        while ( true ){
            $outputArray = null;
            exec($cmd, $outputArray);
            if ( empty($outputArray) ){
                break;
            }
            
            // 对不是本次停止操作目标进程的进程信息进行过滤
            foreach ( $outputArray as $lineId => $line ){
                // 移除 cli.php 及 其之前的部分
                $line = trim(ltrim(substr($line, strpos($line, $_SERVER['argv'][0])), $_SERVER['argv'][0]));
                // 如果进程信息内容为空, 则丢弃
                // 如果以 stop 或者 stop_all 开头, 则丢弃
                // 如果该进程标识不是本系统中已存在的CliApp，则丢弃
                $notTargetProc = empty($line) || strpos($line, self::CMD_STOP) === 0;
                $notTargetProc = $notTargetProc || strpos($line, self::CMD_STOP_ALL) === 0;
                $notTargetProc = $notTargetProc || self::isCliAppExist(explode(' ', $line)[0]) == false;
                if ( $notTargetProc ){
                    unset($outputArray[$lineId]);
                    continue;
                }
                $outputArray[$lineId] = $line;
            }
            
            // 如果还有未退出的进程, 则
            if ( sizeof($outputArray) > 0 ){
                echo self::formatMsg("Total unexit process: " . sizeof($outputArray) . "\t[" . implode(",", $outputArray) . "]");
                sleep(1); // 暂停一秒, 继续检测
                continue; // 继续执行下个while循环
            }
            break; // 执行到此处说明没有还在执行中的task了
        }
        unlink($signalChkFilePath);
        return self::formatMsg("All the specified cli app are successfuly exited.");
    }

    /**
     * 结束并给出响应结果
     *
     * @param string $response
     *            响应的数据
     * @param number $status
     *            请求处理状态
     * @param string $message
     *            请求处理消息
     * @return array
     */
    public function endResponse($response = null, $status = 1, $message = 'success')
    {
        $response = array(
            'response' => $response,
            'status' => $status,
            'message' => $message
        );
        return $response;
    }

    /**
     * 调用其他CliApp
     *
     * @param string $call
     *            要调用的API名称
     * @param string $args
     *            API调用参数
     */
    public function invokeCliApp($call, $args = [])
    {
        return self::invokeRequestCliApp($call, $args);
    }
    
    // 调用请求的CliApp处理对象
    public static function invokeRequestCliApp($call, $args)
    {
        if ( $call == self::CMD_STOP || $call == self::CMD_STOP_ALL ){
            return self::stopRunningCliApp($call, $args);
        }
        
        // 请求分发前预处理
        $call = explode('.', $call);
        if ( sizeof($call) != 3 ){
            $response = array(
                'response' => null,
                'status' => '-7',
                'message' => 'illegal request. call cli app format is not correct.'
            );
            return $response;
        }
        
        $requestMethod = $call[2]; // 请求方法名
        $requestClass = CLIAPP_DIR_NAME . "\\{$call[0]}\\{$call[1]}"; // 请求类名
        
        ClassLoader::import($requestClass);
        if ( class_exists($requestClass, false) == false || method_exists($requestClass, $requestMethod) == false ){
            $response = array(
                'response' => null,
                'status' => '-8',
                'message' => 'illegal request. the cli app you called is not exists.'
            );
            return $response;
        }
        
        // 初始化cli app类，并调用对应cli app方法
        $requestClassInstance = new $requestClass();
        if ( is_string($args) ){
            $args = json_decode($args, true);
            if ( $args === null ){
                $response = array(
                    'response' => null,
                    'status' => '-9',
                    'message' => 'illegal request. the cli app arguments is not a good json string. '
                );
                return $response;
            }
        }
        $response = $requestClassInstance->$requestMethod($args);
        return $response;
    }
}