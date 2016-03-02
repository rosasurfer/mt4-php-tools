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
      $symbol = strRight($arg, -3);
      if (strIsQuoted($symbol))
         $symbol = strLeft(strRight($symbol, -1), 1);
      if (!MT4::isValidSymbol($symbol)) help('invalid symbol: '.$symbol) & exit(1);
      $options['symbol'] = $symbol;
      unset($args[$i]);
      continue;
   }

   // -p=PERIOD
   if (strStartsWith($arg, '-p=')) {
      $period = strRight($arg, -3);
      if (strIsQuoted($period))
         $period = strLeft(strRight($period, -1), 1);
      if (strIsDigit($period) && $period{0}!='0') {
         if (!MT4::isBuiltinTimeframe((int) $period)) help('invalid timeframe: '.$period) & exit(1);
         $options['period'] = (int) $period;
      }
      else if (!MT4::isTimeframeDescription($period)) help('invalid timeframe: '.$period) & exit(1);
      else $options['period'] = MT4::timeframeToId($period);
      unset($args[$i]);
      continue;
   }
}

//  [-from=DATE] [-to=DATE] [-model=TYPE] [-spread=PIPS] [...]

echoPre($options);
exit(0);


// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (!is_null($message))
      echo($message.NL.NL);

   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END
Generates a MetaTrader Strategy Tester tick file for the specified symbol and timeframe.

  Syntax:  $self -s=SYMBOL -p=PERIOD [-from=DATE] [-to=DATE] [-model=TYPE] [-spread=PIPS] [...]

  Options:  -s=SYMBOL       The symbol to generate the tick file for.
            -p=PERIOD       Timeframe of the generated tick file in minutes.
            -from=DATE      Testing start date of the generated tick file.                     default: start of data
            -to=DATE        Testing end date of the generated tick file.                       default: end of data
            -model=[E|S|B]  Tick generation algorythm: (E)VERYTICK|(S)IMULATEDTICKS|(B)AROPEN. default: every tick
            -spread=PIPS    Fixed spread of the generated tick file in (fractional) pips.      default: 0.1 pip
            -v              Verbose output.
            -vv             More verbose output.
            -vvv            Most verbose output.
            -h              This help screen.


END;
}
