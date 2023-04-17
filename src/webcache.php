<?php
/*
 * 主要功能:
/*   缓存的使用办法
//web cache
$valueArray = array(
        'id' 	=>$id
);
$set = array(
    'pageName'	=> '',	//本页面的文件名 没有扩展名
    'minSize' 	=> 10240,		//最少cache的大小 字节
    'dirCount'	=> 30,			//几*几的目录
    'cacheTime'	=> 30*24*3600,		//cache时间
    'outEnd'	=> '.html',		//cache 文件的扩展名
    'endDoing'  => 'in'			//在以有缓存时 go:跳转(go?:加随机时间跳转) in:包含
);
webcache::cache($valueArray,$set,$_GET['cc']);

//路径构成
//domainPath/pagePath/fileName
*/

namespace lyhiving\quickio;

class webcache
{
    protected static $baseDir   = '#_webcache_#';
    protected static $domainPath	= false; // 设定域名路径，不为false则会设定，为0自动设定为当前hostname
    protected static $pagePath	= false; // 设定页面路径，不为false则会设定
    protected static $fileName	= false; // 文件名称，不含后缀，允许目录接入，不允许/结尾
    protected static $minSize	= 10240;//最少字节数
    protected static $dirCount	= 30;
    protected static $cacheTime	= 3600;//每次生成间隔时间
    protected static $expiresTime = 600; //浏览器缓存时间
    protected static $outEnd	= ".html";
    protected static $endDoing	=  false;
    protected static $orderBy	=  false;
    protected static $auto	=  false;
    protected static $theHtml	=  '';
    protected static $theDot	=  '-'; //默认情况下参数相连字符串
    protected static $theIndex	=  'index'; //默认页面文件名
    protected static $writeHook = false; //增加钩子来弄写操作，有定义就走定义函数，如果定义函数返回true就自动结束，如果返回false就继续本地写
    protected static $callbackFunc = '_webCacheCallback'; //用于设定回调函数
    ///////
    protected static $_fileName;
    protected static $_stop			= false;
    protected static $_tmpFileTime	= 30;//touch最长时间 一般30-60秒
    protected static $_domainPath;
    protected static $_pagePath;
    protected static $_filePath;
    //////


    public static function cache(&$valueArray, $config=array(), $clean = false)
    {
        $time 		= time();
        $makeHtml	= false;
        if ((!$config ||!isset($config['fileName']))) {
            if (isset($valueArray['fileName'])) {
                $config['fileName'] = $valueArray['fileName'];
                unset($valueArray['fileName']);
            }
            if (!$valueArray) {
                $config['fileName'] = self::$theIndex;
            }
        }
        self::setConfig($config);
        $fileName 	=self::getFileName($valueArray);
        if ($clean == 'clean') {
            @unlink($fileName);
            exit();
            return;
        } elseif ($clean == 'c') {
            @unlink($fileName);
            $fileMTime 	=0;
            $fileMSize 	=0;
        } elseif ($clean) {
            return;
        } else {
            $fileMTime 	=@filemtime(self::$baseDir.$fileName);
            $fileMSize 	=@filesize(self::$baseDir.$fileName);
        }
        $left=$time-$fileMTime;
        if (!file_exists(self::$baseDir.$fileName)
            or (self::$cacheTime >0 && $fileMTime<$time-self::$cacheTime)
            or ($fileMSize==0 && $fileMTime<$time-self::$_tmpFileTime)
            or ($fileMSize>0 && $fileMSize<self::$minSize)
            or (self::$auto && $fileMTime<$time-self::$auto)) {
            $makeHtml = true;
        }
        if ($makeHtml) {
            ob_start(self::$callbackFunc);
            ob_implicit_flush(true);
        } elseif (self::$endDoing) {
            if (self::$endDoing == 'in') {
                if (self::$auto && $left > self::$auto) {
                    self::go(1);
                } else {
                    self::in();
                }
            } elseif (self::$endDoing == 'go') {
                if (file_exists(self::$baseDir.$fileName)) {
                    self::go();
                } else {
                    self::in();
                }
            } elseif (self::$endDoing == 'go?') {
                if (file_exists(self::$baseDir.$fileName)) {
                    self::go(1);
                } else {
                    self::in();
                }
            }
        } else {
            self::in();
        }
    }
    /*
     * 功能:设定
     */
    public static function setConfig(&$config)
    {
        if (is_array($config)) {
            foreach ($config as $k => $v) {
                if (isset(self::$$k)) {
                    self::$$k = $v;
                }
            }
        }
        self::$_domainPath = self::$domainPath===false ? '' : (self::$domainPath ? self::$domainPath : $_SERVER['HTTP_HOST']).'/';
        self::$_pagePath = self::$pagePath===false ? '' : (self::$pagePath ? self::$pagePath.'/' : '');
        self::$_filePath =  (self::$theHtml ? self::$theHtml.'/' : '').self::$_domainPath.self::$_pagePath;
    }

