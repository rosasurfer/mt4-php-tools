<?php
declare(strict_types=1);

namespace rosasurfer\rt;

use DateTimeZone;

use rosasurfer\ministruts\core\exception\InvalidTypeException;
use rosasurfer\ministruts\core\exception\InvalidValueException;
use rosasurfer\ministruts\core\exception\UnimplementedFeatureException;

use function rosasurfer\ministruts\strStartsWith;

use const rosasurfer\ministruts\DAY;
use const rosasurfer\ministruts\DAYS;
use const rosasurfer\ministruts\FRIDAY;
use const rosasurfer\ministruts\HOURS;
use const rosasurfer\ministruts\MINUTES;
use const rosasurfer\ministruts\MONTHS;
use const rosasurfer\ministruts\SATURDAY;
use const rosasurfer\ministruts\SUNDAY;
use const rosasurfer\ministruts\WEEKS;

/**
 * Timezones and timezone ids
 *
 * @see  definition in MT4Expander::defines.h
 */
const TIMEZONE_ALPARI              = 'Alpari';              // until 03/2012 "Europe/Berlin", since 04/2012 "Europe/Kiev"
const TIMEZONE_AMERICA_NEW_YORK    = 'America/New_York';
const TIMEZONE_EUROPE_BERLIN       = 'Europe/Berlin';
const TIMEZONE_EUROPE_KIEV         = 'Europe/Kiev';
const TIMEZONE_EUROPE_LONDON       = 'Europe/London';
const TIMEZONE_EUROPE_MINSK        = 'Europe/Minsk';
const TIMEZONE_FXT                 = 'FXT';                 // "Europe/Kiev"   with DST transitions of "America/New_York"
const TIMEZONE_FXT_MINUS_0200      = 'FXT-0200';            // "Europe/London" with DST transitions of "America/New_York"
const TIMEZONE_GLOBALPRIME         = 'GlobalPrime';         // "FXT" with one-time transition error of "Europe/Kiev" on 24.10.2015
const TIMEZONE_GMT                 = 'GMT';

const TIMEZONE_ID_ALPARI           =  1;
const TIMEZONE_ID_AMERICA_NEW_YORK =  2;
const TIMEZONE_ID_EUROPE_BERLIN    =  3;
const TIMEZONE_ID_EUROPE_KIEV      =  4;
const TIMEZONE_ID_EUROPE_LONDON    =  5;
const TIMEZONE_ID_EUROPE_MINSK     =  6;
const TIMEZONE_ID_FXT              =  7;
const TIMEZONE_ID_FXT_MINUS_0200   =  8;
const TIMEZONE_ID_GLOBALPRIME      =  9;
const TIMEZONE_ID_GMT              = 10;


// period/timeframe identifiers
const PERIOD_TICK  =      0;                // tick data (no period)
const PERIOD_M1    =      1;                // 1 minute
const PERIOD_M5    =      5;                // 5 minutes
const PERIOD_M15   =     15;                // 15 minutes
const PERIOD_M30   =     30;                // 30 minutes
const PERIOD_H1    =     60;                // 1 hour
const PERIOD_H4    =    240;                // 4 hours
const PERIOD_D1    =   1440;                // daily
const PERIOD_W1    =  10080;                // weekly
const PERIOD_MN1   =  43200;                // monthly
const PERIOD_Q1    = 129600;                // a quarter (3 months)


// operation types
const OP_BUY       =       0;               //    MT4: long position
const OP_LONG      =  OP_BUY;               //
const OP_SELL      =       1;               //         short position
const OP_SHORT     = OP_SELL;               //
const OP_BUYLIMIT  =       2;               //         buy limit order
const OP_SELLLIMIT =       3;               //         sell limit order
const OP_BUYSTOP   =       4;               //         stop buy order
const OP_SELLSTOP  =       5;               //         stop sell order
const OP_BALANCE   =       6;               //         account credit or withdrawal transaction
const OP_CREDIT    =       7;               //         credit facility, no transaction
const OP_TRANSFER  =       8;               // custom: balance update by client (deposit or withdrawal)
const OP_VENDOR    =       9;               //         balance update by criminal (dividends, swap, manual etc.)


