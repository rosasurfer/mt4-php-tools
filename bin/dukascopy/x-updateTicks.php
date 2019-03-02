#!/usr/bin/env php
<?php
/**
 * Aktualisiert die lokal vorhandenen Dukascopy-Tickdaten. Die Daten werden nach FXT konvertiert und im Rosatrader-Format
 * gespeichert. Am Wochenende, an Feiertagen und wenn keine Tickdaten verfuegbar sind, sind die Dukascopy-Dateien leer.
 * Wochenenden werden lokal nicht gespeichert. Montags frueh koennen die Daten erst um 01:00 FXT beginnen.
 * Die Daten der aktuellen Stunde sind fruehestens ab der naechsten Stunde verfuegbar.
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
 * URL-Format:    Eine Datei je Tagestunde GMT,
 *                z.B.: (Januar = 00)
 *                - http://datafeed.dukascopy.com/datafeed/EURUSD/2013/00/06/00h_ticks.bi5
 *                - http://datafeed.dukascopy.com/datafeed/EURUSD/2013/05/10/23h_ticks.bi5
 *
 * Dateiformat:   - Binaer, LZMA-gepackt, Zeiten in GMT (keine Sommerzeit).
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
namespace rosasurfer\rt\bin\dukascopy\update_tickdata;

use rosasurfer\Application;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\file\FileSystem as FS;
use rosasurfer\net\http\CurlHttpClient;
use rosasurfer\net\http\HttpClient;
use rosasurfer\net\http\HttpRequest;
use rosasurfer\net\http\HttpResponse;
use rosasurfer\process\Process;

use rosasurfer\rt\lib\LZMA;
use rosasurfer\rt\lib\RT;
use rosasurfer\rt\lib\dukascopy\Dukascopy;
use rosasurfer\rt\lib\dukascopy\exception\DukascopyException;
use rosasurfer\rt\model\DukascopySymbol;
use rosasurfer\rt\model\RosaSymbol;

use function rosasurfer\rt\fxtStrToTime;
use function rosasurfer\rt\fxtTimezoneOffset;
use function rosasurfer\rt\isWeekend;

require(dirname(realpath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');


// -- Konfiguration ---------------------------------------------------------------------------------------------------------


$verbose = 0;                                   // output verbosity

$saveCompressedDukascopyFiles = false;          // ob heruntergeladene Dukascopy-Dateien zwischengespeichert werden sollen
$saveRawDukascopyFiles        = false;          // ob entpackte Dukascopy-Dateien zwischengespeichert werden sollen
$saveRawRTData                = true;           // ob unkomprimierte RT-Historydaten gespeichert werden sollen


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
    if (!$symbol)                       exit(1|stderr('error: unknown symbol "'.$args[$i].'"'));
    if (!$symbol->getDukascopySymbol()) exit(1|stderr('error: no Dukascopy mapping found for symbol "'.$args[$i].'"'));
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
 * Aktualisiert die Tickdaten eines Symbol.
 *
 * Eine Dukascopy-Datei enthaelt eine Stunde Tickdaten. Die Daten der aktuellen Stunde sind fruehestens
 * ab der naechsten Stunde verfuegbar.
 *
 * @param  RosaSymbol $symbol
 *
 * @return bool - Erfolgsstatus
 */
