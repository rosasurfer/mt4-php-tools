#!/usr/bin/env php
<?php
/**
 * Update the M1 history of the specified Rosatrader symbols with data fetched from Dukascopy.
 *
 * Dukascopy provides separate bid and ask price series in GMT covering weekends and holidays. Data of the current day (GMT)
 * is available the earliest at the next day (GMT).
 *
 * Bid and ask prices are merged to median, converted to FXT and stored in Rosatrader format (ROST_PRICE_BAR). Weekend data
 * is not stored. Currently holiday data is stored as some holidays are instrument specific and irregular.
 *
 *
 * Website:       https://www.dukascopy.com/swiss/english/marketwatch/historical/
 *                https://www.dukascopy.com/free/candelabrum/                                       (inactive)
 *
 * Instruments:   https://www.dukascopy.com/free/candelabrum/data.json                              (inactive)
 *
 * History start: http://datafeed.dukascopy.com/datafeed/metadata/HistoryStart.bi5                  (big-endian)
 *                http://datafeed.dukascopy.com/datafeed/AUDUSD/metadata/HistoryStart.bi5           (big-endian)
 *
 * Prices:        One file per calendar day (January = 00) since history start. During trade breaks
 *                the last close price (OHLC) and a volume of zero (V=0) are indicated:
 *                - http://datafeed.dukascopy.com/datafeed/GBPUSD/2013/00/10/BID_candles_min_1.bi5  (LZMA-compressed, DUKASCOPY_BAR[])
 *                - http://datafeed.dukascopy.com/datafeed/GBPUSD/2013/11/31/ASK_candles_min_1.bi5
 *
 *          +------------++------------+------------+------------+------------+------------++------------+------------++------------+
 * GMT:     |   Sunday   ||   Monday   |  Tuesday   | Wednesday  |  Thursday  |   Friday   ||  Saturday  |   Sunday   ||   Monday   |
 *          +------------++------------+------------+------------+------------+------------++------------+------------++------------+
 *      +------------++------------+------------+------------+------------+------------++------------+------------++------------+
 * FXT: |   Sunday   ||   Monday   |  Tuesday   | Wednesday  |  Thursday  |   Friday   ||  Saturday  |   Sunday   ||   Monday   |
 *      +------------++------------+------------+------------+------------+------------++------------+------------++------------+
 *
 *
 * TODO: check info from Zorro forum:  http://www.opserver.de/ubb7/ubbthreads.php?ubb=showflat&Number=463361#Post463345
 */
namespace rosasurfer\rost\dukascopy\update_m1_bars;

use rosasurfer\config\Config;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\net\http\CurlHttpClient;
use rosasurfer\net\http\HttpRequest;
use rosasurfer\net\http\HttpResponse;

use rosasurfer\rost\LZMA;
use rosasurfer\rost\Rost;
use rosasurfer\rost\dukascopy\Dukascopy;
use rosasurfer\rost\model\DukascopySymbol;
use rosasurfer\rost\model\RosaSymbol;

use function rosasurfer\rost\isFxtWeekend;

