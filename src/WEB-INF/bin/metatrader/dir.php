#!/usr/bin/php -Cq
<?php
/**
 * Verzeichnislisting für MetaTrader-Historydateien
 *
 *
 * Folgende Einträge zur Datei <path>/4NT/alias.lst hinzufügen:
 * ------------------------------------------------------------
 *  mt4dir     =mt4dir.php
 *  mt4dir.php =<project_dir>/src/WEB-INF/bin/metatrader/mt4dir.php
 *  mtdir      =mt4dir
 */
require(dirName(realPath(__FILE__)).'/../../config.php');


// Unpack-Format des HistoryHeaders definieren: PHP 5.5.0 - The "a" code now retains trailing NULL bytes, "Z" replaces the former "a".
if (PHP_VERSION < '5.5.0') $hstHeaderFormat = 'Vformat/a64description/a12symbol/Vperiod/Vdigits/VsyncMark/VlastSync/VtimezoneId/x48';
else                       $hstHeaderFormat = 'Vformat/Z64description/Z12symbol/Vperiod/Vdigits/VsyncMark/VlastSync/VtimezoneId/x48';


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenparameter auswerten
$args = array_slice($_SERVER['argv'], 1);
!$args && ($args[0]='*');

$arg0 = $args[0];                                                    // Die Funktion glob() kann nicht verwendet werden, da sie beim Patternmatching unter Windows
if (realPath($arg0)) {                                               // Groß-/Kleinschreibung unterscheidet. Stattdessen werden Directory-Funktionen benutzt.
   $arg0 = realPath($arg0);
   if (is_dir($arg0)) { $dirName = $arg0;          $baseName = '';              }
   else               { $dirName = dirName($arg0); $baseName = baseName($arg0); }
}
else                  { $dirName = dirName($arg0); $baseName = baseName($arg0); }

!$baseName && ($baseName='*');
$baseName = str_replace('*', '.*', str_replace('.', '\.', $baseName));


// (2) Verzeichnis öffnen
$dir = Dir($dirName);
!$dir && exit("No history files found for \"$args[0]\"\n");


// (3.1) Dateinamen einlesen, filtern und aus dem Header "Symbol,Periode" auslesen (nicht aus dem Dateinamen, weil nicht eindeutig)
$matches = $formats = $symbols = $periods = array();
while (($entry=$dir->read()) !== false) {
   if (preg_match("/^$baseName$/i", $entry) && preg_match('/^(.+)\.hst$/i', $entry, $match)) {
      $matches[] = $entry;
      $filesize  = fileSize($entry);

      if ($filesize < HISTORY_HEADER_SIZE) {
         $formats[] = 0;
         $symbols[] = strToUpper($match[1]);                         // invalid or unknown history file format
         $periods[] = 0;
      }
      else {
         $hFile     = fOpen($entry, 'rb');
         $hstHeader = unpack($hstHeaderFormat, fRead($hFile, HISTORY_HEADER_SIZE));
         if ($hstHeader['format']==400 || $hstHeader['format']==401) {
            $formats[] =            $hstHeader['format'];
            $symbols[] = strToUpper($hstHeader['symbol']);
            $periods[] =            $hstHeader['period'];
         }
         else {
            $formats[] = 0;
            $symbols[] = strToUpper($match[1]);                      // unknown history file format
            $periods[] = 0;
         }
         fClose($hFile);
      }
   }
}
$dir->close();
!$matches && exit("No history files found for \"$args[0]\"\n");

// (3.2) gefundene Dateien sortieren: ORDER by Symbol ASC, Periode ASC
array_multisort($symbols, SORT_ASC, $periods, SORT_ASC, $matches);


// (4) Tabellen-Format definieren und Header ausgeben
$tableHeader    = 'Symbol           Digits  SyncMark             LastSync                  Bars  From                 To                   Format';
$tableSeparator = '------------------------------------------------------------------------------------------------------------------------------';
$tableRowFormat = '%-15s    %d     %-19s  %-19s  %9s  %-19s  %-19s    %s';
echoPre($tableHeader);


$lastSymbol = null;


// (5) sortierte Dateien erneut öffnen, alle Daten auslesen und fortlaufend anzeigen
foreach ($matches as $i => $filename) {
   if ($formats[$i]) {
      $filesize  = fileSize($filename);
      $hFile     = fOpen($filename, 'rb');
      $hstHeader = unpack($hstHeaderFormat, fRead($hFile, HISTORY_HEADER_SIZE));

      extract($hstHeader);
      $period   = MT4 ::periodDescription($period);
      $syncMark = $syncMark ? date('Y.m.d H:i:s', $syncMark) : '';
      $lastSync = $lastSync ? date('Y.m.d H:i:s', $lastSync) : '';

      if ($format == 400) { $barSize = HISTORY_BAR_400_SIZE; $barFormat = 'Vtime/dopen/dlow/dhigh/dclose/dticks';                          }
      else         /*401*/{ $barSize = HISTORY_BAR_401_SIZE; $barFormat = 'Vtime/x4/dopen/dhigh/dlow/dclose/Vticks/x4/lspread/Vvolume/x4'; }

      $bars    = floor(($filesize-HISTORY_HEADER_SIZE)/$barSize);
      $barFrom = $barTo = array();
      if ($bars) {
         $barFrom  = unpack($barFormat, fRead($hFile, $barSize));
         if ($bars > 1) {
            fSeek($hFile, HISTORY_HEADER_SIZE + $barSize*($bars-1));
            $barTo = unpack($barFormat, fRead($hFile, $barSize));
         }
      }
      fClose($hFile);

      $barFrom = $barFrom  ? gmDate('Y.m.d H:i:s', $barFrom['time']) : '';
      $barTo   = $barTo    ? gmDate('Y.m.d H:i:s', $barTo  ['time']) : '';
      if ($symbol != $lastSymbol)
         echoPre($tableSeparator);
      echoPre(sprintf($tableRowFormat, $symbol.','.$period, $digits, $syncMark, $lastSync, number_format($bars), $barFrom, $barTo, $format));
      $lastSymbol = $symbol;
   }
   else {
      echoPre(str_pad($filename, 21).' invalid or unknown history file format');
   }
}


// Programm-Ende
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

  Syntax: $self <file-pattern>

END;
}
?>
