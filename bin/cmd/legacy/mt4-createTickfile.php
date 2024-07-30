#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * TODO: replace by Ministruts console command
 *
 *
 * Erzeugt eine FXT-Tickdatei fuer den Strategy Tester.
 */
namespace rosasurfer\rt\cmd\mt4_create_tick_file;

use rosasurfer\rt\lib\metatrader\MT4;

use function rosasurfer\ministruts\echof;
use function rosasurfer\ministruts\strIsDigits;
use function rosasurfer\ministruts\strIsQuoted;
use function rosasurfer\ministruts\strLeft;
use function rosasurfer\ministruts\strRight;
use function rosasurfer\ministruts\strStartsWith;
use function rosasurfer\ministruts\strStartsWithI;
use function rosasurfer\ministruts\strToTimestamp;

require(dirname(realpath(__FILE__)).'/../../../app/init.php');
date_default_timezone_set('GMT');


// -- Konfiguration ---------------------------------------------------------------------------------------------------------


$options = ['verbose' => 0];


// -- Start -----------------------------------------------------------------------------------------------------------------


// Befehlszeilenargumente einlesen und validieren
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// Hilfe
foreach ($args as $i => $arg) {
    if ($arg == '-h') {
        help();
        exit(1);
    }
}

// Optionen und Argumente parsen
foreach ($args as $i => $arg) {
    if ($arg == '-v'  ) { $options['verbose'] = max($options['verbose'], 1); unset($args[$i]); continue; }   // verbose output
    if ($arg == '-vv' ) { $options['verbose'] = max($options['verbose'], 2); unset($args[$i]); continue; }   // more verbose output
    if ($arg == '-vvv') { $options['verbose'] = max($options['verbose'], 3); unset($args[$i]); continue; }   // very verbose output

    // -s=SYMBOL
    if (strStartsWith($arg, '-s=')) {
        if (isset($options['symbol'])) {
            help('invalid/multiple symbol arguments: -s='.$arg);
            exit(1);
        }
        $value = $arg = strRight($arg, -3);
        if (strIsQuoted($value)) {
            $value = strLeft(strRight($value, -1), 1);
        }
        if (!MT4::isValidSymbol($value)) {
            help('invalid symbol: '.$arg);
            exit(1);
        }
        $options['symbol'] = $value;
        continue;
    }

    // -p=PERIOD
    if (strStartsWith($arg, '-p=')) {
        if (isset($options['period'])) {
            help('invalid/multiple period arguments: -p='.$arg);
            exit(1);
        }
        $value = $arg = strRight($arg, -3);
        if (strIsQuoted($value)) {
            $value = strLeft(strRight($value, -1), 1);
        }
        if (strIsDigits($value) && $value[0]!='0') {
            $value = (int) $value;
            if (!MT4::isStdTimeframe($value)) {
                help('invalid period: '.$arg);
                exit(1);
            }
            $options['period'] = $value;
        }
        else if (!MT4::isTimeframeDescription($value)) {
            help('invalid period: '.$arg);
            exit(1);
        }
        else {
            $options['period'] = MT4::strToTimeframe($value);
        }
        continue;
    }

    // -from=DATE
    if (strStartsWith($arg, '-from=')) {
        if (isset($options['startDate'])) {
            help('invalid/multiple start date arguments: -from='.$arg);
            exit(1);
        }
        $value = $arg = strRight($arg, -6);
        if (strIsQuoted($value)) {
            $value = strLeft(strRight($value, -1), 1);
        }
        $timestamp = strToTimestamp($value, ['Y-m-d', 'Y.m.d', 'd.m.Y', 'd/m/Y']);
        if (!is_int($timestamp) || $timestamp < 0) {
            help('invalid start date: '.$arg);
            exit(1);
        }
        $options['startDate'] = $timestamp;
        if (isset($options['endDate']) && $options['startDate'] > $options['endDate']) {
            help('start date/end date mis-match: '.gmdate('Y.m.d', $options['startDate']).' > '.gmdate('Y.m.d', $options['endDate']));
            exit(1);
        }
        continue;
    }

    // -to=DATE
    if (strStartsWith($arg, '-to=')) {
        if (isset($options['endDate'])) {
            help('invalid/multiple end date arguments: -to='.$arg);
            exit(1);
        }
        $value = $arg = strRight($arg, -4);
        if (strIsQuoted($value)) {
            $value = strLeft(strRight($value, -1), 1);
        }
        $timestamp = strToTimestamp($value, ['Y-m-d', 'Y.m.d', 'd.m.Y', 'd/m/Y']);
        if (!is_int($timestamp) || $timestamp<=0) {
            help('invalid end date: '.$arg);
            exit(1);
        }
        $options['endDate'] = $timestamp;
        if (isset($options['startDate']) && $options['startDate'] > $options['endDate']) {
            help('start date/end date mis-match: '.gmdate('Y.m.d', $options['startDate']).' > '.gmdate('Y.m.d', $options['endDate']));
            exit(1);
        }
        continue;
    }

    // -model=TYPE
    if (strStartsWith($arg, '-model=')) {
        if (isset($options['model'])) {
            help('invalid/multiple model arguments: -model='.$arg);
            exit(1);
        }
        $arg   = strRight($arg, -7);
        $value = strtoupper($arg);
        if (strIsQuoted($value)) {
            $value = strLeft(strRight($value, -1), 1);
        }
        if     (strStartsWith('REALTICKS',      $value)) $options['model'] = 'REALTICKS';
        elseif (strStartsWith('SIMULATEDTICKS', $value)) $options['model'] = 'SIMULATEDTICKS';
        elseif (strStartsWith('BAROPEN',        $value)) $options['model'] = 'BAROPEN';
        else {
            help('invalid model type: '.$arg);
            exit(1);
        }
        continue;
    }

    // -spread=PIPS
    if (strStartsWith($arg, '-spread=')) {
        if (isset($options['spread'])) {
            help('invalid/multiple spread arguments: -spread='.$arg);
            exit(1);
        }
        $value = $arg = strRight($arg, -8);
        if (strIsQuoted($value)) {
            $value = strLeft(strRight($value, -1), 1);
        }
        if (!is_numeric($value) || strStartsWithI($value, '0x')) {
            help('invalid spread: '.$arg);
            exit(1);
        }
        $value = (float) $value;
        if ($value < 0) {
            help('invalid spread: '.$arg);
            exit(1);
        }
        $spread = round($value, 1);
        if ($spread != $value) {
            help('invalid spread: '.$arg);
            exit(1);
        }
        $options['spread'] = $spread;
        continue;
    }
}
if (!isset($options['symbol'   ])) { help('missing symbol argument'); exit(1); }
if (!isset($options['period'   ])) { help('missing period argument'); exit(1); }
if (!isset($options['startDate'])) $options['startDate'] = 0;
if (!isset($options['endDate'  ])) $options['endDate'  ] = 0;
if (!isset($options['model'    ])) $options['model'    ] = 'REALTICKS';
if (!isset($options['spread'   ])) $options['spread'   ] = 0;