require(dirName(realPath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');


// -- Konfiguration ---------------------------------------------------------------------------------------------------------


$verbose = 0;                                   // output verbosity

$storeCompressedDukaFiles   = false;            // ob heruntergeladene Dukascopy-Dateien zwischengespeichert werden sollen
$storeDecompressedDukaFiles = false;            // ob entpackte Dukascopy-Dateien zwischengespeichert werden sollen
$storeUncompressedRostFiles = true;             // ob unkomprimierte Rost-Historydaten gespeichert werden sollen

$barBuffer = [];


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
    if (!$symbol)                       exit(1|stderror('error: unknown symbol "'.$args[$i].'"'));
    if (!$symbol->getDukascopySymbol()) exit(1|stderror('error: no Dukascopy mapping found for symbol "'.$args[$i].'"'));
    $symbols[$symbol->getName()] = $symbol;                                         // using the name as index removes duplicates
}
$symbols = $symbols ?: RosaSymbol::dao()->findAllDukascopyMapped();                 // ohne Angabe werden alle Instrumente verarbeitet


// (2) Daten aktualisieren
foreach ($symbols as $symbol) {
    updateSymbol($symbol) || exit(1);
}
exit(0);


// --- Funktionen -----------------------------------------------------------------------------------------------------------


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

    $startFxt  = $dukaSymbol->getHistoryStartM1();
    $startTime = $startFxt ? Rost::fxtStrToTime($startFxt) : 0;         // Beginn der Dukascopy-Daten dieses Symbols in GMT
    $startTime -= $startTime % DAY;                                     // 00:00 GMT

    global $verbose, $barBuffer;
    $barBuffer        = [];                                             // Barbuffer zuruecksetzen
    $barBuffer['bid'] = [];
    $barBuffer['ask'] = [];
    $barBuffer['avg'] = [];

    echoPre('[Info]    '.$symbolName);


    // (1) Pruefen, ob sich der Startzeitpunkt der History des Symbols geaendert hat
    if (array_search($symbolName, ['USDNOK', 'USDSEK', 'USDSGD', 'USDZAR', 'XAUUSD']) === false) {
        $content = downloadData($symbolName, $day=$startTime-1*DAY, $type='bid', $quiet=true, $saveData=false, $saveError=false);
        if (strLen($content)) {
            echoPre('[Notice]  '.$symbolName.' M1 history was extended. Please update the history start time.');
            return false;
        }
    }


    // (2) Gesamte Zeitspanne inklusive Wochenenden tageweise durchlaufen, um von vorherigen Durchlaufen ggf. vorhandene
    //     Zwischendateien finden und loeschen zu koennen.
    static $lastMonth=-1;
    $today = ($today=time()) - $today%DAY;                  // 00:00 GMT aktueller Tag

    for ($day=$startTime; $day < $today; $day+=1*DAY) {
        $month = (int) gmDate('m', $day);
        if ($month != $lastMonth) {
            if ($verbose > 0) echoPre('[Info]    '.gmDate('M-Y', $day));
            $lastMonth = $month;
        }
        if (!checkHistory($symbolName, $day)) return false;
        if (!WINDOWS) pcntl_signal_dispatch();              // auf Ctrl-C pruefen
    }

    echoPre('[Ok]      '.$symbolName);
    return true;
}


/**
 * Prueft den Stand der Rost-History eines einzelnen Forex-Tages und stoesst ggf. das Update an.
 *
 * @param  string $symbol - Symbol
 * @param  int    $day    - GMT-Timestamp des zu pruefenden Tages
 *
 * @return bool - Erfolgsstatus
 */
function checkHistory($symbol, $day) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $verbose, $storeCompressedDukaFiles, $storeDecompressedDukaFiles, $storeUncompressedRostFiles, $barBuffer;
    $day -= $day%DAY;                                               // 00:00 GMT

    // (1) nur an Wochentagen: pruefen, ob die Rost-History existiert und ggf. aktualisieren
    if (!isFxtWeekend($day, 'FXT')) {                               // um 00:00 GMT sind GMT- und FXT-Wochentag immer gleich
        // History ist ok, wenn entweder die komprimierte Rost-Datei existiert...
        if (is_file($file=getVar('rostFile.compressed', $symbol, $day))) {
            if ($verbose > 1) echoPre('[Ok]      '.$shortDate.'   Rost compressed history file: '.baseName($file));
        }
        // ...oder die unkomprimierte Rost-Datei gespeichert wird und existiert
        else if ($storeUncompressedRostFiles && is_file($file=getVar('rostFile.raw', $symbol, $day))) {
            if ($verbose > 1) echoPre('[Ok]      '.$shortDate.'   Rost raw history file: '.baseName($file));
        }
        // andererseits History aktualisieren
        else if (!updateHistory($symbol, $day)) {                   // da 00:00, kann der GMT- als FXT-Timestamp uebergeben werden
            return false;
        }
    }


    // (2) an allen Tagen: nicht mehr benoetigte Dateien, Verzeichnisse und Barbuffer-Daten loeschen
    $previousDay   = $day - 1*DAY;
    $shortDatePrev = gmDate('D, d-M-Y', $previousDay);

    // Dukascopy-Downloads (gepackt) des Vortages
    if (!$storeCompressedDukaFiles) {
        if (is_file($file=getVar('dukaFile.compressed', $symbol, $previousDay, 'bid'))) unlink($file);
        if (is_file($file=getVar('dukaFile.compressed', $symbol, $previousDay, 'ask'))) unlink($file);
    }
    // dekomprimierte Dukascopy-Daten des Vortages
    if (!$storeDecompressedDukaFiles) {
        if (is_file($file=getVar('dukaFile.raw', $symbol, $previousDay, 'bid'))) unlink($file);
        if (is_file($file=getVar('dukaFile.raw', $symbol, $previousDay, 'ask'))) unlink($file);
    }

    // lokales Historyverzeichnis des Vortages, wenn Wochenende und es leer ist
    if (isFxtWeekend($previousDay, 'FXT')) {                            // um 00:00 GMT sind GMT- und FXT-Wochentag immer gleich
        if (is_dir($dir=getVar('rostDir', $symbol, $previousDay))) @rmDir($dir);
    }
    // lokales Historyverzeichnis des aktuellen Tages, wenn Wochenende und es leer ist
    if (isFxtWeekend($day, 'FXT')) {                                    // um 00:00 GMT sind GMT- und FXT-Wochentag immer gleich
        if (is_dir($dir=getVar('rostDir', $symbol, $day))) @rmDir($dir);
    }

    // Barbuffer-Daten des Vortages
    unset($barBuffer['bid'][$shortDatePrev]);
    unset($barBuffer['ask'][$shortDatePrev]);
    unset($barBuffer['avg'][$shortDatePrev]);

    // Barbuffer-Daten des aktuellen Tages
    unset($barBuffer['bid'][$shortDate]);
    unset($barBuffer['ask'][$shortDate]);
    unset($barBuffer['avg'][$shortDate]);

    return true;
}


