<?php

namespace lyhiving\quickio;

class quickio
{
    /**
     * 遍历文件夹，可指定是否包括文件夹
     * @param string $path     文件路口
     * @param bool $include_dirs 是否包含文件夹，默认是不包含
     */
    public static function glob($path, $include_dirs = false)
    {
        $path = rtrim($path, '/*');
        if (is_readable($path)) {
            $dh = opendir($path);
            while (($file = readdir($dh)) !== false) {
                if (substr($file, 0, 1) == '.') {
                    continue;
                }
                $rfile = "{$path}/{$file}";
                if (is_dir($rfile)) {
                    $sub = self::glob($rfile, $include_dirs);
                    while ($sub->valid()) {
                        yield $sub->current();
                        $sub->next();
                    }
                    if ($include_dirs) {
                        yield $rfile;
                    }
                } else {
                    yield $rfile;
                }
            }
            closedir($dh);
        }
    }

    /**
     * 读取指定的文件
     * @param string $path     文件路口
     */
    public static function read($path)
    {
        if ($handle = fopen($path, 'r')) {
            while (!feof($handle)) {
                yield trim(fgets($handle));
            }
            fclose($handle);
        }
    }

    /**
     * 读取文件的多行
     * @param string $path     文件名
     * @param int $count       读取多少行
     * @param int $offset      从第几行开始读，默认为0
     */
    public static function reads($path, $count, $offset = 0)
    {
        $arr = [];
        if (!is_readable($path)) {
            return $arr;
        }

        $fp = new \SplFileObject($path, 'r');

        // 定位到指定的行数开始读
        if ($offset) {
            $fp->seek($offset);
        }

        $i = 0;

        while (!$fp->eof()) {
            // 必须放在开头
            ++$i;

            // 只读 $count 这么多行
            if ($i > $count) {
                break;
            }

            $line = $fp->current();
            $line = trim($line);

            $arr[] = $line;

            // 指向下一个，不能少
            $fp->next();
        }

        return $arr;
    }

    /**
     * 高性能复制文件
     * @param string $path     源文件
     * @param string $to_file  目标文件路径
     */
    public static function copy($path, $to_file)
    {
        if (!is_readable($path)) {
            return false;
        }

        if (!is_dir(dirname($to_file))) {
            @mkdir(dirname($to_file) . '/', 0747, true);
        }

        if (
            ($handle1 = fopen($path, 'r'))
            && ($handle2 = fopen($to_file, 'w'))
        ) {
            stream_copy_to_stream($handle1, $handle2);

            fclose($handle1);
            return fclose($handle2);
        }
    }

    // 查询webserver
    public static function sapi()
    {
        $sapi = PHP_SAPI;
        $val = null;
        switch ($sapi) {
            case 'fpm-fcgi':
                $val = 'nginx';
                break;
            case 'cgi-fcgi':
                $val = 'nginx';
                break;
            case 'apache2handler':
                $val = 'apache';
                break;
            case 'cli':
                $val = 'cli';
                break;
            default:
                $val = $sapi;
                break;
        }
        return $val;
    }

    // 耗时任务执行
    public static function output($str = '', $type = '')
    {
        @ini_set('max_execution_time', '0');
        $sapi = self::sapi();
        ignore_user_abort(true);
        if (!$type) $type = 'text/html;charset=utf-8';
        if ($sapi == 'nginx') {
            echo $str;
            fastcgi_finish_request();
        } else if ($sapi == 'apache') {
            ob_end_flush();
            ob_start();
            echo $str;
            header('Content-Type: ' . $type);
            header('Connection: close');
            header('Content-Length: ' . ob_get_length());
            ob_flush();
            flush();
        }
    }

    // 递归删除文件夹
    public static function rmdir($dir)
    {
        // 打开指定目录
        if (!is_dir($dir)) return true;
        if ($handle = @opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
                if (($file == ".") || ($file == "..")) {
                    continue;
                }
                if (is_dir($dir . '/' . $file)) {
                    // 递归
                    self::rmdir($dir . '/' . $file);
                } else {
                    unlink($dir . '/' . $file); // 删除文件
                }
            }
            @closedir($handle);
            rmdir($dir);
            return true;
        }
        return false;
    }


    // 访问远程
    public static function url($method, $url, $data = [], $extopt = [], $timeout = null)
    {
        $method = strtoupper($method);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!function_exists('curl_init') || ($extopt && isset($extopt['file_get_contents']) && $extopt['file_get_contents'])) {
            if(isset($extopt['file_get_contents'])) unset($extopt['file_get_contents']);
            if (is_array($data)) {
                $data = http_build_query($data, null, '&');
            }
            $opts = array(
                $scheme => array(
                    'method' => $method,
                    'header' => '',
                    'content' => $data,
                    'timeout' => 60,
                    'Connection' => "close"
                )
            );
            if (!is_numeric($timeout)) unset($opts[$scheme]['timeout']);
            if (is_null($data)) unset($opts[$scheme]['content']);

            if ($scheme == 'https') { //忽略证书部分
                $extopt["ssl"] = array(
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                );
            }
            if ($extopt) {
                if (isset($extopt['header']) && $extopt['header'] && is_array($extopt['header'])) {
                    $extopt['header'] = implode('\r\n', $extopt['header']);
                }
                $opts = array_merge($opts, $extopt);
            }
            $context = stream_context_create($opts);
            $content = file_get_contents($url, false, $context);
            return $content;
        } else {
            if(isset($extopt['file_get_contents'])) unset($extopt['file_get_contents']);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if ($method == 'POST') {
                curl_setopt($ch, CURLOPT_POST, 1);
                if (is_array($data)) {
                    $data = http_build_query($data, null, '&');
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            if($method == 'GET'){
                curl_setopt($ch, CURLOPT_HTTPGET, true); 
            }

            if (isset($extopt['header']) && $extopt['header'] && is_array($extopt['header'])) {
                array_push($extopt['header'], 'Content-Length:' . strlen($data));
                
                curl_setopt($ch, CURLOPT_HTTPHEADER, $extopt['header']);
            }
            $content = curl_exec($ch);
            curl_close($ch);
            return $content;
        }
    }
}
