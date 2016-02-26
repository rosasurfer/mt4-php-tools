#!/usr/bin/php
<?php
/**
 * Aktualisiert die lokal vorhandenen Dukascopy-Tickdaten. Die Daten werden nach FXT konvertiert und im MyFX-Format
 * gespeichert. Die Dukascopy-Daten sind am Wochenende und können an Feiertagen leer sein, in beiden Fällen werden
 * sie lokal nicht gespeichert. Die Daten der aktuellen Stunde sind frühestens ab der nächsten Stunde verfügbar.
 *
 *
 * Webseite:      http://www.dukascopy.com/swiss/english/marketwatch/historical/
 *                http://www.dukascopy.com/free/candelabrum/
 *
 * Instrumente:   http://www.dukascopy.com/free/candelabrum/data.json
 *
 * History-Start: http://www.dukascopy.com/datafeed/metadata/HistoryStart.bi5  (Format unbekannt)
 *
 * URL-Format:    Eine Datei je Tagestunde,
 *                z.B.: (Januar = 00)
 *                • http://www.dukascopy.com/datafeed/EURUSD/2013/00/02/10h_ticks.bi5 - Ticks vom 02.01.2013 10:00:00-10:59:59
 *                • http://www.dukascopy.com/datafeed/EURUSD/2013/05/10/00h_ticks.bi5 - Ticks vom 10.06.2013 00:00:00-00:59:59
 *
 * Dateiformat:   Binär, LZMA-gepackt, Zeiten in GMT (keine Sommerzeit).
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


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$verbose = 0;                                   // output verbosity

$saveCompressedDukascopyFiles = false;          // ob heruntergeladene Dukascopy-Dateien zwischengespeichert werden sollen
$saveRawDukascopyFiles        = false;          // ob entpackte Dukascopy-Dateien zwischengespeichert werden sollen
$saveRawMyFXData              = true;           // ob unkomprimierte MyFX-Historydaten gespeichert werden sollen


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


$result = MyFX::isForexWeekend(strToTime('2016-02-21 23:00:00 GMT'), 'FXT');

echoPre('isForexWeekend = '.(int)$result);


exit();



// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);

// Optionen parsen
foreach ($args as $i => $arg) {
   if ($arg == '-h'  )   help() & exit(1);                                          // Hilfe
   if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; } // verbose output
   if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; } // more verbose output
   if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; } // very verbose output
}

// Symbole parsen
foreach ($args as $i => $arg) {
   $arg = strToUpper($arg);
   if (!isSet(Dukascopy::$historyStart_Ticks[$arg])) help('error: unknown or unsupported symbol "'.$args[$i].'"') & exit(1);
   $args[$i] = $arg;
}
$args = $args ? array_unique($args) : array_keys(Dukascopy::$historyStart_Ticks);   // ohne Angabe werden alle Symbole aktualisiert


// (2) Daten aktualisieren
foreach ($args as $symbol) {
   if (!updateSymbol($symbol, Dukascopy::$historyStart_Ticks[$symbol]))
      exit(1);
}
exit(0);


// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


/**
 * Aktualisiert die Tickdaten eines Symbol.
 *
 * Eine Dukascopy-Datei enthält eine Stunde Tickdaten. Die Daten der aktuellen Stunde sind frühestens
 * ab der nächsten Stunde verfügbar.
 *
 * @param  string $symbol    - Symbol
 * @param  int    $startTime - Beginn der Tickdaten dieses Symbols
 *
 * @return bool - Erfolgsstatus
 */
function updateSymbol($symbol, $startTimeGMT) {
   if (!is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   $symbol = strToUpper($symbol);
   if (!is_int($startTimeGMT)) throw new IllegalTypeException('Illegal type of parameter $startTimeGMT: '.getType($startTimeGMT));
   $startTimeGMT -= $startTimeGMT % HOUR;                            // Stundenbeginn GMT

   global $verbose;
   echoPre('[Info]    '.$symbol);

   // Gesamte Zeitspanne inklusive Wochenenden stundenweise durchlaufen, um von vorherigen Durchlaufen ggf. vorhandene
   // Zwischendateien finden und löschen zu können.
   static $lastMonth=-1;
   $thisHour = ($thisHour=time()) - $thisHour%HOUR;                  // Beginn der aktuellen Stunde GMT
   $lastHour = $thisHour - 1*HOUR;                                   // Beginn der letzten Stunde GMT

   for ($hour=$startTimeGMT; $hour < $lastHour; $hour+=1*HOUR) {
      $month = iDate('m', $hour);
      if ($month != $lastMonth) {
         if ($verbose > 0) echoPre('[Info]    '.date('M-Y', $hour));
         $lastMonth = $month;
      }
      if (!checkHistory($symbol, $hour)) return false;
   }
   echoPre('[Ok]      '.$symbol);

   return true;
}








// Downloadverzeichnis bestimmen
$downloadDirectory = MyFX ::getConfigPath('history.dukascopy');


$thisHour  = time();
$thisHour -= $thisHour % HOUR;

foreach ($data as $symbol => $start) {
   $start -= $start % HOUR;

   for ($time=$start; $time < $thisHour; $time+=HOUR) {              // Daten der aktuellen Stunde können noch nicht existieren
      date_default_timezone_set('America/New_York');
      $dow = (int) date('w', $time + 7*HOURS);
      if ($dow==SATURDAY || $dow==SUNDAY)                            // Wochenenden überspringen, Sessionbeginn/-ende ist America/New_York+0700
         continue;

      // URL zusammenstellen
      date_default_timezone_set('GMT');
      $year  = date('Y', $time);
      $month = subStr(date('n', $time)+99, 1);                       // Januar = 00
      $day   = date('d', $time);
      $hour  = date('H', $time);
      $path  = "$symbol/$year/$month/$day";
      $file  = "{$hour}h_ticks.bin";
      $url   = "http://www.dukascopy.com/datafeed/$path/$file";

      // lokale Datei bestimmen und bereits heruntergeladene Dateien überspringen
      $localPath = $downloadDirectory.PATH_SEPARATOR.$path;
      $localFile = $localPath.PATH_SEPARATOR.$file;
      if (is_file($localFile) || is_file($localFile.'.404')) {       // Datei, für die 404 zurückgegeben wurde
         echoPre("[Info]    Skipping url \"$url\", local file already exists.");
         continue;
      }

      // HTTP-Request abschicken und auswerten
      $request  = HttpRequest ::create()->setUrl($url);
      $response = CurlHttpClient ::create()->send($request);
      $status   = $response->getStatus();
      if ($status!=200 && $status!=404) throw new plRuntimeException("Unexpected HTTP status $status (".HttpResponse ::$sc[$status].") for url \"$url\"\n".printFormatted($response, true));

      // Datei speichern ...
      mkDirWritable($localPath, 0700);

      if ($status == 200) {
         echoPre("[Ok]: $url");
         $hFile = fOpen($localFile, 'xb');
         fWrite($hFile, $response->getContent());
         fClose($hFile);
      }
      else {   // ... oder 404-Status mit leerer .404-Datei merken
         echoPre("[Info]    $status - File not found: \"$url\"");
         fClose(fOpen($localFile.'.404', 'x'));
      }
   }
}
exit(0);


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (!is_null($message))
      echo($message.NL.NL);

   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END

 Syntax:  $self [symbol ...]

 Options:  -v    Verbose output.
           -vv   More verbose output.
           -vvv  Very verbose output.
           -h    This help screen.


END;
}
