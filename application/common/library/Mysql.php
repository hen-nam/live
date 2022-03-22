<?php

namespace app\common\library;

class MySQL
{
    /**
     * 实例
     * @var
     */
    private static $instance;

    /**
     * Mysql 客户端
     * @var \Swoole\Coroutine\MySQL
     */
    public $mysql;

    /**
     * 获取实例
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            // 创建 Redis 客户端
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // 创建 Redis 客户端
        $this->mysql = new \Swoole\Coroutine\MySQL();
        $config = config('mysql.');
        // 连接 Redis 服务器
        $this->mysql->connect($config);
    }

    private function __clone()
    {

    }
}