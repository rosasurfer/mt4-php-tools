#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * TODO: replace by Ministruts console command
 *
 *
 * Verzeichnislisting fuer MetaTrader-Historydateien
 */
namespace rosasurfer\rt\cmd\mt4_dir;

use rosasurfer\rt\lib\metatrader\HistoryHeader;
use rosasurfer\rt\lib\metatrader\MetaTraderException;
use rosasurfer\rt\lib\metatrader\MT4;

use function rosasurfer\ministruts\echof;
use function rosasurfer\ministruts\numf;
use function rosasurfer\ministruts\strCompareI;
use function rosasurfer\ministruts\strEndsWith;
use function rosasurfer\ministruts\strIsQuoted;
use function rosasurfer\ministruts\strLeft;
use function rosasurfer\ministruts\strLeftTo;
use function rosasurfer\ministruts\strRight;
use function rosasurfer\ministruts\strStartsWith;

use function rosasurfer\rt\periodDescription;

use const rosasurfer\ministruts\NL;

require(__DIR__.'/../../../app/init.php');


// -- Start -----------------------------------------------------------------------------------------------------------------


// Befehlszeilenparameter auswerten
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);
!$args && ($args[]='.');                                          // Historydateien des aktuellen Verzeichnis
$expandedArgs = [];

foreach ($args as $arg) {
    $value = $arg;
    strIsQuoted($value) && ($value=strLeft(strRight($value, -1), -1));

    if (file_exists($value)) {
        // explizites Argument oder Argument von Shell expandiert
        if (is_file($value)) {                                      // existierende Datei beliebigen Typs (alle werden analysiert)
            $expandedArgs[] = dirname($value).'/'.basename($value);  // durch dirname() haben wir immer ein Verzeichnis fuer die Ausgabe (ggf. '.')
            continue;
        }
        // Verzeichnis, Glob-Pattern bereitstellen (siehe unten)
        $globPattern = $value.'/*.[Hh][Ss][Tt]';                    // *.hst in beliebiger Gross/Kleinschreibung
    }
    else {
        // Argument existiert nicht, Wildcards expandieren und Ergebnisse pruefen (z.B. unter Windows)
        strEndsWith($value, '/', '\\') && ($value.='*');
        $dirName  = dirname($value);
        $basename = basename($value); strEndsWith($basename, '*') && ($basename.='.hst');

        // um Gross-/Kleinschreibung von Symbolen ignorieren zu koennen, wird $basename modifiziert
        $len = strlen($basename); $s = ''; $inBrace = $inBracket = false;
        for ($i=0; $i < $len; $i++) {
            $char = $basename[$i];                                   // angegebene Expansion-Pattern werden beruecksichtigt: {a,b,c}, [0-9] etc.
            if ($inBrace  ) { $inBrace   = ($char!='}'); $s .= $char; continue; }
            if ($inBracket) { $inBracket = ($char!=']'); $s .= $char; continue; }
            if (($inBrace=($char=='{')) || ($inBracket=($char=='[')) || !ctype_alpha($char)) {
                $s .= $char;
                continue;
            }
            $s .= '['.strtoupper($char).strtolower($char).']';
        }
        $globPattern = $dirName.'/'.$s;                             // $basename=eu*.hst  =>  $s=[Ee][Uu]*.[Hh][Ss][Tt]
    }

    // Glob-Pattern einlesen und gefundene Dateien speichern
    $entries = glob($globPattern, GLOB_NOESCAPE|GLOB_BRACE|GLOB_ERR);
    foreach ($entries as $entry) {
        if (is_file($entry)) $expandedArgs[] = $entry;
    }
}
if (!$expandedArgs) {
    echof('no history files found');
    exit(1);
}
sort($expandedArgs);                                              // alles sortieren (Dateien im aktuellen Verzeichnis ans Ende)

// gefundene Dateien verzeichnisweise verarbeiten
$files = $formats = $symbols = $symbolsU = $periods = $digits = $syncMarkers = $lastSyncTimes = $bars = $barsFrom = $barsTo = $errors = [];
$dirName = $lastDir = null;

