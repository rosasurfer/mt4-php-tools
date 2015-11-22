#!/usr/bin/php
<?php
/**
 * Aktualisiert die vorhandenen Dukascopy-M1-Daten. Fehlende Daten werden heruntergeladen, nach FXT konvertiert und in einem
 * eigenen komprimierten Format gespeichert. Die Dukascopy-Daten sind vollständig durchgehend, lokal werden für Wochenenden
 * und Feiertage (1. Januar und 25. Dezember) jedoch keine Daten gespeichert.
 *
 *
 * Webseite:      http://www.dukascopy.com/swiss/english/marketwatch/historical/
 *                http://www.dukascopy.com/free/candelabrum/
 *
 * Instrumente:   http://www.dukascopy.com/free/candelabrum/data.json
 *
 * History-Start: http://www.dukascopy.com/datafeed/metadata/HistoryStart.bi5  (Format unbekannt)
 *
 * URL-Format:    Durchgehend eine Datei je Kalendertag ab History-Start,
 *                z.B.: (Januar = 00)
 *                • http://www.dukascopy.com/datafeed/GBPUSD/2013/00/10/BID_candles_min_1.bi5
 *                • http://www.dukascopy.com/datafeed/GBPUSD/2013/11/31/ASK_candles_min_1.bi5
 *
 * Dateiformat:   binär, LZMA-gepackt, Zeiten in GMT (keine Sommerzeit)
 *                Handelspausen sind mit dem letzten Schlußkurs (OHLC) und V=0 (zero) angegeben.
 *
 *                @see class Dukascopy
 *
 *      +------------------------+------------+------------+------------+------------------------+------------------------+
 * FXT: |   Sunday      Monday   |  Tuesday   | Wednesday  |  Thursday  |   Friday     Saturday  |   Sunday      Monday   |
 *      +------------------------+------------+------------+------------+------------------------+------------------------+
 *          +------------------------+------------+------------+------------+------------------------+------------------------+
 * GMT:     |   Sunday      Monday   |  Tuesday   | Wednesday  |  Thursday  |   Friday     Saturday  |   Sunday      Monday   |
 *          +------------------------+------------+------------+------------+------------------------+------------------------+
 */
require(dirName(__FILE__).'/../../config.php');
date_default_timezone_set('GMT');


// History-Start der einzelnen Instrumente bei Dukascopy (geprüft am 21.06.2013)
$startTimes = array(//'AUDCAD' => strToTime('2005-12-26 00:00:00 GMT'),
                    //'AUDCHF' => strToTime('2005-12-26 00:00:00 GMT'),
                    //'AUDJPY' => strToTime('2003-11-30 00:00:00 GMT'),
                    //'AUDNZD' => strToTime('2006-12-08 00:00:00 GMT'),
                      'AUDUSD' => strToTime('2003-08-03 00:00:00 GMT'),    // geprüft am 18.11.2015
                    //'CADCHF' => strToTime('2005-12-26 00:00:00 GMT'),
                    //'CADJPY' => strToTime('2004-10-20 00:00:00 GMT'),
                    //'CHFJPY' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'EURAUD' => strToTime('2005-10-02 00:00:00 GMT'),
                    //'EURCAD' => strToTime('2004-10-20 00:00:00 GMT'),
                    //'EURCHF' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'EURGBP' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'EURJPY' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'EURNOK' => strToTime('2004-10-20 00:00:00 GMT'),
                    //'EURNZD' => strToTime('2005-12-26 00:00:00 GMT'),
                    //'EURSEK' => strToTime('2004-10-27 00:00:00 GMT'),
                      'EURUSD' => strToTime('2003-05-04 00:00:00 GMT'),    // geprüft am 18.11.2015
                    //'GBPAUD' => strToTime('2006-01-01 00:00:00 GMT'),
                    //'GBPCAD' => strToTime('2006-01-01 00:00:00 GMT'),
                    //'GBPCHF' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'GBPJPY' => strToTime('2003-08-03 00:00:00 GMT'),
                    //'GBPNZD' => strToTime('2006-01-01 00:00:00 GMT'),
                      'GBPUSD' => strToTime('2003-05-04 00:00:00 GMT'),    // geprüft am 18.11.2015
                    //'NZDCAD' => strToTime('2006-01-01 00:00:00 GMT'),
                    //'NZDCHF' => strToTime('2006-01-01 00:00:00 GMT'),
                    //'NZDJPY' => strToTime('2006-01-01 00:00:00 GMT'),
                      'NZDUSD' => strToTime('2003-08-03 00:00:00 GMT'),    // geprüft am 18.11.2015
                      'USDCAD' => strToTime('2003-08-03 00:00:00 GMT'),    // geprüft am 18.11.2015
                      'USDCHF' => strToTime('2003-05-04 00:00:00 GMT'),    // geprüft am 18.11.2015
                      'USDJPY' => strToTime('2003-05-04 00:00:00 GMT'),    // geprüft am 18.11.2015
                    //'USDNOK' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'USDSEK' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'USDSGD' => strToTime('2004-11-16 00:00:00 GMT'),
                    //'XAGUSD' => strToTime('1997-08-13 00:00:00 GMT'),
                    //'XAUUSD' => strToTime('1999-09-01 00:00:00 GMT'),
);


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);
if (!$args) help() & exit(1);

