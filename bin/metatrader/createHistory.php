#!/usr/bin/env php
<?php
/**
 * Liest die Rosatrader-M1-History der angegebenen Instrumente ein und erzeugt daraus jeweils eine neue MetaTrader-History.
 * Speichert diese MetaTrader-History im globalen MT4-Serververzeichnis. Vorhandene Historydateien werden ueberschrieben.
 * Um vorhandene Historydateien zu aktualisieren, ist "updateHistory.php" zu benutzen.
 */
namespace rosasurfer\rt\bin\metatrader\create_history;

use rosasurfer\Application;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\process\Process;
use rosasurfer\util\PHP;

use rosasurfer\rt\lib\Rost;
use rosasurfer\rt\lib\metatrader\HistorySet;
use rosasurfer\rt\lib\metatrader\MT4;
use rosasurfer\rt\model\RosaSymbol;

use function rosasurfer\rt\fxTime;
use function rosasurfer\rt\isWeekend;

require(dirname(realpath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');

/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

/** @var RosaSymbol $symbol */
if (!$symbol = RosaSymbol::dao()->findByName(first($args))) {
    exit(stderr('error: unknown symbol "'.first($args).'"'));
}

createHistory($symbol);
exit(0);


/**
 * Erzeugt eine neue MetaTrader-History eines Instruments.
 *
 * @param  RosaSymbol $symbol
 *
 * @return bool - Erfolgsstatus
 */
function createHistory(RosaSymbol $symbol) {
    $symbolName = $symbol->getName();
    $startDay   = (int)$symbol->getHistoryStartM1('U');                             // FXT
    $startDay  -= $startDay%DAY;                                                    // 00:00 FXT Starttag
    $today      = ($today=fxTime()) - $today%DAY;                                   // 00:00 FXT aktueller Tag


    // MT4-HistorySet erzeugen
    $directory = Application::getConfig()['app.dir.data'].'/history/mt4/XTrade-Testhistory';
    $hstSet = HistorySet::create($symbol, $format=400, $directory);


    // Gesamte Zeitspanne tageweise durchlaufen
    for ($day=$startDay, $lastMonth=-1; $day < $today; $day+=1*DAY) {
        $shortDate = gmdate('D, d-M-Y', $day);
        $month     = (int) gmdate('m', $day);
        if ($month != $lastMonth) {
            echoPre('[Info]    '.gmdate('M-Y', $day));
            $lastMonth = $month;
        }

        // ausser an Wochenenden: RT-History verarbeiten
        if (!isWeekend($day)) {
            if      (is_file($file=getVar('rtFile.compressed', $symbolName, $day))) {}      // wenn komprimierte RT-Datei existiert
            else if (is_file($file=getVar('rtFile.raw'       , $symbolName, $day))) {}      // wenn unkomprimierte RT-Datei existiert
            else {
                echoPre('[Error]   '.$symbolName.'  Rosatrader history for '.$shortDate.' not found');
                return false;
            }
            // Bars einlesen und der MT4-History hinzufuegen
            $bars = Rost::readBarFile($file, $symbolName);
            $hstSet->appendBars($bars);
        }
        Process::dispatchSignals();                                                         // check for Ctrl-C
    }
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

    if (!is_string($id))                       throw new IllegalTypeException('Illegal type of parameter $id: '.gettype($id));
    if (isset($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.gettype($symbol));
    if (isset($time) && !is_int($time))        throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));

    $self = __FUNCTION__;
    static $dataDir; !$dataDir && $dataDir = Application::getConfig()['app.dir.data'];

    if ($id == 'rtDirDate') {                   // $yyyy/$mm/$dd                                                // lokales Pfad-Datum
        if (!$time) throw new InvalidArgumentException('Invalid parameter $time: '.$time);
        $result = gmdate('Y/m/d', $time);
    }
    else if ($id == 'rtDir') {                  // $dataDir/history/rosatrader/$type/$symbol/$rtDirDate         // lokales Verzeichnis
        $type      = RosaSymbol::dao()->getByName($symbol)->getType();
        $rtDirDate = $self('rtDirDate', null, $time);
        $result    = $dataDir.'/history/rosatrader/'.$type.'/'.$symbol.'/'.$rtDirDate;
    }
    else if ($id == 'rtFile.raw') {             // $rtDir/M1.bin                                                // lokale Datei ungepackt
        $rtDir  = $self('rtDir' , $symbol, $time);
        $result = $rtDir.'/M1.bin';
    }
    else if ($id == 'rtFile.compressed') {      // $rtDir/M1.rar                                                // lokale Datei gepackt
        $rtDir  = $self('rtDir' , $symbol, $time);
        $result = $rtDir.'/M1.rar';
    }
    else {
      throw new InvalidArgumentException('Unknown variable identifier "'.$id.'"');
    }

    $varCache[$key] = $result;
    (sizeof($varCache) > ($maxSize=128)) && array_shift($varCache) /*&& echoPre('cache size limit of '.$maxSize.' hit')*/;

    return $result;
}
