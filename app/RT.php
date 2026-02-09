<?php
declare(strict_types=1);

namespace rosasurfer\rt;

use rosasurfer\ministruts\Application;
use rosasurfer\ministruts\config\ConfigInterface as Config;
use rosasurfer\ministruts\core\StaticClass;
use rosasurfer\ministruts\core\exception\RuntimeException;
use rosasurfer\ministruts\file\FileSystem as FS;

use function rosasurfer\ministruts\echof;
use function rosasurfer\ministruts\strRightFrom;
use function rosasurfer\ministruts\strStartsWith;

use const rosasurfer\ministruts\DAY;
use const rosasurfer\ministruts\HOURS;
use const rosasurfer\ministruts\MINUTES;

use rosasurfer\rt\lib\rosatrader\Rost;
use rosasurfer\rt\model\RosaSymbol;


/**
 * Another class with common functionality.
 *
 * @phpstan-import-type RT_POINT_BAR from \rosasurfer\rt\phpstan\CustomTypes
 * @phpstan-import-type RT_PRICE_BAR from \rosasurfer\rt\phpstan\CustomTypes
 */
class RT extends StaticClass {


    /**
     * Convert a point bar timeseries to a price bar timeseries.
     *
     * @param          array[]        $pointBars - point bars
     * @phpstan-param  RT_POINT_BAR[] $pointBars
     * @param          float          $pointSize - decimal resolution of a price unit, e.g. pointSize(1.00) => 0.01
     *
     * @return         array[] - price bars
     * @phpstan-return RT_PRICE_BAR[]
     *
     * @see \rosasurfer\rt\phpstan\RT_POINT_BAR
     * @see \rosasurfer\rt\phpstan\RT_PRICE_BAR
     */
    public static function convertPointToPriceBars(array $pointBars, float $pointSize): array {
        $priceBars = [];

        foreach ($pointBars as $pointBar) {
            $priceBar = [];
            $priceBar['time' ] = $pointBar['time' ];
            $priceBar['open' ] = $pointBar['open' ] * $pointSize;
            $priceBar['high' ] = $pointBar['high' ] * $pointSize;
            $priceBar['low'  ] = $pointBar['low'  ] * $pointSize;
            $priceBar['close'] = $pointBar['close'] * $pointSize;
            $priceBar['ticks'] = $pointBar['ticks'];
            $priceBars[] = $priceBar;
        }
        return $priceBars;
    }


    /**
     * Read a Rosatrader history file and return a timeseries array.
     *
     * @param  string     $fileName - file name
     * @param  RosaSymbol $symbol   - instrument the data belongs to
     *
     * @return         array<int[]> - timeseries array
     * @phpstan-return RT_POINT_BAR[]
     *
     * @see \rosasurfer\rt\phpstan\RT_POINT_BAR
     */
    public static function readBarFile(string $fileName, RosaSymbol $symbol): array {
        return self::readBarData(file_get_contents($fileName), $symbol);
    }


    /**
     * Convert a string with Rosatrader point bar data into an array.
     *
     * @param  string     $data
     * @param  RosaSymbol $symbol - instrument the data belongs to
     *
     * @return         array[] - point bars
     * @phpstan-return RT_POINT_BAR[]
     *
     * @see \rosasurfer\rt\phpstan\RT_POINT_BAR
     */
    public static function readBarData(string $data, RosaSymbol $symbol): array {
        $lenData = strlen($data);
        if ($lenData % Rost::RT_POINT_BAR_SIZE) throw new RuntimeException('Odd length of passed '.$symbol->getName().' data: '.$lenData.' (not an even Rost::BAR_SIZE)');

        $bars = [];
        for ($offset=0; $offset < $lenData; $offset += Rost::RT_POINT_BAR_SIZE) {
            $bars[] = unpack("@$offset/Vtime/Vopen/Vhigh/Vlow/Vclose/Vticks", $data);
        }
        return $bars;
    }


