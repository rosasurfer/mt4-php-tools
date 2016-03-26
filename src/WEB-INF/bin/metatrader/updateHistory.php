#!/usr/bin/php
<?php
/**
 * Aktualisiert MT4-History ein oder mehrerer Instrumente.
 */
require(dirName(realPath(__FILE__)).'/../../config.php');
date_default_timezone_set('GMT');


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$verbose = 0;                                                                       // output verbosity


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);

// Optionen parsen
foreach ($args as $i => $arg) {
   if ($arg == '-h'  )   exit(1|help());                                            // Hilfe
   if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; } // verbose output
   if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; } // more verbose output
   if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; } // very verbose output
}

// Symbole parsen
foreach ($args as $i => $arg) {
   $arg = strToUpper($arg);
   if (!isSet(MyFX::$symbols[$arg])) exit(1|help('error: unknown or unsupported symbol "'.$args[$i].'"'));
   $args[$i] = $arg;
}                                                                                   // ohne Symbol werden alle Instrumente verarbeitet
$args = $args ? array_unique($args) : array_keys(MyFX::$symbols);


// (2) History aktualisieren
foreach ($args as $symbol) {
   !updateHistory($symbol) && exit(1);
   break;
}
exit(0);


// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


/**
 * Aktualisirrt die MT4-History eines Instruments.
 *
 * @param  string $symbol - Symbol
 *
 * @return bool - Erfolgsstatus
 */
function updateHistory($symbol) {
   if (!is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   if (!strLen($symbol))    throw new plInvalidArgumentException('Invalid parameter $symbol: ""');

   // (1) wenn HistorySet existiert:
   //     HistorySet öffnen
   //     für jeden Timeframe Startzeitpunkt der Aktualisierung bestimmen, beginnend mit M1 aufsteigend
   //     alle Timeframes synchronisieren

   // (2) reguläres Update machen

   // (3) beim Schließen des HistorySets Sync-Zeitpunkt speichern


   echoPre('[Ok]    '.$symbol);
   return true;
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (is_null($message))
      $message = 'Updates the MetaTrader history of the specified symbols.';
   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END
$message

  Syntax:  $self [symbol ...] [OPTIONS]

  Options:  -h   This help screen.


END;
}
