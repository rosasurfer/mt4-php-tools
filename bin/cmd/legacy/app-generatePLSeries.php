#!/usr/bin/env php
<?php
/**
 * TODO: replace by Ministruts console command
 *
 *
 * Generate a profit/loss timeseries for a trade history (at the moment M1 only).
 *
 *
 * TODO: link the PL series to the originating trade history
 * TODO: check and confirm over/rewriting of existing PL series
 */
namespace rosasurfer\rt\cmd\app_generate_pl_series;

use rosasurfer\ministruts\Application;
use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\core\exception\InvalidValueException;
use rosasurfer\ministruts\core\exception\RuntimeException;
use rosasurfer\ministruts\file\FileSystem as FS;

use rosasurfer\rt\lib\Rost;
use rosasurfer\rt\model\RosaSymbol;
use rosasurfer\rt\model\Test;

use function rosasurfer\ministruts\echof;
use function rosasurfer\ministruts\false;
use function rosasurfer\ministruts\isLittleEndian;
use function rosasurfer\ministruts\strCompareI;

use function rosasurfer\rt\isWeekend;

use const rosasurfer\ministruts\DAY;
use const rosasurfer\ministruts\DAYS;
use const rosasurfer\ministruts\HOURS;
use const rosasurfer\ministruts\MINUTE;
use const rosasurfer\ministruts\MINUTES;

require(dirname(realpath(__FILE__)).'/../../../app/init.php');


// --- Configuration --------------------------------------------------------------------------------------------------------


$saveRawRTData = true;                                                 // whether or not to store uncompressed RT data


// --- Start ----------------------------------------------------------------------------------------------------------------


// (1) parse/validate command line arguments
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);
foreach ($args as $arg) {
    if ($arg == '-h') exit(1|help());           // help
}
(sizeof($args) != 1) && exit(1|help());

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
    $symbol && $symbol!=$trade->getSymbol() && exit(1|echof('[Error]   Mixed trade histories are not yet supported (found trades in '.$symbol.' and '.$trade->getSymbol().').'));
    $symbol = $trade->getSymbol();

    // separate trades into deals
    $openDeal         = new \stdClass();
    $openDeal->type   = 'open';
    $openDeal->ticket = $trade->getTicket();
    $openDeal->time   = strtotime($trade->getOpenTime().' GMT');        // FXT timestamp
    $openDeal->lots   = $trade->getLots() * (strCompareI($trade->getType(), 'buy') ? 1 : -1);
    $openDeal->price  = $trade->getOpenPrice();

    $closeDeal         = new \stdClass();
    $closeDeal->type   = 'close';
    $closeDeal->ticket = $trade->getTicket();
    $closeDeal->time   = strtotime($trade->getCloseTime().' GMT');      // FXT timestamp
    $closeDeal->lots   = -$openDeal->lots;
    $closeDeal->price  = $trade->getClosePrice();

    $deals[] = $openDeal;
    $deals[] = $closeDeal;
}
usort($deals, function(\stdClass $a, \stdClass $b) {
    if ($a->time < $b->time)  return -1;
    if ($a->time > $b->time)  return +1;
    if ($a->type != $b->type) return strcmp($a->type, $b->type);        // 'open' > 'close'
    return $a->ticket > $b->ticket ? +1 : -1;
});
$deals = array_values($deals);


// (3) cross-check availability of price history
$firstDeal = reset($deals);
if      (is_file(getVar('rtFile.compressed', $symbol, $firstDeal->time))) {}
else if (is_file(getVar('rtFile.raw'       , $symbol, $firstDeal->time))) {}
else     exit(1|echof('[Error]   '.$symbol.'  Rosatrader price history for '.gmdate('D, d-M-Y', $firstDeal->time).' not found'));

$lastDeal = end($deals);
if      (is_file(getVar('rtFile.compressed', $symbol, $lastDeal->time))) {}
else if (is_file(getVar('rtFile.raw'       , $symbol, $lastDeal->time))) {}
else     exit(1|echof('[Error]   '.$symbol.'  Rosatrader price history for '.gmdate('D, d-M-Y', $lastDeal->time).' not found'));
echof('[Info]    Processing '.sizeof($trades).' trades of test '.$test->getReportingSymbol().' ('.gmdate('d.m.Y', $firstDeal->time).' - '.gmdate('d.m.Y', $lastDeal->time).')');


