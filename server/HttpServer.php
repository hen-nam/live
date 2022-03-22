<?php

use app\common\library\Task;
use think\Container;

/**
 * HTTP 服务器
 */
class HttpServer
{
    /**
     * 主机名
     */
    const HOST = '0.0.0.0';

    /**
     * 端口号
     */
    const PORT = 9503;

    /**
     * HTTP 服务器端
     * @var \Swoole\Http\Server
     */
    private $http;

    /**
     * 构造
     */
    public function __construct()
    {
        // 创建服务器
        $this->http = new \Swoole\Http\Server(self::HOST, self::PORT);

        // 配置静态文件根目录
        $this->http->set([
            'enable_static_handler' => true,
            'document_root' => '/Users/hn/work/project/live/thinkphp/public',
            'task_worker_num' => 4,
        ]);

        // 注册事件回调函数
        $this->http->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->http->on('Request', [$this, 'onRequest']);
        $this->http->on('Task', [$this, 'onTask']);

        // 启动服务器
        $this->http->start();
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
            'server' => $this->http,
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

        /*
        $task = new Task();
        $method = $data['method'] ?? '';
        if (!method_exists($task, $method)) {
            echo '失败：异步任务方法不存在' . PHP_EOL;
        }
        $task->$method($data['data']);
        */
    }
}

new HttpServer();