function updateSymbol(RosaSymbol $symbol) {
    /** @var DukascopySymbol $dukaSymbol */
    $dukaSymbol = $symbol->getDukascopySymbol();
    $symbolName = $symbol->getName();

    echoPre('[Info]    '.$symbolName);


    // (1) Beginn des naechsten Forex-Tages ermitteln
    $startTimeFXT = $dukaSymbol->getHistoryStartTick();
    $startTimeGMT = $startTimeFXT ? fxtStrToTime($startTimeFXT) : 0;        // Beginn der Tickdaten des Symbols in GMT
    $prev = $next = null;
    $fxtOffset    = fxtTimezoneOffset($startTimeGMT, $prev, $next);         // es gilt: FXT = GMT + Offset
    $startTimeFXT = $startTimeGMT + $fxtOffset;                             // Beginn der Tickdaten in FXT

    if ($remainder=$startTimeFXT % DAY) {                                   // Beginn auf den naechsten Forex-Tag 00:00 aufrunden, sodass
        $diff = 1*DAY - $remainder;                                         // wir nur vollstaendige Forex-Tage verarbeiten. Dabei
        if ($startTimeGMT + $diff >= $next['time']) {                       // beruecksichtigen, dass sich zu Beginn des naechsten Forex-Tages
            $startTimeGMT = $next['time'];                                  // der DST-Offset der FXT geaendert haben kann.
            $startTimeFXT = $startTimeGMT + $next['offset'];
            if ($remainder=$startTimeFXT % DAY) $diff = 1*DAY - $remainder;
            else                                $diff = 0;
            $fxtOffset = fxtTimezoneOffset($startTimeGMT, $prev, $next);
        }
        $startTimeGMT += $diff;                                             // naechster Forex-Tag 00:00 in GMT
        $startTimeFXT += $diff;                                             // naechster Forex-Tag 00:00 in FXT
    }


    // (2) Gesamte Zeitspanne inklusive Wochenenden stundenweise durchlaufen, um von vorherigen Durchlaufen ggf. vorhandene
    // Zwischendateien finden und loeschen zu koennen.
    $thisHour = ($thisHour=time()) - $thisHour%HOUR;                        // Beginn der aktuellen Stunde GMT
    $lastHour = $thisHour - 1*HOUR;                                         // Beginn der letzten Stunde GMT

    for ($gmtHour=$startTimeGMT; $gmtHour < $lastHour; $gmtHour+=1*HOUR) {
        if ($gmtHour >= $next['time'])
            $fxtOffset = fxtTimezoneOffset($gmtHour, $prev, $next);         // $fxtOffset on-the-fly aktualisieren
        $fxtHour = $gmtHour + $fxtOffset;

        if (!checkHistory($symbolName, $gmtHour, $fxtHour)) return false;

        Process::dispatchSignals();                                         // check for Ctrl-C
    }
    echoPre('[Ok]      '.$symbolName);
    return true;
}


/**
 * Prueft den Stand der RT-Tickdaten einer einzelnen Stunde und stoesst ggf. das Update an.
 *
 * @param  string $symbol  - Symbol
 * @param  int    $gmtHour - GMT-Timestamp der zu pruefenden Stunde
 * @param  int    $fxtHour - FXT-Timestamp der zu pruefenden Stunde
 *
 * @return bool - Erfolgsstatus
 */
