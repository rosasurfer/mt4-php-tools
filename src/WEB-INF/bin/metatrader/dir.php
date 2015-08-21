#!/usr/bin/php
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


// (3.1) Dateinamen einlesen, filtern und Daten auslesen und zwischenspeichern
$fileNames = $formats = $symbols = $periods = $aDigits = $syncMarks = $lastSyncs = $timezoneIds = $aBars = $barFroms = $barTos = $errors = array();

while (($fileName=$dir->read()) !== false) {
   if (preg_match("/^$baseName$/i", $fileName) && preg_match('/^(.+)\.hst$/i', $fileName, $match)) {
      $fileNames[] = $fileName;
      $fileSize    = fileSize($fileName);

      if ($fileSize < HISTORY_HEADER_SIZE) {
         $formats    [] = null;
         $symbols    [] = strToUpper($match[1]);
         $periods    [] = null;
         $aDigits    [] = null;
         $syncMarks  [] = null;
         $lastSyncs  [] = null;
         $timezoneIds[] = null;
         $aBars      [] = null;
         $barFroms   [] = null;
         $barTos     [] = null;
         $errors     [] = 'invalid or unknown history file format: fileSize '.$fileSize.' < minFileSize';
         continue;
      }

      $hFile     = fOpen($fileName, 'rb');
      $hstHeader = unpack($hstHeaderFormat, fRead($hFile, HISTORY_HEADER_SIZE));
      extract($hstHeader);

      if ($format==400 || $format==401) {
         $formats    [] =            $format;
         $symbols    [] = strToUpper($symbol);
         $periods    [] =            $period;
         $aDigits    [] =            $digits;
         $syncMarks  [] =            $syncMark ? date('Y.m.d H:i:s', $syncMark) : null;
         $lastSyncs  [] =            $lastSync ? date('Y.m.d H:i:s', $lastSync) : null;
         $timezoneIds[] =            $timezoneId;

         if ($format == 400) { $barSize = HISTORY_BAR_400_SIZE; $barFormat = 'Vtime/dopen/dlow/dhigh/dclose/dticks';                          }
         else         /*401*/{ $barSize = HISTORY_BAR_401_SIZE; $barFormat = 'Vtime/x4/dopen/dhigh/dlow/dclose/Vticks/x4/lspread/Vvolume/x4'; }

         $bars    = floor(($fileSize-HISTORY_HEADER_SIZE)/$barSize);
         $barFrom = $barTo = array();
         if ($bars) {
            $barFrom  = unpack($barFormat, fRead($hFile, $barSize));
            if ($bars > 1) {
               fSeek($hFile, HISTORY_HEADER_SIZE + $barSize*($bars-1));
               $barTo = unpack($barFormat, fRead($hFile, $barSize));
            }
         }

         $aBars   [] = $bars;
         $barFroms[] = $barFrom ? gmDate('Y.m.d H:i:s', $barFrom['time']) : null;
         $barTos  [] = $barTo   ? gmDate('Y.m.d H:i:s', $barTo  ['time']) : null;

         if (strToUpper($fileName) != strToUpper($symbol.$period.'.hst')) {
            $formats[sizeOf($formats)-1] = null;
            $symbols[sizeOf($symbols)-1] = strToUpper($match[1]);
            $periods[sizeOf($periods)-1] = null;
            $error = 'file name/data mis-match: data='.$symbol.','.MT4 ::periodDescription($period);
         }
         else {
            $trailingBytes = ($fileSize-HISTORY_HEADER_SIZE) % $barSize;
            $error = !$trailingBytes ? null : 'corrupted ('.$trailingBytes.' trailing bytes)';
         }
         $errors[] = $error;
      }
      else {
         $formats    [] = null;
         $symbols    [] = strToUpper($match[1]);
         $periods    [] = null;
         $aDigits    [] = null;
         $syncMarks  [] = null;
         $lastSyncs  [] = null;
         $timezoneIds[] = null;
         $aBars      [] = null;
         $barFroms   [] = null;
         $barTos     [] = null;
         $errors     [] = 'invalid or unknown history file format: '.$format;
      }
      fClose($hFile);
   }
}
$dir->close();
!$fileNames && exit("No history files found for \"$args[0]\"\n");

// (3.2) Daten sortieren: ORDER by Symbol, Periode (ASC ist default); alle anderen "Spalten" mitsortieren
array_multisort($symbols, SORT_ASC, $periods, SORT_ASC/*bis_hier*/, array_keys($symbols), $fileNames, $formats, $aDigits, $syncMarks, $lastSyncs, $timezoneIds, $aBars, $barFroms, $barTos, $errors);


// (4) Tabellen-Format definieren und Header ausgeben
$tableHeader    = 'Symbol           Digits  SyncMark             LastSync                  Bars  From                 To                   Format';
$tableSeparator = '------------------------------------------------------------------------------------------------------------------------------';
$tableRowFormat = '%-15s    %d     %-19s  %-19s  %9s  %-19s  %-19s    %s  %s';
echoPre($tableHeader);


// (5) sortierte Daten anzeigen
$lastSymbol = null;

foreach ($fileNames as $i => $fileName) {
   if ($symbols[$i] != $lastSymbol)
      echoPre($tableSeparator);

   if ($formats[$i]) {
      $period = MT4 ::periodDescription($periods[$i]);
      echoPre(sprintf($tableRowFormat, $symbols[$i].','.$period, $aDigits[$i], $syncMarks[$i], $lastSyncs[$i], number_format($aBars[$i]), $barFroms[$i], $barTos[$i], $formats[$i], $errors[$i]));
   }
   else {
      echoPre(str_pad($fileName, 18).' '.$errors[$i]);
   }
   $lastSymbol = $symbols[$i];
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
