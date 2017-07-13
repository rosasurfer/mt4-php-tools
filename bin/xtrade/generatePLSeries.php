#!/usr/bin/env php
<?php
/**
 * Generate a profit/loss timeseries for a trade history (at the moment M1 only).
 *
 *
 * TODO: link the PL series to the originating trade history
 * TODO: check and confirm over/rewriting an existing PL series
 */
namespace rosasurfer\xtrade\generate_pl_series;

use rosasurfer\config\Config;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;

use rosasurfer\xtrade\XTrade;
use rosasurfer\xtrade\model\metatrader\Test;

use function rosasurfer\isLittleEndian;
use function rosasurfer\xtrade\isFxtWeekend;

require(dirName(realPath(__FILE__)).'/../../app/init.php');


// --- Configuration --------------------------------------------------------------------------------------------------------


$saveRawXTradeData = true;                                              // whether or not to store uncompressed XTrade data


// --- Start ----------------------------------------------------------------------------------------------------------------


// (1) parse/validate command line arguments
$args = array_slice($_SERVER['argv'], 1);
foreach ($args as $i => $arg) {
    if ($arg == '-h') exit(1|help());           // help
}
(sizeOf($args) != 1) && exit(1|help());

// the only argument must be a test report symbol
$value = array_shift($args);
/** @var Test $test */
$test  = Test::dao()->findByReportingSymbol($value);
!$test && exit(1|help('unknown test report symbol "'.$value.'"'));


// (2) load trades and order trade events chronologically
$trades = $test->getTrades();
$deals  = [];
$symbol = null;
foreach ($trades as $trade) {                                           // atm: error out on mixed symbols
    $symbol && $symbol!=$trade->getSymbol() && exit(1|echoPre('[Error]   Mixed trade histories are not yet supported (found trades in '.$symbol.' and '.$trade->getSymbol().').'));
    $symbol = $trade->getSymbol();

    // separate trades into deals
    $openDeal         = new \StdClass();
    $openDeal->time   = strToTime($trade->getOpenTime().' GMT');        // FXT timestamp
    $openDeal->lots   = $trade->getLots() * (strCompareI($trade->getType(), 'buy') ? 1 : -1);
    $openDeal->price  = $trade->getOpenPrice();

    $closeDeal        = new \StdClass();
    $closeDeal->time  = strToTime($trade->getCloseTime().' GMT');       // FXT timestamp
    $closeDeal->lots  = -$openDeal->lots;
    $closeDeal->price = $trade->getClosePrice();

    $deals[$openDeal->time ] = $openDeal;                               // TODO: handle multiple deals per minute or second
    $deals[$closeDeal->time] = $closeDeal;
}
kSort($deals);
$deals = array_values($deals);


// (3) cross-check availability of price history
$firstDeal = reset($deals);
if      (is_file(getVar('xtradeFile.compressed', $symbol, $firstDeal->time))) {}
else if (is_file(getVar('xtradeFile.raw'       , $symbol, $firstDeal->time))) {}
else     exit(1|echoPre('[Error]   '.$symbol.' XTrade history for '.gmDate('D, d-M-Y', $firstDeal->time).' not found'));

$lastDeal = end($deals);
if      (is_file(getVar('xtradeFile.compressed', $symbol, $lastDeal->time))) {}
else if (is_file(getVar('xtradeFile.raw'       , $symbol, $lastDeal->time))) {}
else     exit(1|echoPre('[Error]   '.$symbol.' XTrade history for '.gmDate('D, d-M-Y', $lastDeal->time).' not found'));
echoPre('[Info]    Processing '.sizeof($trades).' trades of test '.$test->getReportingSymbol().' ('.gmDate('d.m.Y', $firstDeal->time).' - '.gmDate('d.m.Y', $lastDeal->time).')');


// (4) calculate total position and price at each deal time
$sum = $position = $prevPosition = 0;
foreach ($deals as $deal) {
    $prevPosition = $position;
    $position += $deal->lots;

    if ($position) {
        $sum                += $deal->lots * $deal->price;
        $deal->position      = $position;
        $deal->positionPrice = $sum / $position;
    }
    else {                                  // flat or hedged
        $sum                 = 0;
        $deal->position      = 0;
        $deal->positionPrice = 0;
    }
}
if (end($deals)->position) throw new RuntimeException('Unexpected total position after last deal: '.end($deals)->position.' (not flat)');


// (5) generate a reporting symbol for the new PL series
$reportSymbol = $test->getReportingSymbol();
define('PIP',   XTrade::$symbols[$symbol]['pip'   ]); define('PIPS',   PIP);
define('POINT', XTrade::$symbols[$symbol]['point' ]); define('POINTS', POINT);


// (6) generate the PL series
$firstDealDay   = $firstDeal->time - $firstDeal->time % DAY;        // 00:00
$lastDealDay    = $lastDeal->time - $lastDeal->time % DAY;          // 00:00
$currentDeal    = reset($deals);
$nextDeal       = next($deals);
$nextDealMinute = $nextDeal->time - $nextDeal->time % MINUTE;
$totalPL        = $pl = $lastPositionPrice = 0;
$prevMonth      = -1;

