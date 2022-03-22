<?php

namespace app\common\library;

class Redis
{
    /**
     * 实例
     * @var
     */
    private static $instance;

    /**
     * Redis 客户端
     * @var \Swoole\Coroutine\Redis
     */
    public $redis;

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
        $this->redis = new \Swoole\Coroutine\Redis();
        $config = config('redis.');
        // 连接 Redis 服务器
        $this->redis->connect($config['host'], $config['port']);
    }

    private function __clone()
    {

    }
}