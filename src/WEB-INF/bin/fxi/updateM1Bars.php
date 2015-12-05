#!/usr/bin/php
<?php
/**
 * Aktualisiert die MyFX-M1-History der angegebenen FX-Indizes. Nach Möglichkeit werden zur Berechnung vorhandene Tickdaten benutzt.
 *
 * Unterstützte Instrumente:
 *
 * ok • LFX-Indizes:    LiteForex-Formel (JPYLFX nicht gespiegelt, normalisiert)
 * ok • USDX:           ICE-Formel
 *
 *    • EURX:           ICE-Formel
 *    • FX6-Indizes:    AUDFX6, CADFX6, CHFFX6, EURFX6, GBPFX6, JPYFX6,         USDFX6 (geometrisches Mittel, JPYFX6 normalisiert)
 *    • FX7-Indizes:    AUDFX7, CADFX7, CHFFX7, EURFX7, GBPFX7, JPYFX7, NZDFX7, USDFX7 (geometrisches Mittel, JPYFX7 normalisiert)
 *    • SEKFX6, SEKFX7: SEK gegen USDFX6 bzw. USDFX7
 *    • NOKFX6, NOKFX7: NOK gegen USDFX6 bzw. USDFX7
 *
 *
 * Note: Zur Zeit wird als Datenquelle ausschließlich Dukascopy benutzt.
 */
require(dirName(realPath(__FILE__)).'/../../config.php');
date_default_timezone_set('GMT');


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$verbose         = 0;                                 // output verbosity
$saveRawMyFXData = true;                              // ob unkomprimierte MyFX-Historydaten gespeichert werden sollen


// Indizes und die zu ihrer Berechnung benötigten Instrumente
$indizes['AUDLFX'] = array('AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY');
$indizes['CADLFX'] = array('AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY');
$indizes['CHFLFX'] = array('AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY');
$indizes['EURLFX'] = array('AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY');
$indizes['GBPLFX'] = array('AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY');
$indizes['JPYLFX'] = array('AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY');
$indizes['NZDLFX'] = array('AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'NZDUSD');
$indizes['USDLFX'] = array('AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY');

$indizes['EURX'  ] = array('EURUSD', 'GBPUSD', 'USDCHF', 'USDJPY', 'USDSEK');
$indizes['USDX'  ] = array('EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDSEK');


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);

// Optionen parsen
foreach ($args as $i => $arg) {
   if ($arg == '-h'  )   help() & exit(1);                              // Hilfe
   if ($arg == '-v'  ) { $verbose = 1; unset($args[$i]); continue; }    // verbose output
   if ($arg == '-vv' ) { $verbose = 2; unset($args[$i]); continue; }    // more verbose output
   if ($arg == '-vvv') { $verbose = 3; unset($args[$i]); continue; }    // very verbose output
}
if (!$args) help() & exit(1);

// Symbole parsen
foreach ($args as $i => $arg) {
   $arg = strToUpper($arg);
   if (!array_key_exists($arg, $indizes)) help('error: unsupported symbol "'.$args[$i].'"') & exit(1);
   $args[$i] = $arg;
}
$args = array_unique($args);


// (2) Index berechnen
foreach ($args as $index) {
   if (!createIndex($index))
      exit(1);
}
exit(0);


// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


/**
 * Berechnet die M1-History eines Indexes und speichert sie im MyFX-Format.
 *
 * @param  string $index - Symbol des Index
 *
 * @return bool - Erfolgsstatus
 */
function createIndex($index) {
   if (!is_string($index)) throw new IllegalTypeException('Illegal type of parameter $index: '.getType($index));
   if (!strLen($index))    throw new plInvalidArgumentException('Invalid parameter $index: ""');

   global $verbose, $indizes;

   // (1) Starttag der benötigten Daten ermitteln
   $startTime = 0;
   $symbols   = array_flip($indizes[$index]);
   foreach($symbols as $symbol => &$data) {
      $data      = array();                                                            // $data on-the-fly initialisieren
      $startTime = max($startTime, Dukascopy::$historyStart_M1[$symbol]);
   }
   $startDay = $startTime     - $startTime%DAY;                                        // 00:00 Starttag
   $today    = ($today=time())- $today    %DAY;                                        // 00:00 aktueller Tag


   // (2) Gesamte Zeitspanne tageweise durchlaufen
   for ($day=$startDay, $lastMonth=-1; $day < $today; $day+=1*DAY) {
      $month = iDate('m', $day);
      if ($month != $lastMonth) {
         if ($verbose > 0) echoPre('[Info]    '.date('M-Y', $day));
         $lastMonth = $month;
      }

      if (!MyFX::isWeekend($day)) {                                                    // außer an Wochenenden
         // History der beteiligten Symbole einlesen
         foreach($symbols as $symbol => $data) {
            if      (is_file($file=getVar('myfxSource.compressed', $symbol, $day))) {} // komprimierte MyFX-Datei...
            else if (is_file($file=getVar('myfxSource.raw'       , $symbol, $day))) {} // ...oder unkomprimierte MyFX-Datei
            else {
               echoPre('[Error]   '.$symbol.' history for '.date('D, d-M-Y', $day).' not found');
               return false;
            }
            // Bars zwischenspeichern
            $symbols[$symbol]['bars'] = MyFX::readBarFile($file);
         }

         // Indexdaten für diesen Tag berechnen
         $function = 'calculate'.$index;
         $ixBars   = $function($day, $symbols); if (!$ixBars) return false;

         // Indexdaten speichern
         if (!saveBars($index, $day, $ixBars)) return false;
      }
   }
   echoPre('[Ok]    '.$index);
   return true;
}


