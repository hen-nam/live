<?php

namespace app\common\library;

class Task
{
    /**
     * Redis 客户端
     * @var
     */
    private $redis;

    /**
     * 构造
     * @param $server
     */
    public function __constuct($server)
    {
        $this->server = $server;
        $this->redis = Redis::getInstance()->redis;
    }

    /**
     * 发送验证码
     * @param $data
     */
    public function sendAuthCode($data)
    {
        $dataJson = json_encode($data);
        echo '异步任务：发送验证码 ' . $dataJson . PHP_EOL;

        $key = config('redis.key.auth_code') . $data['phone_num'];
        // 设置验证码
        $this->redis->set($key, $data['auth_code'], 300);
    }

    /**
     * 推送消息
     * @param $data
     */
    public function pushMessage($data)
    {
        $dataJson = json_encode($data);
        echo '异步任务：推送消息 ' . $dataJson . PHP_EOL;

        // 遍历服务器当前所有的连接
        foreach ($this->server->connections as $fd) {
            // 检查连接是否为有效的 WebSocket 客户端连接
            if ($this->server->isEstablished($fd)) {
                // 向 WebSocket 客户端连接推送数据
                $this->server->push($fd, $dataJson);
            }
        }
    }
}