// price types
const PRICE_CLOSE    = 0;                   // Close
const PRICE_OPEN     = 1;                   // Open
const PRICE_HIGH     = 2;                   // High
const PRICE_LOW      = 3;                   // Low
const PRICE_MEDIAN   = 4;                   // (H+L)/2
const PRICE_TYPICAL  = 5;                   // (H+L+C)/3
const PRICE_WEIGHTED = 6;                   // (H+L+C+C)/4
const PRICE_BID      = 7;                   // Bid
const PRICE_ASK      = 8;                   // Ask


// Strategy Tester bar models
const BARMODEL_EVERYTICK     = 0;
const BARMODEL_CONTROLPOINTS = 1;
const BARMODEL_BAROPEN       = 2;


// trade directions, can be combined
const TRADE_DIRECTIONS_LONG  = 1;
const TRADE_DIRECTIONS_SHORT = 2;
const TRADE_DIRECTIONS_BOTH  = 3;


// struct sizes
const DUKASCOPY_BAR_SIZE  = 24;
const DUKASCOPY_TICK_SIZE = 20;


/**
 * Convert a GMT timestamp (seconds since 1970-01-01 00:00 GMT) to an FXT timestamp (seconds since 1970-01-01 00:00 FXT).
 * Without the parameter the function returns the current FXT time.
 *
 * @param  int|float|null $unixTime [optional] - timestamp with support for microseconds (default: the current time)
 *
 * @return int|float - FXT timestamp
 */
function fxTime($unixTime = null) {
    if      (!func_num_args())                           $unixTime = time();
    else if (!is_int($unixTime) && !is_float($unixTime)) throw new InvalidTypeException('Illegal type of parameter $unixTime: '.gettype($unixTime));

    try {
        $currentTZ = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        $offset = idate('Z', (int)$unixTime);
        return $unixTime + $offset + 7*HOURS;
    }
    finally { date_default_timezone_set($currentTZ); }

    // Calculating the offset by switching default timezones is 3-10 times faster then using an OOP approach. Only on HHVM
    // OOP speed becomes better but is still 1.5 times slower:
    //
    // $localTime = new DateTime();
    // $timezone  = new DateTimeZone('America/New_York');
    // $offset    = $timezone->getOffset($localTime);
    // $fxTime    = $localTime->getTimestamp() + $offset + 7*HOURS;
}


/**
 * Convert an FXT timestamp (seconds since 1970-01-01 00:00 FXT) to a GMT timestamp (seconds since 1970-01-01 00:00 GMT).
 * Without the parameter the function returns the current GMT timestamp (same as the PHP function {@link \time()}).
 *
 * @param  int|float|null $fxTime [optional] - timestamp with support for microseconds (default: the current time)
 *
 * @return int - GMT timestamp
 */
function unixTime($fxTime = null) {
    if      (!func_num_args())                       $fxTime = fxTime();
    else if (!is_int($fxTime) && !is_float($fxTime)) throw new InvalidTypeException('Illegal type of parameter $fxTime: '.gettype($fxTime));

    try {
        $currentTZ = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        $offset1  = idate('Z', (int)$fxTime);
        $unixTime = $fxTime - $offset1 - 7*HOURS;
        if ($offset1 != ($offset2=idate('Z', (int)$unixTime)))  // detect and handle a DST change between EasternTime
            $unixTime = $fxTime - $offset2 - 7*HOURS;           // and EasternTime+0700
        return $unixTime;
    }
    finally { date_default_timezone_set($currentTZ); }          // TODO: detect and handle invalid FXT timestamps
}


/**
 * Format a timestamp and return an FXT representation. This function works exactly as <tt>gmdate()</tt> except that the
 * applied timezone is FXT.
 *
 * @param  string $format           - format as accepted by <tt>date($format, $timestamp)</tt>
 * @param  ?int   $time  [optional] - timestamp (default: the current time)
 * @param  bool   $isFxt [optional] - whether the timestamp is an FXT timestamp (default: GMT)
 *
 * @return string - formatted string
 */