function checkHistory($symbol, $gmtHour, $fxtHour) {
    if (!is_int($gmtHour)) throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.gettype($gmtHour));
    if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.gettype($fxtHour));
    $shortDate = gmdate('D, d-M-Y H:i', $fxtHour);

    global $verbose, $saveCompressedDukascopyFiles, $saveRawDukascopyFiles, $saveRawRTData;
    static $lastDay=-1, $lastMonth=-1;

    // (1) nur an Handelstagen pruefen, ob die RT-History existiert und ggf. aktualisieren
    if (!isWeekend($fxtHour)) {
        $day = (int) gmdate('d', $fxtHour);
        if ($day != $lastDay) {
            if ($verbose > 1) echoPre('[Info]    '.gmdate('d-M-Y', $fxtHour));
            else {
                $month = (int) gmdate('m', $fxtHour);
                if ($month != $lastMonth) {
                    if ($verbose > 0) echoPre('[Info]    '.gmdate('M-Y', $fxtHour));
                    $lastMonth = $month;
                }
            }
            $lastDay = $day;
        }

        // History ist ok, wenn entweder die komprimierte RT-Datei existiert...
        if (is_file($file=getVar('rtFile.compressed', $symbol, $fxtHour))) {
            if ($verbose > 1) echoPre('[Ok]      '.$shortDate.'  Rosatrader compressed tick file: '.RT::relativePath($file));
        }
        // History ist ok, ...oder die unkomprimierte RT-Datei gespeichert wird und existiert
        else if ($saveRawRTData && is_file($file=getVar('rtFile.raw', $symbol, $fxtHour))) {
            if ($verbose > 1) echoPre('[Ok]      '.$shortDate.'  Rosatrader uncompressed tick file: '.RT::relativePath($file));
        }
        // andererseits Tickdaten aktualisieren
        else {
            try {
                if (!updateTicks($symbol, $gmtHour, $fxtHour)) return false;
            }
            catch (DukascopyException $ex) {    // bei leerem Response fortfahren (Fehler wurde schon gespeichert)
                if (!strStartsWithI($ex->getMessage(), 'empty response for url:')) throw $ex;
            }
        }
    }


    // (2) an allen Tagen: nicht mehr benoetigte Dateien und Verzeichnisse loeschen
    // komprimierte Dukascopy-Daten (Downloads) der geprueften Stunde
    if (!$saveCompressedDukascopyFiles) {
        if (is_file($file=getVar('dukaFile.compressed', $symbol, $gmtHour))) unlink($file);
    }
    // dekomprimierte Dukascopy-Daten der geprueften Stunde
    if (!$saveRawDukascopyFiles) {
        if (is_file($file=getVar('dukaFile.raw', $symbol, $gmtHour))) unlink($file);
    }
    // Dukascopy-Downloadverzeichnis der aktuellen Stunde, wenn es leer ist
    if (is_dir($dir=getVar('rtDir', $symbol, $gmtHour))) @rmdir($dir);
    // lokales Historyverzeichnis der aktuellen Stunde, wenn Wochenende und es leer ist
    if (isWeekend($fxtHour)) {
        if (is_dir($dir=getVar('rtDir', $symbol, $fxtHour))) @rmdir($dir);
    }

    return true;
}


/**
 * Aktualisiert die Tickdaten einer einzelnen Forex-Handelstunde. Wird aufgerufen, wenn fuer diese Stunde keine lokalen
 * RT-Tickdateien existieren.
 *
 * @param  string $symbol  - Symbol
 * @param  int    $gmtHour - GMT-Timestamp der zu aktualisierenden Stunde
 * @param  int    $fxtHour - FXT-Timestamp der zu aktualisierenden Stunde
 *
 * @return bool - Erfolgsstatus
 */
function updateTicks($symbol, $gmtHour, $fxtHour) {
    if (!is_int($gmtHour)) throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.gettype($gmtHour));
    if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.gettype($fxtHour));
    $shortDate = gmdate('D, d-M-Y H:i', $fxtHour);

    // Tickdaten laden
    /** @var array $ticks */
    $ticks = loadTicks($symbol, $gmtHour, $fxtHour);
    if (!$ticks) return false;

    // Tickdaten speichern
    return saveTicks($symbol, $gmtHour, $fxtHour, $ticks);
}


/**
 * Laedt die Daten einer einzelnen Forex-Handelsstunde und gibt sie zurueck.
 *
 * @param  string $symbol  - Symbol
 * @param  int    $gmtHour - GMT-Timestamp der zu ladenden Stunde
 * @param  int    $fxtHour - FXT-Timestamp der zu ladenden Stunde
 *
 * @return array - Array mit Tickdaten oder an empty value in case of errors
 */
