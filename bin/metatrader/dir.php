#!/usr/bin/env php
<?php
namespace rosasurfer\xtrade\metatrader\dir;

/**
 * Verzeichnislisting fuer MetaTrader-Historydateien
 */
use rosasurfer\xtrade\XTrade;

use rosasurfer\xtrade\metatrader\HistoryHeader;
use rosasurfer\xtrade\metatrader\MetaTraderException;
use rosasurfer\xtrade\metatrader\MT4;

use rosasurfer\xtrade\model\metatrader\Order;

require(dirName(realPath(__FILE__)).'/../../app/init.php');


// -- Start -----------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenparameter auswerten
$args = array_slice($_SERVER['argv'], 1);
!$args && ($args[]='.');                                          // Historydateien des aktuellen Verzeichnis
$expandedArgs = [];

foreach ($args as $arg) {
    $value = $arg;
    strIsQuoted($value) && ($value=strLeft(strRight($value, -1), -1));

    if (file_exists($value)) {
        // explizites Argument oder Argument von Shell expandiert
        if (is_file($value)) {                                      // existierende Datei beliebigen Typs (alle werden analysiert)
            $expandedArgs[] = dirName($value).'/'.baseName($value);  // durch dirName() haben wir immer ein Verzeichnis fuer die Ausgabe (ggf. '.')
            continue;
        }
        // Verzeichnis, Glob-Pattern bereitstellen (siehe unten)
        $globPattern = $value.'/*.[Hh][Ss][Tt]';                    // *.hst in beliebiger Gross/Kleinschreibung
    }
    else {
        // Argument existiert nicht, Wildcards expandieren und Ergebnisse pruefen (z.B. unter Windows)
        strEndsWith($value, ['/', '\\']) && ($value.='*');
        $dirName  = dirName($value);
        $baseName = baseName($value); strEndsWith($baseName, '*') && ($baseName.='.hst');

        // um Gross-/Kleinschreibung von Symbolen ignorieren zu koennen, wird $baseName modifiziert
        $len = strLen($baseName); $s = ''; $inBrace = $inBracket = false;
        for ($i=0; $i < $len; $i++) {
            $char = $baseName[$i];                                   // angegebene Expansion-Pattern werden beruecksichtigt: {a,b,c}, [0-9] etc.
            if ($inBrace  ) { $inBrace   = ($char!='}'); $s .= $char; continue; }
            if ($inBracket) { $inBracket = ($char!=']'); $s .= $char; continue; }
            if (($inBrace=($char=='{')) || ($inBracket=($char=='[')) || !ctype_alpha($char)) {
                $s .= $char;
                continue;
            }
            $s .= '['.strToUpper($char).strToLower($char).']';
        }
        $globPattern = $dirName.'/'.$s;                             // $baseName=eu*.hst  =>  $s=[Ee][Uu]*.[Hh][Ss][Tt]
    }

    // Glob-Pattern einlesen und gefundene Dateien speichern
    $entries = glob($globPattern, GLOB_NOESCAPE|GLOB_BRACE|GLOB_ERR);
    foreach ($entries as $entry) if (is_file($entry))
        $expandedArgs[] = $entry;
}
!$expandedArgs && exit(1|echoPre('no history files found'));
sort($expandedArgs);                                              // alles sortieren (Dateien im aktuellen Verzeichnis ans Ende)


// (2) gefundene Dateien verzeichnisweise verarbeiten
$files   = [];
$formats = $symbols = $symbolsU = $periods = $digits = $syncMarkers = $lastSyncTimes = [];
$bars    = $barsFrom = $barsTo = $errors = [];
$dirName = $lastDir = null;