function fxDate(string $format, ?int $time=null, bool $isFxt=false): string {
    $time ??= $isFxt ? fxTime() : time();
    if ($isFxt) return gmdate($format, $time);

    try {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        return date($format, $time+7*HOURS);
    }
    finally {
        date_default_timezone_set($timezone);
    }
}


/**
 * Return the FXT offset of a GMT time, and optionally the adjacent DST transition data.
 *
 * @param  int                  $time                      - GMT time
 * @param  ?array<string, ?int> $prevTransition [optional] - if specified the array is filled with the data of the previous DST transition
 * @param  ?array<string, ?int> $nextTransition [optional] - if specified the array is filled with the data of the next DST transition
 *
 * @return ?int - Offset in seconds or NULL if parameter $time is not in the range of the timezone information for FXT. <br>
 *                Offset is always positive, it holds: GMT + offset = FXT                                               <br>
 *                                                                                                                      <br>
 * <pre>
 * Transition data is returned as follows:
 * ---------------------------------------
 * array(
 *     'time'   => (int),       // GMT timestamp of the previous|next DST transition
 *     'offset' => (int),       // FXT offset before|after that transition
 * )
 * </pre>
 */
function fxtOffset(int $time, ?array &$prevTransition=null, ?array &$nextTransition=null): ?int {
    /**
     * @phpstan-var ?TZ_TRANSITION[] $transitions
     *
     * @see \rosasurfer\rt\phpstan\TZ_TRANSITION
     */
    static $transitions;
    $transitions ??= (new DateTimeZone('America/New_York'))->getTransitions();

    $i = -2;
    foreach ($transitions as $i => $transition) {
        if ($transition['ts'] > $time) {
            $i--;
            break;                                                  // hier zeigt $i auf die aktuelle Periode
        }
    }

    $transSize = sizeof($transitions);
    $argsSize  = func_num_args();

    // $prevTransition definieren
    if ($argsSize > 1) {
        $prevTransition = [];

        if ($i < 0) {                                               // $transitions ist leer oder $time
            $prevTransition['time'  ] = null;                       // liegt vor der ersten Periode
            $prevTransition['offset'] = null;
        }
        else if ($i == 0) {                                         // $time liegt in erster Periode
            $prevTransition['time'  ] = $transitions[0]['ts'];
            $prevTransition['offset'] = null;                       // vorheriger Offset unbekannt
        }
        else {
            $prevTransition['time'  ] = $transitions[$i  ]['ts'    ];
            $prevTransition['offset'] = $transitions[$i-1]['offset'] + 7*HOURS;
        }
    }

    // $nextTransition definieren
    if ($argsSize > 2) {
        $nextTransition = [];

        if ($i==-2 || $i >= $transSize-1) {                         // $transitions ist leer oder
            $nextTransition['time'  ] = null;                       // $time liegt in letzter Periode
            $nextTransition['offset'] = null;
        }
        else {
            $nextTransition['time'  ] = $transitions[$i+1]['ts'    ];
            $nextTransition['offset'] = $transitions[$i+1]['offset'] + 7*HOURS;
        }
    }

    // Rueckgabewert definieren
    $offset = null;
    if ($i >= 0)                                                    // $transitions ist nicht leer und
        $offset = $transitions[$i]['offset'] + 7*HOURS;             // $time liegt nicht vor der ersten Periode
    return $offset;
}


/**
 * Parst die String-Repraesentation einer FXT-Zeit in einen GMT-Timestamp.
 *
 * @param  string $time - FXT-Zeit in einem der Funktion strtotime() verstaendlichen Format
 *
 * @return int - Unix-Timestamp
 *
 * TODO:  Funktion unnoetig: strtotime() ueberladen und um Erkennung der FXT-Zeitzone erweitern
 */
