<?php

include __DIR__ . '/../autoload.php';

use lyhiving\quickio\quickio;

// $quickio = new quickio();

// // 遍历目录
echo "---START glob Dir---" . PHP_EOL;
$glob = quickio::glob('./logs');
// $glob = quickio::glob('./logs',true); //如需读取文件夹
while ($glob->valid()) {
    // 当前文件
    $filename = $glob->current();
    echo $filename . PHP_EOL;
    // 指向下一个，不能少
    $glob->next();
}
echo "---END glob Dir---" . PHP_EOL . PHP_EOL;

// 单行读取大文件
echo "---START read file by single line---" . PHP_EOL;

$glob = quickio::read('./logs/jd.log');
while ($glob->valid()) {
    // 当前行文本
    $line = $glob->current();

    // 逐行处理数据
    echo $line . PHP_EOL;

    // 指向下一个，不能少
    $glob->next();
}
echo "---END read file by single line---" . PHP_EOL . PHP_EOL;

// 多行读取大文件
echo "---START read file by multi lines---" . PHP_EOL;
$lines = quickio::reads('./logs/jd.log', 3, 1);
var_dump($lines);
echo "---END read file by multi lines---" . PHP_EOL . PHP_EOL;


// 复制文件
echo "---START copy file---" . PHP_EOL;
$ret = quickio::copy('./logs/jd.log','./logs/jd.log.new');
var_dump($ret);
echo "---END copy file---" . PHP_EOL . PHP_EOL;



// 先输出，其他可执行内容
echo "---START output first---" . PHP_EOL;
quickio::output('RUN First! You can see this.');
echo "YOU CAN'T SEE ME! ". PHP_EOL;
sleep(1);
file_put_contents('./logs/output.log', date('Y-m-d H:i:s'). PHP_EOL, FILE_APPEND | LOCK_EX);
sleep(2);
file_put_contents('./logs/output.log', date('Y-m-d H:i:s'). PHP_EOL,FILE_APPEND | LOCK_EX);
sleep(2);
echo "BUT YOU CAN RUN OTHER THINGS ". PHP_EOL;
echo "---END output first---" . PHP_EOL . PHP_EOL;