function loadTicks($symbol, $gmtHour, $fxtHour) {
    if (!is_int($gmtHour)) throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.gettype($gmtHour));
    if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.gettype($fxtHour));
    $shortDate = gmdate('D, d-M-Y H:i', $fxtHour);

    // Die Tickdaten der Handelsstunde werden in folgender Reihenfolge gesucht:
    //  - in bereits dekomprimierten Dukascopy-Dateien
    //  - in noch komprimierten Dukascopy-Dateien
    //  - als Dukascopy-Download

    global $saveCompressedDukascopyFiles;
    $ticks = [];

    // dekomprimierte Dukascopy-Datei suchen und bei Erfolg Ticks laden
    if (!$ticks) {
        if (is_file($file=getVar('dukaFile.raw', $symbol, $gmtHour))) {
            $ticks = loadRawDukascopyTickFile($file, $symbol, $gmtHour, $fxtHour);
            if (!$ticks) return [];
        }
    }

    // ggf. komprimierte Dukascopy-Datei suchen und bei Erfolg Ticks laden
    if (!$ticks) {
        if (is_file($file=getVar('dukaFile.compressed', $symbol, $gmtHour))) {
            $ticks = loadCompressedDukascopyTickFile($file, $symbol, $gmtHour, $fxtHour);
            if (!$ticks) return [];
        }
    }

    // ggf. Dukascopy-Datei herunterladen und Ticks laden
    if (!$ticks) {
        $data = downloadTickdata($symbol, $gmtHour, $fxtHour, false, $saveCompressedDukascopyFiles);
        if (!$data) return [];

        $ticks = loadCompressedDukascopyTickData($data, $symbol, $gmtHour, $fxtHour);
    }
    return $ticks;
}


/**
 * Schreibt die Tickdaten einer Handelsstunde in die lokale RT-Tickdatei.
 *
 * @param  string $symbol  - Symbol
 * @param  int    $gmtHour - GMT-Timestamp der Handelsstunde
 * @param  int    $fxtHour - FXT-Timestamp der Handelsstunde
 * @param  array  $ticks   - zu speichernde Ticks
 *
 * @return bool - Erfolgsstatus
 */
function saveTicks($symbol, $gmtHour, $fxtHour, array $ticks) {
    if (!is_int($gmtHour)) throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.gettype($gmtHour));
    if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.gettype($fxtHour));
    $shortDate = gmdate('D, d-M-Y H:i', $fxtHour);
    global $saveRawRTData;


    // (1) Tickdaten nochmal pruefen
    if (!$ticks) throw new RuntimeException('No ticks for '.$shortDate);
    $size = sizeof($ticks);
    $fromHour = ($time=$ticks[      0]['time_fxt']) - $time%HOUR;
    $toHour   = ($time=$ticks[$size-1]['time_fxt']) - $time%HOUR;
    if ($fromHour != $fxtHour) throw new RuntimeException('Ticks for '.$shortDate.' do not match the specified hour: $tick[0]=\''.gmdate('d-M-Y H:i:s \F\X\T', $ticks[0]['time_fxt']).'\'');
    if ($fromHour != $toHour)  throw new RuntimeException('Ticks for '.$shortDate.' span multiple hours from=\''.gmdate('d-M-Y H:i:s \F\X\T', $ticks[0]['time_fxt']).'\' to=\''.gmdate('d-M-Y H:i:s \F\X\T', $ticks[$size-1]['time_fxt']).'\'');


    // (2) Ticks binaer packen
    $data = null;
    foreach ($ticks as $tick) {
        $data .= pack('VVV', $tick['timeDelta'],
                                    $tick['bid'      ],
                                    $tick['ask'      ]);
    }


    // (3) binaere Daten ggf. unkomprimiert speichern
    if ($saveRawRTData) {
        if (is_file($file=getVar('rtFile.raw', $symbol, $fxtHour))) {
            echoPre('[Error]   '.$symbol.' ticks for '.$shortDate.' already exists');
            return false;
        }
        FS::mkDir(dirname($file));
        $tmpFile = tempnam(dirname($file), basename($file));
        file_put_contents($tmpFile, $data);
        rename($tmpFile, $file);            // So kann eine existierende Datei niemals korrupt sein.
    }


    // (4) binaere Daten ggf. komprimieren und speichern

    return true;
}