foreach ($args as $i => $arg) {
   if ($arg=="'*'" || $arg=='"*"')
      $args[$i] = $arg = '*';
   if ($arg != '*') {
      $arg = strToUpper($arg);
      if (!isSet($startTimes[$arg])) help('error: unknown symbol "'.$args[$i].'"') & exit(1);
      $args[$i] = $arg;
   }
}
$args = in_array('*', $args) ? array_keys($startTimes) : array_unique($args);    // '*' wird durch alle Symbole ersetzt


// (2) Buffer zum Zwischenspeichern geladener Bardaten
$barBuffer;


// (3) Daten aktualisieren
foreach ($args as $symbol) {
   if (!updateInstrument($symbol, $startTimes[$symbol]))
      exit(1);
}


// (4) Ende
exit(0);


// -- Ende -----------------------------------------------------------------------------------------------------------------------------------------


/**
 * Aktualisiert die M1-Daten eines Instruments.
 *
 * @param string $symbol    - Symbol des Instruments
 * @param int    $startTime - Beginn der Dukascopy-Daten dieses Instruments
 *
 * @return bool - Erfolgsstatus
 */
function updateInstrument($symbol, $startTime) {
   if (!is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   if (!is_int($startTime)) throw new IllegalTypeException('Illegal type of parameter $startTime: '.getType($startTime));
   $symbol = strToUpper($symbol);

   static $dataDirectory = null;
   if (!$dataDirectory) $dataDirectory = MyFX ::getConfigPath('myfx.data_directory');

   global $barBuffer;
   $barBuffer        = null;                                         // Barbuffer zurücksetzen
   $barBuffer['bid'] = array();
   $barBuffer['ask'] = array();


   // (1) Prüfen, ob sich der letzte bekannte Startzeitpunkt des Symbols geändert hat
   $startTime -= $startTime % DAY;                                   // 00:00 GMT des Starttages
   $today      = ($today=time()) - $today%DAY;                       // 00:00 GMT des aktuellen Tages

   // URL der letzten Datei vor dem Startzeitpunkt zusammenstellen
   $beforeTime = $startTime -1*DAY;                                  // 00:00 GMT des Starttages
   $yyyy  = date('Y', $beforeTime);
   $mmD   = strRight(iDate('m', $beforeTime)+ 99, 2);                // Dukascopy-Monat: Januar = 00
   $mmL   = strRight(iDate('m', $beforeTime)+100, 2);                // lokaler Monat:   Januar = 01
   $dd    = date('d', $beforeTime);
   $dateD = "$yyyy/$mmD/$dd";                                        // Dukascopy-Datum
   $dateL = "$yyyy/$mmL/$dd";                                        // lokales Datum
   $url   = "http://www.dukascopy.com/datafeed/$symbol/$dateD/BID_candles_min_1.bi5";

   // Existiert die Datei, hat sich der angegebene Startzeitpunkt geändert.
   $content = downloadUrl(null, $url);
   if (strLen($content)) {
      echoPre("[Notice]  $symbol history was extended. Please update the history's start time.");
      return false;
   }


   // (2) Dateien der gesamten Zeitspanne tageweise durchlaufen
   for ($day=$startTime; $day < $today; $day+=1*DAY) {                                 // der aktuelle Tag wird ignoriert (unvollständig)
      if (iDate('w', $day) == SATURDAY)                                                // Samstage werden ignoriert (keine Daten)
         continue;
      $yyyy       = date('Y', $day);
      $mmD        = strRight(iDate('m', $day)+ 99, 2);                                 // Dukascopy-Monat: Januar = 00
      $mmL        = strRight(iDate('m', $day)+100, 2);                                 // lokaler Monat:   Januar = 01
      $dd         = date('d', $day);
      $dateD      = "$yyyy/$mmD/$dd";                                                  // Dukascopy-Datum
      $dateL      = "$yyyy/$mmL/$dd";                                                  // lokales Datum

      // Bid-Preise
      $nameD      = "BID_candles_min_1";                                               // Dukascopy-Name
      $nameL      = "M1,Bid";                                                          // lokaler Name
      $url        = "http://www.dukascopy.com/datafeed/$symbol/$dateD/$nameD.bi5";
      $fileD_lzma = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameD.bi5";      // Dukascopy-Datei gepackt
      $fileD_bin  = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameD.bin";      // Dukascopy-Datei ungepackt
      $fileL_rar  = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameL.bin.rar";  // lokale Datei gepackt
      $fileL_bin  = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameL.bin";      // lokale Datei ungepackt
      $file404    = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameD.404";      // Dukascopy-Fehlerdatei (404)
      if (!processFiles($symbol, $day, 'bid', $url, $file404, $nameD, $fileD_lzma, $fileD_bin, $nameL, $fileL_rar, $fileL_bin))
         return false;
      continue;

      // Ask-Preise
      $nameD      = "ASK_candles_min_1";
      $nameL      = "M1,Ask";
      $url        = "http://www.dukascopy.com/datafeed/$symbol/$dateD/$nameD.bi5";
      $fileD_lzma = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameD.bi5";
      $fileD_bin  = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameD.bin";
      $fileL_rar  = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameL.bin.rar";
      $fileL_bin  = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameL.bin";
      $file404    = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameD.404";
      if (!processFiles($symbol, $day, 'ask', $url, $file404, $nameD, $fileD_lzma, $fileD_bin, $nameL, $fileL_rar, $fileL_bin))
         return false;
   }
   return true;
}


/**
 * Wickelt den Download und die Verarbeitung einer einzelnen Dukascopy-Historydatei ab.
 *
 * @param string $symbol     - Symbol
 * @param int    $day        - Timestamp des Tags der zu verarbeitenden Daten
 * @param string $type       - Kurstyp: 'bid'|'ask'
 * @param string $url        - URL
 * @param string $file404    - vollständiger Name der Datei, die einen Download-Fehler markiert (404)
 * @param string $nameD      - Dukascopy-Name
 * @param string $fileD_lzma - vollständiger Name, unter dem eine LZMA-gepackte Dukascopy-Datei gespeichert wird
 * @param string $fileD_bin  - vollständiger Name, unter dem eine entpackte Dukascopy-Datei gespeichert wird
 * @param string $nameL      - lokaler Name
 * @param string $fileL_rar  - vollständiger Name, unter dem eine RAR-gepackte lokale Kursdatei gespeichert wird
 * @param string $fileL_bin  - vollständiger Name, unter dem eine entpackte lokale Kursdatei gespeichert wird
 *
 * @return bool - Erfolgsstatus
 */
function processFiles($symbol, $day, $type, $url, $file404, $nameD, $fileD_lzma, $fileD_bin, $nameL, $fileL_rar, $fileL_bin) {
   $day      -= $day % DAY;                                          // 00:00 GMT
   $shortDate = date('D, d-M-Y', $day).' GMT';                       // Fri, 11-Jul-2003 GMT


   // TODO: DIESE Funktion in DIESEM Verzeichnis mit Combi-Lock synchronisieren: http://stackoverflow.com/questions/5449395/file-locking-in-php
   // TODO: temporäre Dateien löschen


   // Mögliche Varianten bereits existierender Dateien prüfen.

   // (1) falls .rar-Datei existiert
   if (is_file($fileL_rar)) {
      echoPre('[Ok]    '.$shortDate.'   MyFX compressed history file: '.baseName($fileL_rar));
   }


   // (2) falls lokale .bin-Datei existiert
   else if (is_file($fileL_bin)) {
      echoPre('[Info]  '.$shortDate.'   MyFX raw history file: '.baseName($fileL_bin));
   }


   // (3) falls dekomprimierte Dukascopy-Datei existiert
   else if (is_file($fileD_bin)) {
      echoPre('[Info]  '.$shortDate.'   Dukascopy raw history file: '.baseName($fileD_bin));
      if (!processRawDukascopyFile($fileD_bin, $day, $type, $nameD, $nameL, $fileL_rar, $fileL_bin))
         return false;
   }


   // (4) falls komprimierte Dukascopy-Datei existiert
   else if (is_file($fileD_lzma)) {
      echoPre('[Info]  '.$shortDate.'   Dukascopy compressed file: '.baseName($fileD_lzma));
      if (!processCompressedDukascopyFile($fileD_lzma, $day, $type, $nameD, $fileD_bin, $nameL, $fileL_rar, $fileL_bin))
         return false;
   }


   // (5) falls Fehlerdatei existiert
   else if (is_file($file404)) {
      echoPre('[Info]  '.$shortDate.'   Skipping '.$symbol.' (404 status file found)');
   }


   // (6) anderenfalls URL laden und verarbeiten
   else {
      $content = downloadUrl($day, $url, /*$fileD_lzma*/null, $file404);
      if ($content)
         if (!processCompressedDukascopyString($content, $day, $type, $nameD, $fileD_bin, $nameL, $fileL_rar, $fileL_bin))
            return false;
   }
   return true;
}


/**
 * Lädt eine URL und gibt ihren Inhalt zurück. Wird als zweiter Parameter ein Dateiname angegeben (während der Entwicklung),
 * wird der geladene Inhalt zusätzlich unter dem angegebenen Dateinamen gespeichert.
 *
 * @param string $url       - URL
 * @param string $saveAs    - vollständiger Name der Datei, in der der Inhalt gespeichert wwerden soll
 * @param string $saveError - vollständiger Name der Datei, die einen 404-Fehler beim Download markieren soll
 *
 * @return string - Content der heruntergeladenen Datei oder Leerstring, wenn die Resource nicht gefunden wurde (404).
 */
function downloadUrl($day, $url, $saveAs=null, $saveError=null) {
   if (!is_null($day) && !is_int($day))                throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   if (!is_string($url))                               throw new IllegalTypeException('Illegal type of parameter $url: '.getType($url));
   if (!is_null($saveAs) && !is_string($saveAs))       throw new IllegalTypeException('Illegal type of parameter $saveAs: '.getType($saveAs));
   if (!is_null($saveError) && !is_string($saveError)) throw new IllegalTypeException('Illegal type of parameter $saveError: '.getType($saveError));

   if (!is_null($day)) {
      $day      -= $day % DAY;                                          // 00:00 GMT
      $shortDate = date('D, d-M-Y', $day).' GMT';                       // Fri, 11-Jul-2003 GMT
      echoPre('[Info]  '.$shortDate.'   url: '.$url);
   }

   // (1) Standard-Browser simulieren
   $userAgent = Config ::get('myfx.useragent'); if (!$userAgent) throw new plInvalidArgumentException('Invalid user agent configuration: "'.$userAgent.'"');
   $request = HttpRequest ::create()
                          ->setUrl($url)
                          ->setHeader('User-Agent'     , $userAgent                                                       )
                          ->setHeader('Accept'         , 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                          ->setHeader('Accept-Language', 'en-us'                                                          )
                          ->setHeader('Accept-Charset' , 'ISO-8859-1,utf-8;q=0.7,*;q=0.7'                                 )
                          ->setHeader('Keep-Alive'     , '115'                                                            )
                          ->setHeader('Connection'     , 'keep-alive'                                                     )
                          ->setHeader('Cache-Control'  , 'max-age=0'                                                      )
                          ->setHeader('Referer'        , 'http://www.dukascopy.com/free/candelabrum/'                     );
   $options[CURLOPT_SSL_VERIFYPEER] = false;                         // falls HTTPS verwendet wird

   // (2) HTTP-Request abschicken und auswerten
   $response = CurlHttpClient ::create($options)->send($request);    // TODO: CURL-Fehler wie bei SimpleTrader behandeln
   $status   = $response->getStatus();
   if ($status!=200 && $status!=404) throw new plRuntimeException("Unexpected HTTP status $status (".HttpResponse ::$sc[$status].") for url \"$url\"\n".printFormatted($response, true));

   // (3) Download-Success
   if ($status == 200) {
      // vorhandene Fehlerdatei(en) löschen
      $errorFiles = array();
      if ($saveAs) {
         $errorFiles[] =         $saveAs                               .'.404';
         $errorFiles[] = dirName($saveAs).'/'.baseName($saveAs, '.bi5').'.404';
         $errorFiles[] = dirName($saveAs).'/'.baseName($url           ).'.404';
         $errorFiles[] = dirName($saveAs).'/'.baseName($url,    '.bi5').'.404';
      }
      if ($saveError) {
         $errorFiles[] =         $saveError;
         $errorFiles[] = dirName($saveError).'/'.baseName($url        ).'.404';
         $errorFiles[] = dirName($saveError).'/'.baseName($url, '.bi5').'.404';
      }
      foreach ($errorFiles as $file) {
         if (is_file($file)) unlink($file);
      }

      // bei Parameter $saveAs Content speichern
      if ($saveAs) {
         mkDirWritable(dirName($saveAs), 0700);
         $tmpFile = tempNam(dirName($saveAs), baseName($saveAs));
         $hFile   = fOpen($tmpFile, 'wb');
         fWrite($hFile, $response->getContent());
         fClose($hFile);
         if (is_file($saveAs)) unlink($saveAs);                      // So kann eine existierende Datei niemals korrupt sein.
         rename($tmpFile, $saveAs);
      }
   }

   // (4) Download-Fehler: bei Parameter $saveError Fehler speichern
   if ($status == 404) {
      if (!is_null($day)) {
         echoPre('[Error] '.$shortDate.'   url not found (404): '.$url);
      }
      if ($saveError) {
         mkDirWritable(dirName($saveError), 0700);
         fClose(fOpen($saveError, 'wb'));
      }
   }
   return ($status==200) ? $response->getContent() : '';
}


/**
 * @return bool - Erfolgsstatus
 */
function processCompressedDukascopyFile($file, $day, $type, $nameD, $fileD_bin, $nameL, $fileL_rar, $fileL_bin) {
   if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
   return processCompressedDukascopyString(file_get_contents($file), $day, $type, $nameD, $fileD_bin, $nameL, $fileL_rar, $fileL_bin);
}


/**
 * @return bool - Erfolgsstatus
 */
function processCompressedDukascopyString($string, $day, $type, $nameD, $fileD_bin, $nameL, $fileL_rar, $fileL_bin) {
   if (!is_string($string)) throw new IllegalTypeException('Illegal type of parameter $string: '.getType($string));
   if (!is_string($nameD))  throw new IllegalTypeException('Illegal type of parameter $nameD: '.getType($nameD));

   $rawString = Dukascopy ::decompressBars($string, $fileD_bin);
   echoPre('                               decompressed '.$nameD);
   return processRawDukascopyString($rawString, $day, $type, $nameD, $nameL, $fileL_rar, $fileL_bin);
}


/**
 * @return bool - Erfolgsstatus
 */
function processRawDukascopyFile($file, $day, $type, $nameD, $nameL, $fileL_rar, $fileL_bin) {
   if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
   return processRawDukascopyString(file_get_contents($file), $day, $type, $nameD, $nameL, $fileL_rar, $fileL_bin);
}


/**
 * @return bool - Erfolgsstatus
 */
function processRawDukascopyString($string, $day, $type, $nameD, $nameL, $fileL_rar, $fileL_bin) {
   if (!is_string($string))                  throw new IllegalTypeException('Illegal type of parameter $string: '.getType($string));
   if (!is_int($day))                        throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   if (!is_string($type))                    throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));
   global $barBuffer;
   if (!array_key_exists($type, $barBuffer)) throw new plInvalidArgumentException('Invalid parameter $type: "'.$type.'"');
   if (!is_string($nameD))                   throw new IllegalTypeException('Illegal type of parameter $nameD: '.getType($nameD));

   // Bars einlesen
   $bars = Dukascopy ::readBars($string);
   $size = sizeOf($bars); if ($size != 1*DAY/MINUTES) throw new plRuntimeException('Unexpected number of Dukascopy bars in '.$nameD.': '.$size.' ('.($size > 1*DAY/MINUTES ? 'more':'less').' then a day)');

   // Timestamps und FXT-Daten hinzufügen
   $dstChange = false;
   $prev = $next = null;                                             // Die Daten der Datei können einen DST-Wechsel abdecken, wenn
   $fxtOffset = MyFX ::getGmtToFxtTimeOffset($day, $prev, $next);    // $day = "Sun, 00:00 GMT" ist. In diesem Fall muß innerhalb
   foreach ($bars as $i => &$bar) {                                  // der Datenreihe auf den nächsten DST-Offset gewechselt werden.
      $bar['time_gmt' ] = $day + $bar['timeDelta'];
      $bar['delta_gmt'] =        $bar['timeDelta'];
      if ($bar['time_gmt'] >= $next['time']) {
         if ($fxtOffset != $next['offset']) {
            echoPre(NL.'DST change'.NL.NL);
            $dstChange = true;
         }
         $fxtOffset = $next['offset'];                               // $fxtOffset on-the-fly aktualisieren
      }
      $bar['time_fxt' ] = $bar['time_gmt'] - $fxtOffset;
      $bar['delta_fxt'] = $bar['time_fxt'] % DAY;
      unset($bar['timeDelta']);
   }
   if ($dstChange) {
      $newDayOffset = $size + $fxtOffset/MINUTES;
      echoPre('previous day ended with:');
      echoPre($bars[$newDayOffset-1]);
      echoPre('current day starts with:');
      echoPre($bars[$newDayOffset]);
   }

   // Index von 00:00 FXT bestimmen und Bars FXT-tageweise verarbeiten
   $newDayOffset = $size + $fxtOffset/MINUTES;
   if (!processFxtBarRange($type, array_slice($bars, 0, $newDayOffset), $nameD, $nameL, $fileL_rar, $fileL_bin)) return false;
   if (!processFxtBarRange($type, array_slice($bars, $newDayOffset   ), $nameD, $nameL, $fileL_rar, $fileL_bin)) return false;

   return true;
}


