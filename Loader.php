<?php

namespace Pll;

class Loader
{
    private const DEBUG_VERSION = '0.0.0.-1';

    /**
     * @param string $name
     * @param string $directory
     * @return string
     * @throws \Exception
     */
    public static function initialize(string $name, string $directory = ''): string
    {
        $path = realpath(($directory === '' ? './' : $directory . '/')) . '/' . $name;
        $dir = realpath($path);
        $file = ( !$dir ? $path : $dir ) . '.pll';

        if (file_exists($file)) {
            return Loader::fileMode($file);
        } elseif (file_exists($dir)) {
            Loader::directoryMode($dir);
        } else {
            throw new \Exception('Could not locate "' . $name . '"');
        }

        return self::DEBUG_VERSION;
    }

    /**
     * @param string $directory
     * @return string
     */
    public static function packageDevelop(string $directory): string
    {
        $path = realpath($directory);
        if ($path) {
            spl_autoload_register(function (string $className) use ($path) {
                $parts = explode('\\', $className);
                $right = array_slice($parts, 1);
                $file = $path . '\\' . implode('\\', $right) . '.php';

                require_once $file;
            });
        }

        return self::DEBUG_VERSION;
    }

    /**
     * @param mixed $fh
     * @param int $vPad
     * @return string
     */
    private static function decodeVersion(mixed $fh, int $vPad): string
    {
        $v1 = self::decodeInt(fread($fh, $vPad));
        $v2 = self::decodeInt(fread($fh, $vPad));
        $v3 = self::decodeInt(fread($fh, $vPad));
        $v4 = self::decodeInt(fread($fh, $vPad));

        return $v1 . '.' . $v2 . '.' . $v3 . '.' . $v4;
    }

    /**
     * @param string $file
     * @return string
     */
    private static function fileMode(string $file): string
    {
        $pi = pathinfo($file);
        $mt = filemtime($file);

        $fh = fopen($file, 'rb');
        if (!$fh) {
            return self::DEBUG_VERSION;
        }

        $vPad = self::decodeInt(fread($fh, 1));
        $fileversion = self::decodeVersion($fh, $vPad);
        $skipVersion = 1 + ($vPad * 4);

        $directory = __DIR__ . '/' . $fileversion . '/' . $pi['filename'] . '@' . $mt;

        fclose($fh);

        spl_autoload_register(function (string $className) use ($file, $directory, $skipVersion) {
            $ns = self::parseNamespace($className);
            $nsFile = $directory . '/' . preg_replace('/[^a-z0-9]+/i', '+', $ns) . '.cphp';
            if (file_exists($nsFile)) {
                require_once $nsFile;
                return;
            }

            $fh = fopen($file, 'rb');
            if (!$fh) {
                return;
            }

            if ($skipVersion > 0) {
                fread($fh, $skipVersion);
            }
            $mLen = self::decodeInt(fread($fh, 4));
            $map = (array)json_decode(gzdecode(fread($fh, $mLen)));

            $pos = array_search($ns, array_keys($map));
            if (!$pos && $pos !== 0) {
                return;
            }

            $mSlice = array_slice($map, 0, $pos);
            $skip = array_sum($mSlice);
            if ($skip > 0) {
                fread($fh, $skip);
            }

            $fileData = gzdecode(fread($fh, $map[$ns]));

            fclose($fh);

            $pi2 = pathinfo($nsFile);
            if (!file_exists($pi2['dirname'])) {
                mkdir($pi2['dirname'], 0777, true);
            }

            file_put_contents($nsFile, $fileData);
            require_once $nsFile;
        });

        return $fileversion;
    }

    /**
     * @param string $value
     * @return int
     */
    private static function decodeInt(string $value): int
    {
        $binary = '';
        for ($i = 0; $i < strlen($value); $i++) {
            $binary .= str_pad(decbin(ord($value[$i])), 8, '0', 0);
        }

        return bindec($binary);
    }

    /**
     * @param string $className
     * @return string|null
     */
    private static function parseNamespace(string $className): ?string
    {
        $parts = explode('\\', $className);
        if (count($parts) === 1) {
            return null;
        }

        $ns = array_slice($parts, 0, count($parts) - 1);
        return implode('\\', $ns);
    }

    /**
     * @param string $dir
     * @return void
     */
    private static function directoryMode(string $dir): void
    {
        spl_autoload_register(function (string $className) use ($dir) {
            if ($className[0] === '\\') {
                $className = substr($className, 1);
            }

            $parts = explode('\\', $className);
            $target = $dir . '/' . implode('/', array_slice($parts, 1, count($parts))) . '.php';

            require_once($target);
        });
    }
}