/**
 * Aktualisiert die Daten eines einzelnen Forex-Tages. Wird aufgerufen, wenn fuer einen Wochentag keine lokalen
 * Rost-Historydateien existieren.
 *
 * @param  string $symbol - Symbol
 * @param  int    $day    - FXT-Timestamp des zu aktualisierenden Forex-Tages
 *
 * @return bool - Erfolgsstatus
 */
function updateHistory($symbol, $day) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);
    global $barBuffer;

    // Bid- und Ask-Daten im Barbuffer suchen und ggf. laden
    $types = ['bid', 'ask'];
    foreach ($types as $type) {
        if (!isSet($barBuffer[$type][$shortDate]) || sizeOf($barBuffer[$type][$shortDate])!=1*DAY/MINUTES)
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
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);
    global $barBuffer, $storeCompressedDukaFiles; $barBuffer[$type];

    // Fuer jeden Forex-Tag werden die GMT-Dukascopy-Daten des vorherigen und des aktuellen Tages benoetigt.
    // Die Daten werden jeweils in folgender Reihenfolge gesucht:
    //  - im Barbuffer selbst
    //  - in bereits dekomprimierten Dukascopy-Dateien
    //  - in noch komprimierten Dukascopy-Dateien
    //  - als Dukascopy-Download

    $previousDay = $day - 1*DAY; $previousDayData = false;
    $currentDay  = $day;         $currentDayData  = false;


    // (1) Daten des vorherigen Tages suchen bzw. bereitstellen
    // - im Buffer nachschauen
    if (!$previousDayData && isSet($barBuffer[$type][$shortDate])) {              // Beginnen die Daten im Buffer mit 00:00, liegt
        $previousDayData = ($barBuffer[$type][$shortDate][0]['delta_fxt'] == 0);   // der Teil des vorherigen GMT-Tags dort schon bereit.
    }
    // - dekomprimierte Dukascopy-Datei suchen und verarbeiten
    if (!$previousDayData) {
        if (is_file($file=getVar('dukaFile.raw', $symbol, $previousDay, $type)))
            if (!$previousDayData=processRawDukascopyBarFile($file, $symbol, $previousDay, $type))
                return false;
    }
    // - komprimierte Dukascopy-Datei suchen und verarbeiten
    if (!$previousDayData) {
        if (is_file($file=getVar('dukaFile.compressed', $symbol, $previousDay, $type)))
            if (!$previousDayData=processCompressedDukascopyBarFile($file, $symbol, $previousDay, $type))
                return false;
    }
    // - ggf. Dukascopy-Datei herunterladen und verarbeiten
    if (!$previousDayData) {
        $data = downloadData($symbol, $previousDay, $type, false, $storeCompressedDukaFiles);
        if (!$data)                                                                // bei HTTP status 404 (file not found) Abbruch
            return false;
        if (!processCompressedDukascopyBarData($data, $symbol, $previousDay, $type))
            return false;
        $previousDayData = true;
    }


    // (2) Daten des aktuellen Tages suchen bzw.bereitstellen
    // - im Buffer nachschauen
    if (!$currentDayData && isSet($barBuffer[$type][$shortDate])) {               // Enden die Daten im Buffer mit 23:59, liegt
        $size = sizeOf($barBuffer[$type][$shortDate]);                             // der Teil des aktuellen GMT-Tags dort schon bereit.
        $currentDayData = ($barBuffer[$type][$shortDate][$size-1]['delta_fxt'] == 23*HOURS+59*MINUTES);
    }
    // - dekomprimierte Dukascopy-Datei suchen und verarbeiten
    if (!$currentDayData) {
        if (is_file($file=getVar('dukaFile.raw', $symbol, $currentDay, $type)))
            if (!$currentDayData=processRawDukascopyBarFile($file, $symbol, $currentDay, $type))
                return false;
    }
    // - komprimierte Dukascopy-Datei suchen und verarbeiten
    if (!$currentDayData) {
        if (is_file($file=getVar('dukaFile.compressed', $symbol, $currentDay, $type)))
            if (!$currentDayData=processCompressedDukascopyBarFile($file, $symbol, $currentDay, $type))
                return false;
    }
    // - ggf. Dukascopy-Datei herunterladen und verarbeiten
    if (!$currentDayData) {
        static $yesterday; if (!$yesterday) $yesterday=($today=time()) - $today%DAY - 1*DAY;    // 00:00 GMT gestriger Tag
        $saveFile = ($storeCompressedDukaFiles || $currentDay==$yesterday);                     // beim letzten Durchlauf immer speichern

        $data = downloadData($symbol, $currentDay, $type, false, $saveFile);
        if (!$data)                                                                             // HTTP status 404 (file not found) => Abbruch
            return false;
        if (!processCompressedDukascopyBarData($data, $symbol, $currentDay, $type))
            return false;
        $currentDayData = true;
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
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);
    global $barBuffer;


    // (1) beide Datenreihen nochmal pruefen
    $types = ['bid', 'ask'];
    foreach ($types as $type) {
        if (!isSet($barBuffer[$type][$shortDate]) || ($size=sizeOf($barBuffer[$type][$shortDate]))!=1*DAY/MINUTES)
            throw new RuntimeException('Unexpected number of Rost '.$type.' bars for '.$shortDate.' in bar buffer: '.$size.' ('.($size > 1*DAY/MINUTES ? 'more':'less').' then a day)');
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
 * Laedt eine Dukascopy-M1-Datei und gibt ihren Inhalt zurueck.
 *
 * @param  string $symbol    - Symbol der herunterzuladenen Datei
 * @param  int    $day       - Tag der herunterzuladenen Datei
 * @param  string $type      - Kurstyp der herunterzuladenen Datei: 'bid'|'ask'
 * @param  bool   $quiet     - ob Statusmeldungen unterdrueckt werden sollen (default: nein)
 * @param  bool   $saveData  - ob die Datei gespeichert werden soll (default: nein)
 * @param  bool   $saveError - ob ein 404-Fehler mit einer entsprechenden Fehlerdatei signalisiert werden soll (default: ja)
 *
 * @return string - Content der heruntergeladenen Datei oder Leerstring, wenn die Resource nicht gefunden wurde (404-Fehler).
 */
function downloadData($symbol, $day, $type, $quiet=false, $saveData=false, $saveError=true) {
    if (!is_int($day))        throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    if (!is_bool($quiet))     throw new IllegalTypeException('Illegal type of parameter $quiet: '.getType($quiet));
    if (!is_bool($saveData))  throw new IllegalTypeException('Illegal type of parameter $saveData: '.getType($saveData));
    if (!is_bool($saveError)) throw new IllegalTypeException('Illegal type of parameter $saveError: '.getType($saveError));

    $config    = Config::getDefault();
    $shortDate = gmDate('D, d-M-Y', $day);
    $url       = getVar('dukaUrl', $symbol, $day, $type);
    if (!$quiet) echoPre('[Info]    '.$shortDate.'   url: '.$url);

    // (1) Standard-Browser simulieren
    $userAgent = $config->get('rost.useragent'); if (!$userAgent) throw new InvalidArgumentException('Invalid user agent configuration: "'.$userAgent.'"');
    $request = HttpRequest::create()
                          ->setUrl($url)
                          ->setHeader('User-Agent'     , $userAgent                                                       )
                          ->setHeader('Accept'         , 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                          ->setHeader('Accept-Language', 'en-us'                                                          )
                          ->setHeader('Accept-Charset' , 'ISO-8859-1,utf-8;q=0.7,*;q=0.7'                                 )
                          ->setHeader('Connection'     , 'keep-alive'                                                     )
                          ->setHeader('Cache-Control'  , 'max-age=0'                                                      )
                          ->setHeader('Referer'        , 'http://www.dukascopy.com/free/candelabrum/'                     );
    $options[CURLOPT_SSL_VERIFYPEER] = false;                           // falls HTTPS verwendet wird
    //$options[CURLOPT_VERBOSE     ] = true;


    // (2) HTTP-Request abschicken und auswerten
    static $client;
    !$client && $client = CurlHttpClient::create($options);             // Instanz fuer KeepAlive-Connections wiederverwenden

    $response = $client->send($request);                                // TODO: CURL-Fehler wie bei SimpleTrader behandeln
    $status   = $response->getStatus();
    if ($status!=200 && $status!=404) throw new RuntimeException('Unexpected HTTP status '.$status.' ('.HttpResponse::$sc[$status].') for url "'.$url.'"'.NL.printPretty($response, true));

    // eine leere Antwort ist moeglich und wird wie ein 404-Fehler behandelt
    $content = $response->getContent();
    if (!strLen($content))
        $status = 404;


    // (3) Download-Success
    if ($status == 200) {
        // ggf. vorhandene Fehlerdatei loeschen
        if (is_file($file=getVar('dukaFile.404', $symbol, $day, $type))) unlink($file);

        // ist das Flag $saveData gesetzt, Content speichern
        if ($saveData) {
            mkDirWritable(getVar('rostDir', $symbol, $day, $type));
            $tmpFile = tempNam(dirName($file=getVar('dukaFile.compressed', $symbol, $day, $type)), baseName($file));
            $hFile   = fOpen($tmpFile, 'wb');
            fWrite($hFile, $response->getContent());
            fClose($hFile);
            if (is_file($file)) unlink($file);
            rename($tmpFile, $file);                                       // So kann eine existierende Datei niemals korrupt sein.
        }
    }


    // (4) Download-Fehler: ist das Flag $saveError gesetzt, Fehler speichern
    if ($status == 404) {
        if (!$quiet)
            echoPre('[Error]   '.$shortDate.'   url not found (404): '.$url);

        if ($saveError) {
            mkDirWritable(dirName($file=getVar('dukaFile.404', $symbol, $day, $type)));
            fClose(fOpen($file, 'wb'));
        }
    }
    return ($status==200) ? $response->getContent() : '';
}


/**
 * @return bool - Erfolgsstatus
 */
function processCompressedDukascopyBarFile($file, $symbol, $day, $type) {
    if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
    if (!is_int($day))     throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

    global $verbose;
    if ($verbose > 0) echoPre('[Info]    '.gmDate('D, d-M-Y', $day).'   Dukascopy compressed bar file: '.baseName($file));

    return processCompressedDukascopyBarData(file_get_contents($file), $symbol, $day, $type);
}


/**
 * @return bool - Erfolgsstatus
 */
function processCompressedDukascopyBarData($data, $symbol, $day, $type) {
    if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));

    global $storeDecompressedDukaFiles;
    $saveAs = $storeDecompressedDukaFiles ? getVar('dukaFile.raw', $symbol, $day, $type) : null;

    $rawData = Dukascopy::decompressHistoryData($data, $saveAs);
    return processRawDukascopyBarData($rawData, $symbol, $day, $type);
}