/**
 * Berechnet für die übergebenen Daten den USDFX6-Index.
 *
 * @param  int   $day     - Tag der zu berechnenden Daten
 * @param  array $symbols - Array mit den Daten der beteiligten Instrumente für diesen Tag
 *
 * @return MYFX_BAR[] - Array mit den resultierenden Indexdaten
 */
function calculateUSDFX6($day, array $symbols) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = date('D, d-M-Y', $day);

   global $verbose;
   if ($verbose > 1) echoPre('[Info]    USDFX6  '.$shortDate);

   $AUDUSD = $symbols['AUDUSD']['bars'];
   $EURUSD = $symbols['EURUSD']['bars'];
   $GBPUSD = $symbols['GBPUSD']['bars'];
   $USDCAD = $symbols['USDCAD']['bars'];
   $USDCHF = $symbols['USDCHF']['bars'];
   $USDJPY = $symbols['USDJPY']['bars'];
   $index  = array();

   foreach ($AUDUSD as $i => $bar) {
      $audusd = $AUDUSD[$i]['open'];
      $eurusd = $EURUSD[$i]['open'];
      $gbpusd = $GBPUSD[$i]['open'];
      $usdcad = $USDCAD[$i]['open'];                                 // Die Divisionen müssen vor den Multiplikationen erfolgen, da die
      $usdchf = $USDCHF[$i]['open'];                                 // Multiplikation der MyFX-Ganzzahlen den Zahlenbereich eines
      $usdjpy = $USDJPY[$i]['open'];                                 // 32bit-Integers überschreitet.
      $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/6);
      $iOpen  = round($open * 100000);

      $audusd = $AUDUSD[$i]['close'];
      $eurusd = $EURUSD[$i]['close'];
      $gbpusd = $GBPUSD[$i]['close'];
      $usdcad = $USDCAD[$i]['close'];
      $usdchf = $USDCHF[$i]['close'];
      $usdjpy = $USDJPY[$i]['close'];
      $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/6);
      $iClose = round($close * 100000);

      $index[$i]['time' ] = $bar['time'];
      $index[$i]['open' ] = $iOpen;
      $index[$i]['high' ] = max($iOpen, $iClose);
      $index[$i]['low'  ] = min($iOpen, $iClose);
      $index[$i]['close'] = $iClose;
      $index[$i]['ticks'] = abs($iOpen-$iClose) << 1;
   }
   return $index;
}


/**
 * Berechnet für die übergebenen Daten den AUDLFX-Index.
 *
 * @param  int   $day     - Tag der zu berechnenden Daten
 * @param  array $symbols - Array mit den Daten der beteiligten Instrumente für diesen Tag
 *
 * @return MYFX_BAR[] - Array mit den resultierenden Indexdaten
 *
 * @see    Formel: MetaTrader::mql4\indicators\LFX-Recorder.mq4
 */
function calculateAUDLFX($day, array $symbols) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = date('D, d-M-Y', $day);

   global $verbose;
   if ($verbose > 1) echoPre('[Info]    AUDLFX  '.$shortDate);

   $AUDUSD = $symbols['AUDUSD']['bars'];
   $EURUSD = $symbols['EURUSD']['bars'];
   $GBPUSD = $symbols['GBPUSD']['bars'];
   $USDCAD = $symbols['USDCAD']['bars'];
   $USDCHF = $symbols['USDCHF']['bars'];
   $USDJPY = $symbols['USDJPY']['bars'];
   $index  = array();

   foreach ($AUDUSD as $i => $bar) {
      $audusd = $AUDUSD[$i]['open'];
      $eurusd = $EURUSD[$i]['open'];
      $gbpusd = $GBPUSD[$i]['open'];
      $usdcad = $USDCAD[$i]['open'];                                 // Die Divisionen müssen vor den Multiplikationen erfolgen, da die
      $usdchf = $USDCHF[$i]['open'];                                 // Multiplikation der MyFX-Ganzzahlen den Zahlenbereich eines
      $usdjpy = $USDJPY[$i]['open'];                                 // 32bit-Integers überschreitet.
      $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) * $audusd;
      $iOpen  = round($open);

      $audusd = $AUDUSD[$i]['close'];
      $eurusd = $EURUSD[$i]['close'];
      $gbpusd = $GBPUSD[$i]['close'];
      $usdcad = $USDCAD[$i]['close'];
      $usdchf = $USDCHF[$i]['close'];
      $usdjpy = $USDJPY[$i]['close'];
      $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) * $audusd;
      $iClose = round($close);

      $index[$i]['time' ] = $bar['time'];
      $index[$i]['open' ] = $iOpen;
      $index[$i]['high' ] = max($iOpen, $iClose);
      $index[$i]['low'  ] = min($iOpen, $iClose);
      $index[$i]['close'] = $iClose;
      $index[$i]['ticks'] = abs($iOpen-$iClose) << 1;
   }
   return $index;
}


