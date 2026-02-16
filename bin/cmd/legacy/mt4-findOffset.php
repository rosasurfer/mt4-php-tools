#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * TODO: replace by Ministruts console command
 *
 *
 * Gibt den Offset der ersten Bar einer MetaTrader-Historydatei zurueck, die am oder nach dem angegebenen Zeitpunkt beginnt
 * oder -1, wenn keine solche Bar existiert.
 */
namespace rosasurfer\rt\cmd\mt4_find_offset;

use rosasurfer\rt\lib\metatrader\HistoryHeader;
use rosasurfer\rt\lib\metatrader\MetaTraderException;
use rosasurfer\rt\lib\metatrader\MT4;

use function rosasurfer\ministruts\strIsQuoted;
use function rosasurfer\ministruts\strLeft;
use function rosasurfer\ministruts\strRight;
use function rosasurfer\ministruts\strStartsWith;
use function rosasurfer\ministruts\strToTimestamp;

use const rosasurfer\ministruts\NL;

require(__DIR__.'/../../../app/init.php');
date_default_timezone_set('GMT');


// -- Konfiguration ---------------------------------------------------------------------------------------------------------


$byteOffset = false;
$quietMode  = false;


// -- Start -----------------------------------------------------------------------------------------------------------------


// Befehlszeilenargumente einlesen und validieren
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// Optionen parsen
foreach ($args as $i => $arg) {
    $arg = strtolower($arg);
    if ($arg == '-h') { help(); exit(1); }                                  // Hilfe
    if ($arg == '-c') { $byteOffset=true; unset($args[$i]); continue; }     // -c: byte offset
    if ($arg == '-q') { $quietMode =true; unset($args[$i]); continue; }     // -q: quiet mode
}

// Das verbleibende erste Argument muss ein Zeitpunkt sein.
if (sizeof($args) < 2) {
    help();
    exit(1);
}
$sTime = $arg = array_shift($args);

if (strIsQuoted($sTime)) $sTime = trim(strLeft(strRight($sTime, -1), -1));
if (!strToTimestamp($sTime, ['Y-m-d', 'Y-m-d H:i', 'Y-m-d H:i:s'])) {
    echof('invalid argument datetime = '.$arg);
    exit(1);
}
$datetime = strtotime($sTime.' GMT');

// Das verbleibende zweite Argument muss ein History-File sein.
$fileName = array_shift($args);
if (!is_file($fileName)) {
    echof('file not found "'.$fileName.'"');
    exit(1);
}

// Datei oeffnen und Header auslesen
$fileSize = filesize($fileName);
if ($fileSize < HistoryHeader::SIZE) {
    echof('invalid or unknown history file format: file size of "'.$fileName.'" < MinFileSize ('.HistoryHeader::SIZE.')');
    exit(1);
}
$hFile  = fopen($fileName, 'rb');
$header = null;
try {
    $header = HistoryHeader::fromStruct(fread($hFile, HistoryHeader::SIZE));
}
catch (MetaTraderException $ex) {
    if (strStartsWith($ex->getMessage(), 'version.unsupported')) {
        echof('unsupported history format in "'.$fileName.'": '.$ex->getMessage());
        exit(1);
    }
    throw $ex;
}

if ($header->getFormat() == 400) { $barSize = MT4::HISTORY_BAR_400_SIZE; $barFormat = 'Vtime/dopen/dlow/dhigh/dclose/dticks';                          }
else                      /*401*/{ $barSize = MT4::HISTORY_BAR_401_SIZE; $barFormat = 'Vtime/x4/dopen/dhigh/dlow/dclose/Vticks/x4/lspread/Vvolume/x4'; }


// Anzahl der Bars bestimmen und Beginn- und Endbar auslesen
$i = 0;
$bars = ($fileSize-HistoryHeader::SIZE)/$barSize;
if (!is_int($bars)) {
    echof('unexpected EOF of "'.$fileName.'"');
    $bars = (int) $bars;
}
$barFrom = $barTo = [];
$iFrom = $iTo = null;
if (!$bars) {
    $i = -1;                      // Datei enthaelt keine Bars
}
else {
    $barFrom = unpack($barFormat, fread($hFile, $barSize));
    $iFrom = 0;
    fseek($hFile, HistoryHeader::SIZE + $barSize*($bars-1));
    $barTo = unpack($barFormat, fread($hFile, $barSize));
    $iTo = $bars-1;
}


// Zeitfenster von Beginn- und Endbar rekursiv bis zum gesuchten Zeitpunkt verkleinern
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

    $halfSize = (int) ceil($bars/2);
    $iMid     = $iFrom + $halfSize - 1;
    fseek($hFile, HistoryHeader::SIZE + $iMid*$barSize);
    $barMid   = unpack($barFormat, fread($hFile, $barSize));
    if ($barMid['time'] <= $datetime) { $barFrom = $barMid; $iFrom = $iMid; }
    else                              { $barTo   = $barMid; $iTo   = $iMid; }
    $bars = $iTo - $iFrom + 1;
}


// Ergebnis ausgeben
if ($i>=0 && $byteOffset) $result = HistoryHeader::SIZE + $i*$barSize;
else                      $result = $i;

if      ($quietMode ) echo $result;
else if ($byteOffset) echof('byte offset: '.$result);
else                  echof('bar offset: ' .$result);

exit(0);



// --- Funktionen -----------------------------------------------------------------------------------------------------------


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  ?string $message [optional] - zusaetzlich zur Syntax anzuzeigende Message (default: keine)
 *
 * @return void
 */
function help($message = null) {
    if (isset($message))
        echo $message.NL.NL;

    $self = basename($_SERVER['PHP_SELF']);

echo <<<HELP
Returns the offset of the first bar in a MetaTrader history file at or after a specified time or -1 if no such bar is found.

  Syntax:  $self  [OPTION]... TIME FILE

  Options:  -c  Returns the byte offset of the found bar instead of the bar offset.
            -q  Quiet mode. Returns only the numeric result value.
            -h  This help screen.


HELP;
}
