<?php
/**
 * Ein HistorySet zur Verwaltung der vollständigen MetaTrader-History eines Instruments.
 */
class HistorySet extends Object {

   private static /*HistorySet[]*/ $openSets = array();  // alle Instanzen dieser Klasse mit einem offenen Set

   protected /*string*/  $symbol;
   protected /*int   */  $digits;
   protected /*string*/  $serverName;
   protected /*string*/  $serverDirectory;         // vollständiger Verzeichnisname

   protected /*array[]*/ $history = array(PERIOD_M1 =>array('hFile'=>null, 'bars'=>array(), 'currentCloseTime'=>PHP_INT_MIN),
                                          PERIOD_M5 =>array('hFile'=>null, 'bars'=>array(), 'currentCloseTime'=>PHP_INT_MIN),
                                          PERIOD_M15=>array('hFile'=>null, 'bars'=>array(), 'currentCloseTime'=>PHP_INT_MIN),
                                          PERIOD_M30=>array('hFile'=>null, 'bars'=>array(), 'currentCloseTime'=>PHP_INT_MIN),
                                          PERIOD_H1 =>array('hFile'=>null, 'bars'=>array(), 'currentCloseTime'=>PHP_INT_MIN),
                                          PERIOD_H4 =>array('hFile'=>null, 'bars'=>array(), 'currentCloseTime'=>PHP_INT_MIN),
                                          PERIOD_D1 =>array('hFile'=>null, 'bars'=>array(), 'currentCloseTime'=>PHP_INT_MIN),
                                          PERIOD_W1 =>array('hFile'=>null, 'bars'=>array(), 'currentCloseTime'=>PHP_INT_MIN),
                                          PERIOD_MN1=>array('hFile'=>null, 'bars'=>array(), 'currentCloseTime'=>PHP_INT_MIN),
   );
   protected /*HistoryFile[]*/ $historyFiles = array(PERIOD_M1  => null,
                                                     PERIOD_M5  => null,
                                                     PERIOD_M15 => null,
                                                     PERIOD_M30 => null,
                                                     PERIOD_H1  => null,
                                                     PERIOD_H4  => null,
                                                     PERIOD_D1  => null,
                                                     PERIOD_W1  => null,
                                                     PERIOD_MN1 => null,
   );

   protected /*int    */ $bufferSize = 10000;            // maximale Anzahl vor dem Schreiben zwischengespeicherter Bars


   /**
    * Überladener Constructor.
    *
    * Signaturen:
    * -----------
    * new HistorySet($fileNames)
    * new HistorySet($symbol, $digits, $format, $serverDirectory)
    */
   public function __construct($arg1=null, $arg2=null, $arg3=null, $arg4=null) {
      $argc = func_num_args();

      if      ($argc == 1) $this->__construct_1($arg1);
      else if ($argc == 4) $this->__construct_2($arg1, $arg2, $arg3, $arg4);

      else throw new plInvalidArgumentException('Invalid number of arguments: '.$argc);
   }