/**
 * Berechnet für die übergebenen Daten den CADLFX-Index.
 *
 * @param  int   $day     - Tag der zu berechnenden Daten
 * @param  array $symbols - Array mit den Daten der beteiligten Instrumente für diesen Tag
 *
 * @return MYFX_BAR[] - Array mit den resultierenden Indexdaten
 *
 * @see    Formel: MetaTrader::mql4\indicators\LFX-Recorder.mq4
 */
function calculateCADLFX($day, array $symbols) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = date('D, d-M-Y', $day);

   global $verbose;
   if ($verbose > 1) echoPre('[Info]    CADLFX  '.$shortDate);

   $AUDUSD = $symbols['AUDUSD']['bars'];
   $EURUSD = $symbols['EURUSD']['bars'];
   $GBPUSD = $symbols['GBPUSD']['bars'];
   $USDCAD = $symbols['USDCAD']['bars'];
   $USDCHF = $symbols['USDCHF']['bars'];
   $USDJPY = $symbols['USDJPY']['bars'];
   $index  = array();

   foreach ($AUDUSD as $i => $bar) {
      $audusd = $AUDUSD[$i]['open'];
      $eurusd = $EURUSD[$i]['open'];
      $gbpusd = $GBPUSD[$i]['open'];
      $usdcad = $USDCAD[$i]['open'];                                 // Die Divisionen müssen vor den Multiplikationen erfolgen, da die
      $usdchf = $USDCHF[$i]['open'];                                 // Multiplikation der MyFX-Ganzzahlen den Zahlenbereich eines
      $usdjpy = $USDJPY[$i]['open'];                                 // 32bit-Integers überschreitet.
      $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdcad * 100000;
      $iOpen  = round($open * 100000);

      $audusd = $AUDUSD[$i]['close'];
      $eurusd = $EURUSD[$i]['close'];
      $gbpusd = $GBPUSD[$i]['close'];
      $usdcad = $USDCAD[$i]['close'];
      $usdchf = $USDCHF[$i]['close'];
      $usdjpy = $USDJPY[$i]['close'];
      $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdcad * 100000;
      $iClose = round($close * 100000);

      $index[$i]['time' ] = $bar['time'];
      $index[$i]['open' ] = $iOpen;
      $index[$i]['high' ] = max($iOpen, $iClose);
      $index[$i]['low'  ] = min($iOpen, $iClose);
      $index[$i]['close'] = $iClose;
      $index[$i]['ticks'] = abs($iOpen-$iClose) << 1;
   }
   return $index;
}


/**
 * Berechnet für die übergebenen Daten den CHFLFX-Index.
 *
 * @param  int   $day     - Tag der zu berechnenden Daten
 * @param  array $symbols - Array mit den Daten der beteiligten Instrumente für diesen Tag
 *
 * @return MYFX_BAR[] - Array mit den resultierenden Indexdaten
 *
 * @see    Formel: MetaTrader::mql4\indicators\LFX-Recorder.mq4
 */
function calculateCHFLFX($day, array $symbols) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = date('D, d-M-Y', $day);

   global $verbose;
   if ($verbose > 1) echoPre('[Info]    CHFLFX  '.$shortDate);

   $AUDUSD = $symbols['AUDUSD']['bars'];
   $EURUSD = $symbols['EURUSD']['bars'];
   $GBPUSD = $symbols['GBPUSD']['bars'];
   $USDCAD = $symbols['USDCAD']['bars'];
   $USDCHF = $symbols['USDCHF']['bars'];
   $USDJPY = $symbols['USDJPY']['bars'];
   $index  = array();

   foreach ($AUDUSD as $i => $bar) {
      $audusd = $AUDUSD[$i]['open'];
      $eurusd = $EURUSD[$i]['open'];
      $gbpusd = $GBPUSD[$i]['open'];
      $usdcad = $USDCAD[$i]['open'];                                 // Die Divisionen müssen vor den Multiplikationen erfolgen, da die
      $usdchf = $USDCHF[$i]['open'];                                 // Multiplikation der MyFX-Ganzzahlen den Zahlenbereich eines
      $usdjpy = $USDJPY[$i]['open'];                                 // Integers überschreitet.
      $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdchf * 100000;
      $iOpen  = round($open * 100000);

      $audusd = $AUDUSD[$i]['close'];
      $eurusd = $EURUSD[$i]['close'];
      $gbpusd = $GBPUSD[$i]['close'];
      $usdcad = $USDCAD[$i]['close'];
      $usdchf = $USDCHF[$i]['close'];
      $usdjpy = $USDJPY[$i]['close'];
      $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdchf * 100000;
      $iClose = round($close * 100000);

      $index[$i]['time' ] = $bar['time'];
      $index[$i]['open' ] = $iOpen;
      $index[$i]['high' ] = max($iOpen, $iClose);
      $index[$i]['low'  ] = min($iOpen, $iClose);
      $index[$i]['close'] = $iClose;
      $index[$i]['ticks'] = abs($iOpen-$iClose) << 1;
   }
   return $index;
}


