<?php
namespace rosasurfer\rost;

use rosasurfer\core\StaticClass;
use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rost\model\RosaSymbol;
use rosasurfer\exception\RuntimeException;


/**
 * Rosatrader related functionality.
 */
class RT extends StaticClass {


    /**
     * Read a Rosatrader history file and return a timeseries array.
     *
     * @param  string     $fileName - file name
     * @param  RosaSymbol $symbol   - instrument the data belongs to
     *
     * @return array[] - array with each element describing a bar as following:
     *
     * <pre>
     * Array [
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (double),         // open value
     *     'high'  => (double),         // high value
     *     'low'   => (double),         // low value
     *     'close' => (double),         // close value
     *     'ticks' => (int),            // ticks or volume (if available)
     * ]
     * </pre>
     */
    public static function readBarFile($fileName, RosaSymbol $symbol) {
        if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.getType($fileName));
        return self::readBarData(file_get_contents($fileName), $symbol);
    }


    /**
     * Read a string with Rosatrader bar data and convert it to a timeseries array.
     *
     * @param  string     $data
     * @param  RosaSymbol $symbol - instrument the data belongs to
     *
     * @return array[] - array with each element describing a bar as following:
     *
     * <pre>
     * Array [
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (double),         // open value
     *     'high'  => (double),         // high value
     *     'low'   => (double),         // low value
     *     'close' => (double),         // close value
     *     'ticks' => (int),            // ticks or volume (if available)
     * ]
     * </pre>
     */
    public static function readBarData($data, RosaSymbol $symbol) {
        if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));

        $lenData = strLen($data); if ($lenData % Rost::BAR_SIZE) throw new RuntimeException('Odd length of passed '.$symbol->getName().' data: '.$lenData.' (not an even Rost::BAR_SIZE)');
        $bars  = [];
        $point = $symbol->getPoint();

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
}
