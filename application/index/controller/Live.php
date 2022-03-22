<?php

namespace app\index\controller;

use app\common\library\MySQL;
use app\common\library\Redis;
use think\Controller;

class Live extends Controller
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
     * 校验 Token
     */
    private function checkToken()
    {
        // 校验 Token
        $phoneNum = $_COOKIE['phone_num'] ?? null;
        $token = $_COOKIE['token'] ?? null;
        if (!$phoneNum || !$token) {
            $this->error('请重新登录', '/static/index/login.html');
        }
        $key = config('redis.key.token') . $phoneNum;
        $value = $this->redis->get($key);
        if (!$value || $value != $token) {
            $this->error('请重新登录', '/static/index/login.html');
        }
    }

    /**
     * 获取赛况
     */
    public function getOuts()
    {
        config('default_return_type', 'json');

        $gameId = intval($_GET['game_id']);

        // 获取赛况数据
        $sql = "SELECT * FROM `live_outs` WHERE `game_id` = '{$gameId}'";
        $outs = $this->mysql->query($sql);

        // 获取球队数据
        if ($outs) {
            $teamIds = array_column($outs, 'team_id');
            $sql = "SELECT `id`, `name`, `image` FROM `live_team` WHERE `id` IN ('" . implode("', '", $teamIds) . "')";
            $teams = $this->mysql->query($sql);
            $teamInfos = [];
            foreach ($teams as $team) {
                $teamInfos[$team['id']] = $team;
            }
            foreach ($outs as &$out) {
                $out['team_name'] = $teamInfos[$out['team_id']]['name'] ?? null;
                $out['team_image'] = $teamInfos[$out['team_id']]['image'] ?? null;
                $out['create_date'] = date('H:i:s', $out['create_time']);
            }
            unset($out);
        }
        
        $data = [
            'outs' => $outs,
        ];
        $this->success('成功', null, $data);
    }

    /**
     * 获取聊天
     */
    public function getChats()
    {
        config('default_return_type', 'json');

        $gameId = intval($_GET['game_id']);

        // 获取聊天数据
        $sql = "SELECT * FROM `live_chat` WHERE `game_id` = '{$gameId}' ORDER BY `id` DESC LIMIT 100";
        $chats = $this->mysql->query($sql);

        // 获取用户数据
        if ($chats) {
            $userIds = array_column($chats, 'user_id');
            $sql = "SELECT `id`, `name` FROM `live_user` WHERE `id` IN ('" . implode("', '", $userIds) . "')";
            $users = $this->mysql->query($sql);
            $userNames = array_column($users, 'name', 'id');
            foreach ($chats as &$chat) {
                $chat['user_name'] = $userNames[$chat['user_id']] ?? null;
            }
            unset($chat);
            rsort($chats);
        }

        $data = [
            'chats' => $chats,
        ];
        $this->success('成功', null, $data);
    }

    /**
     * 添加聊天
     */
    public function addChat()
    {
        config('default_return_type', 'json');

        // $this->checkToken();
        
        $gameId = intval($_POST['game_id']);
        $phoneNum = $_COOKIE['phone_num'] ?? '19924533669';
        $text = trim($_POST['text']);
        
        // 插入数据
        if ($phoneNum) {
            $sql = "SELECT `id`, `name` FROM `live_user` WHERE `phone_num` = '{$phoneNum}'";
            $users = $this->mysql->query($sql);
            if (!$users) {
                $this->error('失败：用户数据查询失败');
            }
            $user = $users[0];
        }
        $userId = $user['id'] ?? 0;
        $userName = $user['name'] ?? '游客';
        $createTime = time();
        $sql = "INSERT `live_chat` (`game_id`, `user_id`, `text`, `create_time`) 
        VALUE ('{$gameId}', '{$userId}', '{$text}', '{$createTime}')";
        $result = $this->mysql->query($sql);
        if (!$result) {
            $this->error('失败：聊天数据插入失败');
        }

        // 推送数据
        $content = [
            'user_name' => $userName,
            'text' => $text,
        ];
        $taskData = [
            'method' => 'pushMessage',
            'data' => [
                'game_id' => $gameId,
                'type' => 'chat',
                'content' => $content,
            ],
        ];
        $this->server->task($taskData);

        $this->success('成功');
    }
}
