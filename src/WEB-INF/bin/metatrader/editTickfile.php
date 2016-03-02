#!/usr/bin/php
<?php
/**
 * Modifiziert eine FXT-Tickdatei für den Strategy Tester.
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
   if ($arg == '-h'  )   help() & exit(1);                                          // Hilfe
   if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; } // verbose output
   if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; } // more verbose output
   if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; } // very verbose output
}

help() & exit(1);


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
Modifies a MetaTrader Strategy Tester tick file.

  Syntax:  $self [OPTIONS...] TICKFILE

  Options:  -h  This help screen.


END;
}
