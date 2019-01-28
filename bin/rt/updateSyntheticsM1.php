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
 * Berechnet fuer die uebergebenen M1-Daten den AUDFX7-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: AUDFX7 = ((AUDCAD * AUDCHF * AUDJPY * AUDNZD * AUDUSD ) / (EURAUD * GBPAUD)) ^ 1/7
 */
function calculateAUDFX7($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    AUDFX7  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $NZDUSD = $data['NZDUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $nzdusd = $NZDUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = pow(($usdcad/$eurusd) * ($usdchf/$gbpusd) * ($usdjpy/$nzdusd), 1/7) * $audusd;
        $iOpen  = (int) round($open);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $nzdusd = $NZDUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = pow(($usdcad/$eurusd) * ($usdchf/$gbpusd) * ($usdjpy/$nzdusd), 1/7) * $audusd;
        $iClose = (int) round($close);

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
 * Berechnet fuer die uebergebenen M1-Daten den CADFX6-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: CADFX6 = ((CADCHF * CADJPY) / (AUDCAD * EURCAD * GBPCAD * USDCAD)) ^ 1/6
 */
function calculateCADFX6($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    CADFX6  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = pow(($usdchf/$audusd) * ($usdjpy/$eurusd) * (100000/$gbpusd) * 100, 1/6) / $usdcad * 100000;
        $iOpen  = (int) round($open * 100000);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = pow(($usdchf/$audusd) * ($usdjpy/$eurusd) * (100000/$gbpusd) * 100, 1/6) / $usdcad * 100000;
        $iClose = (int) round($close * 100000);

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
 * Berechnet fuer die uebergebenen M1-Daten den CADFX7-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: CADFX7 = ((CADCHF * CADJPY) / (AUDCAD * EURCAD * GBPCAD * NZDCAD * USDCAD)) ^ 1/7
 */
function calculateCADFX7($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    CADFX7  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $NZDUSD = $data['NZDUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $nzdusd = $NZDUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = pow(($usdchf/$audusd) * ($usdjpy/$eurusd) * (100000/$gbpusd) * (100000/$nzdusd) * 100, 1/7) / $usdcad * 100000;
        $iOpen  = (int) round($open * 100000);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $nzdusd = $NZDUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = pow(($usdchf/$audusd) * ($usdjpy/$eurusd) * (100000/$gbpusd) * (100000/$nzdusd) * 100, 1/7) / $usdcad * 100000;
        $iClose = (int) round($close * 100000);

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
 * Berechnet fuer die uebergebenen M1-Daten den CHFFX6-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: CHFFX6 = (CHFJPY / (AUDCHF * CADCHF * EURCHF * GBPCHF * USDCHF)) ^ 1/6
 */
function calculateCHFFX6($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    CHFFX6  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = pow(($usdcad/$audusd) * ($usdjpy/$eurusd) * (100000/$gbpusd) * 100, 1/6) / $usdchf * 100000;
        $iOpen  = (int) round($open * 100000);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = pow(($usdcad/$audusd) * ($usdjpy/$eurusd) * (100000/$gbpusd) * 100, 1/6) / $usdchf * 100000;
        $iClose = (int) round($close * 100000);

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
 * Berechnet fuer die uebergebenen M1-Daten den CHFFX7-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: CHFFX7 = (CHFJPY / (AUDCHF * CADCHF * EURCHF * GBPCHF * NZDCHF * USDCHF)) ^ 1/7
 */
function calculateCHFFX7($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    CHFFX7  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $NZDUSD = $data['NZDUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $nzdusd = $NZDUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = pow(($usdcad/$audusd) * ($usdjpy/$eurusd) * (100000/$gbpusd) * (100000/$nzdusd) * 100, 1/7) / $usdchf * 100000;
        $iOpen  = (int) round($open * 100000);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $nzdusd = $NZDUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = pow(($usdcad/$audusd) * ($usdjpy/$eurusd) * (100000/$gbpusd) * (100000/$nzdusd) * 100, 1/7) / $usdchf * 100000;
        $iClose = (int) round($close * 100000);

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
 * Berechnet fuer die uebergebenen M1-Daten den EURFX6-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: EURFX6 = (EURAUD * EURCAD * EURCHF * EURGBP * EURJPY * EURUSD) ^ 1/6
 */
function calculateEURFX6($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    EURFX6  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = pow(($usdcad/$audusd) * ($usdchf/$gbpusd) * ($usdjpy/1000), 1/6) * $eurusd;
        $iOpen  = (int) round($open);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = pow(($usdcad/$audusd) * ($usdchf/$gbpusd) * ($usdjpy/1000), 1/6) * $eurusd;
        $iClose = (int) round($close);

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
 * Berechnet fuer die uebergebenen M1-Daten den EURFX7-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: EURFX7 = (EURAUD * EURCAD * EURCHF * EURGBP * EURJPY * EURNZD * EURUSD) ^ 1/7
 */
function calculateEURFX7($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    EURFX7  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $NZDUSD = $data['NZDUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $nzdusd = $NZDUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = pow(($usdcad/$audusd) * ($usdchf/$gbpusd) * ($usdjpy/$nzdusd) * 100, 1/7) * $eurusd;
        $iOpen  = (int) round($open);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $nzdusd = $NZDUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = pow(($usdcad/$audusd) * ($usdchf/$gbpusd) * ($usdjpy/$nzdusd) * 100, 1/7) * $eurusd;
        $iClose = (int) round($close);

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
 * Berechnet fuer die uebergebenen M1-Daten den GBPFX6-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: GBPFX6 = ((GBPAUD * GBPCAD * GBPCHF * GBPJPY * GBPUSD) / EURGBP) ^ 1/6
 */
function calculateGBPFX6($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    GBPFX6  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/1000), 1/6) * $gbpusd;
        $iOpen  = (int) round($open);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/1000), 1/6) * $gbpusd;
        $iClose = (int) round($close);

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
 * Berechnet fuer die uebergebenen M1-Daten den GBPFX7-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: GBPFX7 = ((GBPAUD * GBPCAD * GBPCHF * GBPJPY * GBPNZD * GBPUSD) / EURGBP) ^ 1/7
 */
function calculateGBPFX7($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    GBPFX7  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $NZDUSD = $data['NZDUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $nzdusd = $NZDUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$nzdusd) * 100, 1/7) * $gbpusd;
        $iOpen  = (int) round($open);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $nzdusd = $NZDUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$nzdusd) * 100, 1/7) * $gbpusd;
        $iClose = (int) round($close);

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
 * Berechnet fuer die uebergebenen M1-Daten den JPYFX6-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: JPYFX6 = 100 * (1 / (AUDJPY * CADJPY * CHFJPY * EURJPY * GBPJPY * USDJPY)) ^ 1/6
 */
function calculateJPYFX6($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    JPYFX6  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * (100000/$gbpusd), 1/6) / $usdjpy * 100000;
        $iOpen  = (int) round($open * 100000);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * (100000/$gbpusd), 1/6) / $usdjpy * 100000;
        $iClose = (int) round($close * 100000);

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
 * Berechnet fuer die uebergebenen M1-Daten den JPYFX7-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: JPYFX7 = 100 * (1 / (AUDJPY * CADJPY * CHFJPY * EURJPY * GBPJPY * NZDJPY * USDJPY)) ^ 1/7
 */
function calculateJPYFX7($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    JPYFX7  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $NZDUSD = $data['NZDUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $nzdusd = $NZDUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * (100000/$gbpusd) * (100000/$nzdusd), 1/7) / $usdjpy * 100000;
        $iOpen  = (int) round($open * 100000);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $nzdusd = $NZDUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * (100000/$gbpusd) * (100000/$nzdusd), 1/7) / $usdjpy * 100000;
        $iClose = (int) round($close * 100000);

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
 * Berechnet fuer die uebergebenen M1-Daten den JPYLFX-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: JPYLFX = 100 * (1 / (AUDJPY * CADJPY * CHFJPY * EURJPY * GBPJPY * USDJPY)) ^ 1/7
 *   oder: JPYLFX = 100 * USDLFX / USDJPY
 */
function calculateJPYLFX($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    JPYLFX  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = 100 * pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdjpy * 1000;
        $iOpen  = (int) round($open * 100000);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = 100 * pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdjpy * 1000;
        $iClose = (int) round($close * 100000);

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
 * Berechnet fuer die uebergebenen M1-Daten den NOKFX7-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: NOKFX7 = 10 * (NOKJPY / (AUDNOK * CADNOK * CHFNOK * EURNOK * GBPNOK * USDNOK)) ^ 1/7
 *   oder: NOKFX7 = 10 * USDLFX / USDNOK
 */
function calculateNOKFX7($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    NOKFX7  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $USDNOK = $data['USDNOK']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $usdnok = $USDNOK[$i]['open'];
        $open   = 10 * pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdnok * 100000;
        $iOpen  = (int) round($open * 100000);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $usdnok = $USDNOK[$i]['close'];
        $close  = 10 * pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdnok * 100000;
        $iClose = (int) round($close * 100000);

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
 * Berechnet fuer die uebergebenen M1-Daten den NZDFX7-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: NZDFX7 = ((NZDCAD * NZDCHF * NZDJPY * NZDUSD) / (AUDNZD * EURNZD * GBPNZD)) ^ 1/7
 */
function calculateNZDFX7($day, array $data) {
    //return calculateNZDLFX($day, $data, 'NZDFX7');
    return [];
}


/**
 * Berechnet fuer die uebergebenen M1-Daten den SEKFX7-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: SEKFX7 = 10 * (SEKJPY / (AUDSEK * CADSEK * CHFSEK * EURSEK * GBPSEK * USDSEK)) ^ 1/7
 *   oder: SEKFX7 = 10 * USDLFX / USDSEK
 */
function calculateSEKFX7($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    SEKFX7  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $USDSEK = $data['USDSEK']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $usdsek = $USDSEK[$i]['open'];
        $open   = 10 * pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdsek * 100000;
        $iOpen  = (int) round($open * 100000);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $usdsek = $USDSEK[$i]['close'];
        $close  = 10 * pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdsek * 100000;
        $iClose = (int) round($close * 100000);

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
 * Berechnet fuer die uebergebenen M1-Daten den SGDFX7-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: SGDFX7 = (SGDJPY / (AUDSGD * CADSGD * CHFSGD * EURSGD * GBPSGD * USDSGD)) ^ 1/7
 *   oder: SGDFX7 = USDLFX / USDSGD
 */
function calculateSGDFX7($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    SGDFX7  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $USDSGD = $data['USDSGD']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $usdsgd = $USDSGD[$i]['open'];
        $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdsgd * 100000;
        $iOpen  = (int) round($open * 100000);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $usdsgd = $USDSGD[$i]['close'];
        $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdsgd * 100000;
        $iClose = (int) round($close * 100000);

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
 * Berechnet fuer die uebergebenen M1-Daten den USDFX6-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: USDFX6 = ((USDCAD * USDCHF * USDJPY) / (AUDUSD * EURUSD * GBPUSD)) ^ 1/6
 */
function calculateUSDFX6($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    USDFX6  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/6);
        $iOpen  = (int) round($open * 100000);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/6);
        $iClose = (int) round($close * 100000);

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
 * Berechnet fuer die uebergebenen M1-Daten den USDFX7-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: USDFX7 = ((USDCAD * USDCHF * USDJPY) / (AUDUSD * EURUSD * GBPUSD * NZDUSD)) ^ 1/7
 */
function calculateUSDFX7($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    USDFX7  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $NZDUSD = $data['NZDUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $nzdusd = $NZDUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * (100000/$nzdusd) * 100, 1/7);
        $iOpen  = (int) round($open * 100000);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $nzdusd = $NZDUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * (100000/$nzdusd) * 100, 1/7);
        $iClose = (int) round($close * 100000);

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
 * Berechnet fuer die uebergebenen M1-Daten den ZARFX7-Index.
 *
 * @param  int   $day  - FXT-Timestamp des Tages der zu berechnenden Daten
 * @param  array $data - M1-Bars dieses Tages aller fuer den Index benoetigten Instrumente
 *
 * @return ROST_PRICE_BAR[] - Array mit den resultierenden M1-Indexdaten
 *
 * Formel: ZARFX7 = 10 * (ZARJPY / (AUDZAR * CADZAR * CHFZAR * EURZAR * GBPZAR * USDZAR)) ^ 1/7
 *   oder: ZARFX7 = 10 * USDLFX / USDZAR
 */
function calculateZARFX7($day, array $data) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose;
    if ($verbose > 1) echoPre('[Info]    SEKFX7  '.$shortDate);

    $AUDUSD = $data['AUDUSD']['bars'];
    $EURUSD = $data['EURUSD']['bars'];
    $GBPUSD = $data['GBPUSD']['bars'];
    $USDCAD = $data['USDCAD']['bars'];
    $USDCHF = $data['USDCHF']['bars'];
    $USDJPY = $data['USDJPY']['bars'];
    $USDZAR = $data['USDZAR']['bars'];
    $index  = [];

    foreach ($AUDUSD as $i => $bar) {
        $audusd = $AUDUSD[$i]['open'];
        $eurusd = $EURUSD[$i]['open'];
        $gbpusd = $GBPUSD[$i]['open'];
        $usdcad = $USDCAD[$i]['open'];
        $usdchf = $USDCHF[$i]['open'];
        $usdjpy = $USDJPY[$i]['open'];
        $usdzar = $USDZAR[$i]['open'];
        $open   = 10 * pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdzar * 100000;
        $iOpen  = (int) round($open * 100000);

        $audusd = $AUDUSD[$i]['close'];
        $eurusd = $EURUSD[$i]['close'];
        $gbpusd = $GBPUSD[$i]['close'];
        $usdcad = $USDCAD[$i]['close'];
        $usdchf = $USDCHF[$i]['close'];
        $usdjpy = $USDJPY[$i]['close'];
        $usdzar = $USDZAR[$i]['close'];
        $close  = 10 * pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdzar * 100000;
        $iClose = (int) round($close * 100000);

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
