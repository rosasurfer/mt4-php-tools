#!/usr/bin/env php
<?php
/**
 * Update the M1 history of the specified Rosatrader symbols with data fetched from Dukascopy.
 */
namespace rosasurfer\rt\bin\dukascopy\update_m1_bars;

use rosasurfer\Application;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\file\FileSystem as FS;
use rosasurfer\process\Process;

use rosasurfer\rt\lib\Rosatrader as RT;
use rosasurfer\rt\lib\dukascopy\Dukascopy;
use rosasurfer\rt\lib\dukascopy\HttpClient as DukascopyClient;
use rosasurfer\rt\model\DukascopySymbol;
use rosasurfer\rt\model\RosaSymbol;

use function rosasurfer\rt\fxtStrToTime;
use function rosasurfer\rt\fxTimezoneOffset;
use function rosasurfer\rt\isWeekend;

use const rosasurfer\rt\PERIOD_D1;
use const rosasurfer\rt\PRICE_BID;
use const rosasurfer\rt\PRICE_ASK;

require(dirname(realpath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');


// -- configuration ---------------------------------------------------------------------------------------------------------


$verbose   = 0;                                             // output verbosity
$barBuffer = [];


// -- start -----------------------------------------------------------------------------------------------------------------


// parse and validate CLI arguments
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// parse options
foreach ($args as $i => $arg) {
    if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; }    // verbose output
    if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; }    // more verbose output
    if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; }    // very verbose output
}

/** @var RosaSymbol[] $symbols */
$symbols = [];

// parse symbols
foreach ($args as $i => $arg) {
    /** @var RosaSymbol $symbol */
    $symbol = RosaSymbol::dao()->findByName($arg);
    if (!$symbol)                       exit(1|stderr('error: unknown symbol "'.$args[$i].'"'));
    if (!$symbol->getDukascopySymbol()) exit(1|stderr('error: no Dukascopy mapping found for symbol "'.$args[$i].'"'));
    $symbols[$symbol->getName()] = $symbol;                                             // using the name as index removes duplicates
}
$symbols = $symbols ?: RosaSymbol::dao()->findAllDukascopyMappedForUpdate();            // if none is specified update all


// update instruments
foreach ($symbols as $symbol) {
    //$symbol->updateHistory();
    updateSymbol($symbol);
    Process::dispatchSignals();                                                         // process Ctrl-C
}
exit(0);


// --- functions ------------------------------------------------------------------------------------------------------------


/**
 * Aktualisiert die M1-Daten eines Symbol.
 *
 * Eine Dukascopy-Datei enthaelt immer anteilige Daten zweier FXT-Tage. Zum Update eines FXT-Tages sind immer die Daten
 * zweier Dukascopy-Tage notwendig. Die Daten des aktuellen Tags sind fruehestens am naechsten Tag verfuegbar.
 *
 * @param  RosaSymbol $symbol
 *
 * @return bool - Erfolgsstatus
 */
function updateSymbol(RosaSymbol $symbol) {
    /** @var DukascopySymbol $dukaSymbol */
    $dukaSymbol = $symbol->getDukascopySymbol();
    $symbolName = $symbol->getName();

    $startFxt   = $dukaSymbol->getHistoryStartM1();
    $startTime  = $startFxt ? fxtStrToTime($startFxt) : 0;              // Beginn der Dukascopy-Daten dieses Symbols in GMT
    $startTime -= $startTime % DAY;                                     // 00:00 GMT

    global $verbose, $barBuffer;
    $barBuffer = [                                                      // Barbuffer zuruecksetzen
        'bid' => [],
        'ask' => [],
        'avg' => [],
    ];
    echoPre('[Info]    '.$symbolName);

    // Gesamte Zeitspanne inklusive Wochenenden tageweise durchlaufen, um von vorherigen Durchlaufen ggf. vorhandene
    // Zwischendateien finden und loeschen zu koennen.
    static $lastMonth=-1;
    $today = ($today=time()) - $today%DAY;                              // 00:00 GMT aktueller Tag

    for ($day=$startTime; $day < $today; $day+=1*DAY) {
        $month = (int) gmdate('m', $day);
        if ($month != $lastMonth) {
            if ($verbose > 0) echoPre('[Info]    '.gmdate('M-Y', $day).'  checking for existing history files');
            $lastMonth = $month;
        }
        if (!checkHistory($symbolName, $day))
            return false;
    }

    echoPre('[Ok]      '.$symbolName);
    return true;
}


