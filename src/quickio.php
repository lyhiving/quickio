<?php

namespace lyhiving\quickio;

class quickio
{
    public const SAFEDOT = "/#SAFE#/";
    public const DEBUGPRE = __NAMESPACE__ . '\_debug_pre';
    public const CACHEPATH = __NAMESPACE__ . '\CACHE_PATH';

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
        if (!$type) {
            $type = 'text/html;charset=utf-8';
        }
        if ($sapi == 'nginx') {
            echo $str;
            fastcgi_finish_request();
        } elseif ($sapi == 'apache') {
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
        if (!is_dir($dir)) {
            return true;
        }
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
            if (isset($extopt['file_get_contents'])) {
                unset($extopt['file_get_contents']);
            }
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
            if (!is_numeric($timeout)) {
                unset($opts[$scheme]['timeout']);
            }
            if (is_null($data)) {
                unset($opts[$scheme]['content']);
            }

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
            $content = @file_get_contents($url, false, $context);
            return $content;
        } else {
            if (isset($extopt['file_get_contents'])) {
                unset($extopt['file_get_contents']);
            }
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
            if ($method == 'GET') {
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



    /**
     * 判断以什么开始
     */
    public static function isStartWith($haystack, $needle)
    {
        return strpos($haystack, $needle) === 0;
    }


    /**
     * 判断以什么结束
     */
    public static function isEndWith($haystack, $needle)
    {
        if (strlen($haystack) < strlen($needle)) return false;
        return $needle === substr($haystack, 0 - strlen($needle));
    }


    // 静态缓存
    public static function webCache(&$valueArray, $config = array(), $clean = false)
    {
        return webcache::cache($valueArray, $config, $clean);
    }

    //静态缓存生成回调
    public static function webCacheCallback(&$buffer)
    {
        return webcache::callback($buffer);
    }


    // 用eTag来识别内容变化
    public static function eTag($content, $echo = true)
    {
        $sEtag = $sEtagCheck = md5($content);
        if ($_SERVER['HTTP_IF_NONE_MATCH'] && self::isStartWith($_SERVER['HTTP_IF_NONE_MATCH'], 'W/')) {
            $sEtagCheck = 'W/"' . $sEtag . '"';
        }
        //确保有变化就通知更新
        @header('Etag: "' . $sEtag . '"');
        if (in_array($_SERVER['HTTP_IF_NONE_MATCH'], array($sEtag, $sEtagCheck))) {
            @header('HTTP/1.1 304 Not Modified');
        } else {
            if ($echo) {
                echo $content;
            }
        }
    }


    /** 
     * 浏览器缓存
     *  $offser 缓存时间
     *  $starttime 开始缓存的时间，默认就是time()
     *  $content 内容标识，如有则输出eTag 
     */
    public static function ieCache($offset = 3600, $start = null, $content = null)
    {
        @header_remove("Cache-Control");
        @header_remove("Pragma");
        @header_remove("Expires");
        @header_remove("Last-Modified");
        @header("Cache-Control: public");
        @header("Pragma: cache");
        if (is_null($start)) {
            $start = time();
        }
        @header("Expires: " . gmdate("D, d M Y H:i:s", $start + $offset) . " GMT");
        @header("Last-Modified: " . gmdate("D, d M Y H:i:s", $start) . " GMT");
        if ($content) {
            self::eTag($content, false);
        }
    }

    /**
     * 不缓存
     */
    public static function noCache($code = null)
    {
        $http = array(
            100 => "HTTP/1.1 100 Continue",
            101 => "HTTP/1.1 101 Switching Protocols",
            200 => "HTTP/1.1 200 OK",
            201 => "HTTP/1.1 201 Created",
            202 => "HTTP/1.1 202 Accepted",
            203 => "HTTP/1.1 203 Non-Authoritative Information",
            204 => "HTTP/1.1 204 No Content",
            205 => "HTTP/1.1 205 Reset Content",
            206 => "HTTP/1.1 206 Partial Content",
            300 => "HTTP/1.1 300 Multiple Choices",
            301 => "HTTP/1.1 301 Moved Permanently",
            302 => "HTTP/1.1 302 Found",
            303 => "HTTP/1.1 303 See Other",
            304 => "HTTP/1.1 304 Not Modified",
            305 => "HTTP/1.1 305 Use Proxy",
            307 => "HTTP/1.1 307 Temporary Redirect",
            400 => "HTTP/1.1 400 Bad Request",
            401 => "HTTP/1.1 401 Unauthorized",
            402 => "HTTP/1.1 402 Payment Required",
            403 => "HTTP/1.1 403 Forbidden",
            404 => "HTTP/1.1 404 Not Found",
            405 => "HTTP/1.1 405 Method Not Allowed",
            406 => "HTTP/1.1 406 Not Acceptable",
            407 => "HTTP/1.1 407 Proxy Authentication Required",
            408 => "HTTP/1.1 408 Request Time-out",
            409 => "HTTP/1.1 409 Conflict",
            410 => "HTTP/1.1 410 Gone",
            411 => "HTTP/1.1 411 Length Required",
            412 => "HTTP/1.1 412 Precondition Failed",
            413 => "HTTP/1.1 413 Request Entity Too Large",
            414 => "HTTP/1.1 414 Request-URI Too Large",
            415 => "HTTP/1.1 415 Unsupported Media Type",
            416 => "HTTP/1.1 416 Requested range not satisfiable",
            417 => "HTTP/1.1 417 Expectation Failed",
            500 => "HTTP/1.1 500 Internal Server Error",
            501 => "HTTP/1.1 501 Not Implemented",
            502 => "HTTP/1.1 502 Bad Gateway",
            503 => "HTTP/1.1 503 Service Unavailable",
            504 => "HTTP/1.1 504 Gateway Time-out",
        );
        if ($code && isset($http[$code])) {
            header($http[$code]);
        }
        @header("Cache-Control: no-cache, must-revalidate");
        @header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    }

    public static function setCachePath($path)
    {
        $_ENV[self::CACHEPATH] = $path;
    }


    public static function getCachePath()
    {
        return $_ENV[self::CACHEPATH];
    }

    /**
     * 写入文件
     *
     * @param string $file 文件名
     * @param string $data 文件内容
     * @param boolean $append 是否追加写入
     * @return int
     */
    public static function writeFile($file, $data, $append = false)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $result = false;
        $fp = @fopen($file, $append ? 'ab' : 'wb');
        if ($fp && @flock($fp, LOCK_EX)) {
            $result = @fwrite($fp, $data);
            @flock($fp, LOCK_UN);
            @fclose($fp);
            @chmod($file, 0777);
        }
        return $result;
    }

    /**
     * 读取缓存
     *
     * @param string $file 文件名
     * @param string $path 文件路径，默认为CACHE_PATH
     * @param boolean $iscachevar 是否启用缓存
     * @return array
     */
    public static function get($file, $path = null, $iscachevar = 0)
    {
        if (!$path) {
            $path =  $_ENV[self::CACHEPATH];
        }
        $cachefile = $path . self::SAFEDOT . $file;
        if ($iscachevar) {
            $key = __NAMESPACE__ . '\cache_' . (strpos($file, '.php') ? substr($file, 0, -4) : $file);
            return isset($_ENV[$key]) ? $_ENV[$key] : $_ENV[$key] = @include $cachefile;
        }
        return @include $cachefile;
    }

    /**
     * 写入缓存
     *
     * @param string $file 文件名
     * @param array $array 缓存内容
     * @param string $path 文件路径，默认CACHE_PATH
     * @return int
     */
    public static function set($file, $array, $path = null)
    {
        $array = "<?php\nreturn " . var_export($array, true) . ";";
        $cachefile = ($path ? $path : $_ENV[self::CACHEPATH]) . self::SAFEDOT . $file;
        $strlen = self::writeFile($cachefile, $array);
        return $strlen;
    }

    /**
     * 删除缓存
     *
     * @param string $file
     * @param string $path
     * @return boolean
     */
    public static function del($file, $path = '')
    {
        $cachefile = ($path ? $path : $_ENV[self::CACHEPATH]) . self::SAFEDOT . $file;
        $key = __NAMESPACE__ . '\cache_' . (strpos($file, '.php') ? substr($file, 0, -4) : $file);
        if (isset($_ENV[$key])) {
            unset($_ENV[$key]);
        }
        return @unlink($cachefile);
    }

    /**
     * 判断是否在命令行模式
     */
    public static function isCli()
    {
        return preg_match("/cli/i", php_sapi_name()) ? 1 : 0;
    }


    /*4、完整的時間：2018-08-28 10:29:16 247591*/
    public static function udate($format = 'u', $utimestamp = null, $real = true)
    {
        if (is_null($utimestamp)) {
            $utimestamp = microtime(true);
        }
        $timestamp = floor($utimestamp);
        $pertime = 1000; //改這裡的數值控制毫秒位數
        if ($utimestamp && $real && strpos($utimestamp, '.') === false) {
            list($usec, $sec) = explode(' ', microtime());
            $milliseconds = round($usec * $pertime);
        } else {
            $milliseconds = round(($utimestamp - $timestamp) * $pertime);
        }
        //修正位数，确保位数跟控制的毫秒数一致，比如1000毫秒就是3位数
        if (strlen($milliseconds) < strlen($pertime) - 1) {
            $milliseconds = str_pad($milliseconds, strlen($pertime) - 1, '0', STR_PAD_RIGHT);
        }

        return date(preg_replace('`(?<!\\\\)u`', $milliseconds, $format), $timestamp);
    }

    public static function logtime()
    {
        return self::udate('Y-m-d H:i:s.u');
    }

    /**
     * 文件单行转数组
     */
    public static function lineToArray($line, $isfile = true)
    {
        if ($isfile) {
            if (!is_readable($line)) return array();
            $line = self::read($line);
        }
        $data = preg_split("/[\n\r]+/", $line, -1, PREG_SPLIT_NO_EMPTY);
        if (!$data) return array();
        foreach($data as $k=>$v){
            $data[$k] = trim($v);
        }
        return $data;
    }



    /**
     * 直接命令行记录LOG, 比较多信息
     */
    public static function console($var, $label = null, $echo = true)
    {
        $debug = isset($_ENV[self::DEBUGPRE]) && is_array($_ENV[self::DEBUGPRE]) ? $_ENV[self::DEBUGPRE] : debug_backtrace();
        $echostr = '';
        $str = '[DEBUG]: ' . self::logtime() . ' ' . (defined('IA_ROOT') ? substr($debug[0]['file'], strlen(IA_ROOT)) : $debug[0]['file']) . ':(' . $debug[0]['line'] . ")" . PHP_EOL;

        if (is_string($var)) {
            $str .= $echostr .= ($label ? '\'' . $label . '\' =>\'' : '') . $var . ($label ? '\',' : '') . PHP_EOL;
        } else {
            $str .= ($label ? '\'' . $label . '\' =>' : '') . var_export($var, true) . ($label ? ',' : '') . PHP_EOL;
        }
        if ($echo) {
            echo $str;
            return true;
        }
        return $str;
    }

    public static function CORS($limit = '')
    {
        // Allow from any origin
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            if (!$limit) {
                $domain = $_SERVER['HTTP_ORIGIN'] ? $_SERVER['HTTP_ORIGIN'] : "*";
            } else {
                $domain = $limit;
            }
            header("Access-Control-Allow-Origin: {$domain}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');    // cache for 1 day
        }
        // Access-Control headers are received during OPTIONS requests
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
                header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
            }
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            }
        }
    }

    /**
     * +----------------------------------------------------------
     * 变量输出
     * +----------------------------------------------------------
     * @param string $var 变量名
     * @param string $label 显示标签
     * @param string $echo 是否显示
     * +----------------------------------------------------------
     * @return string
     * +----------------------------------------------------------
     */
    public static function dump($var, $label = null, $strict = true, $echo = true)
    {
        if (self::isCli()) {
            return self::console($var, $label);
        }
        $label = ($label === null) ? '' : rtrim($label) . ' ';
        $debug = isset($_ENV[self::DEBUGPRE]) && is_array($_ENV[self::DEBUGPRE]) ? $_ENV[self::DEBUGPRE] : debug_backtrace();
        $mtime = explode(' ', microtime());
        $ntime = microtime(true);
        $_ENV['dumpOrderID'] = isset($_ENV['dumpOrderID']) && $_ENV['dumpOrderID'] ? $_ENV['dumpOrderID'] + 1 : 1;
        $offtime = !isset($_ENV['dumpTimeCountDown']) || !$_ENV['dumpTimeCountDown'] ? 0 : round(($ntime - $_ENV['dumpTimeCountDown']) * 1000, 4);
        if (!isset($_ENV['dumpTimeCountDown']) || !$_ENV['dumpTimeCountDown']) {
            $_ENV['dumpTimeCountDown'] = $ntime;
        }

        $message = '<br /><font color="#fff" style="width: 30px;height: 12px; line-height: 12px;background-color:' . ($label ? 'indianred' : '#2943b3') . ';padding: 2px 6px;border-radius: 4px;">No. ' . sprintf('%02d', $_ENV['dumpOrderID']) . '</font>&nbsp;&nbsp;' . " ~" . (defined('IA_ROOT') ? substr($debug[0]['file'], strlen(IA_ROOT)) : $debug[0]['file']) . ':(' . $debug[0]['line'] . ") &nbsp;" . self::logtime() . " $mtime[0] " . (!$offtime ? "" : "(" . $offtime . "ms)") . '<br />' . PHP_EOL;
        if (!$strict) {
            if (ini_get('html_errors')) {
                $output = print_r($var, true);
                $output = "<pre>" . $label . htmlspecialchars($output, ENT_IGNORE, 'utf-8') . "</pre>";
            } else {
                $output = $label . " : " . print_r($var, true);
            }
        } else {
            ob_start();
            var_dump($var);
            $output = ob_get_clean();
            if (!extension_loaded('xdebug')) {
                $output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_IGNORE, 'utf-8') . '</pre>';
            }
        }
        $output = $message . $output;
        if ($echo) {
            echo ($output);
            return null;
        } else {
            return $output;
        }
    }

    public static function _dump($var, $label = null, $strict = true, $echo = true)
    {
        if (!isset($_ENV[self::DEBUGPRE])) {
            $_ENV[self::DEBUGPRE] = debug_backtrace();
        }
        self::dump($var, $label, $strict, $echo);
        exit;
    }
}