   /**
    * Constructor 1
    *
    * Erzeugt eine HistorySet-Instanz aus mindestens teilweise vorhandenen HistoryFiles. Nach Rückkehr wurden alle
    * noch nicht existierenden HistoryFiles angelegt und ein entsprechender HistoryHeader geschrieben. Existierende Daten
    * werden nicht gelöscht. Die Formate der einzelnen Dateien eines HistorySets können gemischt sein.
    *
    * @param  string[] $fileNames - mindestens ein vollständiger Name eines vorhandenen HistoryFiles
    */
   private function __construct_1(array $fileNames) {
      // (1) für alle übergebenen Dateinamen HistoryFile-Wrapper erzeugen
      foreach($fileNames as $key => $fileName) {
         if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileNames['.$key.']: '.getType($fileName));
         if (!is_file($fileName))   throw new FileNotFoundException('Invalid parameter $fileNames['.$key.']: "'.$fileName.'" (file not found)');

         $file = null;
         try {
            $file = new HistoryFile($fileName);
         }
         catch (MetaTraderException $ex) {
            if (strStartsWith($ex->getMessage(), 'filesize.insufficient')) { Logger::warn($ex, __CLASS__); continue; }
            if (strStartsWith($ex->getMessage(), 'filename.mis-match'   )) { Logger::warn($ex, __CLASS__); continue; }
            throw $ex;
         }

         if (is_null($this->symbol)) {
            // wenn erste Datei, dann Instanzdaten übernehmen
            $this->symbol          = $file->getSymbol();
            $this->digits          = $file->getDigits();
            $this->serverName      = $file->getServerName();
            $this->serverDirectory = $file->getServerDirectory();
         }
         else {
            // wenn weitere Datei, dann Daten mit Instanzdaten abgleichen
            if (!strCompareI($this->symbol, $file->getSymbol()))       throw new plRuntimeException('Symbol mis-match in "'.$fileName.'": '.$file->getSymbol().' instead of '.$this->symbol);
            if ($this->digits != $file->getDigits())                   throw new plRuntimeException('Digits mis-match in "'.$fileName.'": '.$file->getDigits().' instead of '.$this->digits);
            if ($this->serverDirectory != $file->getServerDirectory()) throw new plRuntimeException('Server mis-match in "'.$fileName.'": '.$file->getServerDirectory().' instead of '.$this->serverDirectory);
            if (isSet($this->historyFiles[$file->getTimeframe()]))     throw new plRuntimeException('Multiple parameters for timeframe '.MT4::periodDescription($file->getTimeframe()).': '.print_r($fileNames, true));
         }

         // HistoryFile speichern
         $this->historyFiles[$file->getTimeframe()] = $file;
      }
      if (is_null($this->symbol)) {
         if ($fileNames) throw new MetaTraderException('files.invalid: No valid history files found');
         else            throw new plInvalidArgumentException('Invalid parameter $fileNames: (empty)');
      }


      // (2) fehlende HistoryFiles neu anlegen
      foreach ($this->historyFiles as $timeframe => &$file) {
         if (!$file) $file = new HistoryFile($this->symbol, $timeframe, $this->digits, $format=400, $this->serverDirectory);
      } unset($file);
   }


   /**
    * Constructor 2
    *
    * Erzeugt ein neues HistorySet mit den angegebenen Daten. Nach Rückkehr wurden alle HistoryFiles angelegt und ein
    * entsprechender HistoryHeader geschrieben. Existierende Daten werden gelöscht.
    *
    * @param  string $symbol          - Symbol der HistorySet-Daten
    * @param  int    $digits          - Digits der Datenreihe
    * @param  int    $format          - Speicherformat der Datenreihe:
    *                                   • 400 - MetaTrader <= Build 509
    *                                   • 401 - MetaTrader  > Build 509
    * @param  string $serverDirectory - Serververzeichnis der Historydateien des Sets
    */
   private function __construct_2($symbol, $digits, $format, $serverDirectory) {
      if (!is_string($symbol))                      throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
      if (!strLen($symbol))                         throw new plInvalidArgumentException('Invalid parameter $symbol: ""');
      if (strLen($symbol) > MT4::MAX_SYMBOL_LENGTH) throw new plInvalidArgumentException('Invalid parameter $symbol: "'.$symbol.'" (max '.MT4::MAX_SYMBOL_LENGTH.' characters)');
      if (!is_int($digits))                         throw new IllegalTypeException('Illegal type of parameter $digits: '.getType($digits));
      if ($digits < 0)                              throw new plInvalidArgumentException('Invalid parameter $digits: '.$digits);
      if (!is_int($format))                         throw new IllegalTypeException('Illegal type of parameter $format: '.getType($format));
      if ($format!=400 && $format!=401)             throw new plInvalidArgumentException('Invalid parameter $format: '.$format.' (can be 400 or 401)');
      if (!is_string($serverDirectory))             throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.getType($serverDirectory));
      if (!is_dir($serverDirectory))                throw new plInvalidArgumentException('Directory "'.$serverDirectory.'" not found');

      $this->symbol          = $symbol;
      $this->digits          = $digits;
      $this->format          = $format;
      $this->serverDirectory = realPath($serverDirectory);
      $this->serverName      = baseName($this->serverDirectory);
      mkDirWritable($this->serverDirectory);

