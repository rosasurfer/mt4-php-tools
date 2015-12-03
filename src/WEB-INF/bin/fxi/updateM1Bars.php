#!/usr/bin/php
<?php
/**
 * Erzeugt die MyFX-History verschiedener FX-Indizes (zur Zeit aus Dukascopy-Daten). Nach Möglichkeit werden zur Berechnung vorhandene
 * Tickdaten benutzt.
 *
 * Unterstützte Instrumente:
 *
 *  • USDX und EURX: ICE-Formel
 *  • LFX-Indizes:   LiteForex-Formel
 *  • FX6-Indizes:   AUDLFX, CADLFX, CHFLFX, EURLFX, GBPLFX, JPYLFX, USDLFX (geometrisches Mittel, normalisiert)
 *  • FX7-Indizes:   AUDLFX, CADLFX, CHFLFX, EURLFX, GBPLFX, JPYLFX, NZDLFX, USDLFX (geometrisches Mittel, normalisiert)
 *  • SEKFX6/SEKFX7: SEK gegen FX6/FX7-Index
 *  • NOKFX6/NOKFX7: NOK gegen FX6/FX7-Index
 */
require(dirName(realPath(__FILE__)).'/../../config.php');
date_default_timezone_set('GMT');


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$verbose = 0;                                                        // output verbosity


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);

// Optionen parsen
foreach ($args as $i => $arg) {
   if ($arg == '-h') help() & exit(1);                               // Hilfe
}

exit(0);


// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


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

 Syntax:  $self [symbol ...]


END;
}
?>