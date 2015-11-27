#!/usr/bin/php
<?php
/**
 * Konvertiert die MyFX-History ein oder mehrerer Verzeichnisse ins MetaTrader-Format und legt sie im aktuellen Verzeichnis ab.
 * Der letzte Pfadbestandteil eines angegebenen Verzeichnisses wird als Symbol des zu konvertierenden Instruments interpretiert.
 * Dieses Symbol wird zusätzlich in die Datei "symbols.raw" im aktuellen Verzeichnis eingetragen.
 */
require(dirName(realPath(__FILE__)).'/../../config.php');
date_default_timezone_set('GMT');


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$verbose = 0;                                                           // output verbosity


// History-Start der momentan verfügbaren Dukascopy-Instrumente
$startTimes = array('AUDUSD' => strToTime('2003-08-03 00:00:00 GMT'),
                    'EURUSD' => strToTime('2003-05-04 00:00:00 GMT'),
                    'GBPUSD' => strToTime('2003-05-04 00:00:00 GMT'),
                    'NZDUSD' => strToTime('2003-08-03 00:00:00 GMT'),
                    'USDCAD' => strToTime('2003-08-03 00:00:00 GMT'),
                    'USDCHF' => strToTime('2003-05-04 00:00:00 GMT'),
                    'USDJPY' => strToTime('2003-05-04 00:00:00 GMT'),
);


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenparameter auswerten
$args = array_slice($_SERVER['argv'], 1);
if (!$args) help() & exit(1);