foreach ($expandedArgs as $fileName) {
    $dirName = dirname($fileName);
    $basename = basename($fileName);
    if ($dirName!=$lastDir && $files) {                            // bei jedem neuen Verzeichnis vorherige angesammelte Daten anzeigen
        showDirResults($dirName, $files, $formats, $symbols, $symbolsU, $periods, $digits, $syncMarkers, $lastSyncTimes, $bars, $barsFrom, $barsTo, $errors);
        $files = $formats = $symbols = $symbolsU = $periods = $digits = $syncMarkers = $lastSyncTimes = $bars = $barsFrom = $barsTo = $errors = [];
    }
    $lastDir = $dirName;

    // Daten auslesen und fuer Anzeige zwischenspeichern
    $files[] = $basename;
    $fileSize = filesize($fileName);

    if ($fileSize < HistoryHeader::SIZE) {
        // Fehlermeldung zwischenspeichern
        $formats      [] = null;
        $symbols      [] = ($name=strLeftTo($basename, '.hst'));
        $symbolsU     [] = strtoupper($name);
        $periods      [] = null;
        $digits       [] = null;
        $syncMarkers  [] = null;
        $lastSyncTimes[] = null;
        $bars         [] = null;
        $barsFrom     [] = null;
        $barsTo       [] = null;
        $errors       [] = 'invalid or unsupported file format: file size of '.$fileSize.' < minFileSize of '.HistoryHeader::SIZE;
        continue;
    }

    $hFile = fopen($fileName, 'rb');
    try {
        $header = HistoryHeader::fromStruct(fread($hFile, HistoryHeader::SIZE));

        // Daten zwischenspeichern
        $formats      [] =            $header->getFormat();
        $symbols      [] =            $header->getSymbol();
        $symbolsU     [] = strtoupper($header->getSymbol());
        $periods      [] =            $header->getPeriod();
        $digits       [] =            $header->getDigits();
        $syncMarkers  [] =            $header->getSyncMarker()   ? gmdate('Y.m.d H:i:s', $header->getSyncMarker()  ) : null;
        $lastSyncTimes[] =            $header->getLastSyncTime() ? gmdate('Y.m.d H:i:s', $header->getLastSyncTime()) : null;

        $barVersion = $header->getFormat();
        $barSize    = ($barVersion==400) ? MT4::HISTORY_BAR_400_SIZE : MT4::HISTORY_BAR_401_SIZE;
        $iBars      = (int) floor(($fileSize-HistoryHeader::SIZE)/$barSize);

        $barFrom = $barTo = [];
        if ($iBars) {
            $barFrom  = unpack(MT4::BAR_getUnpackFormat($barVersion), fread($hFile, $barSize));
            if ($iBars > 1) {
                fseek($hFile, HistoryHeader::SIZE + $barSize*($iBars-1));
                $barTo = unpack(MT4::BAR_getUnpackFormat($barVersion), fread($hFile, $barSize));
            }
        }

        $bars    [] = $iBars;
        $barsFrom[] = $barFrom ? gmdate('Y.m.d H:i:s', $barFrom['time']) : null;
        $barsTo  [] = $barTo   ? gmdate('Y.m.d H:i:s', $barTo  ['time']) : null;

        if (!strCompareI($basename, $header->getSymbol().$header->getPeriod().'.hst')) {
            $formats [sizeof($formats )-1] = null;
            $symbols [sizeof($symbols )-1] = ($name = strLeftTo($basename, '.hst'));
            $symbolsU[sizeof($symbolsU)-1] = strtoupper($name);
            $periods [sizeof($periods )-1] = null;
            $error = 'file name/data mis-match: data='.$header->getSymbol().','.periodDescription($header->getPeriod());
        }
        else {
            $trailingBytes = ($fileSize-HistoryHeader::SIZE) % $barSize;
            $error = !$trailingBytes ? null : 'corrupted ('.$trailingBytes.' trailing bytes)';
        }
        $errors[] = $error;
    }
    catch (MetaTraderException $ex) {
        if (!strStartsWith($ex->getMessage(), 'version.unsupported')) throw $ex;
        $name = strLeftTo($basename, '.hst');

        // Fehlermeldung zwischenspeichern
        $formats      [] = null;
        $symbols      [] = $name;
        $symbolsU     [] = strtoupper($name);
        $periods      [] = null;
        $digits       [] = null;
        $syncMarkers  [] = null;
        $lastSyncTimes[] = null;
        $bars         [] = null;
        $barsFrom     [] = null;
        $barsTo       [] = null;
        $errors       [] = $ex->getMessage();
    }
    fclose($hFile);
}

// abschliessende Ausgabe fuer das letzte Verzeichnis
showDirResults($dirName, $files, $formats, $symbols, $symbolsU, $periods, $digits, $syncMarkers, $lastSyncTimes, $bars, $barsFrom, $barsTo, $errors);

exit(0);


// --- Funktionen -----------------------------------------------------------------------------------------------------------


/**
 * Zeigt das Listing eines Verzeichnisses an.
 *
 * @param  string   $dirName
 * @param  string[] $files
 * @param  int[]    $formats
 * @param  string[] $symbols
 * @param  string[] $symbolsU
 * @param  int[]    $periods
 * @param  int[]    $digits
 * @param  string[] $syncMarkers
 * @param  string[] $lastSyncTimes
 * @param  int[]    $bars
 * @param  string[] $barsFrom
 * @param  string[] $barsTo
 * @param  string[] $errors
 *
 * @return void
 */
function showDirResults($dirName, array $files, array $formats, array $symbols, array $symbolsU, array $periods, array $digits, array $syncMarkers, array $lastSyncTimes, array $bars, array $barsFrom, array $barsTo, array $errors): void {
    // Daten sortieren: ORDER by Symbol, Periode (ASC ist default); alle anderen "Spalten" mitsortieren
    array_multisort($symbolsU, SORT_ASC, $periods, SORT_ASC/*bis_hierher*/, array_keys($symbolsU), $symbols, $files, $formats, $digits, $syncMarkers, $lastSyncTimes, $bars, $barsFrom, $barsTo, $errors);

    // Tabellen-Format definieren und Header ausgeben
    $tableHeader    = 'Symbol           Digits  SyncMarker           LastSyncTime              Bars  From                 To                   Format';
    $tableSeparator = '------------------------------------------------------------------------------------------------------------------------------';
    $tableRowFormat = '%-15s    %d     %-19s  %-19s  %9s  %-19s  %-19s    %s  %s';
    echof(NL);
    echof($dirName.':');
    echof($tableHeader);

    // sortierte Daten ausgeben
    $lastSymbol = null;
    foreach ($files as $i => $fileName) {
        if ($symbols[$i] != $lastSymbol)
            echof($tableSeparator);

        if ($formats[$i]) {
            $period = periodDescription($periods[$i]);
            echof(trim(sprintf($tableRowFormat, $symbols[$i].','.$period, $digits[$i], $syncMarkers[$i], $lastSyncTimes[$i], numf($bars[$i]), $barsFrom[$i], $barsTo[$i], $formats[$i], $errors[$i])));
        }
        else {
            echof(str_pad($fileName, 18).' '.$errors[$i]);
        }
        $lastSymbol = $symbols[$i];
    }
    echof($tableSeparator);
}


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

  Syntax: $self  [file-pattern [...]]


HELP;
}