/**
 * Prueft den Stand der RT-History eines einzelnen Forex-Tages und stoesst ggf. das Update an.
 *
 * @param  string $symbol - Symbol
 * @param  int    $day    - GMT-Timestamp des zu pruefenden Tages
 *
 * @return bool - Erfolgsstatus
 */
function checkHistory($symbol, $day) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.gettype($day));
    $day -= $day % DAY;                                     // 00:00 GMT

    // nur an Wochentagen: pruefen, ob die RT-History existiert und ggf. aktualisieren
    if (!isWeekend($day) && !is_file(getVar('rtFile.compressed', $symbol, $day)) && !is_file(getVar('rtFile.raw', $symbol, $day)))
        if (!updateHistory($symbol, $day))                  // da 00:00, kann der GMT- als FXT-Timestamp uebergeben werden
            return false;

    // nicht mehr benoetigte Barbuffer-Daten loeschen
    global $barBuffer;
    $shortDate = gmdate('D, d-M-Y', $day);
    unset($barBuffer['bid'][$shortDate]);                   // aktueller Tag
    unset($barBuffer['ask'][$shortDate]);
    unset($barBuffer['avg'][$shortDate]);

    $shortDatePrev = gmdate('D, d-M-Y', $day - 1*DAY);
    unset($barBuffer['bid'][$shortDatePrev]);               // Vortag
    unset($barBuffer['ask'][$shortDatePrev]);
    unset($barBuffer['avg'][$shortDatePrev]);

    return true;
}


/**
 * Aktualisiert die Daten eines einzelnen Forex-Tages. Wird aufgerufen, wenn fuer einen Wochentag keine lokalen
 * RT-Historydateien existieren.
 *
 * @param  string $symbol - Symbol
 * @param  int    $day    - FXT-Timestamp des zu aktualisierenden Forex-Tages
 *
 * @return bool - Erfolgsstatus
 */
function updateHistory($symbol, $day) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.gettype($day));
    global $barBuffer;
    $shortDate = gmdate('D, d-M-Y', $day);

    // Bid- und Ask-Daten im Barbuffer suchen und ggf. laden
    $types = ['bid', 'ask'];
    foreach ($types as $type) {
        if (!isset($barBuffer[$type][$shortDate]) || sizeof($barBuffer[$type][$shortDate])!=PERIOD_D1)
            if (!loadHistory($symbol, $day, $type)) return false;
    }

    // Bid und Ask im Barbuffer mergen
    if (!mergeHistory($symbol, $day)) return false;

    // gemergte Daten speichern
    if (!saveBars($symbol, $day)) return false;

    return true;
}


/**
 * Laedt die Daten eines einzelnen Forex-Tages und Typs in den Barbuffer.
 *
 * @param  string $symbol - Symbol
 * @param  int    $day    - FXT-Timestamp des zu ladenden Forex-Tages
 * @param  string $type   - Kurstyp
 *
 * @return bool - Erfolgsstatus
 */