/**
 * Laedt eine Dukascopy-Tickdatei und gibt ihren Inhalt zurueck.
 *
 * @param  string $symbol    - Symbol der herunterzuladenen Datei
 * @param  int    $gmtHour   - GMT-Timestamp der zu ladenden Stunde
 * @param  int    $fxtHour   - FXT-Timestamp der zu ladenden Stunde
 * @param  bool   $quiet     - ob Statusmeldungen unterdrueckt werden sollen (default: nein)
 * @param  bool   $saveData  - ob die Datei gespeichert werden soll (default: nein)
 * @param  bool   $saveError - ob ein 404-Fehler mit einer entsprechenden Fehlerdatei signalisiert werden soll (default: ja)
 *
 * @return string - Content der heruntergeladenen Datei oder Leerstring, wenn die Resource nicht gefunden wurde (404-Fehler).
 */
function downloadTickdata($symbol, $gmtHour, $fxtHour, $quiet=false, $saveData=false, $saveError=true) {
    if (!is_int($gmtHour))    throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.gettype($gmtHour));
    if (!is_int($fxtHour))    throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.gettype($fxtHour));
    if (!is_bool($quiet))     throw new IllegalTypeException('Illegal type of parameter $quiet: '.gettype($quiet));
    if (!is_bool($saveData))  throw new IllegalTypeException('Illegal type of parameter $saveData: '.gettype($saveData));
    if (!is_bool($saveError)) throw new IllegalTypeException('Illegal type of parameter $saveError: '.gettype($saveError));
    global$verbose;

    $shortDate = gmdate('D, d-M-Y H:i', $fxtHour);
    $url       = getVar('dukaUrl', $symbol, $gmtHour);
    if (!$quiet && $verbose > 1) echoPre('[Info]    '.$shortDate.'  downloading: '.$url);


    // (1) Standard-Browser simulieren
    $userAgent = Application::getConfig()['rt.http.useragent'];
    $request = (new HttpRequest($url))
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
    static $httpClient = null;
    !$httpClient && $httpClient = new CurlHttpClient($options);         // Instanz fuer KeepAlive-Connections wiederverwenden

    $response = $httpClient->send($request);                            // TODO: CURL-Fehler wie bei SimpleTrader behandeln
    $status   = $response->getStatus();
    if ($status!=200 && $status!=404) throw new RuntimeException('Unexpected HTTP status '.$status.' ('.HttpResponse::$sc[$status].') for url "'.$url.'"'.NL.printPretty($response, true));

    // eine leere Antwort ist moeglich und wird als Fehler behandelt
    $content = $response->getContent();
    if ($status == 404) $content = '';                                  // moeglichen Content eines 404-Fehlers zuruecksetzen


    // (3) Download-Success: 200 und Datei ist nicht leer
    if ($status==200 && strlen($content)) {
        // vorhandene Fehlerdateien loeschen (haben FXT-Namen)
        if (is_file($file=getVar('dukaFile.404',   $symbol, $fxtHour))) unlink($file);
        if (is_file($file=getVar('dukaFile.empty', $symbol, $fxtHour))) unlink($file);

        // ist das Flag $saveData gesetzt, Content speichern
        if ($saveData) {
            FS::mkDir(getVar('rtDir', $symbol, $gmtHour));
            $tmpFile = tempnam(dirname($file=getVar('dukaFile.compressed', $symbol, $gmtHour)), basename($file));
            file_put_contents($tmpFile, $content);
            if (is_file($file)) unlink($file);
            rename($tmpFile, $file);                                       // So kann eine existierende Datei niemals korrupt sein.
        }
    }


    // (4) Download-Fehler: ist das Flag $saveError gesetzt, Fehler speichern
    else {
        if ($saveError) {                                                 // Fehlerdatei unter FXT-Namen speichern
            $file = getVar($status==404 ? 'dukaFile.404':'dukaFile.empty', $symbol, $fxtHour);
            FS::mkDir(dirname($file));
            fclose(fopen($file, 'wb'));
        }

        if (!$quiet) {
            if ($status==404) echoPre('[Error]   '.$shortDate.'  url not found (404): '.$url);
            else              echoPre('[Warn]    '.$shortDate.'  empty response: '.$url);
        }

        // bei leerem Response Exception werfen, damit eine Schleife ggf. fortgesetzt werden kann
        if ($status != 404) throw new DukascopyException('empty response for url: '.$url);
    }
    return $content;
}


