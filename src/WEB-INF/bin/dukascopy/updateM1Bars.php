#!/usr/bin/php
<?php
/**
 * Aktualisiert die vorhandenen Dukascopy-M1-Daten. Die Daten werden heruntergeladen, nach FXT konvertiert und in einem
 * eigenen RAR-komprimierten binären Format gespeichert.
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
 * Dateiformat:   binär, LZMA-gepackt, alle Zeiten in GMT (keine Sommerzeit),
 *                Handelspausen sind mit dem letzten Schlußkurs (OHLC) und V=0 (zero) angegeben.
 *
 *                @see Dukascopy::processBarFile()
 */
require(dirName(__FILE__).'/../../config.php');
date_default_timezone_set('GMT');


// History-Start der einzelnen Instrumente (geprüft am 21.06.2013)
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
$args = in_array('*', $args) ? array_keys($startTimes) : array_unique($args);    // '*' steht für und ersetzt alle Symbole


// (2) Buffer zum dateiübergreifenden Zwischenspeichern der geladenen Bardaten
$barBuffer['bid'] = array();
$barBuffer['ask'] = array();


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
   $content = downloadUrl($url);
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
      $nameL      = "Bid,M1";                                                          // lokaler Name
      $url        = "http://www.dukascopy.com/datafeed/$symbol/$dateD/$nameD.bi5";
      $fileD_lzma = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameD.bi5";      // Dukascopy-Datei gepackt
      $fileD_bin  = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameD.bin";      // Dukascopy-Datei ungepackt
      $fileL_rar  = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameL.bin.rar";  // lokale Datei gepackt
      $fileL_bin  = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameL.bin";      // lokale Datei ungepackt
      $file404    = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameD.404";      // Dukascopy-Fehlerdatei (404)
      if (!processFiles($symbol, $day, 'bid', $url, $file404, $nameD, $fileD_lzma, $fileD_bin, $nameL, $fileL_rar, $fileL_bin))
         return false;

      // Ask-Preise
      $nameD      = "ASK_candles_min_1";
      $nameL      = "Ask,M1";
      $url        = "http://www.dukascopy.com/datafeed/$symbol/$dateD/$nameD.bi5";
      $fileD_lzma = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameD.bi5";
      $fileD_bin  = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameD.bin";
      $fileL_rar  = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameL.bin.rar";
      $fileL_bin  = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameL.bin";
      $file404    = "$dataDirectory/history/dukascopy/$symbol/$dateL/$nameD.404";
      if (!processFiles($symbol, $day, 'ask', $url, $file404, $nameD, $fileD_lzma, $fileD_bin, $nameL, $fileL_rar, $fileL_bin))
         return false;

      //break;    // vorerst nach einem Durchlauf abbrechen
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
   $shortDate = date('D, d-M-Y', $day);                              // Fri, 11-Jul-2003
   global $barBuffer;


   // TODO: DIESE Funktion in DIESEM Verzeichnis mit Combi-Lock synchronisieren: http://stackoverflow.com/questions/5449395/file-locking-in-php
   // TODO: temporäre Dateien löschen


   // Mögliche Varianten bereits existierender Dateien prüfen.

   // (1) falls .rar-Datei existiert
   if (is_file($fileL_rar)) {
      echoPre("[Ok]    $shortDate   RAR history file: ".baseName($fileL_rar));
   }


   // (2) falls lokale .bin-Datei existiert
   else if (is_file($fileL_bin)) {
      echoPre("[Info]  $shortDate   raw history file: ".baseName($fileL_bin));
   }


   // (3) falls dekomprimierte Dukascopy-Datei existiert
   else if (is_file($fileD_bin)) {
      echoPre("[Info]  $shortDate   Dukascopy raw history file: ".baseName($fileD_bin));
      processRawDukascopyFile($fileD_bin, $day, $type, $nameD);
   }


   // (4) falls komprimierte Dukascopy-Datei existiert
   else if (is_file($fileD_lzma)) {
      echoPre("[Info]  $shortDate   Dukascopy compressed file: ".baseName($fileD_lzma));
      processCompressedDukascopyFile($fileD_lzma, $day, $type, $nameD, $fileD_bin);
   }


   // (5) falls Fehlerdatei existiert
   else if (is_file($file404)) {
      echoPre("[Info]  $shortDate   Skipping $symbol (404 status file found)");
   }


   // (6) anderenfalls URL laden und Content verarbeiten
   else {
      // Datei herunterladen
      $content = downloadUrl($url, $fileD_lzma, $file404);
      if (!strLen($content)) { echoPre("[Error] $shortDate   url not found (404): $url"); return true; }
                               echoPre("[Info]  $shortDate   url: $url");
      processCompressedDukascopyString($content, $day, $type, $nameD, $fileD_bin);
   }

   return true;
}


/**
 * Lädt eine URL und gibt ihren Inhalt zurück. Wird als zweiter Parameter ein Dateiname angegeben (während der Entwicklung),
 * wird der geladene Inhalt zusätzlich unter dem angegebenen Dateinamen gespeichert.
 *
 * @param string $url      - URL
 * @param string $contentFile - vollständiger Name der Datei, in der der Inhalt gespeichert wwerden soll
 * @param string $errorFile   - vollständiger Name der Datei, die einen 404-Fehler beim Download markieren soll
 *
 * @return string - Content der heruntergeladenen Datei oder Leerstring, wenn die Resource nicht gefunden wurde (404).
 */