/**
 * @return bool - Erfolgsstatus
 */
function processRawDukascopyBarFile($file, $symbol, $day, $type) {
    if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
    if (!is_int($day))     throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

    global $verbose;
    if ($verbose > 0) echoPre('[Info]    '.gmDate('D, d-M-Y', $day).'   Dukascopy raw bar file: '.baseName($file));

    return processRawDukascopyBarData(file_get_contents($file), $symbol, $day, $type);
}


/**
 * @return bool - Erfolgsstatus
 */
function processRawDukascopyBarData($data, $symbol, $day, $type) {
    if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));
    if (!is_int($day))     throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    if (!is_string($type)) throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));
    $shortDate = gmDate('D, d-M-Y', $day);

    global $barBuffer; $barBuffer[$type];

    // (1) Bars einlesen
    $bars = Dukascopy ::readBarData($data, $symbol, $type, $day);
    $size = sizeOf($bars); if ($size != 1*DAY/MINUTES) throw new RuntimeException('Unexpected number of Dukascopy bars in '.getVar('dukaName', null, null, $type).': '.$size.' ('.($size > 1*DAY/MINUTES ? 'more':'less').' then a day)');


    // (2) Timestamps und FXT-Daten zu den Bars hinzufuegen
    $prev = $next = null;                                               // Die Daten der Datei koennen einen DST-Wechsel abdecken, wenn
    $fxtOffset = Rost::fxtTimezoneOffset($day, $prev, $next);           // $day = "Sun, 00:00 GMT" ist. In diesem Fall muss innerhalb
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
            echoPre('Day of DST change ('.gmDate('D, d-M-Y', $lastBar['time_fxt']).') ended with:');
            echoPre($bars[$newDayOffset-1]);
            echoPre('Day after DST change ('.gmDate('D, d-M-Y', $firstBar['time_fxt']).') started with:');
            echoPre($bars[$newDayOffset]);
        }
    }
    $bars1      = array_slice($bars, 0, $newDayOffset);
    $bars2      = array_slice($bars, $newDayOffset);

    $shortDate1 = gmDate('D, d-M-Y', $bars1[0]['time_fxt']-$bars1[0]['delta_fxt']);
    $shortDate2 = gmDate('D, d-M-Y', $bars2[0]['time_fxt']-$bars2[0]['delta_fxt']);

    if (isSet($barBuffer[$type][$shortDate1])) {
        // Sicherstellen, dass die Daten zu mergender Bars nahtlos ineinander uebergehen.
        $lastBarTime = $barBuffer[$type][$shortDate1][sizeOf($barBuffer[$type][$shortDate1])-1]['time_fxt'];
        $nextBarTime = $bars1[0]['time_fxt'];
        if ($lastBarTime + 1*MINUTE != $nextBarTime) throw new RuntimeException('Bar time mis-match, bars to merge: "'.getVar('dukaName', null, null, $type).'", $lastBarTime='.$lastBarTime.', $nextBarTime='.$nextBarTime);
        $barBuffer[$type][$shortDate1] = array_merge($barBuffer[$type][$shortDate1], $bars1);
    }
    else {
        $barBuffer[$type][$shortDate1] = $bars1;
    }

    if (isSet($barBuffer[$type][$shortDate2])) {
        // Sicherstellen, dass die Daten zu mergender Bars nahtlos ineinander uebergehen.
        $lastBarTime = $barBuffer[$type][$shortDate2][sizeOf($barBuffer[$type][$shortDate2])-1]['time_fxt'];
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
 * Schreibt die gemergten Bardaten eines FXT-Tages aus dem Barbuffer in die lokale Rost-Historydatei.
 *
 * @param  string $symbol - Symbol
 * @param  int    $day    - Timestamp des FXT-Tages
 *
 * @return bool - Erfolgsstatus
 */
function saveBars($symbol, $day) {
    if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
    $shortDate = gmDate('D, d-M-Y', $day);
    global $barBuffer, $storeUncompressedRostFiles;


    // (1) gepufferte Datenreihe nochmal pruefen
    $errorMsg = null;
    if (!$errorMsg && !isSet($barBuffer['avg'][$shortDate]))                                    $errorMsg = 'No "avg" bars of '.$shortDate.' in buffer';
    if (!$errorMsg && ($size=sizeOf($barBuffer['avg'][$shortDate]))!=1*DAY/MINUTES)             $errorMsg = 'Invalid number of "avg" bars for '.$shortDate.' in buffer: '.$size;
    if (!$errorMsg && $barBuffer['avg'][$shortDate][0      ]['delta_fxt']!=0                  ) $errorMsg = 'No beginning "avg" bars for '.$shortDate.' in buffer, first bar:'.NL.printPretty($barBuffer['avg'][$shortDate][0], true);
    if (!$errorMsg && $barBuffer['avg'][$shortDate][$size-1]['delta_fxt']!=23*HOURS+59*MINUTES) $errorMsg = 'No ending "avg" bars for '.$shortDate.' in buffer, last bar:'.NL.printPretty($barBuffer['avg'][$shortDate][$size-1], true);
    if (!$errorMsg && ($size=sizeOf(array_keys($barBuffer['avg']))) > 1)                        $errorMsg = 'Invalid bar buffer state: found more then one "avg" data series ('.$size.')';
    if ($errorMsg) {
        showBarBuffer();
        throw new RuntimeException($errorMsg);
    }


    // (2) Bars in Binaerstring umwandeln
    $data = null;
    foreach ($barBuffer['avg'][$shortDate] as $bar) {
        // Bardaten vorm Schreiben validieren
        if ($bar['open' ] > $bar['high'] ||
            $bar['open' ] < $bar['low' ] ||          // aus (H >= O && O >= L) folgt (H >= L)
            $bar['close'] > $bar['high'] ||          // nicht mit min()/max(), da nicht performant
            $bar['close'] < $bar['low' ] ||
           !$bar['ticks']) throw new RuntimeException('Illegal data for Rost price bar of '.gmDate('D, d-M-Y H:i:s', $bar['time_fxt']).": O=$bar[open] H=$bar[high] L=$bar[low] C=$bar[close] V=$bar[ticks]");

        $data .= pack('VVVVVV', $bar['time_fxt'],
                                $bar['open'    ],
                                $bar['high'    ],
                                $bar['low'     ],
                                $bar['close'   ],
                                $bar['ticks'   ]);
    }


    // (3) binaere Daten ggf. unkomprimiert speichern
    if ($storeUncompressedRostFiles) {
        if (is_file($file=getVar('rostFile.raw', $symbol, $day))) {
            echoPre('[Error]   '.$symbol.' history for '.$shortDate.' already exists');
            return false;
        }
        mkDirWritable(dirName($file));
        $tmpFile = tempNam(dirName($file), baseName($file));
        $hFile   = fOpen($tmpFile, 'wb');
        fWrite($hFile, $data);
        fClose($hFile);
        rename($tmpFile, $file);                                       // So kann eine existierende Datei niemals korrupt sein.
    }


    // (4) binaere Daten ggf. komprimieren und speichern

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

    if (!is_string($id))                       throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
    if (isSet($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
    if (isSet($time)) {
        if (!is_int($time))                    throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));
        if ($time % DAY)                       throw new InvalidArgumentException('Invalid parameter $time: '.$time.' (not 00:00)');
    }
    if (isSet($type)) {
        if (!is_string($type))                 throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));
        if ($type!='bid' && $type!='ask')      throw new InvalidArgumentException('Invalid parameter $type: "'.$type.'"');
    }

    static $dataDir; !$dataDir && $dataDir = Config::getDefault()->get('app.dir.data');
    $self = __FUNCTION__;

    if ($id == 'rostDirDate') {               // $yyyy/$mmL/$dd                                         // lokales Pfad-Datum
        if (!$time) throw new InvalidArgumentException('Invalid parameter $time: '.$time);
        $result = gmDate('Y/m/d', $time);
    }
    else if ($id == 'rostDir') {              // $dataDirectory/history/rost/$type/$symbol/$dateL       // lokales Verzeichnis
        $type   = RosaSymbol::dao()->getByName($symbol)->getType();
        $dateL  = $self('rostDirDate', null, $time, null);
        $result = $dataDir.'/history/rost/'.$type.'/'.$symbol.'/'.$dateL;
    }
    else if ($id == 'rostFile.raw') {         // $rostDir/M1.myfx                                       // lokale Datei ungepackt
        $rostDir = $self('rostDir', $symbol, $time, null);
        $result  = $rostDir.'/M1.myfx';
    }
    else if ($id == 'rostFile.compressed') {  // $rostDir/M1.rar                                        // lokale Datei gepackt
        $rostDir = $self('rostDir', $symbol, $time, null);
        $result  = $rostDir.'/M1.rar';
    }
    else if ($id == 'dukaName') {               // BID_candles_min_1                                    // Dukascopy-Name
        if (is_null($type)) throw new InvalidArgumentException('Invalid parameter $type: (null)');
        $result = ($type=='bid' ? 'BID':'ASK').'_candles_min_1';
    }
    else if ($id == 'dukaFile.raw') {           // $rostDir/$dukaName.bin                               // Dukascopy-Datei ungepackt
        $rostDir  = $self('rostDir', $symbol, $time, null);
        $dukaName = $self('dukaName', null, null, $type);
        $result   = $rostDir.'/'.$dukaName.'.bin';
    }
    else if ($id == 'dukaFile.compressed') {    // $rostDir/$dukaName.bi5                               // Dukascopy-Datei gepackt
        $rostDir  = $self('rostDir', $symbol, $time, null);
        $dukaName = $self('dukaName', null, null, $type);
        $result   = $rostDir.'/'.$dukaName.'.bi5';
    }
    else if ($id == 'dukaUrlDate') {            // $yyyy/$mmD/$dd                                       // Dukascopy-URL-Datum
        if (!$time) throw new InvalidArgumentException('Invalid parameter $time: '.$time);
        $yyyy   = gmDate('Y', $time);
        $mmD    = strRight((string)(gmDate('m', $time)+99), 2);  // Januar = 00
        $dd     = gmDate('d', $time);
        $result = $yyyy.'/'.$mmD.'/'.$dd;
    }
    else if ($id == 'dukaUrl') {                // http://datafeed.dukascopy.com/datafeed/$symbol/$dateD/$dukaName.bi5
        if (!$symbol) throw new InvalidArgumentException('Invalid parameter $symbol: '.$symbol);
        $dateD    = $self('dukaUrlDate', null, $time, null);
        $dukaName = $self('dukaName'   , null, null, $type);
        $result   = 'http://datafeed.dukascopy.com/datafeed/'.$symbol.'/'.$dateD.'/'.$dukaName.'.bi5';
    }
    else if ($id == 'dukaFile.404') {           // $rostDir/$dukaName.404                               // Download-Fehlerdatei (404)
        $rostDir  = $self('rostDir', $symbol, $time, null);
        $dukaName = $self('dukaName', null, null, $type);
        $result   = $rostDir.'/'.$dukaName.'.404';
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
            echoPre('barBuffer['.$type.'] => '.(is_null($days) ? 'null':$days));
            continue;
        }
        foreach ($days as $day => $bars) {
            if (!is_array($bars)) {
                echoPre('barBuffer['.$type.']['.(is_int($day) ? gmDate('D, d-M-Y', $day):$day).'] => '.(is_null($bars) ? 'null':$bars));
                continue;
            }
            $size = sizeOf($bars);
            $firstBar = $size ? gmDate('H:i', $bars[0      ]['time_fxt']):null;
            $lastBar  = $size ? gmDate('H:i', $bars[$size-1]['time_fxt']):null;
            echoPre('barBuffer['.$type.']['.(is_int($day) ? gmDate('D, d-M-Y', $day):$day).'] => '.str_pad($size, 4, ' ', STR_PAD_LEFT).' bar'.pluralize($size).($firstBar?'  '.$firstBar:'').($size>1?'-'.$lastBar:''));
        }
    }
    echoPre(NL);
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
 Update the M1 history of the specified symbols with data from Dukascopy.

 Syntax:  $self [SYMBOL ...]

   SYMBOL    One or more symbols to update. Without a symbol all defined symbols are updated.

   Options:  -v    Verbose output.
             -vv   More verbose output.
             -vvv  Very verbose output.
             -h    This help screen.


HELP;
}
