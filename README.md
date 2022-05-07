# PHP Quick IO

PHP 超低内存遍历目录文件和读取超大文件。

## 安装

使用 Composer

```json
{
  "require": {
    "lyhiving/quickio": "1.0.*"
  }
}
```

## 用法

引入命名空间

```php
<?php

// include __DIR__ . '/../autoload.php'; //引入composer的加载

use lyhiving\quickio\quickio;

```

遍历指定目录：

```php
$glob = quickio::glob('./logs');
// $glob = quickio::glob('./logs',true); //如需读取文件夹
while ($glob->valid()) {
    // 当前文件
    $filename = $glob->current();
    echo $filename . PHP_EOL;
    // 指向下一个，不能少
    $glob->next();
}
```

单行读取大文件：

```php
$glob = quickio::read('./logs/jd.log');
while ($glob->valid()) {
    // 当前行文本
    $line = $glob->current();

    // 逐行处理数据
    echo $line . PHP_EOL;

    // 指向下一个，不能少
    $glob->next();
}
```

多行读取大文件：

```php
$lines = quickio::reads('./logs/jd.log', 3, 1);
var_dump($lines);
```

复制文件：

```php
$ret = quickio::copy('./logs/jd.log','./logs/jd.log.new');
var_dump($ret);
```

递归删除文件夹：

```php
$ret = quickio::rmdir('./logs/abc');
var_dump($ret);
```



文件类缓存操作：

```php
echo "---set cache path---" . PHP_EOL;
quickio::setCachePath('./cache/');
var_dump(quickio::getCachePath());

echo "---set cache data---" . PHP_EOL;
quickio::set('p2', __FILE__);

echo "---get cache data---" . PHP_EOL;
$cache = quickio::get('p2');
var_dump($cache);

echo "---delete cache data---" . PHP_EOL;
quickio::del('p2');
```


浏览器缓存操作：

```php
echo "---no cache output---" . PHP_EOL;
quickio::noCache();

echo "---browser cache output---" . PHP_EOL;
quickio::ieCache(600);
```



简单输出：

```php
echo "---quick dump output---" . PHP_EOL;
quickio::dump([__FILE__,__LINE__]);

echo "---quick dump output end exit ---" . PHP_EOL;
quickio::_dump([__FILE__,__LINE__]);
```

递归删除文件夹：

```php
$ret = quickio::rmdir('./logs/abc');
var_dump($ret);
```





GET远程地址：

```php
$data = quickio::url('get','https://httpbin.org/get',[],['file_get_contents'=>false]);
var_dump($data);
```

POST远程地址：

```php
$data = quickio::url('post','http://httpbin.org/post',['date'=>date('Y-m-d H:i:s')],['file_get_contents'=>true]);
    var_dump($data);
```



优先输出：

`在执行耗时任务时需要提前返回。`

`这个时候http的进程已结束，客户端可以快速根据响应进行处理。但在服务端php代码会继续执行余下的内容`

`对命令行无效，仅针对web服务`

```php
quickio::output('RUN First! You can see this.');
echo "YOU CAN'T SEE ME! ". PHP_EOL;
echo "BUT YOU CAN RUN OTHER THINGS ". PHP_EOL;
```

🌹Thanks TO： [小明](https://segmentfault.com/a/1190000019051193)
