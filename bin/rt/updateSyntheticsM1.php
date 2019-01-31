#!/usr/bin/env php
<?php
/**
 * Update the M1 history of synthetic Rosatrader instruments.
 *
 * @see  https://github.com/rosasurfer/mt4-tools/blob/master/app/lib/synthetic/README.md
 */
namespace rosasurfer\rt\update_synthetics_m1;

use rosasurfer\Application;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\process\Process;

use rosasurfer\rt\model\RosaSymbol;

require(dirName(realPath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');


// -- configuration ---------------------------------------------------------------------------------------------------------


$verbose = 0;                               // output verbosity


// -- start -----------------------------------------------------------------------------------------------------------------


// (1) parse and validate CLI arguments
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// parse options
foreach ($args as $i => $arg) {
    if ($arg == '-h'  )   exit(1|help());                                               // help
    if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; }    // verbose output
    if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; }    // more verbose output
    if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; }    // very verbose output
}

/** @var RosaSymbol[] $symbols */
$symbols = [];

// parse symbols
foreach ($args as $i => $arg) {
    /** @var RosaSymbol $symbol */
    $symbol = RosaSymbol::dao()->findByName($arg);
    if (!$symbol)                exit(1|stderror('error: unknown symbol "'.$args[$i].'"'));
    if (!$symbol->isSynthetic()) exit(1|stderror('error: not a synthetic instrument "'.$symbol->getName().'"'));
    $symbols[$symbol->getName()] = $symbol;                                             // using the name as index removes duplicates
}
$symbols = $symbols ?: RosaSymbol::dao()->findAllByType(RosaSymbol::TYPE_SYNTHETIC);    // if none is specified update all synthetics
!$symbols && echoPre('no synthetic instruments found');


// (2) update instruments
foreach ($symbols as $symbol) {
    if ($symbol->updateHistory())
        echoPre('[Ok]      '.$symbol->getName());
    Process::dispatchSignals();                                                         // check for Ctrl-C
}
exit(0);


// --- functions ------------------------------------------------------------------------------------------------------------


/**
 * Berechnet fuer die uebergebenen M1-Daten den EURX-Index (ICE).
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: EURX = 34.38805726 * EURCHF^0.1113 * EURGBP^0.3056 * EURJPY^0.1891 * EURSEK^0.0785 * EURUSD^0.3155
 */
function calculateEURX($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    EURX  '.$shortDate);

    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $USDSEK = $data['USDSEK']['bars'];
    $index  = [];

    foreach ($EURUSD as $i => $bar) {
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $usdsek = $USDSEK[$i]['open'];
        $open   = 34.38805726
                  * pow($eurusd/100000 * $usdchf/100000, 0.1113)
                  * pow($eurusd        / $gbpusd       , 0.3056)
                  * pow($eurusd/100000 * $usdjpy/1000  , 0.1891)
                  * pow($eurusd/100000 * $usdsek/100000, 0.0785)
                  * pow($eurusd/100000                 , 0.3155);
        $iOpen  = (int) round($open * 1000);

        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $usdsek = $USDSEK[$i]['close'];
        $close  = 34.38805726
                  * pow($eurusd/100000 * $usdchf/100000, 0.1113)
                  * pow($eurusd        / $gbpusd       , 0.3056)
                  * pow($eurusd/100000 * $usdjpy/1000  , 0.1891)
                  * pow($eurusd/100000 * $usdsek/100000, 0.0785)
                  * pow($eurusd/100000                 , 0.3155);
        $iClose = (int) round($close * 1000);

        $index[$i]['time' ] = $bar['time'];
        $index[$i]['open' ] = $iOpen;
        $index[$i]['high' ] = $iOpen > $iClose ? $iOpen : $iClose;        // min()/max() ist nicht performant
        $index[$i]['low'  ] = $iOpen < $iClose ? $iOpen : $iClose;
        $index[$i]['close'] = $iClose;
        $index[$i]['ticks'] = $iOpen==$iClose ? 1 : (abs($iOpen-$iClose) << 1);
    }
    return $index;
}


/**
 * Berechnet fuer die uebergebenen M1-Daten den USDX-Index (ICE).
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: USDX = 50.14348112 * (USDCAD^0.091 * USDCHF^0.036 * USDJPY^0.136 * USDSEK^0.042) / (EURUSD^0.576 * GBPUSD^0.119)
 */
