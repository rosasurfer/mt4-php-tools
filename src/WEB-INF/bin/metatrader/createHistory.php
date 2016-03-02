#!/usr/bin/php
<?php
/**
 * Konvertiert die M1-History ein oder mehrerer Dukascopy-Instrumente ins MetaTrader-Format und legt sie im
 * Historyverzeichnis "mt4/MyFX-Dukascopy" ab.
 */
require(dirName(realPath(__FILE__)).'/../../config.php');
date_default_timezone_set('GMT');


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$verbose = 0;                                                                       // output verbosity


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


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
   if (!isSet(Dukascopy::$historyStart_M1[$arg])) help('error: unknown or unsupported symbol "'.$args[$i].'"') & exit(1);
   $args[$i] = $arg;
}
$args = $args ? array_unique($args) : array_keys(Dukascopy::$historyStart_M1);      // ohne Symbol werden alle Symbole verarbeitet


// (2) History erstellen
foreach ($args as $symbol) {
   if (!createHistory($symbol))
      exit(1);
}
exit(0);


// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


/**
 * Erzeugt die MetaTrader-History eines Symbol.
 *
 * @param  string $symbol - Symbol
 *
 * @return bool - Erfolgsstatus
 */
function createHistory($symbol) {
   if (!is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   if (!strLen($symbol))    throw new plInvalidArgumentException('Invalid parameter $symbol: ""');

   global $verbose;
   $startDay  = fxtTime(Dukascopy::$historyStart_M1[$symbol]);                      // FXT
   $startDay -= $startDay%DAY;                                                      // 00:00 FXT Starttag
   $today     = ($today=fxtTime()) - $today%DAY;                                    // 00:00 FXT aktueller Tag


   // MT4-HistorySet erzeugen
   $digits  = (strEndsWith($symbol, 'JPY') || array_search($symbol, array('USDX', 'EURX'))!==false) ? 3:5;
   $history = new HistorySet($symbol, $description=null, $digits, $format=400);


   // Gesamte Zeitspanne tageweise durchlaufen
   for ($day=$startDay, $lastMonth=-1; $day < $today; $day+=1*DAY) {
      $month = (int) gmDate('m', $day);
      if ($month != $lastMonth) {
         if ($verbose > 0) echoPre('[Info]    '.gmDate('M-Y', $day));
         $lastMonth = $month;
      }

      // außer an Wochenenden: MyFX-History verarbeiten
      if (!MyFX::isForexWeekend($day, 'FXT')) {
         if      (is_file($file=getVar('myfxFile.compressed', $symbol, $day))) {}   // wenn komprimierte MyFX-Datei existiert
         else if (is_file($file=getVar('myfxFile.raw'       , $symbol, $day))) {}   // wenn unkomprimierte MyFX-Datei existiert
         else continue;
         if ($verbose > 1) echoPre('[Info]    '.gmDate('D, d-M-Y', $day).'   MyFX history file: '.baseName($file));

         // Bars einlesen und der MT4-History hinzufügen
         $bars = MyFX::readBarFile($file);
         $history->addM1Bars($bars);
      }
   }
   echoPre('[Ok]    '.$symbol);
   return true;
}


/**
 * Erzeugt und verwaltet dynamisch generierte Variablen.
 *
 * Evaluiert und cacht ständig wiederbenutzte dynamische Variablen an einem zentralen Ort. Vereinfacht die Logik,
 * da die Variablen nicht global gespeichert oder über viele Funktionsaufrufe hinweg weitergereicht werden müssen,
 * aber trotzdem nicht bei jeder Verwendung neu ermittelt werden brauchen.
 *
 * @param  string $id     - eindeutiger Bezeichner der Variable (ID)
 * @param  string $symbol - Symbol oder NULL
 * @param  int    $time   - Timestamp oder NULL
 *
 * @return string - Variable
 */
function getVar($id, $symbol=null, $time=null) {
   //global $varCache;
   static $varCache = array();
   if (array_key_exists(($key=$id.'|'.$symbol.'|'.$time), $varCache))
      return $varCache[$key];

   if (!is_string($id))                          throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
   if (!is_null($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   if (!is_null($time) && !is_int($time))        throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

   $self = __FUNCTION__;

   if ($id == 'myfxDirDate') {               // $yyyy/$mm/$dd                                            // lokales Pfad-Datum
      if (!$time)   throw new plInvalidArgumentException('Invalid parameter $time: '.$time);
      $result = gmDate('Y/m/d', $time);
   }
   else if ($id == 'myfxDir') {              // $dataDirectory/history/dukascopy/$symbol/$myfxDirDate    // lokales Verzeichnis
      if (!$symbol) throw new plInvalidArgumentException('Invalid parameter $symbol: '.$symbol);
      static $dataDirectory; if (!$dataDirectory)
      $dataDirectory = MyFX::getConfigPath('myfx.data_directory');
      $myfxDirDate   = $self('myfxDirDate', null, $time);
      $result        = "$dataDirectory/history/dukascopy/$symbol/$myfxDirDate";
   }
   else if ($id == 'myfxFile.raw') {         // $myfxDir/M1.myfx                                         // lokale Datei ungepackt
      $myfxDir = $self('myfxDir' , $symbol, $time);
      $result  = "$myfxDir/M1.myfx";
   }
   else if ($id == 'myfxFile.compressed') {  // $myfxDir/M1.rar                                          // lokale Datei gepackt
      $myfxDir = $self('myfxDir' , $symbol, $time);
      $result  = "$myfxDir/M1.rar";
   }
   else {
     throw new plInvalidArgumentException('Unknown parameter $id: "'.$id.'"');
   }

   $varCache[$key] = $result;
   (sizeof($varCache) > ($maxSize=128)) && array_shift($varCache) /*&& echoPre('cache size limit of '.$maxSize.' hit')*/;

   return $result;
}


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


END;
}
