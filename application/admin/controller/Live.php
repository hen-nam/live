<?php

namespace app\admin\controller;

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
     * 上传图片
     */
    public function uploadImage()
    {
        config('default_return_type', 'json');

        $file = request()->file('file');
        $info = $file->move(APP_PATH . '../public/uploads');
        if (!$info) {
            $this->error('失败：' . $file->getError());
        }

        $data = [
            'image' => '/uploads/' . strtolower($info->getSaveName()),
        ];
        $this->success('成功', null, $data);
    }

    /**
     * 添加赛况
     */
    public function addOut()
    {
        config('default_return_type', 'json');
        
        if (!$_POST) {
            $this->error('失败：请求数据为空');
        }

        // 插入数据
        $data = $_POST;
        $data['game_id'] = intval($data['game_id']);
        $data['quarter'] = intval($data['quarter']);
        $data['team_id'] = intval($data['team_id']);
        $data['text'] = trim($data['text']);
        $data['image'] = $data['image'] ?? null;
        $data['create_time'] = time();
        $sql = "INSERT `live_outs` (`game_id`, `quarter`, `team_id`, `text`, `image`, `create_time`) 
        VALUE ('{$data['game_id']}', '{$data['quarter']}', '{$data['team_id']}', '{$data['text']}', '{$data['image']}', '{$data['create_time']}')";
        $result = $this->mysql->query($sql);
        if (!$result) {
            $this->error('失败：赛况数据插入失败');
        }

        // 推送数据
        $content = $data;
        $content['team_name'] = null;
        $content['team_image'] = null;
        if ($content['team_id']) {
            $sql = "SELECT `name`, `image` FROM `live_team` WHERE `id` = '{$content['team_id']}'";
            $teams = $this->mysql->query($sql);
            if (!$teams) {
                $this->error('失败：球队数据查询失败');
            }
            $team = $teams[0];
            $content['team_name'] = $team['name'];
            $content['team_image'] = $team['image'];
        }
        $content['create_date'] = date('H:i:s', $content['create_time']);
        $taskData = [
            'method' => 'pushMessage',
            'data' => [
                'game_id' => $content['game_id'],
                'type' => 'outs',
                'content' => $content,
            ],
        ];
        $this->server->task($taskData);

        $this->success('成功');
    }
}
