<?php
namespace rosasurfer\rt\lib;

use rosasurfer\core\StaticClass;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\file\FileSystem as FS;

use rosasurfer\rt\model\RosaSymbol;
use const rosasurfer\rt\PERIOD_D1;


/**
 * Rosatrader related functionality.
 */
class Rosatrader extends StaticClass {


    /**
     * Read a Rosatrader history file and return a timeseries array.
     *
     * @param  string     $fileName - file name
     * @param  RosaSymbol $symbol   - instrument the data belongs to
     *
     * @return array[] - array with each element describing a bar as follows:
     *
     * <pre>
     * Array(
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (double),         // open value
     *     'high'  => (double),         // high value
     *     'low'   => (double),         // low value
     *     'close' => (double),         // close value
     *     'ticks' => (int),            // ticks or volume (if available)
     * )
     * </pre>
     */
    public static function readBarFile($fileName, RosaSymbol $symbol) {
        if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.gettype($fileName));
        return static::readBarData(file_get_contents($fileName), $symbol);
    }


    /**
     * Convert a string with Rosatrader bar data into a timeseries array.
     *
     * @param  string     $data
     * @param  RosaSymbol $symbol - instrument the data belongs to
     *
     * @return array[] - array with each element describing a bar as follows:
     *
     * <pre>
     * Array(
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (double),         // open value
     *     'high'  => (double),         // high value
     *     'low'   => (double),         // low value
     *     'close' => (double),         // close value
     *     'ticks' => (int),            // ticks or volume (if available)
     * )
     * </pre>
     */
    public static function readBarData($data, RosaSymbol $symbol) {
        if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));

        $lenData = strlen($data); if ($lenData % Rost::BAR_SIZE) throw new RuntimeException('Odd length of passed '.$symbol->getName().' data: '.$lenData.' (not an even Rost::BAR_SIZE)');
        $bars  = [];
        $point = $symbol->getPointValue();

        for ($offset=0; $offset < $lenData; $offset += Rost::BAR_SIZE) {
            $bar = unpack("@$offset/Vtime/Vopen/Vhigh/Vlow/Vclose/Vticks", $data);
            $bar['open' ] *= $point;
            $bar['high' ] *= $point;
            $bar['low'  ] *= $point;
            $bar['close'] *= $point;
            $bars[] = $bar;
        }
        return $bars;
    }


    /**
     * Save a timeseries array with M1 bars of a single day to the file system.
     *
     * @param  array[]    $bars   - bar data
     * @param  RosaSymbol $symbol - instrument the data belongs to
     *
     * @return bool - success status
     */
    public static function saveM1Bars(array $bars, RosaSymbol $symbol) {
        // validate bar range
        $opentime = $bars[0]['time'];
        if ($opentime % DAY)                                   throw new RuntimeException('Invalid daily M1 data, first bar opentime: '.gmdate('D, d-M-Y H:i:s', $opentime));
        $day = $opentime;
        if (($size=sizeof($bars)) != PERIOD_D1)                throw new RuntimeException('Invalid number of M1 bars for '.gmdate('D, d-M-Y', $day).': '.$size);
        if ($bars[$size-1]['time']%DAY != 23*HOURS+59*MINUTES) throw new RuntimeException('Invalid daily M1 data, last bar opentime: '.gmdate('D, d-M-Y H:i:s', $bars[$size-1]['time']));

        $optimized = is_int($bars[0]['open']);
        $point = $symbol->getPointValue();

        // convert all bars to a one large binary string
        $data = '';
        foreach ($bars as $bar) {
            $time  = $bar['time' ];
            $open  = $bar['open' ];
            $high  = $bar['high' ];
            $low   = $bar['low'  ];
            $close = $bar['close'];
            $ticks = $bar['ticks'];

            if (!$optimized) {
                $open  = (int) round($open /$point);    // storing price values in points saves 40% storage place
                $high  = (int) round($high /$point);
                $low   = (int) round($low  /$point);
                $close = (int) round($close/$point);
            }                                           // final bar validation
            if ($open > $high || $open < $low || $close > $high || $close < $low || !$ticks)
                throw new RuntimeException('Illegal M1 bar data for '.gmdate('D, d-M-Y H:i:s', $time).":  O=$open  H=$high  L=$low  C=$close  V=$ticks");

            $data .= pack('VVVVVV', $time, $open, $high, $low, $close, $ticks);
        }

        // delete existing files
        $storageDir  = self::di('config')['app.dir.storage'];
        $storageDir .= '/history/rosatrader/'.$symbol->getType().'/'.$symbol->getName();
        $dir         = $storageDir.'/'.gmdate('Y/m/d', $day);
        $msg         = '[Info]    '.$symbol->getName().'  deleting existing M1 file: ';
        is_file($file=$dir.'/M1.bin'    ) && true(echoPre($msg.static::relativePath($file))) && unlink($file);
        is_file($file=$dir.'/M1.bin.rar') && true(echoPre($msg.static::relativePath($file))) && unlink($file);

        // write data to new file
        $file = $dir.'/M1.bin';
        FS::mkDir(dirname($file));
        $tmpFile = tempnam(dirname($file), basename($file));    // make sure an existing file can't be corrupt
        file_put_contents($tmpFile, $data);
        rename($tmpFile, $file);
        return true;
    }


    /**
     * Convert an absolute file path to a project-relative one.
     *
     * @param  string $path
     *
     * @return string
     */
    public static function relativePath($path) {
        if (!is_string($path)) throw new IllegalTypeException('Illegal type of parameter $path: '.gettype($path));
        $_path = str_replace('\\', '/', $path);

        static $root, $realRoot, $storage, $realStorage;
        if (!$root) {
            $config      = self::di('config');
            $root        = str_replace('\\', '/', $config['app.dir.root'].'/');
            $realRoot    = str_replace('\\', '/', realpath($root).'/');
            $storage     = str_replace('\\', '/', $config['app.dir.storage'].'/');
            $realStorage = str_replace('\\', '/', realpath($storage).'/');
        }

        if (strStartsWith($_path, $root))        return           strRightFrom($_path, $root);
        if (strStartsWith($_path, $realRoot))    return           strRightFrom($_path, $realRoot);
        if (strStartsWith($_path, $storage))     return '{data}/'.strRightFrom($_path, $storage);
        if (strStartsWith($_path, $realStorage)) return '{data}/'.strRightFrom($_path, $realStorage);

        return $path;
    }
}
