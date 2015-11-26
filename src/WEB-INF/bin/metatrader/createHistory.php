#!/usr/bin/php
<?php
/**
 * Konvertiert die MyFX-History ein oder mehrerer Verzeichnisse ins MetaTrader-Format und legt sie im aktuellen Verzeichnis ab.
 * Der letzte Pfadbestandteil eines angegebenen Verzeichnisses wird als Symbol des zu konvertierenden Instruments interpretiert.
 * Dieses Symbol wird zusätzlich in die Datei "symbols.raw" im aktuellen Verzeichnis eingetragen.
 */
require(dirName(realPath(__FILE__)).'/../../config.php');


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenparameter auswerten
$args = array_slice($_SERVER['argv'], 1);


exit(0);


// --- Funktionen ---------------------------------------------------------------------------------------------------------------------------------------------


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