/**
 * Berechnet für die übergebenen Daten den EURLFX-Index.
 *
 * @param  int   $day     - Tag der zu berechnenden Daten
 * @param  array $symbols - Array mit den Daten der beteiligten Instrumente für diesen Tag
 *
 * @return MYFX_BAR[] - Array mit den resultierenden Indexdaten
 *
 * @see    Formel: MetaTrader::mql4\indicators\LFX-Recorder.mq4
 */
function calculateEURLFX($day, array $symbols) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = date('D, d-M-Y', $day);

   global $verbose;
   if ($verbose > 1) echoPre('[Info]    EURLFX  '.$shortDate);

   $AUDUSD = $symbols['AUDUSD']['bars'];
   $EURUSD = $symbols['EURUSD']['bars'];
   $GBPUSD = $symbols['GBPUSD']['bars'];
   $USDCAD = $symbols['USDCAD']['bars'];
   $USDCHF = $symbols['USDCHF']['bars'];
   $USDJPY = $symbols['USDJPY']['bars'];
   $index  = array();

   foreach ($AUDUSD as $i => $bar) {
      $audusd = $AUDUSD[$i]['open'];
      $eurusd = $EURUSD[$i]['open'];
      $gbpusd = $GBPUSD[$i]['open'];
      $usdcad = $USDCAD[$i]['open'];                                 // Die Divisionen müssen vor den Multiplikationen erfolgen, da die
      $usdchf = $USDCHF[$i]['open'];                                 // Multiplikation der MyFX-Ganzzahlen den Zahlenbereich eines
      $usdjpy = $USDJPY[$i]['open'];                                 // 32bit-Integers überschreitet.
      $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) * $eurusd;
      $iOpen  = round($open);

      $audusd = $AUDUSD[$i]['close'];
      $eurusd = $EURUSD[$i]['close'];
      $gbpusd = $GBPUSD[$i]['close'];
      $usdcad = $USDCAD[$i]['close'];
      $usdchf = $USDCHF[$i]['close'];
      $usdjpy = $USDJPY[$i]['close'];
      $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) * $eurusd;
      $iClose = round($close);

      $index[$i]['time' ] = $bar['time'];
      $index[$i]['open' ] = $iOpen;
      $index[$i]['high' ] = max($iOpen, $iClose);
      $index[$i]['low'  ] = min($iOpen, $iClose);
      $index[$i]['close'] = $iClose;
      $index[$i]['ticks'] = abs($iOpen-$iClose) << 1;
   }
   return $index;
}


/**
 * Berechnet für die übergebenen Daten den GBPLFX-Index.
 *
 * @param  int   $day     - Tag der zu berechnenden Daten
 * @param  array $symbols - Array mit den Daten der beteiligten Instrumente für diesen Tag
 *
 * @return MYFX_BAR[] - Array mit den resultierenden Indexdaten
 *
 * @see    Formel: MetaTrader::mql4\indicators\LFX-Recorder.mq4
 */
function calculateGBPLFX($day, array $symbols) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = date('D, d-M-Y', $day);

   global $verbose;
   if ($verbose > 1) echoPre('[Info]    GBPLFX  '.$shortDate);

   $AUDUSD = $symbols['AUDUSD']['bars'];
   $EURUSD = $symbols['EURUSD']['bars'];
   $GBPUSD = $symbols['GBPUSD']['bars'];
   $USDCAD = $symbols['USDCAD']['bars'];
   $USDCHF = $symbols['USDCHF']['bars'];
   $USDJPY = $symbols['USDJPY']['bars'];
   $index  = array();

   foreach ($AUDUSD as $i => $bar) {
      $audusd = $AUDUSD[$i]['open'];
      $eurusd = $EURUSD[$i]['open'];
      $gbpusd = $GBPUSD[$i]['open'];
      $usdcad = $USDCAD[$i]['open'];                                 // Die Divisionen müssen vor den Multiplikationen erfolgen, da die
      $usdchf = $USDCHF[$i]['open'];                                 // Multiplikation der MyFX-Ganzzahlen den Zahlenbereich eines
      $usdjpy = $USDJPY[$i]['open'];                                 // 32bit-Integers überschreitet.
      $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) * $gbpusd;
      $iOpen  = round($open);

      $audusd = $AUDUSD[$i]['close'];
      $eurusd = $EURUSD[$i]['close'];
      $gbpusd = $GBPUSD[$i]['close'];
      $usdcad = $USDCAD[$i]['close'];
      $usdchf = $USDCHF[$i]['close'];
      $usdjpy = $USDJPY[$i]['close'];
      $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) * $gbpusd;
      $iClose = round($close);

      $index[$i]['time' ] = $bar['time'];
      $index[$i]['open' ] = $iOpen;
      $index[$i]['high' ] = max($iOpen, $iClose);
      $index[$i]['low'  ] = min($iOpen, $iClose);
      $index[$i]['close'] = $iClose;
      $index[$i]['ticks'] = abs($iOpen-$iClose) << 1;
   }
   return $index;
}