function fxtStrToTime(string $time): int {
    $currentTZ = date_default_timezone_get();
    try {
        date_default_timezone_set('America/New_York');
        $unixTime = strtotime($time);
        if ($unixTime === false) throw new InvalidValueException('Invalid argument $time: "'.$time.'"');
        return $unixTime - 7*HOURS;
    }
    finally {
        date_default_timezone_set($currentTZ);
    }
}


/**
 * Format a date/time as an integer in GMT.
 *
 * Missing equivalent to PHP's built-in function idate() which formats a time in the local timezone.
 *
 * @param  string $format          - single character format as accepted by <tt>idate($format, $time)</tt>
 * @param  ?int   $time [optional] - timestamp (default: the current time)
 *
 * @return int
 */
function igmdate(string $format, ?int $time = null): int {
    $time ??= time();
    try {
        $currentTZ = date_default_timezone_get();
        date_default_timezone_set('GMT');
        return idate($format, $time);
    }
    finally {
        date_default_timezone_set($currentTZ);
    }
}


/**
 * Whether a time is on a Good Friday (Karfreitag). Good Friday is 2 days before Easter Sunday
 * and a day where many exchanges operate at limited opening times.
 *
 * @param  int $time - GMT or FXT timestamp
 *
 * @return bool
 */
function isGoodFriday(int $time): bool {
    if (!$time) return false;

    $dow = (int) gmdate('w', $time);
    if ($dow == FRIDAY) {
        $year       = (int)gmdate('Y', $time);
        $spring     = strtotime($year.'-03-21 GMT');
        $easter     = $spring + easter_days($year)*DAYS;
        $goodFriday = $easter - 2*DAYS;
        $time      -= $time%DAY;
        return ($time == $goodFriday);
    }
    return false;
}


/**
 * Whether a time is on a common Christmas or New Year Holiday.
 *
 * @param  int $time - GMT or FXT timestamp
 *
 * @return bool
 */
function isHoliday(int $time): bool {
    if (!$time) return false;

    $m   = (int)gmdate('n', $time);             // month
    $dom = (int)gmdate('j', $time);             // day of month

    if ($dom==1 && $m==1)                       // 1. January
        return true;
    if ($dom==25 && $m==12)                     // 25. December
        return true;
    return false;
}


/**
 * Whether a time is on a Saturday or Sunday.
 *
 * @param  int $time - GMT or FXT timestamp
 *
 * @return bool
 */
function isWeekend(int $time): bool {
    if (!$time) return false;

    $dow = (int)gmdate('w', $time);
    return ($dow==SATURDAY || $dow==SUNDAY);
}


/**
 * Return a string representation of a period identifier.
 *
 * @param  int $value - period identifier (number of minutes per bar)
 *
 * @return string
 */
function periodToStr(int $value): string {
    switch ($value) {
        case PERIOD_TICK: return 'PERIOD_TICK';         //      0 = no period
        case PERIOD_M1  : return 'PERIOD_M1';           //      1 = 1 minute
        case PERIOD_M5  : return 'PERIOD_M5';           //      5 = 5 minutes
        case PERIOD_M15 : return 'PERIOD_M15';          //     15 = 15 minutes
        case PERIOD_M30 : return 'PERIOD_M30';          //     30 = 30 minutes
        case PERIOD_H1  : return 'PERIOD_H1';           //     60 = 1 hour
        case PERIOD_H4  : return 'PERIOD_H4';           //    240 = 4 hour
        case PERIOD_D1  : return 'PERIOD_D1';           //   1440 = 1 day
        case PERIOD_W1  : return 'PERIOD_W1';           //  10080 = 1 week
        case PERIOD_MN1 : return 'PERIOD_MN1';          //  43200 = 1 month
        case PERIOD_Q1  : return 'PERIOD_Q1';           // 129600 = 1 quarter (3 months)
    }
    return (string) $value;
}


/**
 * Return a human-readable description of a period identifier.
 *
 * @param  int $value - period identifier (number of minutes per bar)
 *
 * @return string
 */
