<?php

use Swoole\Timer;
use function Swoole\Coroutine\run;

/**
 * 服务监控
 */
class Monitor
{
    /**
     * WebSocket 端口号
     */
    const WEBSOCKET_PORT = 9504;

    /**
     * 警报
     * @var bool
     */
    private static $alert = false;

    /**
     * 检测端口状态
     */
    public static function checkPort()
    {
        $command = 'netstat -an 2>/dev/null | grep ' . self::WEBSOCKET_PORT . ' | grep LISTEN | wc -l';
        $result = shell_exec($command);

        $date = date('Y-m-d H:i:s');
        $message = $result == 1 ? 'success' : 'error';
        echo $date . ' ' . $message . PHP_EOL;

        if (!self::$alert && $result != 1) {
            self::$alert = true;
            // 发送短信、邮件
        }
    }
}

run(function () {
    // 设置一个间隔时间定时器
    Timer::tick(1000, 'Monitor::checkPort');
});