/**
 * Laedt die in einem komprimierten Dukascopy-Tickfile enthaltenen Ticks.
 *
 * @return array[] - Array mit Tickdaten
 */
function loadCompressedDukascopyTickFile($file, $symbol, $gmtHour, $fxtHour) {
    if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.gettype($file));
    if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.gettype($fxtHour));

    global $verbose;
    if ($verbose > 0) echoPre('[Info]    '.gmdate('D, d-M-Y H:i', $fxtHour).'  Dukascopy compressed tick file: '.RT::relativePath($file));

    return loadCompressedDukascopyTickData(file_get_contents($file), $symbol, $gmtHour, $fxtHour);
}


/**
 * Laedt die in einem komprimierten String enthaltenen Dukascopy-Tickdaten.
 *
 * @return array - Array mit Tickdaten
 */
function loadCompressedDukascopyTickData($data, $symbol, $gmtHour, $fxtHour) {
    if (!is_int($gmtHour)) throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.gettype($gmtHour));

    global $saveRawDukascopyFiles;
    $saveAs = $saveRawDukascopyFiles ? getVar('dukaFile.raw', $symbol, $gmtHour) : null;

    $rawData = Dukascopy::decompressHistoryData($data, $saveAs);
    return loadRawDukascopyTickData($rawData, $symbol, $gmtHour, $fxtHour);
}


/**
 * Laedt die in einem unkomprimierten Dukascopy-Tickfile enthaltenen Ticks.
 *
 * @return array - Array mit Tickdaten
 */
function loadRawDukascopyTickFile($file, $symbol, $gmtHour, $fxtHour) {
    if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.gettype($file));
    if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.gettype($fxtHour));

    global $verbose;
    if ($verbose > 0) echoPre('[Info]    '.gmdate('D, d-M-Y H:i', $fxtHour).'  Dukascopy uncompressed tick file: '.RT::relativePath($file));

    return loadRawDukascopyTickData(file_get_contents($file), $symbol, $gmtHour, $fxtHour);
}


/**
 * Laedt die in einem unkomprimierten String enthaltenen Dukascopy-Tickdaten.
 *
 * @return array - Array mit Tickdaten
 */
function loadRawDukascopyTickData($data, $symbol, $gmtHour, $fxtHour) {
    if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));
    if (!is_int($gmtHour)) throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.gettype($gmtHour));
    if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.gettype($fxtHour));

    // Ticks einlesen
    $ticks = Dukascopy::readTickData($data);

    // GMT- und FXT-Timestamps hinzufuegen
    foreach ($ticks as &$tick) {
        $sec    = (int)($tick['timeDelta'] / 1000);
        $millis =       $tick['timeDelta'] % 1000;

        $tick['time_gmt']    = $gmtHour + $sec;
        $tick['time_fxt']    = $fxtHour + $sec;
        $tick['time_millis'] = $millis;
    }; unset($tick);
    return $ticks;
}