function loadHistory($symbol, $day, $type) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.gettype($day));
    static $httpClient;
    global $barBuffer; $barBuffer[$type];

    $config     = Application::getConfig();
    $shortDate  = gmdate('D, d-M-Y', $day);
    $priceTypes = [
        'bid' => PRICE_BID,
        'ask' => PRICE_ASK,
    ];

    // Fuer jeden Forex-Tag werden die GMT-Dukascopy-Daten des vorherigen und des aktuellen Tages benoetigt.
    $previousDay = $day - 1*DAY; $previousDayData = false;
    $currentDay  = $day;         $currentDayData  = false;

    // (1) Daten des vorherigen Tages suchen bzw. bereitstellen
    // im Buffer nachschauen
    if (isset($barBuffer[$type][$shortDate])) {                                     // Beginnen die Daten im Buffer mit 00:00, liegt
        $previousDayData = ($barBuffer[$type][$shortDate][0]['delta_fxt'] == 0);    // der Teil des vorherigen GMT-Tags dort schon bereit.
    }
    // ggf. Dukascopy-Datei herunterladen und verarbeiten
    if (!$previousDayData) {
        !$httpClient && $httpClient = new DukascopyClient();
        $data = $httpClient->downloadHistory($symbol, $previousDay, $priceTypes[$type]);
        if (!$data) return false;
        if (!processCompressedDukascopyBarData($data, $symbol, $previousDay, $type))
            return false;
    }

    // (2) Daten des aktuellen Tages suchen bzw. bereitstellen
    // im Buffer nachschauen
    if (isset($barBuffer[$type][$shortDate])) {                                     // Enden die Daten im Buffer mit 23:59, liegt
        $size = sizeof($barBuffer[$type][$shortDate]);                              // der Teil des aktuellen GMT-Tags dort schon bereit.
        $currentDayData = ($barBuffer[$type][$shortDate][$size-1]['delta_fxt'] == 23*HOURS+59*MINUTES);
    }
    // ggf. Dukascopy-Datei herunterladen und verarbeiten
    if (!$currentDayData) {
        !$httpClient && $httpClient = new DukascopyClient();

        $data = $httpClient->downloadHistory($symbol, $currentDay, $priceTypes[$type]);
        if (!$data) return false;
        if (!processCompressedDukascopyBarData($data, $symbol, $currentDay, $type))
            return false;
    }
    return true;
}


/**
 * Merged die Historydaten eines einzelnen Forex-Tages. Wird aufgerufen, wenn Bid- und Ask-Kurse des Tages im Barbuffer liegen.
 *
 * @param  string $symbol - Symbol
 * @param  int    $day    - FXT-Timestamp des zu mergenden Forex-Tages
 *
 * @return bool - Erfolgsstatus
 */
function mergeHistory($symbol, $day) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.gettype($day));
    $shortDate = gmdate('D, d-M-Y', $day);
    global $barBuffer;

    // (1) beide Datenreihen nochmal pruefen
    $types = ['bid', 'ask'];
    foreach ($types as $type) {
        if (!isset($barBuffer[$type][$shortDate]) || ($size=sizeof($barBuffer[$type][$shortDate]))!=PERIOD_D1)
            throw new RuntimeException('Unexpected number of Rosatrader '.$type.' bars for '.$shortDate.' in bar buffer: '.$size.' ('.($size > PERIOD_D1 ? 'more':'less').' then a day)');
    }

    // (2) Daten mergen
    foreach ($barBuffer['bid'][$shortDate] as $i => $bid) {
        $ask = $barBuffer['ask'][$shortDate][$i];

        $avg = [];
        $avg['time_fxt' ] =              $bid['time_fxt' ];
        $avg['delta_fxt'] =              $bid['delta_fxt'];
        $avg['open'     ] = (int) round(($bid['open'     ] + $ask['open' ])/2);
        $avg['high'     ] = (int) round(($bid['high'     ] + $ask['high' ])/2);
        $avg['low'      ] = (int) round(($bid['low'      ] + $ask['low'  ])/2);
        $avg['close'    ] = (int) round(($bid['close'    ] + $ask['close'])/2);

        // Resultierende Avg-Bar validieren (Bid- und Ask-Bar fuer sich allein sind schon validiert).
        // Es kann Spikes mit negativem Spread geben. In diesem Fall werden Open und Close normal berechnet (Average),
        // und High und Low auf das Extrem gesetzt.
        if ($bid['open'] > $ask['open'] || $bid['high'] > $ask['high'] || $bid['low'] > $ask['low'] || $bid['close'] > $ask['close']) {
            $avg['high'] = max($avg['open'], $avg['high'], $avg['low'], $avg['close']);
            $avg['low' ] = min($avg['open'], $avg['high'], $avg['low'], $avg['close']);
        }

        // Urspruenglich wurden die Ticks von Bid- und Ask-Bar einzeln berechnet und diese Werte addiert.
        // Ziel ist jedoch ein moeglichst kleiner Tickwert (um Tests nicht unnoetig zu verlangsamen).
        // Daher werden die Ticks nur noch von der Avg-Bar berechnet und dieser eine Wert gespeichert.
        $ticks = ($avg['high'] - $avg['low']) << 1;                                            // unchanged bar (O == C)
        if      ($avg['open'] < $avg['close']) $ticks += ($avg['open' ] - $avg['close']);      // bull bar
        else if ($avg['open'] > $avg['close']) $ticks += ($avg['close'] - $avg['open' ]);      // bear bar
        $avg['ticks'] = $ticks ? $ticks : 1;                                                   // Ticks mindestens auf 1 setzen

        $barBuffer['avg'][$shortDate][$i] = $avg;
    }
    return true;
}