/**
 * Berechnet für die übergebenen Daten den JPYLFX-Index.
 *
 * @param  int   $day     - Tag der zu berechnenden Daten
 * @param  array $symbols - Array mit den Daten der beteiligten Instrumente für diesen Tag
 *
 * @return MYFX_BAR[] - Array mit den resultierenden Indexdaten
 *
 * @see    Formel: MetaTrader::mql4\indicators\LFX-Recorder.mq4
 */
function calculateJPYLFX($day, array $symbols) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = date('D, d-M-Y', $day);

   global $verbose;
   if ($verbose > 1) echoPre('[Info]    JPYLFX  '.$shortDate);

   $AUDUSD = $symbols['AUDUSD']['bars'];
   $EURUSD = $symbols['EURUSD']['bars'];
   $GBPUSD = $symbols['GBPUSD']['bars'];
   $USDCAD = $symbols['USDCAD']['bars'];
   $USDCHF = $symbols['USDCHF']['bars'];
   $USDJPY = $symbols['USDJPY']['bars'];
   $index  = array();

   foreach ($AUDUSD as $i => $bar) {
      $audusd = $AUDUSD[$i]['open'];
      $eurusd = $EURUSD[$i]['open'];
      $gbpusd = $GBPUSD[$i]['open'];
      $usdcad = $USDCAD[$i]['open'];                                 // Die Divisionen müssen vor den Multiplikationen erfolgen, da die
      $usdchf = $USDCHF[$i]['open'];                                 // Multiplikation der MyFX-Ganzzahlen den Zahlenbereich eines
      $usdjpy = $USDJPY[$i]['open'];                                 // 32bit-Integers überschreitet.
      $open   = 100 * pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdjpy * 1000;
      $iOpen  = round($open * 100000);

      $audusd = $AUDUSD[$i]['close'];
      $eurusd = $EURUSD[$i]['close'];
      $gbpusd = $GBPUSD[$i]['close'];
      $usdcad = $USDCAD[$i]['close'];
      $usdchf = $USDCHF[$i]['close'];
      $usdjpy = $USDJPY[$i]['close'];
      $close  = 100 * pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) / $usdjpy * 1000;
      $iClose = round($close * 100000);

      $index[$i]['time' ] = $bar['time'];
      $index[$i]['open' ] = $iOpen;
      $index[$i]['high' ] = max($iOpen, $iClose);
      $index[$i]['low'  ] = min($iOpen, $iClose);
      $index[$i]['close'] = $iClose;
      $index[$i]['ticks'] = abs($iOpen-$iClose) << 1;
   }
   return $index;
}


/**
 * Berechnet für die übergebenen Daten den NZDLFX-Index. Die zugrundeliegende LiteForex-Formel ist falsch.
 *
 * @param  int   $day     - Tag der zu berechnenden Daten
 * @param  array $symbols - Array mit den Daten der beteiligten Instrumente für diesen Tag
 *
 * @return MYFX_BAR[] - Array mit den resultierenden Indexdaten
 *
 * @see    Formel: MetaTrader::mql4\indicators\LFX-Recorder.mq4
 */
function calculateNZDLFX($day, array $symbols) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = date('D, d-M-Y', $day);

   global $verbose;
   if ($verbose > 1) echoPre('[Info]    NZDLFX  '.$shortDate);

   $AUDUSD = $symbols['AUDUSD']['bars'];
   $EURUSD = $symbols['EURUSD']['bars'];
   $GBPUSD = $symbols['GBPUSD']['bars'];
   $USDCAD = $symbols['USDCAD']['bars'];
   $USDCHF = $symbols['USDCHF']['bars'];
   $USDJPY = $symbols['USDJPY']['bars'];
   $NZDUSD = $symbols['NZDUSD']['bars'];
   $index  = array();

   foreach ($AUDUSD as $i => $bar) {
      $audusd = $AUDUSD[$i]['open'];
      $eurusd = $EURUSD[$i]['open'];
      $gbpusd = $GBPUSD[$i]['open'];
      $usdcad = $USDCAD[$i]['open'];
      $usdchf = $USDCHF[$i]['open'];                                 // Die Divisionen müssen vor den Multiplikationen erfolgen, da die
      $usdjpy = $USDJPY[$i]['open'];                                 // Multiplikation der MyFX-Ganzzahlen den Zahlenbereich eines
      $nzdusd = $NZDUSD[$i]['open'];                                 // 32bit-Integers überschreitet.
      $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) * $nzdusd;
      $iOpen  = round($open);

      $audusd = $AUDUSD[$i]['close'];
      $eurusd = $EURUSD[$i]['close'];
      $gbpusd = $GBPUSD[$i]['close'];
      $usdcad = $USDCAD[$i]['close'];
      $usdchf = $USDCHF[$i]['close'];
      $usdjpy = $USDJPY[$i]['close'];
      $nzdusd = $NZDUSD[$i]['close'];
      $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7) * $nzdusd;
      $iClose = round($close);

      $index[$i]['time' ] = $bar['time'];
      $index[$i]['open' ] = $iOpen;
      $index[$i]['high' ] = max($iOpen, $iClose);
      $index[$i]['low'  ] = min($iOpen, $iClose);
      $index[$i]['close'] = $iClose;
      $index[$i]['ticks'] = abs($iOpen-$iClose) << 1;
   }
   return $index;
}