function downloadUrl($url, $contentFile=null, $errorFile=null) {
   if (!is_string($url))                                   throw new IllegalTypeException('Illegal type of parameter $url: '.getType($url));
   if (!is_null($contentFile) && !is_string($contentFile)) throw new IllegalTypeException('Illegal type of parameter $contentFile: '.getType($contentFile));
   if (!is_null($errorFile  ) && !is_string($errorFile  )) throw new IllegalTypeException('Illegal type of parameter $errorFile: '.getType($errorFile));


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
      if ($contentFile) {
         $errorFiles[] =         $contentFile                                    .'.404';
         $errorFiles[] = dirName($contentFile).'/'.baseName($contentFile, '.bi5').'.404';
         $errorFiles[] = dirName($contentFile).'/'.baseName($url                ).'.404';
         $errorFiles[] = dirName($contentFile).'/'.baseName($url,         '.bi5').'.404';
      }
      if ($errorFile) {
         $errorFiles[] =         $errorFile;
         $errorFiles[] = dirName($errorFile).'/'.baseName($url        ).'.404';
         $errorFiles[] = dirName($errorFile).'/'.baseName($url, '.bi5').'.404';
      }
      foreach ($errorFiles as $file) {
         if (is_file($file)) unlink($file);
      }

      // bei Parameter $contentFile Content speichern
      if ($contentFile) {
         mkDirWritable(dirName($contentFile), 0700);
         $tmpFile = tempNam(dirName($contentFile), baseName($contentFile));
         $hFile   = fOpen($tmpFile, 'wb');
         fWrite($hFile, $response->getContent());
         fClose($hFile);
         if (is_file($contentFile)) unlink($contentFile);            // So kann eine existierende Datei niemals korrupt sein.
         rename($tmpFile, $contentFile);
      }
   }


   // (4) Download-Fehler: bei Parameter $errorFile Fehler speichern
   if ($status==404 && $errorFile) {
      mkDirWritable(dirName($errorFile), 0700);
      fClose(fOpen($errorFile, 'wb'));
   }

   return ($status==200) ? $response->getContent() : '';
}


/**
 *
 */
function processCompressedDukascopyFile($file, $day, $type, $nameD, $fileD_bin) {
   if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
   processCompressedDukascopyString(file_get_contents($file), $day, $type, $nameD, $fileD_bin);
}


/**
 *
 */
function processCompressedDukascopyString($string, $day, $type, $nameD, $fileD_bin) {
   if (!is_string($string)) throw new IllegalTypeException('Illegal type of parameter $string: '.getType($string));
   if (!is_string($nameD))  throw new IllegalTypeException('Illegal type of parameter $nameD: '.getType($nameD));

   $rawString = Dukascopy ::decompressBars($string, $fileD_bin);
   echoPre('                           decompressed: '.$nameD);
   processRawDukascopyString($rawString, $day, $type, $nameD);
}


/**
 *
 */
function processRawDukascopyFile($file, $day, $type, $nameD) {
   if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
   processRawDukascopyString(file_get_contents($file), $day, $type, $nameD);
}


/**
 *
 */
function processRawDukascopyString($string, $day, $type, $nameD) {
   if (!is_string($string))                  throw new IllegalTypeException('Illegal type of parameter $string: '.getType($string));
   if (!is_int($day))                        throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   if (!is_string($type))                    throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));
   global $barBuffer;
   if (!array_key_exists($type, $barBuffer)) throw new plInvalidArgumentException('Invalid parameter $type: "'.$type.'"');
   if (!is_string($nameD))                   throw new IllegalTypeException('Illegal type of parameter $nameD: '.getType($nameD));

   // Bars einlesen
   $bars = Dukascopy ::readBars($string);
   $size = sizeOf($bars); if ($size != 1*DAY/MINUTES) throw new plRuntimeException('Unexpected number of Dukascopy bars in '.$nameD.': '.$size.' ('.($size > 1*DAY/MINUTES ? 'more':'less').' then a day)');

   // Timestamps und Delta zu 00:00 FXT hinzufügen
   $fxtOffset = MyFX ::getGmtToFxtTimeOffset($day);                  // negativ, es gilt: FXT + Offset = GMT
   foreach ($bars as $i => &$bar) {
      $bar['time'     ] = $day + $bar['timeDelta'];
      $bar['delta_gmt'] =        $bar['timeDelta'];
      $bar['delta_fxt'] = ($bar['time'] - $fxtOffset) % DAY;
      unset($bar['timeDelta']);
   }

   $barBuffer[$type] = array_merge($barBuffer[$type], $bars);
   //echoPre('now '.sizeof($barBuffer[$type])." $type bars in buffer".NL.NL);



   //exit();

   // (3) Bars getrennt nach FXT-Tag/Bid/Ask in Buffern ablegen

   // (4) volle Buffer in Datei speichern, die Reste im Speicher behalten

   // (5) nächste Datei herunterladen und entpacken
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