function periodDescription(int $value): string {
    switch ($value) {
        case PERIOD_TICK: return 'TICK';                //      0 = no period
        case PERIOD_M1  : return 'M1';                  //      1 = 1 minute
        case PERIOD_M5  : return 'M5';                  //      5 = 5 minutes
        case PERIOD_M15 : return 'M15';                 //     15 = 15 minutes
        case PERIOD_M30 : return 'M30';                 //     30 = 30 minutes
        case PERIOD_H1  : return 'H1';                  //     60 = 1 hour
        case PERIOD_H4  : return 'H4';                  //    240 = 4 hour
        case PERIOD_D1  : return 'D1';                  //   1440 = 1 day
        case PERIOD_W1  : return 'W1';                  //  10080 = 1 week
        case PERIOD_MN1 : return 'MN1';                 //  43200 = 1 month
        case PERIOD_Q1  : return 'Q1';                  // 129600 = 1 quarter (3 months)
    }
    return (string) $value;
}


/**
 * Alias of periodToStr()
 *
 * @param  int $timeframe
 *
 * @return string
 */
function timeframeToStr($timeframe) {
    return periodToStr($timeframe);
}


/**
 * Alias of periodDescription()
 *
 * @param  int $timeframe
 *
 * @return string
 */
function timeframeDescription($timeframe) {
    return periodDescription($timeframe);
}


/**
 * Return a human-readable description of a price type identifier.
 *
 * @param  int $type - price type
 *
 * @return string
 */
function priceTypeDescription(int $type): string {
    switch ($type) {
        case PRICE_CLOSE   : return("Close"   );
        case PRICE_OPEN    : return("Open"    );
        case PRICE_HIGH    : return("High"    );
        case PRICE_LOW     : return("Low"     );
        case PRICE_MEDIAN  : return("Median"  );        // (High+Low)/2
        case PRICE_TYPICAL : return("Typical" );        // (High+Low+Close)/3
        case PRICE_WEIGHTED: return("Weighted");        // (High+Low+Close+Close)/4
        case PRICE_BID     : return("Bid"     );
        case PRICE_ASK     : return("Ask"     );
    }
    return (string) $type;
}


/**
 * Return a nicely formatted time range description.
 *
 * @param  string $startTime
 * @param  string $endTime
 *
 * @return string
 */
function prettyTimeRange($startTime, $endTime) {
    $startDate = new \DateTime($startTime);
    $endDate   = new \DateTime($endTime);

    $startTimestamp = $startDate->getTimestamp();
    $endTimestamp   = $endDate->getTimestamp();

    $range = null;

    if (idate('Y', $startTimestamp) == idate('Y', $endTimestamp)) {
        if (idate('m', $startTimestamp) == idate('m', $endTimestamp)) {
            if (idate('d', $startTimestamp) == idate('d', $endTimestamp)) {
                 $range = $startDate->format('d.m.Y H:i').'-'.$endDate->format('H:i');
            }
            else $range = $startDate->format('d.').'-'.$endDate->format('d.m.Y');
        }
        else $range = $startDate->format('d.m.').'-'.$endDate->format('d.m.Y');
    }
    else $range = $startDate->format('d.m.Y').'-'.$endDate->format('d.m.Y');

    return $range;
}


/**
 * Return a nicely formatted recovery time description.
 *
 * @param  int $duration - recovery duration in seconds
 *
 * @return string
 */
function prettyRecoveryTime($duration) {
    if ($duration < 10*HOURS) {                         // H:i
        $duration = round($duration/MINUTES)*MINUTES;
        $ii = $duration % HOURS;
        $hh = ($duration-$ii) / HOURS;
        $result = $hh.'h '.round($ii/MINUTES)."'";
    }
    else if ($duration < 3*DAYS) {                      // d, H
        $duration = round($duration/HOURS)*HOURS;
        $hh = $duration % DAYS;
        $dd = ($duration-$hh) / DAYS;
        $result = $dd.'d '.round($hh/HOURS).'h';
    }
    else if ($duration < 5*WEEKS) {                     // w, d
        $duration = round($duration/DAYS)*DAYS;
        $dd = $duration % WEEKS;
        $ww = ($duration-$dd) / WEEKS;
        $result = $ww.'w '.round($dd/DAYS).'d';
    }
    else {                                              // w
        $ww = round($duration / WEEKS);
        $result = $ww.'w';
    }
    return $result;
}


