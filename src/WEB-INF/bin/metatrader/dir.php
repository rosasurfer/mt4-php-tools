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


// gefundene Dateien sortieren: ORDER by Symbol ASC, Periode ASC
array_multisort($symbols, SORT_ASC, $periods, SORT_ASC, $matches);


// Ausgabeformat der Zeilen definieren
$tableHeader    = 'Symbol           Digits  TimeSign             LastSync                  Bars  From                 To                   Version';
$tableSeparator = '-------------------------------------------------------------------------------------------------------------------------------';
$tableRowFormat = '%-15s    %d     %-19s  %-19s  %9s  %-19s  %-19s    %s';
$lastSymbol     = null;


// Tabellenheader ausgeben
echoPre($tableHeader);


// Unpack-Format des HistoryHeaders anpassen: PHP 5.5.0 - The "a" code now retains trailing NULL bytes, "Z" replaces the former "a".
if (PHP_VERSION < '5.5.0') $hstHeaderFormat = 'Vversion/a64description/a12symbol/Vperiod/Vdigits/VtimeSign/VlastSync/x52';
else                       $hstHeaderFormat = 'Vversion/Z64description/Z12symbol/Vperiod/Vdigits/VtimeSign/VlastSync/x52';


// Dateien öffnen und auslesen
foreach ($matches as $i => $filename) {
   $filesize = fileSize($filename);
   if ($filesize < HISTORY_HEADER_SIZE) {
      echoPre(str_pad($filename, 21).' invalid history file');
   }
   else {
      $hFile     = fOpen($filename, 'rb');
      $hstHeader = unpack($hstHeaderFormat, fRead($hFile, HISTORY_HEADER_SIZE));

      extract($hstHeader);
      $period   = MT4 ::periodDescription($period);
      $timeSign = $timeSign ? date('Y.m.d H:i:s', $timeSign) : '';
      $lastSync = $lastSync ? date('Y.m.d H:i:s', $lastSync) : '';

      if ($version == 400) {
         $barSize   = HISTORY_BAR_400_SIZE;
         $barFormat = 'Vtime/dopen/dlow/dhigh/dclose/dticks';
      }
      else if ($version == 401) {
         $barSize   = HISTORY_BAR_401_SIZE;
         $barFormat = 'Vtime/x4/dopen/dhigh/dlow/dclose/Vticks/x4/lspread/Vvolume/x4';
      }
      else {
         echoPre(str_pad($filename, 21).' unknown history file format ('.$version.')');
         fClose($hFile);
         continue;
      }

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
      echoPre(sprintf($tableRowFormat, $symbol.','.$period, $digits, $timeSign, $lastSync, number_format($bars), $barFrom, $barTo, $version));
      $lastSymbol = $symbol;
   }
}
?>