function calculateUSDX($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    USDX  '.$shortDate);

    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $USDSEK = $data['USDSEK']['bars'];
    $index  = [];

    foreach ($EURUSD as $i => $bar) {
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $usdsek = $USDSEK[$i]['open'];
        $open   = 50.14348112
                  * pow($usdcad/100000, 0.091) * pow($usdchf/100000, 0.036) * pow($usdjpy/1000, 0.136) * pow($usdsek/100000, 0.042)
                  / pow($eurusd/100000, 0.576) / pow($gbpusd/100000, 0.119);
        $iOpen  = (int) round($open * 1000);

        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $usdsek = $USDSEK[$i]['close'];
        $close  = 50.14348112
                  * pow($usdcad/100000, 0.091) * pow($usdchf/100000, 0.036) * pow($usdjpy/1000, 0.136) * pow($usdsek/100000, 0.042)
                  / pow($eurusd/100000, 0.576) / pow($gbpusd/100000, 0.119);
        $iClose = (int) round($close * 1000);

        $index[$i]['time' ] = $bar['time'];
        $index[$i]['open' ] = $iOpen;
        $index[$i]['high' ] = $iOpen > $iClose ? $iOpen : $iClose;        // min()/max() ist nicht performant
        $index[$i]['low'  ] = $iOpen < $iClose ? $iOpen : $iClose;
        $index[$i]['close'] = $iClose;
        $index[$i]['ticks'] = $iOpen==$iClose ? 1 : (abs($iOpen-$iClose) << 1);
    }
    return $index;
}


/**
 * Erzeugt und verwaltet dynamisch generierte Variablen.
 *
 * Evaluiert und cacht staendig wiederbenutzte dynamische Variablen an einem zentralen Ort. Vereinfacht die Logik,
 * da die Variablen nicht global gespeichert oder ueber viele Funktionsaufrufe hinweg weitergereicht werden muessen,
 * aber trotzdem nicht bei jeder Verwendung neu ermittelt werden brauchen.
 *
 * @param  string $id     - eindeutiger Bezeichner der Variable (ID)
 * @param  string $symbol - Symbol oder NULL
 * @param  int    $time   - Timestamp oder NULL
 *
 * @return string - Variable
 */
function getVar($id, $symbol=null, $time=null) {
    static $varCache = [];
    if (array_key_exists(($key=$id.'|'.$symbol.'|'.$time), $varCache))
        return $varCache[$key];

    if (!is_string($id))                       throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
    if (isSet($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
    if (isSet($time) && !is_int($time))        throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

    static $dataDir; !$dataDir && $dataDir = Application::getConfig()['app.dir.data'];
    $self = __FUNCTION__;

    if ($id == 'rtDir') {                       // $dataDir/history/rosatrader/$type/$symbol/$rtDirDate     // lokales Verzeichnis
        $type      = RosaSymbol::dao()->getByName($symbol)->getType();
        $rtDirDate = $self('rtDirDate', null, $time);
        $result    = $dataDir.'/history/rosatrader/'.$type.'/'.$symbol.'/'.$rtDirDate;
    }
    else if ($id == 'rtDirDate') {              // $yyyy/$mm/$dd                                            // lokales Pfad-Datum
        if (!$time) throw new InvalidArgumentException('Invalid parameter $time: '.$time);
        $result = gmDate('Y/m/d', $time);
    }
    else if ($id == 'rtFile.raw') {             // $rtDir/M1.bin                                            // lokale Datei ungepackt
        $rtDir  = $self('rtDir', $symbol, $time);
        $result = $rtDir.'/M1.bin';
    }
    else if ($id == 'rtFile.compressed') {      // $rtDir/M1.rar                                            // lokale Datei gepackt
        $rtDir  = $self('rtDir', $symbol, $time);
        $result = $rtDir.'/M1.rar';
    }
    else {
      throw new InvalidArgumentException('Unknown variable identifier "'.$id.'"');
    }

    $varCache[$key] = $result;
    (sizeof($varCache) > ($maxSize=128)) && array_shift($varCache) /*&& echoPre('cache size limit of '.$maxSize.' hit')*/;

    return $result;
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message [optional] - zusaetzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message = null) {
    if (isSet($message))
        echo $message.NL.NL;

    $self = baseName($_SERVER['PHP_SELF']);

echo <<<HELP
 Update the M1 history of the specified synthetic symbols.

 Syntax:  $self [SYMBOL...]

   SYMBOL    One or more symbols to update. Without a symbol all defined synthetic symbols are updated.

   Options:  -v    Verbose output.
             -vv   More verbose output.
             -vvv  Very verbose output.
             -h    This help screen.


HELP;
}