// Optionen parsen
$looping = $fileSyncOnly = false;
foreach ($args as $i => $arg) {
   if (in_array($arg, array('-h','--help'))) help() & exit(1);          // Hilfe
   if ($arg == '-v'  ) { $verbose = 1; unset($args[$i]); continue; }    // verbose output
   if ($arg == '-vv' ) { $verbose = 2; unset($args[$i]); continue; }    // more verbose output
   if ($arg == '-vvv') { $verbose = 3; unset($args[$i]); continue; }    // very verbose output
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


// (2) Buffer zum Zwischenspeichern geladener Bardaten
$barBuffer = array();
$varCache  = array();


// (3) History erstellen
foreach ($args as $symbol) {
   if (!createHistory($symbol, 'bid'))
      exit(1);
}
exit(0);


// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


/**
 * Erzeugt die MetaTrader-History eines Symbol.
 *
 * @param string $symbol - Symbol
 * @param string $type   - Kurstyp
 *
 * @return bool - Erfolgsstatus
 */
function createHistory($symbol, $type) {
   if (!is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   if (!strLen($symbol))    throw new plInvalidArgumentException('Invalid parameter $symbol: ""');

   global $verbose, $startTimes, $barBuffer;
   $startDay = ($startDay=$startTimes[$symbol]) - $startDay%DAY;     // 00:00 Starttag
   $today    = ($today=time())                  - $today   %DAY;     // 00:00 aktueller Tag

   $barBuffer             = null;                                    // Barbuffer zurücksetzen
   $barBuffer[PERIOD_M1 ] = array();
   $barBuffer[PERIOD_M5 ] = array();
   $barBuffer[PERIOD_M15] = array();
   $barBuffer[PERIOD_M30] = array();
   $barBuffer[PERIOD_H1 ] = array();
   $barBuffer[PERIOD_H4 ] = array();
   $barBuffer[PERIOD_D1 ] = array();
   $barBuffer[PERIOD_W1 ] = array();
   $barBuffer[PERIOD_MN1] = array();


   // Gesamte Zeitspanne tageweise durchlaufen
   for ($day=$startDay; $day < $today; $day+=1*DAY) {

      // nur an Handelstagen vorhandene MyFX-History einlesen
      if (MyFX::isTradingDay($day)) {
         if      (is_file($file=getVar('myfxFile.compressed', $symbol, $day, $type))) {}  // wenn komprimierte MyFX-Datei existiert
         else if (is_file($file=getVar('myfxFile.raw'       , $symbol, $day, $type))) {}  // wenn unkomprimierte MyFX-Datei existiert
         else continue;

         $shortDate = date('D, d-M-Y', $day);
         if ($verbose > 0)
            echoPre('[Info]  '.$shortDate.'   MyFX history file: '.baseName($file));

         // Bars einlesen
         $bars = MyFX::readBarFile($file);
         $size = sizeOf($bars); if ($size != 1*DAY/MINUTES) throw new plRuntimeException('Unexpected number of MyFX bars in '.$file.': '.$size.' ('.($size > 1*DAY/MINUTES ? 'more':'less').' then a day)');

         // Bars im Barbuffer zwischenspeichern
         $barBuffer[PERIOD_M1] = array_merge($barBuffer[PERIOD_M1], $bars);

         foreach ($bars as $i => $bar) {
            $time = $bar['time'];
         }


         // jeden Timeframe ab bestimmter Baranzahl in MT4-Datei speichern
      }


      static $counter; $counter++;
      if ($counter > 10) {
         showBuffer();
         return false;
      }
   }




   // MT4-Historydateien anlegen und öffnen

   // Trigger für Timeframe-Wechsel jeder Bar einrichten


   return true;
}


/**
 * Erzeugt und verwaltet dynamisch generierte Variablen.
 *
 * Evaluiert und cacht ständig wiederbenutzte dynamische Variablen an einem zentralen Ort. Vereinfacht die Logik,
 * da die Variablen nicht global gespeichert oder über viele Funktionsaufrufe hinweg weitergereicht werden müssen,
 * aber trotzdem nicht bei jeder Verwendung neu ermittelt werden brauchen.
 *
 * @param string $id     - eindeutiger Schlüssel des Bezeichners (ID)
 * @param string $symbol - Symbol oder NULL
 * @param int    $time   - Timestamp oder NULL
 * @param string $type   - Kurstyp (bid|ask) oder NULL
 *
 * @return string - Variable
 */
function getVar($id, $symbol=null, $time=null, $type=null) {
   //global $varCache;
   static $varCache = array();
   if (array_key_exists(($key=$id.'|'.$symbol.'|'.$time.'|'.$type), $varCache))
      return $varCache[$key];

   if (!is_string($id))                          throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
   if (!is_null($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   if (!is_null($time) && !is_int($time))        throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));
   if (!is_null($type)) {
      if (!is_string($type))                     throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));
      if ($type!='bid' && $type!='ask')          throw new plInvalidArgumentException('Invalid parameter $type: "'.$type.'"');
   }

   $self = __FUNCTION__;

   if ($id == 'myfxName') {                  // M1,Bid                                                // lokaler Name
      if (!$type)   throw new plInvalidArgumentException('Invalid parameter $type: (null)');
      $result = 'M1,'.($type=='bid' ? 'Bid':'Ask');
   }
   else if ($id == 'myfxDirDate') {          // $yyyy/$mmL/$dd                                        // lokales Pfad-Datum
      if (!$time)   throw new plInvalidArgumentException('Invalid parameter $time: '.$time);
      $result = date('Y/m/d', $time);
   }
   else if ($id == 'myfxDir') {              // $dataDirectory/history/dukascopy/$symbol/$dateL       // lokales Verzeichnis
      if (!$symbol) throw new plInvalidArgumentException('Invalid parameter $symbol: '.$symbol);
      static $dataDirectory; if (!$dataDirectory)
      $dataDirectory = MyFX::getConfigPath('myfx.data_directory');
      $dateL         = $self('myfxDirDate', null, $time, null);
      $result        = "$dataDirectory/history/dukascopy/$symbol/$dateL";
   }
   else if ($id == 'myfxFile.raw') {         // $myfxDir/$nameL.bin                                   // lokale Datei ungepackt
      $myfxDir = $self('myfxDir' , $symbol, $time, null);
      $nameL   = $self('myfxName', null, null, $type);
      $result  = "$myfxDir/$nameL.bin";
   }
   else if ($id == 'myfxFile.compressed') {  // $myfxDir/$nameL.rar                                   // lokale Datei gepackt
      $myfxDir = $self('myfxDir' , $symbol, $time, null);
      $nameL   = $self('myfxName', null, null, $type);
      $result  = "$myfxDir/$nameL.rar";
   }
   else {
     throw new plInvalidArgumentException('Unknown parameter $id: "'.$id.'"');
   }

   $varCache[$key] = $result;
   (sizeof($varCache) > ($maxSize=64)) && array_shift($varCache) /*&& echoPre('cache size limit of '.$maxSize.' hit')*/;

   return $result;
}


/**
 *
 */
function showBuffer() {
   global $barBuffer;

   echoPre(NL);
   foreach ($barBuffer as $timeframe => &$bars) {
      $size = sizeOf($bars);
      $firstBar = $size ? date('d-M-Y H:i', $bars[0      ]['time']):null;
      $lastBar  = $size ? date('d-M-Y H:i', $bars[$size-1]['time']):null;
      echoPre('barBuffer['. str_pad(MyFX::timeframeToStr($timeframe), 10, ' ', STR_PAD_RIGHT).'] => '.str_pad($size, 5, ' ', STR_PAD_LEFT).' bar'.($size==1?'':'s').($firstBar?'  from='.$firstBar:'').($size>1?'  to='.$lastBar:''));
   }
   echoPre(NL);
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (!is_null($message))
      echo($message."\n\n");

   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END

 Syntax:  $self [symbol ...]


END;
}
?>
