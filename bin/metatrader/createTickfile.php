#!/usr/bin/php
<?php
/**
 * Erzeugt eine FXT-Tickdatei für den Strategy Tester.
 */
require(__DIR__.'/../../app/init.php');
date_default_timezone_set('GMT');


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$options = array('verbose' => 0);


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);

// Hilfe
foreach ($args as $i => $arg) {
   if ($arg == '-h') exit(1|help());
}

// Optionen und Argumente parsen
foreach ($args as $i => $arg) {
   if ($arg == '-v'  ) { $options['verbose'] = max($options['verbose'], 1); unset($args[$i]); continue; }   // verbose output
   if ($arg == '-vv' ) { $options['verbose'] = max($options['verbose'], 2); unset($args[$i]); continue; }   // more verbose output
   if ($arg == '-vvv') { $options['verbose'] = max($options['verbose'], 3); unset($args[$i]); continue; }   // very verbose output

   // -s=SYMBOL
   if (strStartsWith($arg, '-s=')) {
      if (isSet($options['symbol'])) exit(1|help('invalid/multiple symbol arguments: -s='.$arg));
      $value = $arg = strRight($arg, -3);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      if (!MT4::isValidSymbol($value)) exit(1|help('invalid symbol: '.$arg));
      $options['symbol'] = $value;
      continue;
   }

   // -p=PERIOD
   if (strStartsWith($arg, '-p=')) {
      if (isSet($options['period'])) exit(1|help('invalid/multiple period arguments: -p='.$arg));
      $value = $arg = strRight($arg, -3);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      if (strIsDigits($value) && $value{0}!='0') {
         $value = (int) $value;
         if (!MT4::isStdTimeframe($value)) exit(1|help('invalid period: '.$arg));
         $options['period'] = $value;
      }
      else if (!MT4::isTimeframeDescription($value)) exit(1|help('invalid period: '.$arg));
      else $options['period'] = MT4::timeframeToId($value);
      continue;
   }

   // -from=DATE
   if (strStartsWith($arg, '-from=')) {
      if (isSet($options['startDate'])) exit(1|help('invalid/multiple start date arguments: -from='.$arg));
      $value = $arg = strRight($arg, -6);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      $timestamp = is_datetime($value, array('Y-m-d', 'Y.m.d', 'd.m.Y', 'd/m/Y'));
      if (!is_int($timestamp) || $timestamp < 0) exit(1|help('invalid start date: '.$arg));
      $options['startDate'] = $timestamp;
      if (isSet($options['endDate']) && $options['startDate'] > $options['endDate']) {
         exit(1|help('start date/end date mis-match: '.gmDate('Y.m.d', $options['startDate']).' > '.gmDate('Y.m.d', $options['endDate'])));
      }
      continue;
   }

   // -to=DATE
   if (strStartsWith($arg, '-to=')) {
      if (isSet($options['endDate'])) exit(1|help('invalid/multiple end date arguments: -to='.$arg));
      $value = $arg = strRight($arg, -4);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      $timestamp = is_datetime($value, array('Y-m-d', 'Y.m.d', 'd.m.Y', 'd/m/Y'));
      if (!is_int($timestamp) || $timestamp<=0) exit(1|help('invalid end date: '.$arg));
      $options['endDate'] = $timestamp;
      if (isSet($options['startDate']) && $options['startDate'] > $options['endDate']) {
         exit(1|help('start date/end date mis-match: '.gmDate('Y.m.d', $options['startDate']).' > '.gmDate('Y.m.d', $options['endDate'])));
      }
      continue;
   }

   // -model=TYPE
   if (strStartsWith($arg, '-model=')) {
      if (isSet($options['model'])) exit(1|help('invalid/multiple model arguments: -model='.$arg));
      $arg   = strRight($arg, -7);
      $value = strToUpper($arg);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      if     (strStartsWith('REALTICKS',      $value)) $options['model'] = 'REALTICKS';
      elseif (strStartsWith('SIMULATEDTICKS', $value)) $options['model'] = 'SIMULATEDTICKS';
      elseif (strStartsWith('BAROPEN',        $value)) $options['model'] = 'BAROPEN';
      else                                    exit(1|help('invalid model type: '.$arg));
      continue;
   }

   // -spread=PIPS
   if (strStartsWith($arg, '-spread=')) {
      if (isSet($options['spread'])) exit(1|help('invalid/multiple spread arguments: -spread='.$arg));
      $value = $arg = strRight($arg, -8);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      if (!is_numeric($value) || strStartsWithI($value, '0x')) exit(1|help('invalid spread: '.$arg));
      $value = (double) $value;
      if ($value < 0) exit(1|help('invalid spread: '.$arg));
      $spread = round($value, 1);
      if ($spread != $value) exit(1|help('invalid spread: '.$arg));
      $options['spread'] = $spread;
      continue;
   }
}
if (!isSet($options['symbol'   ])) exit(1|help('missing symbol argument'));
if (!isSet($options['period'   ])) exit(1|help('missing period argument'));
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