/**
 * @return bool - Erfolgsstatus
 */
function processFxtBarRange($type, array $bars, $nameD, $nameL, $fileL_rar, $fileL_bin) {
   if (!is_string($type))                    throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));
   global $barBuffer;
   if (!array_key_exists($type, $barBuffer)) throw new plInvalidArgumentException('Invalid parameter $type: "'.$type.'"');
   if (!$bars)                               throw new plInvalidArgumentException('Invalid parameter $bars: (empty)');
   if (!is_string($nameD))                   throw new IllegalTypeException('Illegal type of parameter $nameD: '.getType($nameD));

   // Bars dem jeweiligen FXT-Tag der internen Buffer hinzufügen
   $fxtDay = $bars[0]['time_fxt'] - $bars[0]['delta_fxt'];
   $dow    = iDate('w', $fxtDay);
   if ($dow==SATURDAY || $dow==SUNDAY)                               // FXT-Wochenenden überspringen
      return true;

   if (isSet($barBuffer[$type][$fxtDay])) {
      // Sicherstellen, daß die Daten zu mergender Bars nahtlos ineinander übergehen.
      $lastDelta = $barBuffer[$type][$fxtDay][sizeOf($barBuffer[$type][$fxtDay])-1]['delta_fxt'];
      $nextDelta = $bars[0]['delta_fxt'];
      if ($lastDelta + 1*MINUTE != $nextDelta) throw new plRuntimeException('Bar delta mis-match, bars to merge: "'.$nameD.'", $lastDelta='.$lastDelta.', $nextDelta='.$nextDelta);
      $barBuffer[$type][$fxtDay] = array_merge($barBuffer[$type][$fxtDay], $bars);
   }
   else {
      // Sicherstellen, daß vorherige Tage im Buffer verarbeitet und gelöscht wurden.
      if ($keys=array_keys($barBuffer[$type])) throw new plRuntimeException('Found unfinished bars of '.date('D, d-M-Y', $keys[0]).' while buffering bars of '.date('D, d-M-Y', $fxtDay));
      $barBuffer[$type][$fxtDay] = $bars;
   }

   // Prüfen, ob die aktuellen Daten des Tages abgeschlossen sind (ist der Fall, wenn sie bis Mitternacht reichen)
   $bars = &$barBuffer[$type][$fxtDay];
   $size = sizeOf($bars);

   if ($bars[$size-1]['delta_fxt'] == 23*HOURS + 59*MINUTES)         // abgeschlossenen Tag weiterverarbeiten
      if (!processFinishedFxtBars($type, $fxtDay, $nameL, $fileL_rar, $fileL_bin))
         return false;

   return true;
}