// (4) calculate total position and price at each deal time
$sum = $position = 0;
foreach ($deals as $deal) {
    $position = round($position + $deal->lots, 2);

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


// (5) generate a reporting symbol for the PL series
$reportSymbol = $test->getReportingSymbol();


// (6) generate the PL series
$firstDealDay   = $firstDeal->time - $firstDeal->time % DAY;        // 00:00
$lastDealDay    = $lastDeal->time - $lastDeal->time % DAY;          // 00:00
$currentDeal    = reset($deals);
$nextDeal       = next($deals);
$nextDealMinute = $nextDeal->time - $nextDeal->time % MINUTE;
$totalPL        = $pl = $lastPositionPrice = 0;
$prevMonth      = -1;

for ($day=$firstDealDay; $day <= $lastDealDay; $day+=1*DAY) {
    $shortDate = gmdate('D, d-M-Y', $day);
    $month     = (int) gmdate('m', $day);
    if ($month != $prevMonth) {
        echof('[Info]    '.gmdate('M-Y', $day));
        $prevMonth = $month;
    }
    if (isWeekend($day))                                            // skip non-trading days
        continue;

    if      (is_file($file=getVar('rtFile.compressed', $symbol, $day))) {}
    else if (is_file($file=getVar('rtFile.raw'       , $symbol, $day))) {}
    else exit(1|echof('[Error]   '.$symbol.'  Rosatrader price history for '.$shortDate.' not found'));

    $bars    = Rost::readBarFile($file, $symbol);
    $partial = false;

    if ($day == $firstDealDay) {                                    // drop leading bars of the first trading day
        $offset = (int)($firstDeal->time % DAY / MINUTES);
        array_splice($bars, 0, $offset);
        if (($barTime=reset($bars)['time']) != $firstDeal->time) throw new RuntimeException('Unexpected Rosatrader price bar for '.gmdate('D, d-M-Y H:i:s', $firstDeal->time).' at offset '.$offset.' (found '.gmdate('H:i:s', $barTime).')');
        $partial = true;
    }
    else if ($day == $lastDealDay) {                                // drop trailing bars of the last trading day
        $offset = (int)($lastDeal->time % DAY / MINUTES);
        array_splice($bars, $offset+1);
        if (($barTime=end($bars)['time']) != $lastDeal->time)    throw new RuntimeException('Unexpected Rosatrader price bar for '.gmdate('D, d-M-Y H:i:s', $lastDeal->time).' at offset '.$offset.' (found '.gmdate('H:i:s', $barTime).')');
        $partial = true;
    }

    /** array $pipSeries - ROST_PIP_BAR[] */
    $pipSeries = [];

    // calculate PL bars of a single day                            // TODO: handle multiple deals per minute or second
    foreach ($bars as $bar) {
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
echof('[Info]    total pips: '.round($totalPL + $pl, 3));


// (7) the ugly end
exit(0);


// --- Functions ------------------------------------------------------------------------------------------------------------


/**
 * Store a single day's PL series in an RT history file.
 *
 * @param  string $symbol
 * @param  int    $day                - the day's timestamp (FXT)
 * @param  array  $bars               - ROST_PIP_BAR[]
 * @param  bool   $partial [optional] - whether or not the bars cover only a part of the day (default: FALSE)
 *
 * @return bool - success status
 */
function saveBars($symbol, $day, array $bars, $partial = false) {
    Assert::int($day, '$day');
    $shortDate = gmdate('D, d-M-Y', $day);

    // re-check the bars
    if (!$partial) {
        if (sizeof($bars) != 1*DAY/MINUTES)                  throw new RuntimeException('Invalid number of bars for '.$shortDate.': '.sizeof($bars));
        if ($bars[   0]['time']%DAYS != 0)                   throw new RuntimeException('Invalid start bar for '.$shortDate.': '.gmdate('d-M-Y H:i:s', $bars[0]['time']));
        if ($bars[1439]['time']%DAYS != 23*HOURS+59*MINUTES) throw new RuntimeException('Invalid end bar for '.$shortDate.':'.gmdate('d-M-Y H:i:s', end($bars)['time']));
    }

    // convert bars into a binary string
    $data = null;
    foreach ($bars as $bar) {
        $data .= pack('Vdddd', $bar['time' ],   // V                // TODO: validate bar data (@see bin/rt/updateSyntheticsM1.php)
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
        $open  = strrev(substr($data,  4, 8));
        $high  = strrev(substr($data, 20, 8));
        $low   = strrev(substr($data, 12, 8));
        $close = strrev(substr($data, 28, 8));
        $data  = $time.$open.$high.$low.$close;
    }

    global $saveRawRTData;

    // write binary data
    if ($saveRawRTData) {
        if (is_file($file=getVar('rtFile.pl.raw', $symbol, $day)))
            return false(echof('[Error]   PL series '.$symbol.' for '.gmdate('D, d-M-Y', $day).' already exists'));
        FS::mkDir(dirname($file));
        $tmpFile = tempnam(dirname($file), basename($file));    // make sure an existing file can't be corrupt
        file_put_contents($tmpFile, $data);
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

    Assert::string($id, '$id');
    Assert::nullOrString($symbol, '$symbol');
    Assert::nullOrInt($time, '$time');

    $self = __FUNCTION__;
    static $storageDir; !$storageDir && $storageDir = Application::getConfig()['app.dir.storage'];

    if ($id == 'rtDirDate') {                   // $yyyy/$mm/$dd                                                // local path date
        if (!$time) throw new InvalidValueException('Invalid parameter $time: '.$time);
        $result = gmdate('Y/m/d', $time);
    }
    else if ($id == 'rtDir') {                  // $dataDir/history/rosatrader/$type/$symbol/$rtDirDate         // local directory
        $type      = RosaSymbol::dao()->getByName($symbol)->getType();
        $rtDirDate = $self('rtDirDate', null, $time);
        $result    = $storageDir.'/history/rosatrader/'.$type.'/'.$symbol.'/'.$rtDirDate;
    }
    else if ($id == 'rtDirPL') {                // $dataDir/stats/pl/$symbol/$rtDirDate                         // local directory
        if (!$symbol) throw new InvalidValueException('Invalid parameter $symbol: '.$symbol);
        $rtDirDate = $self('rtDirDate', null, $time);
        $result    = $storageDir.'/stats/pl/'.$symbol.'/'.$rtDirDate;
    }
    else if ($id == 'rtFile.raw') {             // $rtDir/M1.bin                                                // local file uncompressed
        $rtDir  = $self('rtDir' , $symbol, $time);
        $result = $rtDir.'/M1.bin';
    }
    else if ($id == 'rtFile.compressed') {      // $rtDir/M1.rar                                                // local file compressed
        $rtDir  = $self('rtDir' , $symbol, $time);
        $result = $rtDir.'/M1.rar';
    }
    else if ($id == 'rtFile.pl.raw') {          // $rtDirPL/M1.bin                                              // local file uncompressed
        $rtDirPL = $self('rtDirPL' , $symbol, $time);
        $result  = $rtDirPL.'/M1.bin';
    }
    else {
      throw new InvalidValueException('Unknown variable identifier "'.$id.'"');
    }

    $varCache[$key] = $result;
    (sizeof($varCache) > ($maxEntries=2048)) && echof('cache limit of '.$maxEntries.' entries hit')           // ~200KB
                                               |echof('memory used: '.strlen(serialize($varCache)).' bytes')
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
        $message = 'Generate a profit/loss timeseries for the trade history of a specified test.';
    $self = basename($_SERVER['PHP_SELF']);

echo <<<HELP
$message

  Syntax:  $self  TEST_SYMBOL [OPTIONS]

    TEST_SYMBOL - report symbol of the test to process

  Options: -h     This help screen.

Note: Trade histories containing mixed symbols are not yet supported.


HELP;
}