foreach ($expandedArgs as $fileName) {
    $dirName  = dirName($fileName);
    $baseName = baseName($fileName);
    if ($dirName!=$lastDir && $files) {                            // bei jedem neuen Verzeichnis vorherige angesammelte Daten anzeigen
        showDirResults($dirName, $files, $formats, $symbols, $symbolsU, $periods, $digits, $syncMarkers, $lastSyncTimes, $bars, $barsFrom, $barsTo, $errors);
        $files   = [];
        $formats = $symbols = $symbolsU = $periods = $digits = $syncMarkers = $lastSyncTimes = [];
        $bars    = $barsFrom = $barsTo = $errors = [];
    }
    $lastDir = $dirName;

    // Daten auslesen und fuer Anzeige zwischenspeichern
    $files[]  = $baseName;
    $fileSize = fileSize($fileName);

    if ($fileSize < HistoryHeader::SIZE) {
        // Fehlermeldung zwischenspeichern
        $formats      [] = null;
        $symbols      [] = ($name=strLeftTo($baseName, '.hst'));
        $symbolsU     [] = strToUpper($name);
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

    $hFile  = fOpen($fileName, 'rb');
    $header = null;
    try {
        $header = new HistoryHeader(fRead($hFile, HistoryHeader::SIZE));
    }
    catch (MetaTraderException $ex) {
        if (!strStartsWith($ex->getMessage(), 'version.unsupported')) throw $ex;
        $header = $ex->getMessage();
    }

    if (is_object($header)) {
        // Daten zwischenspeichern
        $formats      [] =            $header->getFormat();
        $symbols      [] =            $header->getSymbol();
        $symbolsU     [] = strToUpper($header->getSymbol());
        $periods      [] =            $header->getPeriod();
        $digits       [] =            $header->getDigits();
        $syncMarkers  [] =            $header->getSyncMarker()   ? gmDate('Y.m.d H:i:s', $header->getSyncMarker()  ) : null;
        $lastSyncTimes[] =            $header->getLastSyncTime() ? gmDate('Y.m.d H:i:s', $header->getLastSyncTime()) : null;

        $barVersion = $header->getFormat();
        $barSize    = ($barVersion==400) ? MT4::HISTORY_BAR_400_SIZE : MT4::HISTORY_BAR_401_SIZE;
        $iBars      = (int) floor(($fileSize-HistoryHeader::SIZE)/$barSize);

        $barFrom = $barTo = [];
        if ($iBars) {
            $barFrom  = unpack(MT4::BAR_getUnpackFormat($barVersion), fRead($hFile, $barSize));
            if ($iBars > 1) {
                fSeek($hFile, HistoryHeader::SIZE + $barSize*($iBars-1));
                $barTo = unpack(MT4::BAR_getUnpackFormat($barVersion), fRead($hFile, $barSize));
            }
        }

        $bars    [] = $iBars;
        $barsFrom[] = $barFrom ? gmDate('Y.m.d H:i:s', $barFrom['time']) : null;
        $barsTo  [] = $barTo   ? gmDate('Y.m.d H:i:s', $barTo  ['time']) : null;

        if (!strCompareI($baseName, $header->getSymbol().$header->getPeriod().'.hst')) {
            $formats [sizeOf($formats )-1] = null;
            $symbols [sizeOf($symbols )-1] = ($name=strLeftTo($baseName, '.hst'));
            $symbolsU[sizeOf($symbolsU)-1] = strToUpper($name);
            $periods [sizeOf($periods )-1] = null;
            $error = 'file name/data mis-match: data='.$header->getSymbol().','.XTrade::periodDescription($header->getPeriod());
        }
        else {
            $trailingBytes = ($fileSize-HistoryHeader::SIZE) % $barSize;
            $error = !$trailingBytes ? null : 'corrupted ('.$trailingBytes.' trailing bytes)';
        }
        $errors[] = $error;
    }
    else {
        // Fehlermeldung zwischenspeichern
        $formats      [] = null;
        $symbols      [] = ($name=strLeftTo($baseName, '.hst'));
        $symbolsU     [] = strToUpper($name);
        $periods      [] = null;
        $digits       [] = null;
        $syncMarkers  [] = null;
        $lastSyncTimes[] = null;
        $bars         [] = null;
        $barsFrom     [] = null;
        $barsTo       [] = null;
        $errors       [] = $header;   // ist $header kein Object, ist es ein String (Fehlermeldung einer Exception)
    }
    fClose($hFile);
}

// abschliessende Ausgabe fuer das letzte Verzeichnis
if ($files) {
    showDirResults($dirName, $files, $formats, $symbols, $symbolsU, $periods, $digits, $syncMarkers, $lastSyncTimes, $bars, $barsFrom, $barsTo, $errors);
}


// (4) regulaeres Programm-Ende
exit(0);


// --- Funktionen -----------------------------------------------------------------------------------------------------------


/**
 * Zeigt das Listing eines Verzeichnisses an.
 *
 * @param  string $dirName
 * @param  array  ...
 */
function showDirResults($dirName, array $files, array $formats, array $symbols, array $symbolsU, array $periods, array $digits, array $syncMarkers, array $lastSyncTimes, array $bars, array $barsFrom, array $barsTo, array $errors) {
    // Daten sortieren: ORDER by Symbol, Periode (ASC ist default); alle anderen "Spalten" mitsortieren
    array_multisort($symbolsU, SORT_ASC, $periods, SORT_ASC/*bis_hierher*/, array_keys($symbolsU), $symbols, $files, $formats, $digits, $syncMarkers, $lastSyncTimes, $bars, $barsFrom, $barsTo, $errors);

    // Tabellen-Format definieren und Header ausgeben
    $tableHeader    = 'Symbol           Digits  SyncMarker           LastSyncTime              Bars  From                 To                   Format';
    $tableSeparator = '------------------------------------------------------------------------------------------------------------------------------';
    $tableRowFormat = '%-15s    %d     %-19s  %-19s  %9s  %-19s  %-19s    %s  %s';
    echoPre(NL);
    echoPre($dirName.':');
    echoPre($tableHeader);

    // sortierte Daten ausgeben
    $lastSymbol = null;
    foreach ($files as $i => $fileName) {
        if ($symbols[$i] != $lastSymbol)
            echoPre($tableSeparator);

        if ($formats[$i]) {
            $period = XTrade::periodDescription($periods[$i]);
            echoPre(trim(sprintf($tableRowFormat, $symbols[$i].','.$period, $digits[$i], $syncMarkers[$i], $lastSyncTimes[$i], number_format($bars[$i]), $barsFrom[$i], $barsTo[$i], $formats[$i], $errors[$i])));
        }
        else {
            echoPre(str_pad($fileName, 18).' '.$errors[$i]);
        }
        $lastSymbol = $symbols[$i];
    }
    echoPre($tableSeparator);
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message [optional] - zusaetzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message = null) {
    if (!is_null($message))
        echo($message.NL.NL);

    $self = baseName($_SERVER['PHP_SELF']);

echo <<<HELP

  Syntax: $self  [file-pattern [...]]


HELP;
}
