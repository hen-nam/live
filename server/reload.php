<?php
/**
 * 平滑重启服务
 */
// 进程名
const PROCESS_TITLE = 'live_master';
echo 'reloading ...' . PHP_EOL;
$command = 'pidof ' . PROCESS_TITLE;
$pid = shell_exec($command);
$command = 'kill -USR1 ' . $pid;
shell_exec($command);
echo 'reload successfully' . PHP_EOL;