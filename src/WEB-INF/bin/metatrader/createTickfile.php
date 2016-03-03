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
      $value = $arg = strRight($arg, -3);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      if (!MT4::isValidSymbol($value)) help('invalid symbol: '.$arg) & exit(1);
      $options['symbol'] = $value;
      unset($args[$i]);
      continue;
   }

   // -p=PERIOD
   if (strStartsWith($arg, '-p=')) {
      $value = $arg = strRight($arg, -3);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      if (strIsDigit($value) && $value{0}!='0') {
         $value = (int) $value;
         if (!MT4::isBuiltinTimeframe($value)) help('invalid timeframe: '.$arg) & exit(1);
         $options['period'] = $value;
      }
      else if (!MT4::isTimeframeDescription($value)) help('invalid timeframe: '.$arg) & exit(1);
      else $options['period'] = MT4::timeframeToId($value);
      unset($args[$i]);
      continue;
   }

   // -from=DATE
   if (strStartsWith($arg, '-from=')) {
      $value = $arg = strRight($arg, -6);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      $timestamp = Validator::isDate($value, array('Y-m-d', 'Y.m.d', 'd.m.Y', 'd/m/Y'));
      if (!is_int($timestamp) || $timestamp<0) help('invalid start date: '.$arg) & exit(1);
      $options['startDate'] = $timestamp;
      if (isSet($options['endDate']) && $options['startDate'] > $options['endDate']) {
         help('start date/end date mis-match: '.gmDate('Y.m.d', $options['startDate']).' > '.gmDate('Y.m.d', $options['endDate'])) & exit(1);
      }
      unset($args[$i]);
      continue;
   }

   // -to=DATE
   if (strStartsWith($arg, '-to=')) {
      $value = $arg = strRight($arg, -4);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      $timestamp = Validator::isDate($value, array('Y-m-d', 'Y.m.d', 'd.m.Y', 'd/m/Y'));
      if (!is_int($timestamp) || $timestamp<=0) help('invalid end date: '.$arg) & exit(1);
      $options['endDate'] = $timestamp;
      if (isSet($options['startDate']) && $options['startDate'] > $options['endDate']) {
         help('start date/end date mis-match: '.gmDate('Y.m.d', $options['startDate']).' > '.gmDate('Y.m.d', $options['endDate'])) & exit(1);
      }
      unset($args[$i]);
      continue;
   }

   // -model=TYPE: (E)VERYTICK|(S)IMULATEDTICKS|(B)AROPEN
   if (strStartsWith($arg, '-model=')) {
      $arg   = strRight($arg, -7);
      $value = strToUpper($arg);
      if (strIsQuoted($value))
         $value = strLeft(strRight($value, -1), 1);
      if     (strStartsWith('EVERYTICK',      $value)) $options['model'] = 'EVERYTICK';
      elseif (strStartsWith('SIMULATEDTICKS', $value)) $options['model'] = 'SIMULATEDTICKS';
      elseif (strStartsWith('BAROPEN',        $value)) $options['model'] = 'BAROPEN';
      else                                    help('invalid model type: '.$arg) & exit(1);
      unset($args[$i]);
      continue;
   }
}

//  [-model=TYPE] [-spread=PIPS] [...]

echoPre($options);
exit(0);


// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
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
            -p=PERIOD       Timeframe of the generated tick file in minutes.
            -from=DATE      Testing start date of the generated tick file.                     default: start of data
            -to=DATE        Testing end date of the generated tick file.                       default: end of data
            -model=[E|S|B]  Tick generation algorythm: (E)VERYTICK|(S)IMULATEDTICKS|(B)AROPEN. default: every tick
            -spread=PIPS    Fixed spread of the generated tick file in (fractional) pips.      default: 1 point
            -v              Verbose output.
            -vv             More verbose output.
            -vvv            Most verbose output.
            -h              This help screen.


END;
}
