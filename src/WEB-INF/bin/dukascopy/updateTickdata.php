#!/usr/bin/php -Cq
<?
/**
 * Aktualisiert die vorhandenen Dukascopy-Tickdaten.
 *
 */
require(dirName(__FILE__).'/../../config.php');


// Beginn der Tickdaten der einzelnen Pairs
$data = array('AUDJPY' => strToTime('2007-03-30 16:01:15 GMT'),      // Zeitzone der Daten ist GMT+0000 (keine Sommerzeit)
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
              'USDSEK' => strToTime('2008-09-28 23:30:31 GMT'));


// Downloadverzeichnis bestimmen
$downloadDirectory = MyFXHelper ::getAbsoluteConfigPath('history.dukascopy');


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

      // ggf. Zielverzeichnis anlegen
      if (is_file($localPath))                                   throw new plInvalidArgumentException('Cannot write to file "'.$localPath.'" (not a directory)');
      if (!is_dir($localPath) && !mkDir($localPath, 0700, true)) throw new plInvalidArgumentException('Cannot create directory "'.$localPath.'"');
      if (!is_writable($localPath))                              throw new plInvalidArgumentException('Cannot write to directory "'.$localPath.'"');

      // Datei speichern ...
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
?>