/**
 * User-land implementation of PECL::stats_standard_deviation()
 *
 * @param  float[] $values
 * @param  bool    $sample [optional] - whether values represent a sample or an entire population (default: entire population)
 *
 * @return float - standard deviation
 */
function stats_standard_deviation(array $values, bool $sample = false): float {
    if (function_exists('stats_standard_deviation')) {
        return \stats_standard_deviation($values, $sample);
    }

    $n = sizeof($values);
    if ($n==0           ) throw new InvalidValueException('Illegal number of values: 0 (not a population)');
    if ($n==1 && $sample) throw new InvalidValueException('Illegal number of values: 1 (not a sample)');

    $mean = array_sum($values) / $n;        // arythmetic mean (aka simple average)
    $sqrSum = 0;

    foreach ($values as $value) {           // The denominator's reduction by 1 for calculating the variance of a sample
        $diff = $value - $mean;             // tries to correct the "mean error" caused by unknown out-of-sample values.
        $sqrSum += $diff * $diff;           // Think of it as an arbitrary correction when you don't know outliers.
    };                                      //
                                            // standard deviation:
    if ($sample) $n--;                      // @see http://www.mathsisfun.com/data/standard-deviation.html
    $variance = $sqrSum / $n;               //
                                            // Bessel's correction:
    return sqrt($variance);                 // @see https://en.wikipedia.org/wiki/Bessel%27s_correction
}


/**
 * Calculate the Sharpe ratio of the given returns (the average return divided by the standard deviation).      // wrong: it's the total return
 *
 * @param  float[] $returns
 * @param  bool    $growth  [optional] - whether the returns are growth rates or absolute values (default: absolute values)
 * @param  bool    $sample  [optional] - whether the values represent just a sample (default: total population)
 *
 * @return float - over-simplified and non-normalized Sharpe ratio
 *
 * @see    https://www.mql5.com/en/forum/289927#comment_9807456
 */
function stats_sharpe_ratio(array $returns, bool $growth=false, bool $sample=false): float {
    $n = sizeof($returns);
    if ($n==0           ) throw new InvalidValueException('Illegal number of returns: 0 (not a population)');
    if ($n==1 && $sample) throw new InvalidValueException('Illegal number of returns: 1 (not a sample)');

    // edit April 2024: calculations are so wrong
    //  - Sharpe ratio = TotalReturn / TotalVolatility                  // not avgReturn/volatility
    //    TotalVolatility = StdDeviation(AllReturns)
    //
    //  - Growth rates must be handled the same as absolute values. In this case TotalReturn is the sum of all single returns,
    //    NOT the product. StdDevation() returns the same unit as TotalReturn. If one wanted to process growth rates differently
    //    then StdDeviation() would have to make that same distinction, which obviously is non-sense.
    //
    //  - StdDeviation() returns the average deviation of a single data point from the population mean in the same unit as the population.
    //    If we start from monthly growth rates in %, std-deviation is the expected (Sigma=1) deviation in % from the average monthly rate.
    //
    //  - Summary: Parameter $growth can be fully discarded.
    //
    if ($growth) {
        throw new UnimplementedFeatureException('Validation of growth rates not yet implemented');
        // all values must be non-negative and non-zero (no return = zero growth = 1)
        $mean = array_product($returns) ** 1/$n;        // geometric mean (aka geometric average)           // @phpstan-ignore deadCode.unreachable (keep for documentation)
    }
    else {
        $mean = array_sum($returns) / $n;               // arythmetic mean (aka simple average)
    }

    return $mean / stats_standard_deviation($returns, $sample);
}