/**
 * Berechnet für die übergebenen Daten den USDLFX-Index.
 *
 * @param  int   $day     - Tag der zu berechnenden Daten
 * @param  array $symbols - Array mit den Daten der beteiligten Instrumente für diesen Tag
 *
 * @return MYFX_BAR[] - Array mit den resultierenden Indexdaten
 *
 * @see    Formel: MetaTrader::mql4\indicators\LFX-Recorder.mq4
 */
function calculateUSDLFX($day, array $symbols) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = date('D, d-M-Y', $day);

   global $verbose;
   if ($verbose > 1) echoPre('[Info]    USDLFX  '.$shortDate);

   $AUDUSD = $symbols['AUDUSD']['bars'];
   $EURUSD = $symbols['EURUSD']['bars'];
   $GBPUSD = $symbols['GBPUSD']['bars'];
   $USDCAD = $symbols['USDCAD']['bars'];
   $USDCHF = $symbols['USDCHF']['bars'];
   $USDJPY = $symbols['USDJPY']['bars'];
   $index  = array();

   foreach ($AUDUSD as $i => $bar) {
      $audusd = $AUDUSD[$i]['open'];
      $eurusd = $EURUSD[$i]['open'];
      $gbpusd = $GBPUSD[$i]['open'];
      $usdcad = $USDCAD[$i]['open'];                                 // Die Divisionen müssen vor den Multiplikationen erfolgen, da die
      $usdchf = $USDCHF[$i]['open'];                                 // Multiplikation der MyFX-Ganzzahlen den Zahlenbereich eines
      $usdjpy = $USDJPY[$i]['open'];                                 // 32bit-Integers überschreitet.
      $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7);
      $iOpen  = round($open * 100000);

      $audusd = $AUDUSD[$i]['close'];
      $eurusd = $EURUSD[$i]['close'];
      $gbpusd = $GBPUSD[$i]['close'];
      $usdcad = $USDCAD[$i]['close'];
      $usdchf = $USDCHF[$i]['close'];
      $usdjpy = $USDJPY[$i]['close'];
      $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd) * 100, 1/7);
      $iClose = round($close * 100000);

      $index[$i]['time' ] = $bar['time'];
      $index[$i]['open' ] = $iOpen;
      $index[$i]['high' ] = max($iOpen, $iClose);
      $index[$i]['low'  ] = min($iOpen, $iClose);
      $index[$i]['close'] = $iClose;
      $index[$i]['ticks'] = abs($iOpen-$iClose) << 1;
   }
   return $index;
}


/**
 * Berechnet für die übergebenen Daten den EURX-Index (ICE).
 *
 * @param  int   $day     - Tag der zu berechnenden Daten
 * @param  array $symbols - Array mit den Daten der beteiligten Instrumente für diesen Tag
 *
 * @return MYFX_BAR[] - Array mit den resultierenden Indexdaten
 *
 * @see    Formel: MetaTrader::mql4\indicators\LFX-Recorder.mq4
 */
