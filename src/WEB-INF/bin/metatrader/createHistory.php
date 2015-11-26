#!/usr/bin/php
<?php
/**
 * Konvertiert die MyFX-History ein oder mehrerer Verzeichnisse ins MetaTrader-Format und legt sie im aktuellen Verzeichnis ab.
 * Der letzte Pfadbestandteil eines angegebenen Verzeichnisses wird als Symbol des zu konvertierenden Instruments interpretiert.
 * Dieses Symbol wird zusätzlich in die Datei "symbols.raw" im aktuellen Verzeichnis eingetragen.
 */
require(dirName(realPath(__FILE__)).'/../../config.php');
date_default_timezone_set('GMT');


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$verbose = 0;                                                           // output verbosity


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenparameter auswerten
$args = array_slice($_SERVER['argv'], 1);
if (!$args) help() & exit(1);

// Optionen parsen
$looping = $fileSyncOnly = false;
foreach ($args as $i => $arg) {
   if (in_array($arg, array('-h','--help'))) help() & exit(1);          // Hilfe
   if ($arg == '-v'  ) { $verbose = 1; unset($args[$i]); continue; }    // verbose output
   if ($arg == '-vv' ) { $verbose = 2; unset($args[$i]); continue; }    // more verbose output
   if ($arg == '-vvv') { $verbose = 3; unset($args[$i]); continue; }    // very verbose output
}

// Source-Verzeichnis(se) validieren
foreach ($args as $i => $arg) {
   //if (!is_dir($arg)) help('error: not a source directory "'.$args[$i].'"') & exit(1);
}


// (2) Source-Verzeichnisse durchlaufen und History erstellen
foreach ($args as $directory) {
   $symbol = baseName($directory);
   if (!createHistory($symbol, $directory))
      exit(1);
}
exit(0);


// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


/**
 * Erzeugt die MetaTrader-History eines Symbol.
 *
 * @param string $symbol    - Symbol
 * @param string $directory - Source-Verzeichnis
 *
 * @return bool - Erfolgsstatus
 */
function createHistory($symbol, $directory) {
   if (!is_string($symbol))    throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   if (!strLen($symbol))       throw new plInvalidArgumentException('Invalid parameter $symbol: ""');
   if (!is_string($directory)) throw new IllegalTypeException('Illegal type of parameter $directory: '.getType($directory));
   //if (!is_dir($directory))    throw new plInvalidArgumentException('Invalid parameter $directory: "'.$directory.'" (not a directory)');

   echoPre(glob($directory, GLOB_ERR|GLOB_BRACE));

   return true;
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (!is_null($message))
      echo($message."\n\n");

   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END

  Syntax: $self <directory> ...


END;
}
?>
