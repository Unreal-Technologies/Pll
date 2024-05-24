<?php
class PllLoader
{
    public static function initialize(string $library)
    {
        if(!preg_match('/^.+\:([0-9][\.]?){4}$/i', $library))
        {
            throw new \Exception('Invalid library: "'.$library.'"');
        }
        list($name, $version) = explode(':', $library);
        
        $dir = $name;
        $file = $dir.'.pll';

        if(file_exists($file))
        {
            PllLoader::fileMode($file, $version);
        }
        else if(file_exists($dir))
        {
            PllLoader::directoryMode($dir);
        }
        else
        {
            throw new \Exception('Could not locate "'.$library.'"');
        }
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
        
        return $v1.'.'.$v2.'.'.$v3.'.'.$v4;
    }
    
    private static function fileMode(string $file, string $version): void
    {
        $pi = pathinfo($file);
        if(!file_exists($pi['filename']))
        {
            mkdir($pi['filename'], 0777);
        }
        
        spl_autoload_register(function(string $className) use($file, $pi, $version)
        {
            list($ns, $class) = self::parseClass($className);
            $nsFile = $pi['filename'].'/'.$version.'/'.preg_replace('/[^a-z0-9]+/i', '+', $ns).'.php';
            if(file_exists($nsFile))
            {
                require_once $nsFile;
                return;
            }

            $fh = fopen($file, 'rb');
            if(!$fh)
            {
                return;
            }
            
            $vPad = self::decodeInt(fread($fh, 1));
            $fileversion = self::decodeVersion($fh, $vPad);
            
            if($fileversion !== $version)
            {
                throw new \Exception('Version mismatch');
            }
            $mLen = self::decodeInt(fread($fh, 4));
            $map = (array)json_decode(gzdecode(fread($fh, $mLen)));
            
            $pos = array_search($ns, array_keys($map));
            if(!$pos && $pos !== 0)
            {
                return;
            }
            
            $mSlice = array_slice($map, 0, $pos);
            $skip = array_sum($mSlice);
            if($skip > 0)
            {
                fread($fh, $skip);
            }
            
            $fileData = gzdecode(fread($fh, $map[$ns]));

            fclose($fh);
            
            $pi2 = pathinfo($nsFile);
            if(!file_exists($pi2['dirname']))
            {
                mkdir($pi2['dirname'], 0777);
            }
            
            file_put_contents($nsFile, $fileData);
            require_once $nsFile;
        });
    }
    
    /**
     * @param string $value
     * @return int
     */
    private static function decodeInt(string $value): int
    {
        $binary = '';
        for($i=0; $i<strlen($value); $i++)
        {
            $binary .= str_pad(decbin(ord($value[$i])), 8, '0', 0);
        }
        
        return bindec($binary);
    }
    
    private static function parseClass($className)
    {
        $parts = explode('\\', $className);
        if(count($parts) === 1)
        {
            return [null, $parts[0]];
        }
        
        $last = $parts[count($parts) - 1];
        $ns = array_slice($parts, 0, count($parts) - 1);
        return [
            implode('\\', $ns),
            $last
        ];
    }
    
    /**
     * @param string $dir
     * @return void
     */
    private static function directoryMode(string $dir): void
    {
        spl_autoload_register(function(string $className) use($dir)
        {
            $parts = explode('\\', $className);
            $target = $dir.'/Sources/'.implode('/', array_slice($parts, 1, count($parts))).'.php';
            
            require_once($target);
        });
    }
}