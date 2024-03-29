<?php

include __DIR__ . '/../autoload.php';

use lyhiving\quickio\quickio;

// $quickio = new quickio();

 
// echo "---set cache path---" . PHP_EOL;
// quickio::setCachePath('./cache/');
// var_dump(quickio::getCachePath());

// echo "---set cache data---" . PHP_EOL;
// quickio::set('p2', __FILE__);

// echo "---get cache data---" . PHP_EOL;
// $cache = quickio::get('p2');
// var_dump($cache);

// echo "---delete cache data---" . PHP_EOL;
// quickio::del('p2');

// echo "---no cache output---" . PHP_EOL;
// quickio::noCache();

// echo "---browser cache output---" . PHP_EOL;
// quickio::ieCache(600);


// echo "---quick dump output---" . PHP_EOL;
// quickio::dump([__FILE__,__LINE__]);

// echo "---quick dump output end exit ---" . PHP_EOL;
// quickio::_dump([__FILE__,__LINE__]);


// 遍历目录
echo "---start glob dir---" . PHP_EOL;
$glob = quickio::glob('./logs');
// $glob = quickio::glob('./logs',true); //如需读取文件夹
while ($glob->valid()) {
    // 当前文件
    $filename = $glob->current();
    echo $filename . PHP_EOL;
    // 指向下一个，不能少
    $glob->next();
}
echo "---end glob dir---" . PHP_EOL . PHP_EOL;

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


// 递归删除文件夹
echo "---START Recursive Delete Folder---" . PHP_EOL;
$ret = quickio::rmdir('./logs/abc/');
var_dump($ret);
echo "---END Recursive Delete Folder---" . PHP_EOL . PHP_EOL;

// CORS 跨域:
//header send nothing happend 
// echo "---CORS ---" . PHP_EOL;
// quickio::cors('*');
 

// 读取远程
echo "---START get---" . PHP_EOL;
$data = quickio::url('get','https://httpbin.org/get',[],['file_get_contents'=>false]);
    var_dump($data);
echo "---END get url---" . PHP_EOL . PHP_EOL;


// POST远程
echo "---START post url---" . PHP_EOL;
$data = quickio::url('post','http://httpbin.org/post',['date'=>date('Y-m-d H:i:s')],['file_get_contents'=>true]);
    var_dump($data);
echo "---END post url---" . PHP_EOL . PHP_EOL;

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
