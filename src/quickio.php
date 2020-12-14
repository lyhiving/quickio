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
}
