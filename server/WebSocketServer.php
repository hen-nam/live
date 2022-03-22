<?php

use app\common\library\Task;
use Swoole\Coroutine\Redis;
use Swoole\Coroutine\System;
use Swoole\WebSocket\Server;
use think\Container;
use function Swoole\Coroutine\run;

/**
 * WebSocket 服务器
 */
class WebSocketServer
{
    /**
     * Redis 主机名
     */
    const REDIS_HOST = '127.0.0.1';

    /**
     * Redis 端口号
     */
    const REDIS_PORT = 6379;

    /**
     * WebSocket 主机名
     */
    const WEBSOCKET_HOST = '0.0.0.0';

    /**
     * WebSocket 端口号
     */
    const WEBSOCKET_PORT = 9504;

    /**
     * WebSocket 连接 fd 对应的赛事
     */
    const WEBSOCKET_FD_GAME = 'fd_game';

    /**
     * 进程名
     */
    const PROCESS_TITLE = 'live_master';

    /**
     * Redis 客户端
     * @var Redis
     */
    private $redis;

    /**
     * WebSocket 服务器端
     * @var Server
     */
    private $ws;

    /**
     * 构造
     */
    public function __construct()
    {
        run(function () {
            // 创建 Redis 客户端
            $this->redis = new Redis();
            // 连接 Redis 服务器
            $this->redis->connect(self::REDIS_HOST, self::REDIS_PORT);

            // 清空连接 fd 对应的赛事
            $this->redis->del(self::WEBSOCKET_FD_GAME);
        });

        // 创建服务器
        $this->ws = new Server(self::WEBSOCKET_HOST, self::WEBSOCKET_PORT);

        // 配置静态文件根目录
        $this->ws->set([
            'enable_static_handler' => true,
            'document_root' => '/Users/hn/work/project/live/thinkphp/public',
            'task_worker_num' => 4,
        ]);

        // 注册事件回调函数
        $this->ws->on('Start', [$this, 'onStart']);
        $this->ws->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->ws->on('Request', [$this, 'onRequest']);
        $this->ws->on('Task', [$this, 'onTask']);
        $this->ws->on('Open', [$this, 'onOpen']);
        $this->ws->on('Message', [$this, 'onMessage']);
        $this->ws->on('Close', [$this, 'onClose']);

        // 启动服务器
        $this->ws->start();
    }

    /**
     * 监听主进程的主线程启动事件
     * @param $server
     */
    public function onStart($server)
    {
        // 设置进程名
        @cli_set_process_title(self::PROCESS_TITLE);
        // @swoole_set_process_name(self::PROCESS_TITLE);
    }

    /**
     * 监听 Worker 进程 / Task 进程启动事件
     * @param $server
     * @param $worker_id
     */
    public function onWorkerStart($server, $worker_id)
    {
        // 定义应用目录
        define('APP_PATH', __DIR__ . '/../application/');
        // 加载框架基础文件
        require __DIR__ . '/../thinkphp/base.php';
    }

    /**
     * 监听请求事件
     * @param $request
     * @param $response
     */
    public function onRequest($request, $response) {
        $this->log($request);

        $_SERVER = [];
        if ($request->server) {
            foreach ($request->server as $key => $value) {
                $key = strtoupper($key);
                $_SERVER[$key] = $value;
            }
        }
        if ($request->header) {
            foreach ($request->header as $key => $value) {
                $key = strtoupper($key);
                $_SERVER[$key] = $value;
            }
        }
        $_SERVER['swoole'] = [
            'server' => $this->ws,
        ];
        $_GET = $request->get ?: [];
        $_POST = $request->post ?: [];
        $_COOKIE = $request->cookie ?: [];
        $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
        $_FILES = $request->files ?: [];

        ob_start();
        try {
            // 执行应用并响应
            Container::get('app', [APP_PATH])
                ->run()
                ->send();
        } catch (Exception $e) {
            echo '错误：' . $e->getMessage() . PHP_EOL;
        }
        $resultJson = ob_get_clean();

        // 设置 HTTP 响应 header 信息
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $result = json_decode($resultJson, true);
        if ($result) {
            if (isset($result['data']['cookie'])) {
                foreach ($result['data']['cookie'] as $cookie) {
                    // 设置 HTTP 响应 cookie 信息
                    $response->cookie($cookie['key'], $cookie['value'], $cookie['expire'] ?? 0);
                }
                unset($result['data']['cookie']);
            }
            $resultJson = json_encode($result);
        }
        // 发送 HTTP 响应体，并结束请求处理
        $response->end($resultJson);
    }

    /**
     * 监听 Task 进程调用事件
     * @param $server
     * @param $task_id
     * @param $src_worker_id
     * @param $data
     */
    public function onTask($server, $task_id, $src_worker_id, $data)
    {
        echo '异步任务：' . json_encode($data) . PHP_EOL;

        if ($data['method'] == 'pushMessage') {
            $dataJson = json_encode($data['data']);
            // 遍历服务器当前所有的连接
            foreach ($server->connections as $fd) {
                // 检查连接是否为有效的 WebSocket 客户端连接
                if ($server->isEstablished($fd)) {
                    // 向 WebSocket 客户端连接推送数据
                    $server->push($fd, $dataJson);

                    /*
                    $game_id = null;
                    // 获取连接 fd 对应的赛事
                    run(function () use ($fd, &$game_id) {
                        $game_id = $this->redis->hGet(self::WEBSOCKET_FD_GAME, $fd);
                    });
                    if ($game_id == $data['data']['game_id']) {
                        // 向 WebSocket 客户端连接推送数据
                        $server->push($fd, $dataJson);
                    }
                    */
                }
            }
        }

        /*
        $task = new Task($server);
        $method = $data['method'] ?? '';
        if (method_exists($task, $method)) {
            $task->$method($data['data']);
        } else {
            echo '失败：异步任务方法不存在' . PHP_EOL;
        }
        */
    }

    /**
     * 监听连接打开事件
     * @param $ws
     * @param $request
     */
    public function onOpen($ws, $request)
    {
        echo "连接打开事件 - {$request->fd}\n";
        $ws->push($request->fd, 'hello');

        // 设置连接 fd 对应的赛事
        $this->redis->hSet(self::WEBSOCKET_FD_GAME, $request->fd, $request->get['game_id']);
    }

    /**
     * 监听消息事件
     * @param $ws
     * @param $frame
     */
    public function onMessage($ws, $frame)
    {
        echo "消息事件 - data: {$frame->data}, fd: {$frame->fd}, opcode: {$frame->opcode}, finish: {$frame->finish}\n";
    }

    /**
     * 监听连接关闭事件
     * @param $ws
     * @param $fd
     */
    public function onClose($ws, $fd)
    {
        echo "连接关闭事件 - {$fd}\n";

        // 删除连接 fd 对应的赛事
        $this->redis->hDel(self::WEBSOCKET_FD_GAME, $fd);
    }

    /**
     * 记录日志
     * @param $request
     */
    private function log($request)
    {
        $file = APP_PATH . '../runtime/log/' . date('Ym/d') . '_request.log';
        $date = date('Y-m-d H:i:s');
        $data = [
            'server' => $request->server,
            'header' => $request->header,
            'get' => $request->get,
            'post' => $request->post,
            'cookie' => $request->cookie,
            'files' => $request->files,
        ];
        $dataJson = json_encode($data);
        $content = $date . PHP_EOL . $dataJson . PHP_EOL;
        System::writeFile($file, $content, FILE_APPEND);
    }
}

new WebSocketServer();