/**
 * Calculate the Sortino ratio of the given returns (the Sharpe ratio of the negative returns, i.e. of the risk).
 *
 * @param  float[] $returns
 * @param  bool    $growth [optional] - whether the returns are growth rates or absolute values (default: absolute values)
 * @param  bool    $sample [optional] - whether the values represent just a sample (default: total population)
 *
 * @return float - over-simplified and non-normalized Sortino ratio
 *
 * @see    https://www.mql5.com/en/forum/289927#comment_9807456
 */
function stats_sortino_ratio(array $returns, bool $growth=false, bool $sample=false): float {
    $n = sizeof($returns);
    if ($n==0           ) throw new InvalidValueException('Illegal number of returns: 0 (not a population)');
    if ($n==1 && $sample) throw new InvalidValueException('Illegal number of returns: 1 (not a sample)');

    if ($growth) {
        throw new UnimplementedFeatureException('Validation of growth rates not yet implemented');
        // all values must be above zero (no return = zero growth = 1)
        $mean = array_product($returns) ** 1/$n;        // geometric mean (aka geometric average)           // @phpstan-ignore deadCode.unreachable (keep for documentation)
        $zeroReturn = 1;
    }
    else {
        $mean = array_sum($returns) / $n;               // arythmetic mean (aka simple average)
        $zeroReturn = 0;
    }

    foreach ($returns as $i => $return) {
        if ($return > $zeroReturn)                      // set positive returns to 0
            $returns[$i] = $zeroReturn;
    }

    return $mean / stats_standard_deviation($returns, $sample);
}


/**
 * Calculate the Calmar ratio of the given profits (the average return divided by the maximum drawdown)
 *
 * @param  string  $from   - start date of the data series
 * @param  string  $to     - end date of the data series
 * @param  float[] $values - absolute profit values (not growth rates)
 *
 * @return float - over-simplified monthly Calmar ratio
 */
function stats_calmar_ratio(string $from, string $to, array $values): float {
    $total = $maxDrawdown = 0;
    $high  = PHP_INT_MIN;

    foreach ($values as $value) {
        $total += $value;
        if ($total > $high)
            $high = $total;

        $drawdown = $high - $total;
        if ($drawdown > $maxDrawdown)
            $maxDrawdown = $drawdown;                   // TODO: that's maxDD of balance, not of equity
    }

    $months = (strtotime($to) - strtotime($from))/MONTHS;
    $normalizedProfit = $total / $months;               // average profit per month (absolute)

    if ($maxDrawdown == 0)
        return INF;
    return $normalizedProfit / $maxDrawdown;
}


/**
 * Return the integer constant of a timeframe identifier.
 *
 * @param  string $value - M1, M5, M15, M30 etc.
 *
 * @return int - timeframe id or -1 if the value is not recognized
 */
function strToPeriod(string $value): int {
    $value = strtoupper(trim($value));
    if (strStartsWith($value, 'PERIOD_')) {
        $value = substr($value, 7);
    }
    switch ($value) {
        case       'M1' :
        case PERIOD_M1  : return(PERIOD_M1);
        case       'M5' :
        case PERIOD_M5  : return(PERIOD_M5);
        case       'M15':
        case PERIOD_M15 : return(PERIOD_M15);
        case       'M30':
        case PERIOD_M30 : return(PERIOD_M30);
        case       'H1' :
        case PERIOD_H1  : return(PERIOD_H1);
        case       'H4' :
        case PERIOD_H4  : return(PERIOD_H4);
        case       'D1' :
        case PERIOD_D1  : return(PERIOD_D1);
        case       'W1' :
        case PERIOD_W1  : return(PERIOD_W1);
        case       'MN1':
        case PERIOD_MN1 : return(PERIOD_MN1);
        case       'Q1' :
        case PERIOD_Q1  : return(PERIOD_Q1);
    }
   return -1;
}


/**
 * Alias of strToPeriod()
 *
 * Return the integer constant of a timeframe identifier.
 *
 * @param  string $value - M1, M5, M15, M30 etc.
 *
 * @return int - timeframe id or -1 if the value is not recognized
 */
function strToTimeframe($value) {
   return(strToPeriod($value));
}