    /**
     * Save the M1 bars of a day to the file system.
     *
     * @param         array[]                          $bars
     * @phpstan-param array<RT_POINT_BAR|RT_PRICE_BAR> $bars
     * @param         RosaSymbol                       $symbol - instrument the data belongs to
     *
     * @return bool - success status
     *
     * @see \rosasurfer\rt\phpstan\RT_POINT_BAR
     * @see \rosasurfer\rt\phpstan\RT_PRICE_BAR
     */
    public static function saveM1Bars(array $bars, RosaSymbol $symbol): bool {
        // validate bar range
        $opentime = $bars[0]['time'];
        if ($opentime % DAY)                                   throw new RuntimeException('Invalid daily M1 data, first bar opentime: '.gmdate('D, d-M-Y H:i:s', $opentime));
        $day = $opentime;
        if (($size=sizeof($bars)) != PERIOD_D1)                throw new RuntimeException('Invalid number of M1 bars for '.gmdate('D, d-M-Y', $day).': '.$size);
        if ($bars[$size-1]['time']%DAY != 23*HOURS+59*MINUTES) throw new RuntimeException('Invalid daily M1 data, last bar opentime: '.gmdate('D, d-M-Y H:i:s', $bars[$size-1]['time']));

        $isPriceBar = !is_int($bars[0]['open']);
        $point = $symbol->getPointValue();

        // concat all bars to a large binary string
        $data = '';
        foreach ($bars as $bar) {
            $time  = $bar['time' ];
            $open  = $bar['open' ];
            $high  = $bar['high' ];
            $low   = $bar['low'  ];
            $close = $bar['close'];
            $ticks = $bar['ticks'];

            if ($isPriceBar) {
                $open  = (int) round($open /$point);            // storing RT_POINT_BARs saves 40% storage place compared to PRICE_BARs
                $high  = (int) round($high /$point);
                $low   = (int) round($low  /$point);
                $close = (int) round($close/$point);
            }                                                   // final bar validation
            if ($open > $high || $open < $low || $close > $high || $close < $low || !$ticks) {
                throw new RuntimeException('Illegal M1 bar data for '.gmdate('D, d-M-Y H:i:s', $time).":  O=$open  H=$high  L=$low  C=$close  V=$ticks");
            }
            $data .= pack('VVVVVV', $time, $open, $high, $low, $close, $ticks);
        }

        // delete existing files
        $storageDir  = Application::service('config')['app.dir.data'];
        $storageDir .= '/history/rosatrader/'.$symbol->getType().'/'.$symbol->getName();
        $dir         = "$storageDir/".gmdate('Y/m/d', $day);
        $msg         = '[Info]    '.$symbol->getName().'  deleting existing M1 file: ';
        if (is_file($file=$dir.'/M1.bin'    )) { echof($msg.self::relativePath($file)); unlink($file); }
        if (is_file($file=$dir.'/M1.bin.rar')) { echof($msg.self::relativePath($file)); unlink($file); }

        // write data to new file
        $file = "$dir/M1.bin";
        FS::mkDir(dirname($file));
        $tmpFile = tempnam(dirname($file), basename($file));    // make sure an existing file can't be corrupt
        file_put_contents($tmpFile, $data);
        rename($tmpFile, $file);
        return true;
    }


    /**
     * Return the configured mail addresses of signal receivers by email.
     *
     * @return string[] - mail addresses
     */
    public static function getMailSignalReceivers(): array {
        static $addresses;
        return $addresses ??= (function() {
            /** @var Config $config */
            $config = Application::service('config');
            $values = $config->get('mail.signalreceivers', '');

            $addresses = [];
            foreach (explode(',', $values) as $address) {
                if ($address = trim($address)) {
                    $addresses[] = $address;
                }
            }
            return $addresses;
        })();
    }


    /**
     * Return the configured phone numbers of signal receivers by text message.
     *
     * @return string[] - phone numbers
     */
    public static function getSmsSignalReceivers(): array {
        static $numbers;
        return $numbers ??= (function() {
            /** @var Config $config */
            $config = Application::service('config');
            $values = $config->get('sms.signalreceivers', '');

            $numbers = [];
            foreach (explode(',', $values) as $number) {
                if ($number = trim($number)) {
                    $numbers[] = $number;
                }
            }
            return $numbers;
        })();
    }


    /**
     * Convert an absolute file path to a project-relative one.
     *
     * @param  string $path
     *
     * @return string
     */
    public static function relativePath(string $path): string {
        $_path = str_replace('\\', '/', $path);

        static $root, $realRoot, $storage, $realStorage;
        if (!$root) {
            $config      = Application::service('config');
            $root        = str_replace('\\', '/', $config['app.dir.root'].'/');
            $realRoot    = str_replace('\\', '/', realpath($root).'/');
            $storage     = str_replace('\\', '/', $config['app.dir.data'].'/');
            $realStorage = str_replace('\\', '/', realpath($storage).'/');
        }

        if (strStartsWith($_path, $root))        return           strRightFrom($_path, $root);
        if (strStartsWith($_path, $realRoot))    return           strRightFrom($_path, $realRoot);
        if (strStartsWith($_path, $storage))     return '{data}/'.strRightFrom($_path, $storage);
        if (strStartsWith($_path, $realStorage)) return '{data}/'.strRightFrom($_path, $realStorage);

        return $path;
    }
}