function calculateEURX($day, array $symbols) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = date('D, d-M-Y', $day);

   global $verbose;
   if ($verbose > 1) echoPre('[Info]    EURX  '.$shortDate);

   $EURUSD = $symbols['EURUSD']['bars'];
   $GBPUSD = $symbols['GBPUSD']['bars'];
   $USDCHF = $symbols['USDCHF']['bars'];
   $USDJPY = $symbols['USDJPY']['bars'];
   $USDSEK = $symbols['USDSEK']['bars'];
   $index  = array();

   foreach ($EURUSD as $i => $bar) {
      $eurusd = $EURUSD[$i]['open'];
      $gbpusd = $GBPUSD[$i]['open'];
      $usdchf = $USDCHF[$i]['open'];
      $usdjpy = $USDJPY[$i]['open'];
      $usdsek = $USDSEK[$i]['open'];
      $open   = 34.38805726
              * pow($eurusd/100000 * $usdchf/100000, 0.1113)         // Die Divisionen müssen einzeln erfolgen, da der Teiler bei
              * pow($eurusd        / $gbpusd       , 0.3056)         // gemeinsamer Division den Zahlenbereich eines 32bit-Integers
              * pow($eurusd/100000 * $usdjpy/1000  , 0.1891)         // überschreitet.
              * pow($eurusd/100000 * $usdsek/100000, 0.0785)
              * pow($eurusd/100000                 , 0.3155);
      $iOpen  = round($open * 1000);

      $eurusd = $EURUSD[$i]['close'];
      $gbpusd = $GBPUSD[$i]['close'];
      $usdchf = $USDCHF[$i]['close'];
      $usdjpy = $USDJPY[$i]['close'];
      $usdsek = $USDSEK[$i]['close'];
      $close  = 34.38805726
              * pow($eurusd/100000 * $usdchf/100000, 0.1113)
              * pow($eurusd        / $gbpusd       , 0.3056)
              * pow($eurusd/100000 * $usdjpy/1000  , 0.1891)
              * pow($eurusd/100000 * $usdsek/100000, 0.0785)
              * pow($eurusd/100000                 , 0.3155);
      $iClose = round($close * 1000);

      $index[$i]['time' ] = $bar['time'];
      $index[$i]['open' ] = $iOpen;
      $index[$i]['high' ] = max($iOpen, $iClose);
      $index[$i]['low'  ] = min($iOpen, $iClose);
      $index[$i]['close'] = $iClose;
      $index[$i]['ticks'] = abs($iOpen-$iClose) << 1;
   }
}


/**
 * Berechnet für die übergebenen Daten den USDX-Index (ICE).
 *
 * @param  int   $day     - Tag der zu berechnenden Daten
 * @param  array $symbols - Array mit den Daten der beteiligten Instrumente für diesen Tag
 *
 * @return MYFX_BAR[] - Array mit den resultierenden Indexdaten
 *
 * @see    Formel: MetaTrader::mql4\indicators\LFX-Recorder.mq4
 */
function calculateUSDX($day, array $symbols) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = date('D, d-M-Y', $day);

   global $verbose;
   if ($verbose > 1) echoPre('[Info]    USDX  '.$shortDate);

   $EURUSD = $symbols['EURUSD']['bars'];
   $GBPUSD = $symbols['GBPUSD']['bars'];
   $USDCAD = $symbols['USDCAD']['bars'];
   $USDCHF = $symbols['USDCHF']['bars'];
   $USDJPY = $symbols['USDJPY']['bars'];
   $USDSEK = $symbols['USDSEK']['bars'];
   $index  = array();

   foreach ($EURUSD as $i => $bar) {
      $eurusd = $EURUSD[$i]['open'];
      $gbpusd = $GBPUSD[$i]['open'];
      $usdcad = $USDCAD[$i]['open'];
      $usdchf = $USDCHF[$i]['open'];
      $usdjpy = $USDJPY[$i]['open'];
      $usdsek = $USDSEK[$i]['open'];
      $open   = 50.14348112
              * pow($usdcad/100000, 0.091) * pow($usdchf/100000, 0.036) * pow($usdjpy/1000, 0.136) * pow($usdsek/100000, 0.042)
              / pow($eurusd/100000, 0.576) / pow($gbpusd/100000, 0.119);
      $iOpen  = round($open * 1000);

      $eurusd = $EURUSD[$i]['close'];
      $gbpusd = $GBPUSD[$i]['close'];
      $usdcad = $USDCAD[$i]['close'];
      $usdchf = $USDCHF[$i]['close'];
      $usdjpy = $USDJPY[$i]['close'];
      $usdsek = $USDSEK[$i]['close'];
      $close  = 50.14348112
              * pow($usdcad/100000, 0.091) * pow($usdchf/100000, 0.036) * pow($usdjpy/1000, 0.136) * pow($usdsek/100000, 0.042)
              / pow($eurusd/100000, 0.576) / pow($gbpusd/100000, 0.119);
      $iClose = round($close * 1000);

      $index[$i]['time' ] = $bar['time'];
      $index[$i]['open' ] = $iOpen;
      $index[$i]['high' ] = max($iOpen, $iClose);
      $index[$i]['low'  ] = min($iOpen, $iClose);
      $index[$i]['close'] = $iClose;
      $index[$i]['ticks'] = abs($iOpen-$iClose) << 1;
   }
   return $index;
}


/**
 * Schreibt die Indexdaten eines FXT-Tages in die lokale MyFX-Historydatei.
 *
 * @param  string     $symbol - Symbol
 * @param  int        $day    - Timestamp des FXT-Tages
 * @param  MYFX_BAR[] $bars   - Indexdaten des FXT-Tages
 *
 * @return bool - Erfolgsstatus
 */
