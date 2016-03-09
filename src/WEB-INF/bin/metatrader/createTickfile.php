#!/usr/bin/php
<?php
/**
 * Erzeugt eine FXT-Tickdatei für den Strategy Tester.
 */
require(dirName(realPath(__FILE__)).'/../../config.php');
date_default_timezone_set('GMT');


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$options = array('verbose' => 0);


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);

// Hilfe
foreach ($args as $i => $arg) {
   if ($arg == '-h') help() & exit(1);
}

// Optionen und Argumente parsen
foreach ($args as $i => $arg) {
   if ($arg == '-v'  ) { $options['verbose'] = max($options['verbose'], 1); unset($args[$i]); continue; }   // verbose output
   if ($arg == '-vv' ) { $options['verbose'] = max($options['verbose'], 2); unset($args[$i]); continue; }   // more verbose output
   if ($arg == '-vvv') { $options['verbose'] = max($options['verbose'], 3); unset($args[$i]); continue; }   // very verbose output

   // -s=SYMBOL
   if (strStartsWith($arg, '-s=')) {
      if (isSet($options['symbol'])) help('invalid/multiple symbol arguments: -s='.$arg) & exit(1);
      $value = $arg = strRight($arg, -3);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      if (!MT4::isValidSymbol($value)) help('invalid symbol: '.$arg) & exit(1);
      $options['symbol'] = $value;
      continue;
   }

   // -p=PERIOD
   if (strStartsWith($arg, '-p=')) {
      if (isSet($options['period'])) help('invalid/multiple period arguments: -p='.$arg) & exit(1);
      $value = $arg = strRight($arg, -3);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      if (strIsDigit($value) && $value{0}!='0') {
         $value = (int) $value;
         if (!MT4::isBuiltinTimeframe($value)) help('invalid period: '.$arg) & exit(1);
         $options['period'] = $value;
      }
      else if (!MT4::isTimeframeDescription($value)) help('invalid period: '.$arg) & exit(1);
      else $options['period'] = MT4::timeframeToId($value);
      continue;
   }

   // -from=DATE
   if (strStartsWith($arg, '-from=')) {
      if (isSet($options['startDate'])) help('invalid/multiple start date arguments: -from='.$arg) & exit(1);
      $value = $arg = strRight($arg, -6);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      $timestamp = is_datetime($value, array('Y-m-d', 'Y.m.d', 'd.m.Y', 'd/m/Y'));
      if (!is_int($timestamp) || $timestamp<0) help('invalid start date: '.$arg) & exit(1);
      $options['startDate'] = $timestamp;
      if (isSet($options['endDate']) && $options['startDate'] > $options['endDate']) {
         help('start date/end date mis-match: '.gmDate('Y.m.d', $options['startDate']).' > '.gmDate('Y.m.d', $options['endDate'])) & exit(1);
      }
      continue;
   }

   // -to=DATE
   if (strStartsWith($arg, '-to=')) {
      if (isSet($options['endDate'])) help('invalid/multiple end date arguments: -to='.$arg) & exit(1);
      $value = $arg = strRight($arg, -4);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      $timestamp = is_datetime($value, array('Y-m-d', 'Y.m.d', 'd.m.Y', 'd/m/Y'));
      if (!is_int($timestamp) || $timestamp<=0) help('invalid end date: '.$arg) & exit(1);
      $options['endDate'] = $timestamp;
      if (isSet($options['startDate']) && $options['startDate'] > $options['endDate']) {
         help('start date/end date mis-match: '.gmDate('Y.m.d', $options['startDate']).' > '.gmDate('Y.m.d', $options['endDate'])) & exit(1);
      }
      continue;
   }

   // -model=TYPE
   if (strStartsWith($arg, '-model=')) {
      if (isSet($options['model'])) help('invalid/multiple model arguments: -model='.$arg) & exit(1);
      $arg   = strRight($arg, -7);
      $value = strToUpper($arg);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      if     (strStartsWith('REALTICKS',      $value)) $options['model'] = 'REALTICKS';
      elseif (strStartsWith('SIMULATEDTICKS', $value)) $options['model'] = 'SIMULATEDTICKS';
      elseif (strStartsWith('BAROPEN',        $value)) $options['model'] = 'BAROPEN';
      else                                    help('invalid model type: '.$arg) & exit(1);
      continue;
   }

   // -spread=PIPS
   if (strStartsWith($arg, '-spread=')) {
      if (isSet($options['spread'])) help('invalid/multiple spread arguments: -spread='.$arg) & exit(1);
      $value = $arg = strRight($arg, -8);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      if (!is_numeric($value) || strStartsWithI($value, '0x')) help('invalid spread: '.$arg) & exit(1);
      $value = (double) $value;
      if ($value < 0) help('invalid spread: '.$arg) & exit(1);
      $spread = round($value, 1);
      if ($spread != $value) help('invalid spread: '.$arg) & exit(1);
      $options['spread'] = $spread;
      continue;
   }
}
if (!isSet($options['symbol'   ])) help('missing symbol argument') & exit(1);
if (!isSet($options['period'   ])) help('missing period argument') & exit(1);
if (!isSet($options['startDate'])) $options['startDate'] = 0;
if (!isSet($options['endDate'  ])) $options['endDate'  ] = 0;
if (!isSet($options['model'    ])) $options['model'    ] = 'REALTICKS';
if (!isSet($options['spread'   ])) $options['spread'   ] = 0;


echoPre($options);
exit(0);


// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


/**
 * Hilfefunktion
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (is_null($message))
      $message = 'Generates a MetaTrader Strategy Tester tick file for the specified symbol and timeframe.';
   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END
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


END;
}
