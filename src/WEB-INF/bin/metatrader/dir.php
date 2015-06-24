#!/usr/bin/php -Cq
<?php
/**
 * Verzeichnislisting für MetaTrader-Historydateien
 *
 *
 * Zur Datei <path>/4NT/alias.lst hinzufügen:
 * ------------------------------------------
 *  mt4dir     =mt4dir.php
 *  mt4dir.php =<project_dir>/src/WEB-INF/bin/metatrader/mt4dir.php
 *  mtdir      =mt4dir
 */
require(dirName(realPath(__FILE__)).'/../../config.php');


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// Befehlszeilenparameter holen
$args = array_slice($_SERVER['argv'], 1);
!$args && exit("\n  Syntax: ".baseName($_SERVER['PHP_SELF'])." <file-pattern>\n");

$arg0 = $args[0];                                                    // Die Funktion glob() kann nicht verwendet werden, da sie beim Patternmatching unter Windows
if (realPath($arg0)) {                                               // Groß-/Kleinschreibung unerscheidet. Stattdessen werden Directory-Funktionen benutzt.
   $arg0 = realPath($arg0);
   if (is_dir($arg0)) { $dirName = $arg0;          $baseName = '';              }
   else               { $dirName = dirName($arg0); $baseName = baseName($arg0); }
}
else                  { $dirName = dirName($arg0); $baseName = baseName($arg0); }
!$baseName && ($basename='*');
$baseName = str_replace('*', '.*', str_replace('.', '\.', $baseName));


// Verzeichnis öffnen
$dir = Dir($dirName);
!$dir && exit("No history files found for \"$args[0]\"\n");


// Dateien filtern und einlesen
$matches = array();
while (($entry=$dir->read()) !== false) {
   if (preg_match("/^$baseName$/i", $entry) && preg_match('/^([^.]*\D)(\d+)(\.[^.]*)*\.hst$/i', $entry, $match)) {
      $symbols[] = strToUpper($match[1]);
      $periods[] = (int) $match[2];
      $matches[] = $entry;
   }
}
$dir->close();
!$matches && exit("No history files found for \"$args[0]\"\n");


// gefundene Dateien sortieren: order by Symbol ASC, Periode ASC
array_multisort($symbols, SORT_ASC, $periods, SORT_ASC, $matches);


// Tabellenheader ausgeben
echoPre("Symbol           Digits  TimeSign             LastSync                  Bars  From                 To");
echoPre("----------------------------------------------------------------------------------------------------------------------");

// Zeilenformat definieren
$lineFormat = '%-15s    %d     %-19s  %-19s  %9s  %-19s  %-19s';


// Dateien öffnen und auslesen
foreach ($matches as $i => $filename) {
   $filesize = fileSize($filename);
   if ($filesize < 148) {
      echoPre(str_pad($filename, 21).' invalid history file');
   }
   else {
      $hFile   = fOpen($filename, 'rb');
      $header  = unpack('Vversion/a64description/a12symbol/Vperiod/Vdigits/VtimeSign/VlastSync/a52reserved', fRead($hFile, 148));
      $bars    = floor(($filesize-148)/44);
      $barFrom = $barTo = array();
      if ($bars) {
         $barFrom  = unpack('Vtime/dopen/dlow/dhigh/dclose/dvol', fRead($hFile, 44));
         if ($bars > 1) {
            fSeek($hFile, 148 + 44*($bars-1));
            $barTo = unpack('Vtime/dopen/dlow/dhigh/dclose/dvol', fRead($hFile, 44));
         }
      }
      fClose($hFile);

      extract($header);
      $period   = MT4 ::periodDescription($period);
      $timeSign = $timeSign ? date('Y.m.d H:i:s', $timeSign) : '';
      $lastSync = $lastSync ? date('Y.m.d H:i:s', $lastSync) : '';
      $barFrom  = $barFrom  ? gmDate('Y.m.d H:i:s', $barFrom['time']) : '';
      $barTo    = $barTo    ? gmDate('Y.m.d H:i:s', $barTo  ['time']) : '';

      echoPre(sprintf($lineFormat, $symbol.','.$period, $digits, $timeSign, $lastSync, number_format($bars), $barFrom, $barTo));
   }
}
?>