for ($day=$firstDealDay; $day <= $lastDealDay; $day+=1*DAY) {
    $shortDate = gmDate('D, d-M-Y', $day);
    $month     = (int) gmDate('m', $day);
    if ($month != $prevMonth) {
        echoPre('[Info]    '.gmDate('M-Y', $day));
        $prevMonth = $month;
    }
    if (isFxtWeekend($day, 'FXT'))                                  // skip non-trading days
        continue;

    if      (is_file($file=getVar('xtradeFile.compressed', $symbol, $day))) {}
    else if (is_file($file=getVar('xtradeFile.raw'       , $symbol, $day))) {}
    else exit(1|echoPre('[Error]   '.$symbol.' XTrade history for '.$shortDate.' not found'));

    $bars    = XTrade::readBarFile($file, $symbol);
    $partial = false;

    if ($day == $firstDealDay) {                                    // drop leading bars of the first trading day
        $offset = (int)($firstDeal->time % DAY / MINUTES);
        array_splice($bars, 0, $offset);
        if (($barTime=reset($bars)['time']) != $firstDeal->time) throw new RuntimeException('Unexpected XTrade price bar for '.gmDate('D, d-M-Y H:i:s', $firstDeal->time).' at offset '.$offset.' (found '.gmDate('H:i:s', $barTime).')');
        $partial = true;
    }
    else if ($day == $lastDealDay) {                                // drop trailing bars of the last trading day
        $offset = (int)($lastDeal->time % DAY / MINUTES);
        array_splice($bars, $offset+1);
        if (($barTime=end($bars)['time']) != $lastDeal->time)    throw new RuntimeException('Unexpected XTrade price bar for '.gmDate('D, d-M-Y H:i:s', $lastDeal->time).' at offset '.$offset.' (found '.gmDate('H:i:s', $barTime).')');
        $partial = true;
    }

    /** XTRADE_PIP_BAR[] $pipSeries */
    $pipSeries = [];

    // calculate PL bars of a single day                            // TODO: handle multiple deals per minute or second
    foreach ($bars as $i => $bar) {
        if ($bar['time'] == $nextDealMinute) {
            $lastPositionPrice = $currentDeal->positionPrice;
            $currentDeal       = $nextDeal;
            $nextDeal          = next($deals);
            $nextDealMinute    = $nextDeal ? $nextDeal->time - $nextDeal->time % MINUTE : null;
        }

        $pl = 0;
        if ($currentDeal->position > 0) {
            $pl = $bar['close']/10 - $currentDeal->positionPrice/PIPS;
        }
        else if ($currentDeal->position < 0) {
            $pl = $currentDeal->positionPrice/PIPS - $bar['close']/10;
        }
        else {                                                      // now flat or hedged
            if ($currentDeal->lots < 0) {                           // was long
                $totalPL += $currentDeal->price/PIPS - $lastPositionPrice/PIPS;
                $currentDeal->lots = 0;                             // mark deal as processed
            }
            else if ($currentDeal->lots > 0) {                      // was short
                $totalPL += $lastPositionPrice/PIPS - $currentDeal->price/PIPS;
                $currentDeal->lots = 0;                             // mark deal as processed
            }
        }
        $pipSeries[] = [
            'time'  => $bar['time'],
            'open'  => null,
            'high'  => null,
            'low'   => null,
            'close' => round($totalPL + $pl, 3),
        ];
    }

    // save PL bars of each single day
    if (!saveBars($reportSymbol, $day, $pipSeries, $partial)) exit(1);
    $pipSeries = [];
}
echoPre('[Info]    total pips: '.round($totalPL + $pl, 3));


// (7) the ugly end
exit(0);


// --- Functions ------------------------------------------------------------------------------------------------------------


/**
 * Store a single day's PL series in an XTrade history file.
 *
 * @param  string           $symbol
 * @param  int              $day                - the day's timestamp (FXT)
 * @param  XTRADE_PIP_BAR[] $bars
 * @param  bool             $partial [optional] - whether or not the bars cover only a part of the day (default: FALSE)
 *
 * @return bool - success status
 */
