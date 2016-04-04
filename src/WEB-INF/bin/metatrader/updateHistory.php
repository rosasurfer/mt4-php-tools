#!/usr/bin/php
<?php
/**
 * Aktualisiert MT4-History ein oder mehrerer Instrumente.
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
   if ($arg == '-h'  )   exit(1|help());                                            // Hilfe
   if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; } // verbose output
   if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; } // more verbose output
   if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; } // very verbose output
}

// Symbole parsen
foreach ($args as $i => $arg) {
   $arg = strToUpper($arg);
   if (!isSet(MyFX::$symbols[$arg])) exit(1|help('error: unknown or unsupported symbol "'.$args[$i].'"'));
   $args[$i] = $arg;
}
$args = $args ? array_unique($args) : array_keys(MyFX::$symbols);                   // ohne Symbol werden alle Instrumente verarbeitet


// (2) History aktualisieren
foreach ($args as $symbol) {
   !updateHistory($symbol) && exit(1);
   break;
}
exit(0);


// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


/**
 * Aktualisiert die MT4-History eines Instruments.
 *
 * @param  string $symbol - Symbol
 *
 * @return bool - Erfolgsstatus
 */
function updateHistory($symbol) {
   if (!is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   if (!strLen($symbol))    throw new plInvalidArgumentException('Invalid parameter $symbol: ""');

   global $verbose;
   $directory = MyFX::getConfigPath('myfx.data_directory').'/history/mt4/MyFX-Dukascopy';

   // Zeitrange für komplette Aktualisierung
   $startDay  = fxtTime(MyFX::$symbols[$symbol]['historyStart']['M1']);
   $startDay -= $startDay%DAY;                                                                     // 00:00 FXT des History-Starts

   $today     = ($today=fxtTime()) - $today%DAY;                                                   // 00:00 FXT des aktuellen Tages
   $today     = ($today=fxtTime(strToTime('2003-09-27 05:00:00 GMT'))) - $today%DAY;

   $lastMonth = -1;


   // (1.1) versuchen, ein vorhandenes HistorySet zu öffnen
   if ($history=HistorySet::get($symbol, $directory)) {
      $lastSyncTime = $history->getLastSyncTime();
      if ($lastSyncTime) {
         // (1.2) den letzten aktualisierten Tag nochmals aktualisieren
         $startDay  = $lastSyncTime - $lastSyncTime%DAY;                                           // 00:00 des zuletzt aktualisierten Tages
         $shortDate = gmDate('D, d-M-Y', $startDay);

         if (!MyFX::isForexWeekend($startDay, 'FXT')) {                                            // außer an Wochenenden
            if      (is_file($file=MyFX::getVar('myfxFile.M1.compressed', $symbol, $startDay))) {} // wenn komprimierte MyFX-Datei existiert
            else if (is_file($file=MyFX::getVar('myfxFile.M1.raw'       , $symbol, $startDay))) {} // wenn unkomprimierte MyFX-Datei existiert
            else {
               echoPre('[Error]   '.$symbol.' MyFX history for '.$shortDate.' not found');
               return false;
            }
            echoPre('[Info]    '.gmDate('M-Y', $startDay));
            $lastMonth = (int) gmDate('m', $startDay);

            // Bars einlesen und MT4-History aktualisieren
            $bars = MyFX::readBarFile($file, $symbol);

            if ($verbose > 0) echoPre('[Info]    updating existing history: '.$shortDate);
            $history->update($bars);
         }
         $startDay += 1*DAY;
      }
      else {
         // (1.3) $lastSyncTime=0: HistorySet verwerfen und komplett neuschreiben lassen
         if ($verbose > 0) echoPre('[Info]    disposing existing history ($lastSyncTime=0)');
         $history->dispose();
         $history = null;
      }
   }


   // (2) ggf. neues HistorySet erzeugen
   if (!$history) {
      if ($verbose > 0) echoPre('[Info]    creating new history');
      $digits  = MyFX::$symbols[$symbol]['digits'];
      $history = HistorySet::create($symbol, $digits, $format=400, $directory);                    // neue Sets im Format 400 erstellen
   }


   // (3) alle weiteren verfügbaren Daten regulär zur History hinzufügen
   for ($day=$startDay; $day < $today; $day+=1*DAY) {
      $shortDate = gmDate('D, d-M-Y', $day);
      $month     = (int) gmDate('m', $day);
      if ($month != $lastMonth) {
         echoPre('[Info]    '.gmDate('M-Y', $day));
         $lastMonth = $month;
      }

      if (!MyFX::isForexWeekend($day, 'FXT')) {                                                    // außer an Wochenenden
         if      (is_file($file=MyFX::getVar('myfxFile.M1.compressed', $symbol, $day))) {}         // wenn komprimierte MyFX-Datei existiert
         else if (is_file($file=MyFX::getVar('myfxFile.M1.raw'       , $symbol, $day))) {}         // wenn unkomprimierte MyFX-Datei existiert
         else {
            echoPre('[Error]   '.$symbol.' MyFX history for '.$shortDate.' not found');
            return false;
         }
         // Bars einlesen und der MT4-History hinzufügen
         $bars = MyFX::readBarFile($file, $symbol);
         $history->addM1Bars($bars);
      }
   }
   $history->close();

   echoPre('[Ok]      '.$symbol);
   return true;
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (is_null($message))
      $message = 'Updates the MetaTrader history of the specified symbols.';
   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END
$message

  Syntax:  $self [symbol ...] [OPTIONS]

  Options:  -h   This help screen.


END;
}