/**
 * @return bool - Erfolgsstatus
 */
function processCompressedDukascopyBarFile($file, $symbol, $day, $type) {
    if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.gettype($file));
    if (!is_int($day))     throw new IllegalTypeException('Illegal type of parameter $day: '.gettype($day));

    global $verbose;
    if ($verbose > 0) echoPre('[Info]    '.gmdate('D, d-M-Y', $day).'  Dukascopy compressed bar file: '.RT::relativePath($file));

    return processCompressedDukascopyBarData(file_get_contents($file), $symbol, $day, $type);
}


/**
 * @return bool - Erfolgsstatus
 */
function processCompressedDukascopyBarData($data, $symbol, $day, $type) {
    if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));

    /** @var Dukascopy $dukascopy */
    $dukascopy = Application::getDi()[Dukascopy::class];
    $rawData = $dukascopy->decompressData($data);
    return processRawDukascopyBarData($rawData, $symbol, $day, $type);
}


/**
 * @return bool - Erfolgsstatus
 */
function processRawDukascopyBarFile($file, $symbol, $day, $type) {
    if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.gettype($file));
    if (!is_int($day))     throw new IllegalTypeException('Illegal type of parameter $day: '.gettype($day));

    global $verbose;
    if ($verbose > 0) echoPre('[Info]    '.gmdate('D, d-M-Y', $day).'  Dukascopy uncompressed bar file: '.RT::relativePath($file));

    return processRawDukascopyBarData(file_get_contents($file), $symbol, $day, $type);
}


/**
 * @return bool - Erfolgsstatus
 */