function saveBars($symbol, $day, array $bars) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = date('D, d-M-Y', $day);

   global $saveRawMyFXData;


   // (1) Daten nochmal prüfen
   $errorMsg = null;
   if (!$errorMsg && ($size=sizeOf($bars))!=1*DAY/MINUTES)             $errorMsg = 'Invalid number of bars for '.$shortDate.': '.$size;
   if (!$errorMsg && $bars[0]['time']%DAYS!=0)                         $errorMsg = 'No beginning bars for '.$shortDate.' found, first bar:'.NL.printFormatted($bars[0], true);
   if (!$errorMsg && $bars[$size-1]['time']%DAYS!=23*HOURS+59*MINUTES) $errorMsg = 'No ending bars for '.$shortDate.' found, last bar:'.NL.printFormatted($bars[$size-1], true);
   if ($errorMsg) {
      showBuffer($bars);
      throw new plRuntimeException($errorMsg);
   }


   // (2) Bars binär packen
   $data = null;
   foreach ($bars as $bar) {
      $data .= pack('VVVVVV', $bar['time' ],
                              $bar['open' ],
                              $bar['high' ],
                              $bar['low'  ],
                              $bar['close'],
                              $bar['ticks']);
   }


   // (3) binäre Daten ggf. speichern
   if ($saveRawMyFXData) {
      if (is_file($file=getVar('myfxTarget.raw', $symbol, $day))) {
         echoPre('[Error]   '.$symbol.' history for '.date('D, d-M-Y', $day).' already exists');
         return false;
      }
      mkDirWritable(dirName($file));
      $tmpFile = tempNam(dirName($file), baseName($file));
      $hFile   = fOpen($tmpFile, 'wb');
      fWrite($hFile, $data);
      fClose($hFile);
      rename($tmpFile, $file);                                       // So kann eine existierende Datei niemals korrupt sein.
   }


   // (4) binäre Daten komprimieren und speichern

   return true;
}


/**
 *
 */
function showBuffer($bars) {
   echoPre(NL);
   $size = sizeOf($bars);
   $firstBar = $lastBar = null;
   if ($size) {
      if (isSet($bars[0]['time']) && $bars[$size-1]['time']) {
         $firstBar = 'from='.date('d-M-Y H:i', $bars[0      ]['time']);
         $lastBar  = '  to='  .date('d-M-Y H:i', $bars[$size-1]['time']);
      }
      else {
         $firstBar = $lastBar = '  invalid';
         echoPre($bars);
      }
   }
   echoPre('bars['.$size.'] => '.$firstBar.($size>1? $lastBar:''));
   echoPre(NL);
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

   static $dataDirectory;
   $self = __FUNCTION__;

   if ($id == 'myfxDirDate') {                  // $yyyy/$mm/$dd                                         // lokales Pfad-Datum
      if (!$time)   throw new plInvalidArgumentException('Invalid parameter $time: '.$time);
      $result = date('Y/m/d', $time);
   }
   else if ($id == 'myfxSourceDir') {           // $dataDirectory/history/dukascopy/$symbol/$myfxDirDate // lokales Quell-Verzeichnis
      if (!$symbol) throw new plInvalidArgumentException('Invalid parameter $symbol: '.$symbol);
      if (!$dataDirectory)
      $dataDirectory = MyFX::getConfigPath('myfx.data_directory');
      $myfxDirDate   = $self('myfxDirDate', null, $time);
      $result        = "$dataDirectory/history/dukascopy/$symbol/$myfxDirDate";
   }
   else if ($id == 'myfxTargetDir') {           // $dataDirectory/history/myfx/$symbol/$myfxDirDate      // lokales Ziel-Verzeichnis
      if (!$symbol) throw new plInvalidArgumentException('Invalid parameter $symbol: '.$symbol);
      if (!$dataDirectory)
      $dataDirectory = MyFX::getConfigPath('myfx.data_directory');
      $myfxDirDate   = $self('myfxDirDate', null, $time);
      $result        = "$dataDirectory/history/myfx/$symbol/$myfxDirDate";
   }
   else if ($id == 'myfxSource.raw') {          // $myfxSourceDir/M1.bin                                 // lokale Quell-Datei ungepackt
      $myfxSourceDir = $self('myfxSourceDir', $symbol, $time);
      $result        = "$myfxSourceDir/M1.bin";
   }
   else if ($id == 'myfxSource.compressed') {   // $myfxSourceDir/M1.rar                                 // lokale Quell-Datei gepackt
      $myfxSourceDir = $self('myfxSourceDir', $symbol, $time);
      $result        = "$myfxSourceDir/M1.rar";
   }
   else if ($id == 'myfxTarget.raw') {          // $myfxTargetDir/M1.bin                                 // lokale Ziel-Datei ungepackt
      $myfxTargetDir = $self('myfxTargetDir' , $symbol, $time);
      $result        = "$myfxTargetDir/M1.bin";
   }
   else if ($id == 'myfxTarget.compressed') {   // $myfxTargetDir/M1.rar                                 // lokale Ziel-Datei gepackt
      $myfxTargetDir = $self('myfxTargetDir' , $symbol, $time);
      $result        = "$myfxTargetDir/M1.rar";
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
?>
