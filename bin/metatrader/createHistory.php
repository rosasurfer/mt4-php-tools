#!/usr/bin/env php
<?php
/**
 * Liest die Rosatrader-M1-History der angegebenen Instrumente ein und erzeugt daraus jeweils eine neue MetaTrader-History.
 * Speichert diese MetaTrader-History im globalen MT4-Serververzeichnis. Vorhandene Historydateien werden ueberschrieben.
 * Um vorhandene Historydateien zu aktualisieren, ist "updateHistory.php" zu benutzen.
 */
namespace rosasurfer\rost\metatrader\create_history;

use rosasurfer\config\Config;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\util\PHP;

use rosasurfer\rost\Rost;
use rosasurfer\rost\metatrader\HistorySet;
use rosasurfer\rost\metatrader\MT4;

use function rosasurfer\rost\fxtTime;
use function rosasurfer\rost\isFxtWeekend;
use rosasurfer\rost\model\RosaSymbol;

require(dirName(realPath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');


// -- Konfiguration ---------------------------------------------------------------------------------------------------------


$verbose = 0;                                                                       // output verbosity


// -- Start -----------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// Optionen parsen
foreach ($args as $i => $arg) {
    if ($arg == '-h'  )   exit(1|help());                                            // Hilfe
    if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; } // verbose output
    if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; } // more verbose output
    if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; } // very verbose output
}

/** @var RosaSymbol[] $symbols */
$symbols = [];

// Symbole parsen
foreach ($args as $i => $arg) {
    /** @var RosaSymbol $symbol */
    $symbol = RosaSymbol::dao()->findByName($arg);
    if (!$symbol) exit(1|stderror('error: unknown symbol "'.$args[$i].'"'));
    $symbols[$symbol->getName()] = $symbol;                                         // using the name as index removes duplicates
}
$symbols = $symbols ?: RosaSymbol::dao()->findAll();                                // ohne Angabe werden alle Instrumente verarbeitet


// (2) History erstellen
foreach ($symbols as $symbol) {
    createHistory($symbol) || exit(1);
}
exit(0);


// --- Funktionen -----------------------------------------------------------------------------------------------------------


/**
 * Erzeugt eine neue MetaTrader-History eines Instruments.
 *
 * @param  RosaSymbol $symbol
 *
 * @return bool - Erfolgsstatus
 */
function createHistory(RosaSymbol $symbol) {
    $symbolName   = $symbol->getName();
    $symbolDigits = $symbol->getDigits();

    $startDay  = (int)$symbol->getHistoryStartM1('U');                              // FXT
    $startDay -= $startDay%DAY;                                                     // 00:00 FXT Starttag
    $today     = ($today=fxtTime()) - $today%DAY;                                   // 00:00 FXT aktueller Tag


    // MT4-HistorySet erzeugen
    $directory = Config::getDefault()->get('app.dir.data').'/history/mt4/XTrade-Testhistory';
    $hstSet = HistorySet::create($symbolName, $symbolDigits, $format=400, $directory);


    // Gesamte Zeitspanne tageweise durchlaufen
    for ($day=$startDay, $lastMonth=-1; $day < $today; $day+=1*DAY) {
        $shortDate = gmDate('D, d-M-Y', $day);
        $month     = (int) gmDate('m', $day);
        if ($month != $lastMonth) {
            echoPre('[Info]    '.gmDate('M-Y', $day));
            $lastMonth = $month;
        }

        // ausser an Wochenenden: Rost-History verarbeiten
        if (!isFxtWeekend($day, 'FXT')) {
            if      (is_file($file=getVar('rostFile.compressed', $symbolName, $day))) {}    // wenn komprimierte Rost-Datei existiert
            else if (is_file($file=getVar('rostFile.raw'       , $symbolName, $day))) {}    // wenn unkomprimierte Rost-Datei existiert
            else {
                echoPre('[Error]   '.$symbolName.'  Rosatrader history for '.$shortDate.' not found');
                return false;
            }
            // Bars einlesen und der MT4-History hinzufuegen
            $bars = Rost::readBarFile($file, $symbolName);
            $hstSet->appendBars($bars);
        }

        if (!WINDOWS) pcntl_signal_dispatch();                                          // Auf Ctrl-C pruefen, um bei Abbruch den
    }                                                                                   // Schreibbuffer der History leeren zu koennen.
    $hstSet->close();

    echoPre('[Ok]      '.$symbolName);
    return true;
}


/**
 * Erzeugt und verwaltet dynamisch generierte Variablen.
 *
 * Evaluiert und cacht haeufig wiederbenutzte dynamische Variablen an einem zentralen Ort. Vereinfacht die Logik,
 * da die Variablen nicht global gespeichert oder ueber viele Funktionsaufrufe hinweg weitergereicht werden muessen,
 * aber trotzdem nicht bei jeder Verwendung neu ermittelt werden brauchen.
 *
 * @param  string $id     - eindeutiger Bezeichner der Variable (ID)
 * @param  string $symbol - Symbol oder NULL
 * @param  int    $time   - Timestamp oder NULL
 *
 * @return string - Variable
 */
function getVar($id, $symbol=null, $time=null) {
    static $varCache = [];
    if (array_key_exists(($key=$id.'|'.$symbol.'|'.$time), $varCache))
        return $varCache[$key];

    if (!is_string($id))                       throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
    if (isSet($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
    if (isSet($time) && !is_int($time))        throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

    $self = __FUNCTION__;
    static $dataDir; !$dataDir && $dataDir = Config::getDefault()->get('app.dir.data');

    if ($id == 'rostDirDate') {               // $yyyy/$mm/$dd                                                  // lokales Pfad-Datum
        if (!$time) throw new InvalidArgumentException('Invalid parameter $time: '.$time);
        $result = gmDate('Y/m/d', $time);
    }
    else if ($id == 'rostDir') {              // $dataDirectory/history/rost/$type/$symbol/$rostDirDate         // lokales Verzeichnis
        $type        = RosaSymbol::dao()->getByName($symbol)->getType();
        $rostDirDate = $self('rostDirDate', null, $time);
        $result      = $dataDir.'/history/rost/'.$type.'/'.$symbol.'/'.$rostDirDate;
    }
    else if ($id == 'rostFile.raw') {         // $rostDir/M1.myfx                                               // lokale Datei ungepackt
        $rostDir = $self('rostDir' , $symbol, $time);
        $result  = $rostDir.'/M1.myfx';
    }
    else if ($id == 'rostFile.compressed') {  // $rostDir/M1.rar                                                // lokale Datei gepackt
        $rostDir = $self('rostDir' , $symbol, $time);
        $result  = $rostDir.'/M1.rar';
    }
    else {
      throw new InvalidArgumentException('Unknown variable identifier "'.$id.'"');
    }

    $varCache[$key] = $result;
    (sizeof($varCache) > ($maxSize=128)) && array_shift($varCache) /*&& echoPre('cache size limit of '.$maxSize.' hit')*/;

    return $result;
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message [optional] - zusaetzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message = null) {
    if (isSet($message))
        echo $message.NL.NL;

    $self = baseName($_SERVER['PHP_SELF']);

echo <<<HELP

  Syntax:  $self [symbol ...]


HELP;
}