function processRawDukascopyBarData($data, $symbol, $day, $type) {
    if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));
    if (!is_int($day))     throw new IllegalTypeException('Illegal type of parameter $day: '.gettype($day));
    if (!is_string($type)) throw new IllegalTypeException('Illegal type of parameter $type: '.gettype($type));
    $shortDate = gmdate('D, d-M-Y', $day);

    global $barBuffer; $barBuffer[$type];
    $types = [
        'bid' => PRICE_BID,
        'ask' => PRICE_ASK,
    ];

    // (1) Bars einlesen
    $symbol = DukascopySymbol::dao()->getByName($symbol);
    /** @var Dukascopy $dukascopy */
    $dukascopy = Application::getDi()[Dukascopy::class];
    $bars = $dukascopy->readBarData($data, $symbol, $types[$type], $day);
    $size = sizeof($bars); if ($size != PERIOD_D1) throw new RuntimeException('Unexpected number of Dukascopy bars in '.getVar('dukaName', null, null, $type).': '.$size.' ('.($size > PERIOD_D1 ? 'more':'less').' then a day)');


    // (2) Timestamps und FXT-Daten zu den Bars hinzufuegen
    $prev = $next = null;                                               // Die Daten der Datei koennen einen DST-Wechsel abdecken, wenn
    $fxtOffset = fxTimezoneOffset($day, $prev, $next);                  // $day = "Sun, 00:00 GMT" ist. In diesem Fall muss innerhalb
    foreach ($bars as &$bar) {                                          // der Datenreihe bei der Ermittlung von time_fxt und delta_fxt
        $bar['time_gmt' ] = $day + $bar['timeDelta'];                   // auf den naechsten DST-Offset gewechselt werden.
        $bar['delta_gmt'] =        $bar['timeDelta'];
        if ($bar['time_gmt'] >= $next['time'])
            $fxtOffset = $next['offset'];                               // $fxtOffset on-the-fly aktualisieren
        $bar['time_fxt' ] = $bar['time_gmt'] + $fxtOffset;              // Es gilt: FXT = GMT + Offset
        $bar['delta_fxt'] = $bar['time_fxt'] % DAY;                     //     bzw: GMT = FXT - Offset
        unset($bar['timeDelta']);
    }; unset($bar);


    // (3) Index von 00:00 FXT bestimmen und Bars FXT-tageweise im Buffer speichern
    $newDayOffset = $size - $fxtOffset/MINUTES;
    if ($fxtOffset == $next['offset']) {                              // bei DST-Change sicherheitshalber Lots pruefen
        $lastBar  = $bars[$newDayOffset-1];
        $firstBar = $bars[$newDayOffset];
        if ($lastBar['lots']/*|| !$firstBar['lots']*/) {
            echoPre('[Warn]    '.$shortDate.'   lots mis-match during DST change.');
            echoPre('Day of DST change ('.gmdate('D, d-M-Y', $lastBar['time_fxt']).') ended with:');
            echoPre($bars[$newDayOffset-1]);
            echoPre('Day after DST change ('.gmdate('D, d-M-Y', $firstBar['time_fxt']).') started with:');
            echoPre($bars[$newDayOffset]);
        }
    }
    $bars1      = array_slice($bars, 0, $newDayOffset);
    $bars2      = array_slice($bars, $newDayOffset);

    $shortDate1 = gmdate('D, d-M-Y', $bars1[0]['time_fxt']-$bars1[0]['delta_fxt']);
    $shortDate2 = gmdate('D, d-M-Y', $bars2[0]['time_fxt']-$bars2[0]['delta_fxt']);

    if (isset($barBuffer[$type][$shortDate1])) {
        // Sicherstellen, dass die Daten zu mergender Bars nahtlos ineinander uebergehen.
        $lastBarTime = $barBuffer[$type][$shortDate1][sizeof($barBuffer[$type][$shortDate1])-1]['time_fxt'];
        $nextBarTime = $bars1[0]['time_fxt'];
        if ($lastBarTime + 1*MINUTE != $nextBarTime) throw new RuntimeException('Bar time mis-match, bars to merge: "'.getVar('dukaName', null, null, $type).'", $lastBarTime='.$lastBarTime.', $nextBarTime='.$nextBarTime);
        $barBuffer[$type][$shortDate1] = array_merge($barBuffer[$type][$shortDate1], $bars1);
    }
    else {
        $barBuffer[$type][$shortDate1] = $bars1;
    }

    if (isset($barBuffer[$type][$shortDate2])) {
        // Sicherstellen, dass die Daten zu mergender Bars nahtlos ineinander uebergehen.
        $lastBarTime = $barBuffer[$type][$shortDate2][sizeof($barBuffer[$type][$shortDate2])-1]['time_fxt'];
        $nextBarTime = $bars2[0]['time_fxt'];
        if ($lastBarTime + 1*MINUTE != $nextBarTime) throw new RuntimeException('Bar time mis-match, bars to merge: "'.getVar('dukaName', null, null, $type).'", $lastBarTime='.$lastBarTime.', $nextBarTime='.$nextBarTime);
        $barBuffer[$type][$shortDate2] = array_merge($barBuffer[$type][$shortDate2], $bars2);
    }
    else {
        $barBuffer[$type][$shortDate2] = $bars2;
    }

    return true;
}


/**
 * Schreibt die gemergten Bardaten eines FXT-Tages aus dem Barbuffer in die lokale RT-Historydatei.
 *
 * @param  string $symbol - Symbol
 * @param  int    $day    - Timestamp des FXT-Tages
 *
 * @return bool - Erfolgsstatus
 */