function saveBars($symbol, $day, array $bars, $partial = false) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    // re-check the bars
    if (!$partial) {
        if (sizeOf($bars) != 1*DAY/MINUTES)                  throw new RuntimeException('Invalid number of bars for '.$shortDate.': '.sizeOf($bars));
        if ($bars[   0]['time']%DAYS != 0)                   throw new RuntimeException('Invalid start bar for '.$shortDate.': '.gmDate('d-M-Y H:i:s', $bars[0]['time']));
        if ($bars[1439]['time']%DAYS != 23*HOURS+59*MINUTES) throw new RuntimeException('Invalid end bar for '.$shortDate.':'.gmDate('d-M-Y H:i:s', end($bars)['time']));
    }

    // convert bars into a binary string
    $data = null;
    foreach ($bars as $bar) {
        $data .= pack('Vdddd', $bar['time' ],   // V                // TODO: validate bar data (@see fxi.updateM1Bars)
                               $bar['open' ],   // d
                               $bar['high' ],   // d
                               $bar['low'  ],   // d
                               $bar['close']);  // d
    }

    // pack/unpack don't support explicit little-endian doubles, on big-endian machines the byte order
    // has to be reversed manually
    static $isLittleEndian = null; is_null($isLittleEndian) && $isLittleEndian=isLittleEndian();
    if (!$isLittleEndian) {
        $time  =        substr($data,  0, 4);
        $open  = strRev(substr($data,  4, 8));
        $high  = strRev(substr($data, 20, 8));
        $low   = strRev(substr($data, 12, 8));
        $close = strRev(substr($data, 28, 8));
        $data  = $time.$open.$high.$low.$close;
    }

    global $saveRawXTradeData;

    // write binary data
    if ($saveRawXTradeData) {
        if (is_file($file=getVar('xtradeFile.pl.raw', $symbol, $day)))
            return _false(echoPre('[Error]   PL series '.$symbol.' for '.gmDate('D, d-M-Y', $day).' already exists'));
        mkDirWritable(dirName($file));
        $tmpFile = tempNam(dirName($file), baseName($file));
        $hFile   = fOpen($tmpFile, 'wb');
        fWrite($hFile, $data);
        fClose($hFile);
        rename($tmpFile, $file);
    }
    return true;
}


/**
 * Resolve and manage frequently used dynamic variables in a central place. Resolved variables are cached and don't need to
 * get passed countless times through deep function graphs.
 *
 * @param  string $id                - unique variable identifier
 * @param  string $symbol [optional] - symbol (default: NULL)
 * @param  int    $time   [optional] - timestamp (default: NULL)
 *
 * @return string - resolved variable
 */
function getVar($id, $symbol=null, $time=null) {
    static $varCache = [];
    if (array_key_exists(($key=$id.'|'.$symbol.'|'.$time), $varCache))
        return $varCache[$key];

    if (!is_string($id))                          throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
    if (!is_null($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
    if (!is_null($time) && !is_int($time))        throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

    $self = __FUNCTION__;
    static $dataDirectory; !$dataDirectory && $dataDirectory = Config::getDefault()->get('app.dir.data');

    if ($id == 'xtradeDirDate') {               // $yyyy/$mm/$dd                                                // local path date
        if (!$time) throw new InvalidArgumentException('Invalid parameter $time: '.$time);
        $result = gmDate('Y/m/d', $time);
    }
    else if ($id == 'xtradeDir') {              // $dataDirectory/history/xtrade/$group/$symbol/$xtradeDirDate  // local directory
        if (!$symbol) throw new InvalidArgumentException('Invalid parameter $symbol: '.$symbol);
        $group         = XTrade::$symbols[$symbol]['group'];
        $xtradeDirDate = $self('xtradeDirDate', null, $time);
        $result        = $dataDirectory.'/history/xtrade/'.$group.'/'.$symbol.'/'.$xtradeDirDate;
    }
    else if ($id == 'xtradeDirPL') {            // $dataDirectory/stats/pl/$symbol/$xtradeDirDate               // local directory
        if (!$symbol) throw new InvalidArgumentException('Invalid parameter $symbol: '.$symbol);
        $xtradeDirDate = $self('xtradeDirDate', null, $time);
        $result        = $dataDirectory.'/stats/pl/'.$symbol.'/'.$xtradeDirDate;
    }
    else if ($id == 'xtradeFile.raw') {         // $xtradeDir/M1.myfx                                           // local file uncompressed
        $xtradeDir = $self('xtradeDir' , $symbol, $time);
        $result    = $xtradeDir.'/M1.myfx';
    }
    else if ($id == 'xtradeFile.compressed') {  // $xtradeDir/M1.rar                                            // local file compressed
        $xtradeDir = $self('xtradeDir' , $symbol, $time);
        $result    = $xtradeDir.'/M1.rar';
    }
    else if ($id == 'xtradeFile.pl.raw') {      // $xtradeDirPL/M1.myfx                                         // local file uncompressed
        $xtradeDirPL = $self('xtradeDirPL' , $symbol, $time);
        $result      = $xtradeDirPL.'/M1.myfx';
    }
    else {
      throw new InvalidArgumentException('Unknown variable identifier "'.$id.'"');
    }

    $varCache[$key] = $result;
    (sizeof($varCache) > ($maxEntries=2048)) && echoPre('cache limit of '.$maxEntries.' entries hit')           // ~200KB
                                               |echoPre('memory size: '.strLen(serialize($varCache)).' bytes')
                                               |exit(1);
    return $result;
}


/**
 * Show a basic help screen.
 *
 * @param  string $message [optional] - additional status message to show first (default: none)
 */
function help($message = null) {
    if (is_null($message))
        $message = 'Generate a profit/loss time series for a trade history.';
    $self = baseName($_SERVER['PHP_SELF']);

echo <<<HELP
$message

  Syntax:  $self  TEST [OPTIONS]

           TEST - report symbol of the test to process

  Options: -h     This help screen.

Note: Mixed trade histories are not yet supported.


HELP;
}