      // neuen HistoryHeader initialisieren
      $hh = MT4::createHistoryHeader();
      $hh['format'   ] = $this->format;
      $hh['copyright'] = MyFX::$symbols[strToUpper($symbol)]['description'];
      $hh['symbol'   ] = $this->symbol;
      $hh['digits'   ] = $this->digits;

      // alle HistoryFiles erzeugen bzw. zurücksetzen und Header neuschreiben
      foreach (MT4::$timeframes as $timeframe) {
         $fileName = $this->serverDirectory.'/'.$symbol.$timeframe.'.hst';
         $hFile    = fOpen($fileName, 'wb');
         $this->history[$timeframe]['hFile'] = $hFile;

         $hh['period'] = $timeframe;
         MT4::writeHistoryHeader($hFile, $hh);
      }
   }


   /**
    * Destructor
    *
    * Sorgt bei Zerstörung des Objekts dafür, daß alle noch offenen Historydateien geschlossen werden.
    */
   public function __destruct() {
      try {
         foreach ($this->history as $timeframe => &$data) {                      // data by reference
            if (isSet($data['hFile']) && is_resource($hFile=&$data['hFile'])) {  // hFile by reference
               $this->flushBars($timeframe);
               $hTmp=$hFile; $hFile=null;
               fClose($hTmp);
            }
         } unset($data, $hFile);
      }
      catch (Exception $ex) {
         // Ein Destructor darf während des Shutdowns keine Exception werfen.
         Logger::handleException($ex, $inShutdownOnly=true);
         throw $ex;
      }
   }


   /**
    * Sucht und öffnet ein vorhandenes HistorySet. Dazu muß mindestens ein HistoryFile des Symbols existieren.
    * Nicht existierende HistoryFiles werden beim Speichern der ersten hinzugefügten Daten im History-Format v400 angelegt.
    * Mehrfachaufrufe dieser Funktion für dasselbe Symbol und Serververzeichnis geben dieselbe HistorySet-Instanz zurück.
    *
    * @param  string $symbol          - Symbol des HistorySets
    * @param  string $serverDirectory - Serververzeichnis, in dem die Historydateien des Sets gespeichert sind
    *
    * @return HistorySet - Instance oder NULL, wenn keine entsprechenden Historydateien gefunden wurden
    */
   public static function get($symbol, $serverDirectory) {
      if (!is_string($symbol))          throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
      if (!strLen($symbol))             throw new plInvalidArgumentException('Invalid parameter $symbol: ""');
      if (!is_string($serverDirectory)) throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.getType($serverDirectory));
      if (!is_dir($serverDirectory))    throw new plInvalidArgumentException('Directory "'.$serverDirectory.'" not found');

      // offene Instanzen durchsuchen und bei Erfolg die gefundene Instanz zurückgeben
      $symbolUpper   = strToUpper($symbol);
      $realDirectory = realPath($serverDirectory);

      foreach (self::$openSets as $set) {
         if ($symbolUpper==$set->symbolUpper && $realDirectory==$set->serverDirectory)
            return $set;
      }

      // existierende HistoryFiles suchen
      $files = array();
      foreach (MT4::$timeframes as $timeframe) {
         $fileName = $realDirectory.'/'.$symbol.$timeframe.'.hst';
         if (is_file($fileName))
            $files[] = $fileName;
      }

      // bei Erfolg HistorySet anhand der existierenden Dateien zurückgeben
      if ($files) return new HistorySet($files);

      return null;
   }


   /**
    * Fügt dem HistorySet M1-Bardaten hinzu. Die Bardaten werden am Ende der Timeframes gespeichert.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   public function addM1Bars(array $bars) {
      $this->addToM1 ($bars);
      $this->addToM5 ($bars);
      $this->addToM15($bars);
      $this->addToM30($bars);
      $this->addToH1 ($bars);
      $this->addToH4 ($bars);
      $this->addToD1 ($bars);
      $this->addToW1 ($bars);
      $this->addToMN1($bars);
   }


   /**
    * Fügt der M1-History des Sets weitere Bars hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToM1(array $bars) {
      $this->history[PERIOD_M1]['bars'] = array_merge($this->history[PERIOD_M1]['bars'], $bars);

      $sizeM1Bars = sizeOf($this->history[PERIOD_M1]['bars']);
      $lastBar    = $this->history[PERIOD_M1]['bars'][$sizeM1Bars-1];
      $this->history[PERIOD_M1]['currentCloseTime'] = $lastBar['time'] + 1*MINUTE;

      if ($sizeM1Bars > $this->bufferSize)
         $sizeM1Bars -= $this->flushBars(PERIOD_M1, $this->bufferSize);
   }


   /**
    * Fügt der M5-History des Sets weitere M1-Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToM5(array $bars) {
      $sizeM5Bars = sizeOf($this->history[PERIOD_M5]['bars']);
      $currentBar = null;
      if ($sizeM5Bars)
         $currentBar =& $this->history[PERIOD_M5]['bars'][$sizeM5Bars-1];

      foreach ($bars as $i => $bar) {
         // Wechsel zur nächsten M5-Bar erkennen
         if ($bar['time'] >= $this->history[PERIOD_M5]['currentCloseTime']) {
            // neue Bar beginnen
            $bar['time']                                 -= $bar['time'] % 5*MINUTES;
            $this->history[PERIOD_M5]['currentCloseTime'] = $bar['time'] + 5*MINUTES;
            $this->history[PERIOD_M5]['bars'          ][] = $bar;
            $currentBar =& $this->history[PERIOD_M5]['bars'][$sizeM5Bars++];

            if ($sizeM5Bars > $this->bufferSize)
               $sizeM5Bars -= $this->flushBars(PERIOD_M5, $this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der M15-History des Sets weitere M1-Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToM15(array $bars) {
      $sizeM15Bars = sizeOf($this->history[PERIOD_M15]['bars']);
      $currentBar  = null;
      if ($sizeM15Bars)
         $currentBar =& $this->history[PERIOD_M15]['bars'][$sizeM15Bars-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten M15-Bar erkennen
         if ($bar['time'] >= $this->history[PERIOD_M15]['currentCloseTime']) {
            // neue Bar beginnen
            $bar['time']                                  -= $bar['time'] % 15*MINUTES;
            $this->history[PERIOD_M15]['currentCloseTime'] = $bar['time'] + 15*MINUTES;
            $this->history[PERIOD_M15]['bars'          ][] = $bar;
            $currentBar =& $this->history[PERIOD_M15]['bars'][$sizeM15Bars++];

            if ($sizeM15Bars > $this->bufferSize)
               $sizeM15Bars -= $this->flushBars(PERIOD_M15, $this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der M30-History des Sets weitere M1-Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToM30(array $bars) {
      $sizeM30Bars = sizeOf($this->history[PERIOD_M30]['bars']);
      $currentBar  = null;
      if ($sizeM30Bars)
         $currentBar =& $this->history[PERIOD_M30]['bars'][$sizeM30Bars-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten M30-Bar erkennen
         if ($bar['time'] >= $this->history[PERIOD_M30]['currentCloseTime']) {
            // neue Bar beginnen
            $bar['time']                                  -= $bar['time'] % 30*MINUTES;
            $this->history[PERIOD_M30]['currentCloseTime'] = $bar['time'] + 30*MINUTES;
            $this->history[PERIOD_M30]['bars'          ][] = $bar;
            $currentBar =& $this->history[PERIOD_M30]['bars'][$sizeM30Bars++];

            if ($sizeM30Bars > $this->bufferSize)
               $sizeM30Bars -= $this->flushBars(PERIOD_M30, $this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der H1-History des Sets weitere M1-Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToH1(array $bars) {
      $sizeH1Bars = sizeOf($this->history[PERIOD_H1]['bars']);
      $currentBar = null;
      if ($sizeH1Bars)
         $currentBar =& $this->history[PERIOD_H1]['bars'][$sizeH1Bars-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten H1-Bar erkennen
         if ($bar['time'] >= $this->history[PERIOD_H1]['currentCloseTime']) {
            // neue Bar beginnen
            $bar['time']                                 -= $bar['time'] % HOUR;
            $this->history[PERIOD_H1]['currentCloseTime'] = $bar['time'] + 1*HOUR;
            $this->history[PERIOD_H1]['bars'          ][] = $bar;
            $currentBar =& $this->history[PERIOD_H1]['bars'][$sizeH1Bars++];

            if ($sizeH1Bars > $this->bufferSize)
               $sizeH1Bars -= $this->flushBars(PERIOD_H1, $this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der H4-History des Sets weitere M1-Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToH4(array $bars) {
      $sizeH4Bars = sizeOf($this->history[PERIOD_H4]['bars']);
      $currentBar = null;
      if ($sizeH4Bars)
         $currentBar =& $this->history[PERIOD_H4]['bars'][$sizeH4Bars-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten H4-Bar erkennen
         if ($bar['time'] >= $this->history[PERIOD_H4]['currentCloseTime']) {
            // neue Bar beginnen
            $bar['time']                                 -= $bar['time'] % 4*HOURS;
            $this->history[PERIOD_H4]['currentCloseTime'] = $bar['time'] + 4*HOURS;
            $this->history[PERIOD_H4]['bars'          ][] = $bar;
            $currentBar =& $this->history[PERIOD_H4]['bars'][$sizeH4Bars++];

            if ($sizeH4Bars > $this->bufferSize)
               $sizeH4Bars -= $this->flushBars(PERIOD_H4, $this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der D1-History des Sets weitere M1-Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToD1(array $bars) {
      $sizeD1Bars = sizeOf($this->history[PERIOD_D1]['bars']);
      $currentBar = null;
      if ($sizeD1Bars)
         $currentBar =& $this->history[PERIOD_D1]['bars'][$sizeD1Bars-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten D1-Bar erkennen
         if ($bar['time'] >= $this->history[PERIOD_D1]['currentCloseTime']) {
            // neue Bar beginnen
            $bar['time']                                 -= $bar['time'] % DAY;
            $this->history[PERIOD_D1]['currentCloseTime'] = $bar['time'] + 1*DAY;
            $this->history[PERIOD_D1]['bars'          ][] = $bar;
            $currentBar =& $this->history[PERIOD_D1]['bars'][$sizeD1Bars++];

            if ($sizeD1Bars > $this->bufferSize)
               $sizeD1Bars -= $this->flushBars(PERIOD_D1, $this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der W1-History des Sets weitere M1-Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToW1(array $bars) {
      $sizeW1Bars =  sizeOf($this->history[PERIOD_W1]['bars']);
      $currentBar = null;
      if ($sizeW1Bars)
         $currentBar =& $this->history[PERIOD_W1]['bars'][$sizeW1Bars-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten W1-Bar erkennen
         if ($bar['time'] >= $this->history[PERIOD_W1]['currentCloseTime']) {
            // neue Bar beginnen
            $dow = (int) gmDate('w', $bar['time']);
            $bar['time']                                 -= $bar['time']%DAY + (($dow+6)%7)*DAYS;  // 00:00, Montag (Operator-Precedence beachten)
            $this->history[PERIOD_W1]['currentCloseTime'] = $bar['time'] + 1*WEEK;
            $this->history[PERIOD_W1]['bars'          ][] = $bar;
            $currentBar =& $this->history[PERIOD_W1]['bars'][$sizeW1Bars++];

            if ($sizeW1Bars > $this->bufferSize)
               $sizeW1Bars -= $this->flushBars(PERIOD_W1, $this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der MN1-History des Sets weitere M1-Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToMN1(array $bars) {
      $sizeMN1Bars =  sizeOf($this->history[PERIOD_MN1]['bars']);
      $currentBar = null;
      if ($sizeMN1Bars)
         $currentBar =& $this->history[PERIOD_MN1]['bars'][$sizeMN1Bars-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten MN1-Bar erkennen
         if ($bar['time'] >= $this->history[PERIOD_MN1]['currentCloseTime']) {
            // neue Bar beginnen
            $dom = (int) gmDate('d', $bar['time']);
            $m   = (int) gmDate('m', $bar['time']);
            $y   = (int) gmDate('Y', $bar['time']);
            $bar['time']                                  -= $bar['time']%DAYS + ($dom-1)*DAYS;    // 00:00, 1. des Monats (Operator-Precedence beachten)
            $this->history[PERIOD_MN1]['currentCloseTime'] = gmMkTime(0, 0, 0, $m+1, 1, $y);       // 00:00, 1. des nächsten Monats
            $this->history[PERIOD_MN1]['bars'          ][] = $bar;
            $currentBar =& $this->history[PERIOD_MN1]['bars'][$sizeMN1Bars++];

            if ($sizeMN1Bars > $this->bufferSize)
               $sizeMN1Bars -= $this->flushBars(PERIOD_MN1, $this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Schreibt eine Anzahl Bars eines Timeframes in die entsprechende History-Datei und löscht sie aus dem Barbuffer.
    *
    * @param  int $timeframe - Timeframe der zu schreibenden Bars
    * @param  int $count     - Anzahl zu schreibender Bars (default: alle Bars)
    *
    * @return int - Anzahl der geschriebenen und aus dem Buffer gelöschten Bars
    */
   private function flushBars($timeframe, $count=null) {
      if (!is_int($timeframe))                  throw new IllegalTypeException('Illegal type of parameter $timeframe: '.getType($timeframe));
      if (!MT4::isBuiltinTimeframe($timeframe)) throw new plInvalidArgumentException('Invalid parameter $timeframe: '.$timeframe);
      if (is_null($count)) $count = PHP_INT_MAX;
      if (!is_int($count))                      throw new IllegalTypeException('Illegal type of parameter $count: '.getType($count));
      if ($count < 0)                           throw new plInvalidArgumentException('Invalid parameter $count: '.$count);

      $size = sizeOf($this->history[$timeframe]['bars']);
      $todo = min($size, $count);
      if (!$todo)
         return 0;

      $hFile   = $this->history[$timeframe]['hFile'];
      $divider = pow(10, $this->digits);
      $i = 0;

      foreach ($this->history[$timeframe]['bars'] as $i => $bar) {
         $T = $bar['time' ];
         $O = $bar['open' ]/$divider;
         $H = $bar['high' ]/$divider;
         $L = $bar['low'  ]/$divider;
         $C = $bar['close']/$divider;
         $V = $bar['ticks'];

         MT4::addHistoryBar400($hFile, $this->digits, $T, $O, $H, $L, $C, $V);
         if ($i+1 == $todo)
            break;
      }

      if ($todo == $size) $this->history[$timeframe]['bars'] = array();
      else                $this->history[$timeframe]['bars'] = array_slice($this->history[$timeframe]['bars'], $todo);

      return $todo;
   }


   /**
    *
    */
   public function showBuffer() {
      echoPre(NL);
      foreach ($this->history as $timeframe => $data) {
         if (isSet($data['bars'])) {
            $bars = $data['bars'];
            $size = sizeOf($bars);
            $firstBar = $lastBar = null;
            if ($size) {
               if (isSet($bars[0]['time']) && $bars[$size-1]['time']) {
                  $firstBar = '  from='.gmDate('d-M-Y H:i', $bars[0      ]['time']);
                  $lastBar  = '  to='  .gmDate('d-M-Y H:i', $bars[$size-1]['time']);
               }
               else {
                  $firstBar = $lastBar = '  invalid';
                  echoPre($bars);
               }
            }
            echoPre('history['. str_pad(MyFX::timeframeDescription($timeframe), 3, ' ', STR_PAD_RIGHT).'] => '.str_pad($size, 5, ' ', STR_PAD_LEFT).' bar'.($size==1?' ':'s').$firstBar.($size>1? $lastBar:''));
         }
         else {
            echoPre('history['. str_pad(MyFX::timeframeDescription($timeframe), 3, ' ', STR_PAD_RIGHT).'] => '.printFormatted($data, true));
         }
      }
      echoPre(NL);
   }
}