function saveBars($symbol, $day) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.gettype($day));
    global $barBuffer;
    $shortDate = gmdate('D, d-M-Y', $day);

    // (1) gepufferte Datenreihe nochmal pruefen
    $errorMsg = null;
    if (!$errorMsg && !isset($barBuffer['avg'][$shortDate]))                                    $errorMsg = 'No "avg" bars of '.$shortDate.' in buffer';
    if (!$errorMsg && ($size=sizeof($barBuffer['avg'][$shortDate]))!=1*DAY/MINUTES)             $errorMsg = 'Invalid number of "avg" bars for '.$shortDate.' in buffer: '.$size;
    if (!$errorMsg && $barBuffer['avg'][$shortDate][0      ]['delta_fxt']!=0                  ) $errorMsg = 'No beginning "avg" bars for '.$shortDate.' in buffer, first bar:'.NL.printPretty($barBuffer['avg'][$shortDate][0], true);
    if (!$errorMsg && $barBuffer['avg'][$shortDate][$size-1]['delta_fxt']!=23*HOURS+59*MINUTES) $errorMsg = 'No ending "avg" bars for '.$shortDate.' in buffer, last bar:'.NL.printPretty($barBuffer['avg'][$shortDate][$size-1], true);
    if (!$errorMsg && ($size=sizeof(array_keys($barBuffer['avg']))) > 1)                        $errorMsg = 'Invalid bar buffer state: found more then one "avg" data series ('.$size.')';
    if ($errorMsg) {
        showBarBuffer();
        throw new RuntimeException($errorMsg);
    }

    // (2) Bars in Binaerstring umwandeln
    $data = null;
    foreach ($barBuffer['avg'][$shortDate] as $bar) {
        // Bardaten vorm Schreiben validieren
        if ($bar['open' ] > $bar['high'] ||
            $bar['open' ] < $bar['low' ] ||           // aus (H >= O && O >= L) folgt (H >= L)
            $bar['close'] > $bar['high'] ||           // nicht mit min()/max(), da nicht performant
            $bar['close'] < $bar['low' ] ||
           !$bar['ticks']) throw new RuntimeException('Illegal data for Rosatrader price bar of '.gmdate('D, d-M-Y H:i:s', $bar['time_fxt']).": O=$bar[open] H=$bar[high] L=$bar[low] C=$bar[close] V=$bar[ticks]");

        $data .= pack('VVVVVV', $bar['time_fxt'],
                                $bar['open'    ],
                                $bar['high'    ],
                                $bar['low'     ],
                                $bar['close'   ],
                                $bar['ticks'   ]);
    }

    // (3) binaere Daten ggf. unkomprimiert speichern
    $compressHistory = Application::getConfig()->getBool('rt.history.compress');
    if (!$compressHistory) {
        if (is_file($file=getVar('rtFile.raw', $symbol, $day))) {
            echoPre('[Error]   '.$symbol.' history for '.$shortDate.' already exists');
            return false;
        }
        FS::mkDir(dirname($file));
        $tmpFile = tempnam(dirname($file), basename($file));    // make sure an existing file can't be corrupt
        file_put_contents($tmpFile, $data);
        rename($tmpFile, $file);
    }

    // (4) binaere Daten ggf. komprimieren und speichern
    if ($compressHistory) {
    }
    return true;
}


/**
 * Verwaltet dynamisch generierte Variablen.
 *
 * Evaluiert und cacht staendig wiederbenutzte dynamische Variablen an einem zentralen Ort. Vereinfacht die Logik,
 * da die Variablen nicht global gespeichert oder ueber viele Funktionsaufrufe hinweg weitergereicht werden muessen,
 * aber trotzdem nicht bei jeder Verwendung neu ermittelt werden müssen.
 *
 * @param  string $id     - eindeutiger Bezeichner der Variable (ID)
 * @param  string $symbol - Symbol oder NULL
 * @param  int    $time   - Timestamp oder NULL
 * @param  string $type   - Kurstyp (bid|ask) oder NULL
 *
 * @return string - Variable
 */
