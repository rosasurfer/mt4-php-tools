#!/usr/bin/php
<?php
/**
 * Aktualisiert die vorhandenen Dukascopy-Tickdaten.
 * Die Daten der aktuellen Stunde sind frühestens ab der nächsten Stunde verfügbar.
 *
 * http://www.dukascopy.com/datafeed/EURUSD/2013/05/10/07h_ticks.bi5
 */
require(dirName(__FILE__).'/../../config.php');
date_default_timezone_set('GMT');


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$verbose = 0;                                                        // output verbosity


// History-Start der einzelnen Instrumente bei Dukascopy
$data = array('AUDJPY' => strToTime('2007-03-30 16:01:15 GMT'),      // Zeitzone der Daten ist GMT (keine Sommerzeit)
              'AUDNZD' => strToTime('2008-12-22 16:16:02 GMT'),
              'AUDUSD' => strToTime('2007-03-30 16:01:16 GMT'),
              'CADJPY' => strToTime('2007-03-30 16:01:16 GMT'),
              'CHFJPY' => strToTime('2007-03-30 16:01:15 GMT'),
              'EURAUD' => strToTime('2007-03-30 16:01:19 GMT'),
              'EURCAD' => strToTime('2008-09-23 11:32:09 GMT'),
              'EURCHF' => strToTime('2007-03-30 16:01:15 GMT'),
              'EURGBP' => strToTime('2007-03-30 16:01:17 GMT'),
              'EURJPY' => strToTime('2007-03-30 16:01:16 GMT'),
              'EURNOK' => strToTime('2007-03-30 16:01:19 GMT'),
              'EURSEK' => strToTime('2007-03-30 16:01:31 GMT'),
              'EURUSD' => strToTime('2007-03-30 16:01:15 GMT'),
              'GBPCHF' => strToTime('2007-03-30 16:01:15 GMT'),
              'GBPJPY' => strToTime('2007-03-30 16:01:15 GMT'),
              'GBPUSD' => strToTime('2007-03-30 16:01:15 GMT'),
              'NZDUSD' => strToTime('2007-03-30 16:01:53 GMT'),
              'USDCAD' => strToTime('2007-03-30 16:01:16 GMT'),
              'USDCHF' => strToTime('2007-03-30 16:01:15 GMT'),
              'USDJPY' => strToTime('2007-03-30 16:01:15 GMT'),
              'USDNOK' => strToTime('2008-09-28 22:04:55 GMT'),
              'USDSEK' => strToTime('2008-09-28 23:30:31 GMT')
);


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);
if (!$args) help() & exit(1);

// Optionen parsen
$looping = $fileSyncOnly = false;
foreach ($args as $i => $arg) {
   if (in_array($arg, array('-h','--help'))) help() & exit(1);                   // Hilfe
   if ($arg == '-v'  ) { $verbose = 1; unset($args[$i]); continue; }             // verbose output
   if ($arg == '-vv' ) { $verbose = 2; unset($args[$i]); continue; }             // more verbose output
   if ($arg == '-vvv') { $verbose = 3; unset($args[$i]); continue; }             // very verbose output
}

// Symbole parsen
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



// Downloadverzeichnis bestimmen
$downloadDirectory = MyFX ::getConfigPath('history.dukascopy');


$thisHour  = time();
$thisHour -= $thisHour % HOUR;

foreach ($data as $symbol => $start) {
   $start -= $start % HOUR;

   for ($time=$start; $time < $thisHour; $time+=HOUR) {              // Daten der aktuellen Stunde können noch nicht existieren
      date_default_timezone_set('America/New_York');
      $dow = date('w', $time + 7*HOURS);
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
         echoPre("[Info]: Skipping url \"$url\", local file already exists.");
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
         echoPre("[Info]: $status - File not found: \"$url\"");
         fClose(fOpen($localFile.'.404', 'x'));
      }
   }
}
exit(0);


// -- Ende -----------------------------------------------------------------------------------------------------------------------------------------


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

 Options:  -v    Verbose output.
           -vv   More verbose output.
           -vvv  Very verbose output.
           -h    This help screen.


END;
}
