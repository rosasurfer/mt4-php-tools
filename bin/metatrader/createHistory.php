#!/usr/bin/php
<?php
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;


/**
 * Liest die MyFX-M1-History der angegebenen Instrumente ein und erzeugt daraus jeweils eine neue MetaTrader-History.
 * Speichert diese MetaTrader-History im globalen MT4-Serververzeichnis "MyFX-Dukascopy". Vorhandene Historydateien
 * werden ueberschrieben. Um vorhandene Historydateien zu aktualisieren, ist "updateHistory.php" zu benutzen.
 */
require(__DIR__.'/../../app/init.php');
date_default_timezone_set('GMT');


// -- Konfiguration ----------------------------------------------------------------------------------------------------


$verbose = 0;                                                                       // output verbosity


// -- Start ------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);

// Optionen parsen
foreach ($args as $i => $arg) {
    if ($arg == '-h'  )   exit(1|help());                                            // Hilfe
    if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; } // verbose output
    if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; } // more verbose output
    if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; } // very verbose output
}

// Symbole parsen
foreach ($args as $i => $arg) {
    $arg = strToUpper($arg);
    if (!isSet(MyFX::$symbols[$arg])) exit(1|help('error: unknown or unsupported symbol "'.$args[$i].'"'));
    $args[$i] = $arg;
}                                                                                   // ohne Symbol werden alle Instrumente verarbeitet
$args = $args ? array_unique($args) : array_keys(MyFX::$symbols);


// (2) SIGINT-Handler installieren                                                  // Um bei Ctrl-C Destruktoren auszufuehren, reicht es,
if (!WINDOWS) pcntl_signal(SIGINT, create_function('$signal', 'exit();'));          // wenn im Handler exit() aufgerufen wird.


// (3) History erstellen
foreach ($args as $symbol) {
    !createHistory($symbol) && exit(1);
}
exit(0);


// --- Funktionen ------------------------------------------------------------------------------------------------------


/**
 * Erzeugt eine neue MetaTrader-History eines Instruments.
 *
 * @param  string $symbol - Symbol
 *
 * @return bool - Erfolgsstatus
 */
function createHistory($symbol) {
    if (!is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
    if (!strLen($symbol))    throw new InvalidArgumentException('Invalid parameter $symbol: ""');

    $startDay  = fxtTime(MyFX::$symbols[$symbol]['historyStart']['M1']);             // FXT
    $startDay -= $startDay%DAY;                                                      // 00:00 FXT Starttag
    $today     = ($today=fxtTime()) - $today%DAY;                                    // 00:00 FXT aktueller Tag


    // MT4-HistorySet erzeugen
    $digits    = MyFX::$symbols[$symbol]['digits'];
    $format    = 400;
    $directory = MyFX::getConfigPath('myfx.data-directory').'/history/mt4/MyFX-Dukascopy';
    $history   = HistorySet::create($symbol, $digits, $format, $directory);


    // Gesamte Zeitspanne tageweise durchlaufen
    for ($day=$startDay, $lastMonth=-1; $day < $today; $day+=1*DAY) {
        $shortDate = gmDate('D, d-M-Y', $day);
        $month     = (int) gmDate('m', $day);
        if ($month != $lastMonth) {
            echoPre('[Info]    '.gmDate('M-Y', $day));
            $lastMonth = $month;
        }

        // ausser an Wochenenden: MyFX-History verarbeiten
        if (!isForexWeekend($day, 'FXT')) {
            if      (is_file($file=getVar('myfxFile.compressed', $symbol, $day))) {}   // wenn komprimierte MyFX-Datei existiert
            else if (is_file($file=getVar('myfxFile.raw'       , $symbol, $day))) {}   // wenn unkomprimierte MyFX-Datei existiert
            else {
                echoPre('[Error]   '.$symbol.' MyFX history for '.$shortDate.' not found');
                return false;
            }
            // Bars einlesen und der MT4-History hinzufuegen
            $bars = MyFX::readBarFile($file, $symbol);
            $history->appendBars($bars);
        }

        if (!WINDOWS) pcntl_signal_dispatch();                                        // Auf Ctrl-C pruefen, um bei Abbruch den
    }                                                                                // Schreibbuffer der History leeren zu koennen.
    $history->close();

    echoPre('[Ok]      '.$symbol);
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
    //global $varCache;
    static $varCache = [];
    if (array_key_exists(($key=$id.'|'.$symbol.'|'.$time), $varCache))
        return $varCache[$key];

    if (!is_string($id))                          throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
    if (!is_null($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
    if (!is_null($time) && !is_int($time))        throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

    $self = __FUNCTION__;

    if ($id == 'myfxDirDate') {               // $yyyy/$mm/$dd                                                  // lokales Pfad-Datum
        if (!$time)   throw new InvalidArgumentException('Invalid parameter $time: '.$time);
        $result = gmDate('Y/m/d', $time);
    }
    else if ($id == 'myfxDir') {              // $dataDirectory/history/myfx/$type/$symbol/$myfxDirDate         // lokales Verzeichnis
        if (!$symbol) throw new InvalidArgumentException('Invalid parameter $symbol: '.$symbol);
        static $dataDirectory; if (!$dataDirectory)
        $dataDirectory = MyFX::getConfigPath('myfx.data-directory');
        $type          = MyFX::$symbols[$symbol]['type'];
        $myfxDirDate   = $self('myfxDirDate', null, $time);
        $result        = "$dataDirectory/history/myfx/$type/$symbol/$myfxDirDate";
    }
    else if ($id == 'myfxFile.raw') {         // $myfxDir/M1.myfx                                               // lokale Datei ungepackt
        $myfxDir = $self('myfxDir' , $symbol, $time);
        $result  = "$myfxDir/M1.myfx";
    }
    else if ($id == 'myfxFile.compressed') {  // $myfxDir/M1.rar                                                // lokale Datei gepackt
        $myfxDir = $self('myfxDir' , $symbol, $time);
        $result  = "$myfxDir/M1.rar";
    }
    else {
      throw new InvalidArgumentException('Unknown parameter $id: "'.$id.'"');
    }

    $varCache[$key] = $result;
    (sizeof($varCache) > ($maxSize=128)) && array_shift($varCache) /*&& echoPre('cache size limit of '.$maxSize.' hit')*/;

    return $result;
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusaetzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
    if (!is_null($message))
        echo($message.NL.NL);

    $self = baseName($_SERVER['PHP_SELF']);

echo <<<HELP_MESSAGE

  Syntax:  $self [symbol ...]


HELP_MESSAGE;
}