function getVar($id, $symbol=null, $time=null, $type=null) {
    static $varCache = [];
    if (array_key_exists(($key=$id.'|'.$symbol.'|'.$time.'|'.$type), $varCache))
        return $varCache[$key];

    if (!is_string($id))                       throw new IllegalTypeException('Illegal type of parameter $id: '.gettype($id));
    if (isset($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.gettype($symbol));
    if (isset($time)) {
        if (!is_int($time))                    throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));
        if ($time % DAY)                       throw new InvalidArgumentException('Invalid parameter $time: '.$time.' (not 00:00)');
    }
    if (isset($type)) {
        if (!is_string($type))                 throw new IllegalTypeException('Illegal type of parameter $type: '.gettype($type));
        if ($type!='bid' && $type!='ask')      throw new InvalidArgumentException('Invalid parameter $type: "'.$type.'"');
    }

    static $storageDir; $storageDir = $storageDir ?: Application::getConfig()['app.dir.storage'];
    $self = __FUNCTION__;

    if ($id == 'rtDirDate') {                   // $yyyy/$mmL/$dd                                       // lokales Pfad-Datum
        if (!$time) throw new InvalidArgumentException('Invalid parameter $time: '.$time);
        $result = gmdate('Y/m/d', $time);
    }
    else if ($id == 'rtDir') {                  // $dataDir/history/rosatrader/$type/$symbol/$dateL     // lokales Verzeichnis
        $type   = RosaSymbol::dao()->getByName($symbol)->getType();
        $dateL  = $self('rtDirDate', null, $time, null);
        $result = $storageDir.'/history/rosatrader/'.$type.'/'.$symbol.'/'.$dateL;
    }
    else if ($id == 'rtFile.raw') {             // $rtDir/M1.bin                                        // lokale Datei ungepackt
        $rtDir  = $self('rtDir', $symbol, $time, null);
        $result = $rtDir.'/M1.bin';
    }
    else if ($id == 'rtFile.compressed') {      // $rtDir/M1.rar                                        // lokale Datei gepackt
        $rtDir  = $self('rtDir', $symbol, $time, null);
        $result = $rtDir.'/M1.rar';
    }
    else if ($id == 'dukaName') {               // BID_candles_min_1                                    // Dukascopy-Name
        if (is_null($type)) throw new InvalidArgumentException('Invalid parameter $type: (null)');
        $result = ($type=='bid' ? 'BID':'ASK').'_candles_min_1';
    }
    else {
      throw new InvalidArgumentException('Unknown variable identifier "'.$id.'"');
    }

    $varCache[$key] = $result;

    if (sizeof($varCache) > ($maxSize=256)) {                       // 256: ausreichend für Daten ca. eines Monats
        $varCache = array_slice($varCache, $offset=$maxSize/2);
    }
    return $result;
}


/**
 *
 */
function showBarBuffer() {
    global $barBuffer;

    echoPre(NL);
    foreach ($barBuffer as $type => $days) {
        if (!is_array($days)) {
            echoPre('barBuffer['.$type.'] => '.(isset($days) ? $days : 'null'));
            continue;
        }
        foreach ($days as $day => $bars) {
            if (!is_array($bars)) {
                echoPre('barBuffer['.$type.']['.(is_int($day) ? gmdate('D, d-M-Y', $day) : $day).'] => '.(isset($bars) ? $bars : 'null'));
                continue;
            }
            $size = sizeof($bars);
            $firstBar = $size ? gmdate('H:i', $bars[0      ]['time_fxt']) : null;
            $lastBar  = $size ? gmdate('H:i', $bars[$size-1]['time_fxt']) : null;
            echoPre('barBuffer['.$type.']['.(is_int($day) ? gmdate('D, d-M-Y', $day) : $day).'] => '.str_pad($size, 4, ' ', STR_PAD_LEFT).' bar'.pluralize($size).($firstBar ? '  '.$firstBar : '').($size>1 ? '-'.$lastBar : ''));
        }
    }
    echoPre(NL);
}
