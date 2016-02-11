#!/usr/bin/php
<?php
/**
 * Gibt den Offset der ersten Bar einer MetaTrader-Historydatei zurück, die am oder nach dem angegebenen Zeitpunkt beginnt.
 */
require(dirName(realPath(__FILE__)).'/../../config.php');
date_default_timezone_set('GMT');


// Unpack-Formate des History-Headers: PHP 5.5.0 - The "a" code now retains trailing NULL bytes, "Z" replaces the former "a".
if (PHP_VERSION < '5.5.0') $hstHeaderFormat = 'Vformat/a64description/a12symbol/Vperiod/Vdigits/VsyncMark/VlastSync/VtimezoneId/x48';
else                       $hstHeaderFormat = 'Vformat/Z64description/Z12symbol/Vperiod/Vdigits/VsyncMark/VlastSync/VtimezoneId/x48';


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------
$byteOffset = false;
$quietMode  = false;


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);

// (1.1) Optionen parsen
foreach ($args as $i => $arg) {
   $arg = strToLower($arg);
   if (in_array($arg, array('-h','--help'))) help() & exit(1);             // Hilfe
   if ($arg == '-c') { $byteOffset=true; unset($args[$i]); continue; }     // -c: byte offset
   if ($arg == '-q') { $quietMode =true; unset($args[$i]); continue; }     // -q: quiet mode
}

// (1.2) Das verbleibende erste Argument muß ein Zeitpunkt sein.
if (sizeOf($args) < 2) help() & exit(1);
$sTime = $arg = array_shift($args);

if      (strStartsWith($sTime, "'") && strEndsWith($sTime, "'")) $sTime = trim($sTime, " '");
else if (strStartsWith($sTime, '"') && strEndsWith($sTime, '"')) $sTime = trim($sTime, ' "');
(!is_datetime($sTime, 'Y-m-d') && !is_datetime($sTime, 'Y-m-d H:i') && !is_datetime($sTime, 'Y-m-d H:i:s')) && help('invalid argument datetime = '.$arg) & exit(1);

$datetime = strToTime($sTime.' GMT');

// (1.2) Das verbleibende zweite Argument muß ein History-File sein.
$fileName = $arg = array_shift($args);
!is_file($fileName) && help('file not found "'.$fileName.'"') & exit(1);


// (2) Datei öffnen, Header auslesen und History-Format bestimmen
$fileSize = fileSize($fileName);
($fileSize < HISTORY_HEADER_SIZE) && echoPre('invalid or unknown history file format: file size of "'.$fileName.'" < minFileSize ('.HISTORY_HEADER_SIZE.')') & exit(1);
$hFile     = fOpen($fileName, 'rb');
$hstHeader = unpack($hstHeaderFormat, fRead($hFile, HISTORY_HEADER_SIZE));
extract($hstHeader);
if      ($format == 400) { $barSize = HISTORY_BAR_400_SIZE; $barFormat = 'Vtime/dopen/dlow/dhigh/dclose/dticks';                          }
else if ($format == 401) { $barSize = HISTORY_BAR_401_SIZE; $barFormat = 'Vtime/x4/dopen/dhigh/dlow/dclose/Vticks/x4/lspread/Vvolume/x4'; }
else echoPre('unsupported history file format "'.$format.'" in "'.$fileName.'"') & exit(1);


// (3) Anzahl der Bars bestimmen und Beginn- und Endbar auslesen
$i = 0;
$allBars = $bars = ($fileSize-HISTORY_HEADER_SIZE)/$barSize;
if (!is_int($bars)) {
   echoPre('unexpected EOF of "'.$fileName.'"');;
   $allBars = $bars = (int) $bars;
}
$barFrom = $barTo = array();
if (!$bars) {
   $i = -1;                      // Datei enthält keine Bars
}
else {
   $barFrom = unpack($barFormat, fRead($hFile, $barSize));
   $iFrom   = 0;
   fSeek($hFile, HISTORY_HEADER_SIZE + $barSize*($bars-1));
   $barTo   = unpack($barFormat, fRead($hFile, $barSize));
   $iTo     = $bars-1;
}


// (4) Zeitfenster von Beginn- und Endbar rekursiv bis zum gesuchten Zeitpunkt verkleinern
while ($i != -1) {
   if ($barFrom['time'] >= $datetime) {
      $i = $iFrom;
      break;
   }
   if ($barTo['time'] < $datetime) {
      $i = -1;
      break;
   }
   if ($barTo['time']==$datetime || $bars==2) {
      $i = $iTo;
      break;
   }

   $halfBars = ceil($bars/2);
   $iMiddle  = $iFrom+$halfBars-1;
   fSeek($hFile, HISTORY_HEADER_SIZE + $barSize*($iMiddle));
   $barMiddle = unpack($barFormat, fRead($hFile, $barSize));
   if ($barMiddle['time'] <= $datetime) { $barFrom = $barMiddle; $iFrom = $iMiddle; }
   else                                 { $barTo   = $barMiddle; $iTo   = $iMiddle; }
   $bars = $iTo - $iFrom + 1;
}


// (5) Ergebnis ausgeben
if ($i>=0 && $byteOffset) $result = HISTORY_HEADER_SIZE + $i*$barSize;
else                      $result = $i;

if      ($quietMode ) echo $result;
else if ($byteOffset) echoPre('byte offset: '.$result);
else                  echoPre('bar offset: ' .$result);

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
Finds the offset of the first bar in a MetaTrader history file at or after a specified time or -1 if no such bar is found.

  Syntax:  $self  [OPTION]... TIME FILE

  Options:  -c  Prints the character offset of the found bar instead of the bar offset.
            -q  Quiet mode. Prints only the numeric result value.
            -h  This help screen.


END;
}
