<?php

namespace app\index\controller;

use app\common\library\MySQL;
use app\common\library\Redis;
use think\Controller;

class Login extends Controller
{
    /**
     * HTTP / WebSocket 服务器端
     * @var
     */
    private $server;

    /**
     * MySQL 客户端
     * @var \Swoole\Coroutine\MySQL
     */
    private $mysql;

    /**
     * Redis 客户端
     * @var \Swoole\Coroutine\Redis
     */
    private $redis;

    /**
     * 初始化
     */
    protected function initialize()
    {
        $this->server = $_SERVER['swoole']['server'];
        $this->mysql = MySQL::getInstance()->mysql;
        $this->redis = Redis::getInstance()->redis;
    }

    /**
     * 发送验证码
     */
    public function sendAuthCode()
    {
        config('default_return_type', 'json');

//        var_dump(request()->isAjax());
//        var_dump($_SERVER);
//        var_dump($_POST);
//        var_dump(request()->server());
//        var_dump(request()->post());

        $phoneNum = trim($_POST['phone_num']);
        $result = preg_match('/^1\d{10}$/', $phoneNum);
        if (!$result) {
            $this->error('手机号格式错误');
        }

        // 设置验证码
        $authCode = mt_rand(1000, 9999);
        $key = config('redis.key.auth_code') . $phoneNum;
        $this->redis->set($key, $authCode, 300);
        
        // 发送验证码
        $taskData = [
            'method' => 'sendAuthCode',
            'data' => [
                'phone_num' => $phoneNum,
                'auth_code' => $authCode,
            ],
        ];
        // 投递异步任务
        $this->server->task($taskData);

        $data = [
            'auth_code' => $authCode,
        ];
        $this->success('成功', null, $data);
    }

    /**
     * 登录
     */
    public function login()
    {
        config('default_return_type', 'json');

        $phoneNum = trim($_POST['phone_num']);
        $authCode = trim($_POST['auth_code']);
        $result = preg_match('/^1\d{10}$/', $phoneNum);
        if (!$result) {
            $this->error('手机号格式错误');
        }
        $result = preg_match('/^\d{4}$/', $authCode);
        if (!$result) {
            $this->error('验证码格式错误');
        }

        // 获取验证码
        $key = config('redis.key.auth_code') . $phoneNum;
        $value = $this->redis->get($key);
        if (!$value) {
            $this->error('请重新获取验证码');
        }
        if ($value != $authCode) {
            $this->error('验证码错误');
        }
        
        // 删除验证码
        $this->redis->del($key);

        // 设置 Token
        $token = md5($phoneNum . time());
        $expire = 60 * 5;
        $key = config('redis.key.token') . $phoneNum;
        $this->redis->set($key, $token, $expire);
        
        // 设置 Cookie
        $expireTime = time() + $expire;
        $cookie = [
            [
                'key' => 'phone_num',
                'value' => $phoneNum,
                'expire' => $expireTime,
            ],
            [
                'key' => 'token',
                'value' => $token,
                'expire' => $expireTime,
            ],
        ];

        $url = $_SERVER['HTTP_REFERER'] ?? '/static/index/index.html';
        $data = [
            'cookie' => $cookie,
        ];
        $this->success('成功', $url, $data);
    }
}