    public static function setEndDoing($endDoing)
    {
        self::$endDoing	= $endDoing;
    }
    public static function stop()
    {
        self::$_stop	= true;
    }
    /*
     * 功能:写文件
     */
    protected function write($fileName, &$content)
    {
        if (self::$writeHook && function_exists(self::$writeHook)) {
            $writehook = self::$writeHook;
            $hook = $writehook($fileName, $content);
            if ($hook) {
                return $content ? $content : true;
            }
        }
        $bdir = dirname($fileName);
        if (!is_dir($bdir)) {
            mkdir($bdir, 0755, true);
        }
        if (strlen($content)>self::$minSize) {
            return quickio::writeFile($fileName, $content);
        }
        //否则
        @unlink($fileName);
    }

    protected function in()
    {
        if (self::$_stop) {
            quickio::noCache();
        } else {
            $ftime = filemtime(self::$baseDir.self::$_fileName);
            quickio::ieCache(self::$expiresTime, $ftime, self::$_fileName.'@'.$ftime);
        }
        if ($_GET['e']) {
            header("lyhiving: ".self::$_fileName);
        }
        readfile(self::$baseDir.self::$_fileName);
        exit();
    }
    protected function go($a = 0)
    {
        $tmp =self::$_fileName;
        if ($a) {
            $tmp =$tmp.'?'.time();
        }
        header("Location: ".$tmp);
        exit();
    }
    /*
     * 功能:参数设定与排序
     */
    protected function getFileName(&$valueArray)
    {
        //目录
        $fileNameDir = self::$_filePath;
        $isDir = quickio::isEndWith(self::$fileName, '/');
        if (self::$fileName) {
            $fileName = !$isDir ? self::$fileName : self::$fileName.self::$theIndex;
        } else {
            //数组排序
            if ($valueArray) {
                ksort($valueArray);
                $valueArray=array_change_key_case($valueArray, CASE_LOWER);
                $j_j='0';
                foreach ($valueArray as $k=>&$v) {
                    $j_j++;
                    $v 		= explode('@', $v);
                    $v 		= (count($v)>1) ? $v[1] : $v[0];
                    if ($k=='styleid') {
                    } elseif ($k=='index'&& ($v=='1'||!$v)) {
                        $fileName .= 'index';
                    } else {
                        if ($j_j>1) {
                            $fileName .= '-';
                        }
                        $fileName .= $k.'-'.$v;
                    }
                    for ($i = 0; $i<strlen($v); $i++) {
                        $VarOrd1 += ord($v{$i})*$i;
                        $VarOrd2 += ord($v{$i})*($i*2+1);
                    }
                }
                if (!$fileName) {
                    $fileName="index";
                }
                $VarOrd1 %= self::$dirCount;
                $VarOrd2 %= self::$dirCount;
                if (self::$orderBy) {
                    $fileNameDir .=$VarOrd1.'-'.$VarOrd2.'/';
                }
            }
        }
        // dump($fileName);

        self::$_fileName = $fileNameDir.$fileName.self::$outEnd;
        // dump([self::$_fileName, $fileName]);
        return self::$_fileName;
    }


    public static function strReplaceOnce($needle, $replace, $haystack)
    {
        if (($pos = strpos($haystack, $needle)) === false) {
            return $haystack;
        }

        return substr_replace($haystack, $replace, $pos, strlen($needle));
    }

    public static function callback(&$buffer)
    {
        $ftime =time();
        if (isset($_ENV['_endwebcache']) && $_ENV['_endwebcache']) {
            return $buffer;
        }
        if (self::$_stop || strlen($buffer)<self::$minSize) {
            quickio::noCache();
        } else {
            quickio::ieCache(self::$expiresTime, $ftime, self::$_fileName.'@'.$ftime);
            if (quickio::isEndWith($buffer, '</html>')) {
                $buffer = self::strReplaceOnce('</html>', '<!-- Cached: '.gmdate("D, d M Y H:i:s", $ftime).' GMT --><!-- Purge: '.gmdate("D, d M Y H:i:s", $ftime+self::$cacheTime).' GMT --></html>', $buffer);
            }
            self::write(self::$baseDir.self::$_fileName, $buffer);
        }
        if ($_GET['i']) {
            header("lyhiving: ".self::$_fileName);
        }
        return $buffer;
    }
}
if (!function_exists('_webCacheCallback')) {
    function _webCacheCallback(&$buffer)
    {
        return webcache::callback($buffer);
    }
}