/**
 * Erzeugt und verwaltet dynamisch generierte Variablen.
 *
 * Evaluiert und cacht staendig wiederbenutzte dynamische Variablen an einem zentralen Ort. Vereinfacht die Logik,
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

    static $storageDir;
    $storageDir = $storageDir ?: Application::getConfig()['app.dir.storage'];
    $self = __FUNCTION__;

    if ($id == 'rtDirDate') {                   // $yyyy/$mmL/$dd                                               // lokales Pfad-Datum
        if (!$time)   throw new InvalidArgumentException('Invalid parameter $time: '.$time);
        $result = gmdate('Y/m/d', $time);
    }
    else if ($id == 'rtDir') {                  // $dataDir/history/rosatrader/$type/$symbol/$rtDirDate         // lokales Verzeichnis
        $type      = RosaSymbol::dao()->getByName($symbol)->getType();
        $rtDirDate = $self('rtDirDate', null, $time);
        $result    = $storageDir.'/history/rosatrader/'.$type.'/'.$symbol.'/'.$rtDirDate;
    }
    else if ($id == 'rtFile.raw') {             // $rtDir/${hour}h_ticks.bin                                    // lokale Datei ungepackt
        $rtDir  = $self('rtDir', $symbol, $time);
        $hour   = gmdate('H', $time);
        $result = $rtDir.'/'.$hour.'h_ticks.bin';
    }
    else if ($id == 'rtFile.compressed') {      // $rtDir/${hour}h_ticks.rar                                    // lokale Datei gepackt
        $rtDir  = $self('rtDir', $symbol, $time);
        $hour   = gmdate('H', $time);
        $result = $rtDir.'/'.$hour.'h_ticks.rar';
    }
    else if ($id == 'dukaFile.raw') {           // $rtDir/${hour}h_ticks.bin                                    // Dukascopy-Datei ungepackt
        $rtDir  = $self('rtDir', $symbol, $time);
        $hour   = gmdate('H', $time);
        $result = $rtDir.'/'.$hour.'h_ticks.bin';
    }
    else if ($id == 'dukaFile.compressed') {    // $rtDir/${hour}h_ticks.bi5                                    // Dukascopy-Datei gepackt
        $rtDir  = $self('rtDir', $symbol, $time);
        $hour   = gmdate('H', $time);
        $result = $rtDir.'/'.$hour.'h_ticks.bi5';
    }
    else if ($id == 'dukaUrlDate') {            // $yyyy/$mmD/$dd                                               // Dukascopy-URL-Datum
        if (!$time) throw new InvalidArgumentException('Invalid parameter $time: '.$time);
        $yyyy   = gmdate('Y', $time);
        $mmD    = strRight((string)(gmdate('m', $time)+99), 2);  // Januar = 00
        $dd     = gmdate('d', $time);
        $result = $yyyy.'/'.$mmD.'/'.$dd;
    }
    else if ($id == 'dukaUrl') {                // http://datafeed.dukascopy.com/datafeed/$symbol/$dukaUrlDate/${hour}h_ticks.bi5
        if (!$symbol) throw new InvalidArgumentException('Invalid parameter $symbol: '.$symbol);
        $dukaUrlDate = $self('dukaUrlDate', null, $time);
        $hour        = gmdate('H', $time);
        $result      = 'http://datafeed.dukascopy.com/datafeed/'.$symbol.'/'.$dukaUrlDate.'/'.$hour.'h_ticks.bi5';
    }
    else if ($id == 'dukaFile.404') {           // $rtDir/${hour}h_ticks.404                                    // Download-Fehler 404
        $rtDir  = $self('rtDir', $symbol, $time);
        $hour   = gmdate('H', $time);
        $result = $rtDir.'/'.$hour.'h_ticks.404';
    }
    else if ($id == 'dukaFile.empty') {         // $rtDir/${hour}h_ticks.na                                     // Download-Fehler leerer Response
        $rtDir  = $self('rtDir', $symbol, $time);
        $hour   = gmdate('H', $time);
        $result = $rtDir.'/'.$hour.'h_ticks.na';
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
    if (isset($message))
        echo $message.NL.NL;

    $self = basename($_SERVER['PHP_SELF']);

echo <<<HELP

 Syntax:  $self [symbol ...]

 Options:  -v    Verbose output.
           -vv   More verbose output.
           -vvv  Very verbose output.
           -h    This help screen.


HELP;
}