echof($options);
exit(0);


// --- Funktionen -----------------------------------------------------------------------------------------------------------


/**
 * Hilfefunktion
 *
 * @param  ?string $message [optional] - zusaetzlich zur Syntax anzuzeigende Message (default: keine)
 *
 * @return void
 */
function help($message = null) {
    if (is_null($message))
        $message = 'Generates a MetaTrader Strategy Tester tick file for the specified symbol and timeframe.';
    $self = basename($_SERVER['PHP_SELF']);

echo <<<HELP
$message

  Syntax:  $self -s=SYMBOL -p=PERIOD [-from=DATE] [-to=DATE] [-model=TYPE] [-spread=PIPS] [...]

  Options:  -s=SYMBOL       The symbol to generate the tick file for.
            -p=PERIOD       Timeframe of the generated tick file as an id or in minutes.
            -from=DATE      Testing start date of the generated tick file (default: start of data).
            -to=DATE        Testing end date of the generated tick file (default: end of data).
            -model=[R|S|B]  Tick generation algorythm: (R)EALTICKS|(S)IMULATEDTICKS|(B)AROPEN (default: real ticks).
            -spread=PIPS    Fixed spread of the generated tick file in fractional pips (default: 0).
            -v              Verbose output.
            -vv             More verbose output.
            -vvv            Most verbose output.
            -h              This help screen.


HELP;
}