/**
 * @return bool - Erfolgsstatus
 */
function processFinishedFxtBars($type, $fxtDay, $nameL, $fileL_rar, $fileL_bin) {
   if (!is_string($type))                              throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));
   global $barBuffer;
   if (!array_key_exists($type, $barBuffer))           throw new plInvalidArgumentException('Invalid parameter $type: "'.$type.'"');
   if (!is_int($fxtDay))                               throw new IllegalTypeException('Illegal type of parameter $fxtDay: '.getType($fxtDay));
   if (!is_string($nameL))                             throw new IllegalTypeException('Illegal type of parameter $nameL: '.getType($nameL));
   if (!is_string($fileL_rar))                         throw new IllegalTypeException('Illegal type of parameter $fileL_rar: '.getType($fileL_rar));
   if (!is_null($fileL_bin) && !is_string($fileL_bin)) throw new IllegalTypeException('Illegal type of parameter $fileL_bin: '.getType($fileL_bin));


   // (1) Bars binär packen
   $bars         = &$barBuffer[$type][$fxtDay];
   $binaryString = null;

   foreach ($bars as $i => &$bar) {
      $binaryString .= pack('VVVVVV', $bar['time_fxt'],              // alle Felder als uint little-endian speichern
                                      $bar['open'    ],
                                      $bar['high'    ],
                                      $bar['low'     ],
                                      $bar['close'   ],
                                 (int)$bar['volume'  ]/100000);      // Units in Lots konvertieren
   }

   // (2) wenn Parameter $fileL_bin angegeben, binär gepackte Bars in Datei speichern
   if (!is_null($fileL_bin)) {
      mkDirWritable(dirName($fileL_bin));
      $tmpFile = tempNam(dirName($fileL_bin), baseName($fileL_bin));
      $hFile   = fOpen($tmpFile, 'wb');
      fWrite($hFile, $binaryString);
      fClose($hFile);
      if (is_file($fileL_bin)) unlink($fileL_bin);
      rename($tmpFile, $fileL_bin);                                  // So kann eine existierende Datei niemals korrupt sein.
   }

   // (3) binär gepackte Bars komprimieren und speichern

   // (4) Bars im Buffer löschen
   unset($barBuffer[$type][$fxtDay]);


   static $counter = 0; $counter++;
   if ($counter >= 10) {
      //showBuffer();
      exit();
   }
   return true;
}


/**
 *
 */
function showBuffer() {
   global $barBuffer;
   foreach ($barBuffer as $type => &$days) {
      if (!is_array($days)) {
         echoPre('barBuffer['.$type.'] => '.(is_null($days) ? 'null':$days));
         continue;
      }
      foreach ($days as $fxtDay => &$bars) {
         if (!is_array($bars)) {
            echoPre('barBuffer['.$type.']['.date('D, d-M-Y', $fxtDay).' FXT] => '.(is_null($bars) ? 'null':$bars));
            continue;
         }
         $size = sizeOf($bars);
         $firstBar = $size ? date('H:i', $bars[0      ]['time_fxt']):null;
         $lastBar  = $size ? date('H:i', $bars[$size-1]['time_fxt']):null;
         echoPre('barBuffer['.$type.']['.date('D, d-M-Y', $fxtDay).' FXT] => '.str_pad($size, 4, ' ', STR_PAD_LEFT).' bar'.($size==1?'':'s').($firstBar?': '.$firstBar:'').($size>1?'-'.$lastBar:'').($size?' FXT':''));
      }
   }
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (!is_null($message))
      echo($message."\n");

   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END

 Syntax:  $self [symbol ...]


END;
}
?>
