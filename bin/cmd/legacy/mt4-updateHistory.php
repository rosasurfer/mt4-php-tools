#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * TODO: replace by Ministruts console command
 *
 *
 * Aktualisiert die MetaTrader-History der angegebenen Instrumente im globalen MT4-Serververzeichnis.
 */
namespace rosasurfer\rt\cmd\mt4_update_history;

use rosasurfer\ministruts\Application;
use rosasurfer\ministruts\process\Process;

use rosasurfer\rt\lib\Rost;
use rosasurfer\rt\lib\metatrader\HistorySet;
use rosasurfer\rt\lib\metatrader\MetaTrader;
use rosasurfer\rt\model\RosaSymbol;

use function rosasurfer\ministruts\echof;
use function rosasurfer\ministruts\stderr;

use function rosasurfer\rt\fxTime;
use function rosasurfer\rt\isWeekend;

use const rosasurfer\ministruts\DAY;

require(__DIR__.'/../../../app/init.php');
date_default_timezone_set('GMT');


// -- Konfiguration ---------------------------------------------------------------------------------------------------------


$verbose = 0;                                                                       // output verbosity


// -- Start -----------------------------------------------------------------------------------------------------------------


// Befehlszeilenargumente einlesen und validieren
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// Optionen parsen
foreach ($args as $i => $arg) {
    if ($arg == '-h'  ) { help(); exit(1); }                                            // Hilfe
    if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; }    // verbose output
    if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; }    // more verbose output
    if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; }    // very verbose output
}

$symbols = [];

// Symbole parsen
foreach ($args as $i => $arg) {
    $symbol = RosaSymbol::dao()->findByName($arg);
    if (!$symbol) {
        stderr('error: unknown symbol "'.$args[$i].'"');
        exit(1);
    }
    $symbols[$symbol->getName()] = $symbol;                                         // using the name as index removes duplicates
}
/** @var RosaSymbol[] $symbols */
$symbols = $symbols ?: RosaSymbol::dao()->findAll();                                // ohne Angabe werden alle Instrumente verarbeitet

// History aktualisieren
foreach ($symbols as $symbol) {
    updateHistory($symbol) || exit(1);
}
exit(0);


// --- Funktionen -----------------------------------------------------------------------------------------------------------


/**
 * Aktualisiert die MT4-History eines Instruments.
 *
 * @param  RosaSymbol $symbol
 *
 * @return bool - Erfolgsstatus
 */
function updateHistory(RosaSymbol $symbol) {
    global $verbose;
    $symbolName   = $symbol->getName();
    $config       = Application::getDi()['config'];
    $directory    = $config['app.dir.data'].'/history/mt4/'.$config['rt.metatrader.servername'];
    $lastSyncTime = null;
    echof('[Info]    '.$symbolName);

    // HistorySet oeffnen bzw. neues Set erstellen
    if ($history = HistorySet::open($symbolName, $directory)) {
        if ($verbose) echof('[Info]    lastSyncTime: '.(($lastSyncTime=$history->getLastSyncTime()) ? gmdate('D, d-M-Y H:i:s', $lastSyncTime) : 0));
    }
    else {
        /** @var MetaTrader $mt */
        $mt = Application::getDi()[MetaTrader::class];
        $history = $mt->createHistorySet($symbol);
    }

    // History beginnend mit dem letzten synchronisierten Tag aktualisieren
    $startTime = $lastSyncTime ?: (int)$symbol->getHistoryStartM1('U');                             // FXT
    $startDay  = $startTime - $startTime%DAY;                                                       // 00:00 FXT der Startzeit
    $today     = ($time=fxTime()) - $time%DAY;                                                      // 00:00 FXT des aktuellen Tages
    $lastMonth = -1;

    for ($day=$startDay; $day < $today; $day+=1*DAY) {
        $shortDate = gmdate('D, d-M-Y', $day);
        $month     = (int) gmdate('m', $day);
        if ($month != $lastMonth) {
            echof('[Info]    '.gmdate('M-Y', $day));
            $lastMonth = $month;
        }
        if (!isWeekend($day)) {                                                                     // nur an Handelstagen
            if      (is_file($file=Rost::getVar('rtFile.M1.compressed', $symbolName, $day))) {}     // wenn komprimierte RT-Datei existiert
            else if (is_file($file=Rost::getVar('rtFile.M1.raw'       , $symbolName, $day))) {}     // wenn unkomprimierte RT-Datei existiert
            else {
                echof('[Error]   '.$symbolName.'  Rosatrader history for '.$shortDate.' not found');
                return false;
            }
            if ($verbose > 0) echof('[Info]    synchronizing '.$shortDate);

            $bars = Rost::readBarFile($file, $symbolName);
            $history->synchronize($bars);
        }
        Process::dispatchSignals();                                                                 // check for Ctrl-C
    }
    $history->close();

    echof('[Ok]      '.$symbolName);
    return true;
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  ?string $message [optional] - zusaetzlich zur Syntax anzuzeigende Message (default: keine)
 *
 * @return void
 */
function help($message = null) {
    if (is_null($message))
        $message = 'Updates the MetaTrader history of the specified symbols.';
    $self = basename($_SERVER['PHP_SELF']);

echo <<<HELP
$message

  Syntax:  $self [symbol ...] [OPTIONS]

  Options:  -h   This help screen.


HELP;
}
