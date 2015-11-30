<?php
/**
 * HistorySet
 *
 * Ein HistorySet zur Erzeugung einer vollständigen MetaTrader-History aus M1-Daten.
 */
class HistorySet extends Object {


   protected /*string*/     $symbol;
   protected /*string*/     $description;
   protected /*int   */     $digits;
   protected /*int   */     $format;
   protected /*string*/     $directory;

   protected /*MYFX_BAR[]*/ $history;
   protected /*int       */ $flushLimit = 10000;                     // maximale Anzahl von ungespeicherten Bars


   /**
    * Constructor
    *
    * Erzeugt ein neues HistorySet mit den angegebenen Daten.
    *
    * @param  string $symbol      - Symbol der HistorySet-Daten
    * @param  string $description - Beschreibung des Symbols
    * @param  int    $digits      - Digits der Datenreihe
    * @param  int    $format      - Speicherformat der Datenreihe:
    *                               • 400 - wie MetaTrader bis Build 509
    *                               • 401 - wie MetaTrader ab Build 510
    * @param  string $directory   - Serververzeichnis, in dem die Historydateien des Sets gespeichert und das Symbol in die Datei
    *                               "symbols.raw" eingetragen werden (default: aktuelles Verzeichnis).
    */
   public function __construct($symbol, $description, $digits, $format, $directory=null) {
      if (!is_string($symbol))                                throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
      if (!strLen($symbol))                                   throw new plInvalidArgumentException('Invalid parameter $symbol: ""');
      if (strLen($symbol) > MAX_SYMBOL_LENGTH)                throw new plInvalidArgumentException('Invalid parameter $symbol: "'.$symbol.'" (max '.MAX_SYMBOL_LENGTH.' characters)');
      if (!is_null($description) && !is_string($description)) throw new IllegalTypeException('Illegal type of parameter $description: '.getType($description));
      if (!is_int($digits))                                   throw new IllegalTypeException('Illegal type of parameter $digits: '.getType($digits));
      if ($digits < 0)                                        throw new plInvalidArgumentException('Invalid parameter $digits: '.$digits);
      if (!is_int($format))                                   throw new IllegalTypeException('Illegal type of parameter $format: '.getType($format));
      if ($format!=400 && $format!=401)                       throw new plInvalidArgumentException('Invalid parameter $format: '.$format.' (needs to be 400 or 401)');
      if (!is_null($directory)) {
         if (!is_string($directory))                          throw new IllegalTypeException('Illegal type of parameter $directory: '.getType($directory));
         if (!strLen($directory))                             throw new plInvalidArgumentException('Invalid parameter $directory: ""');
      }

      $this->symbol      = $symbol;
      $this->description = strLeft($description, 63);                   // ein zu langer String wird gekürzt
      $this->digits      = $digits;
      $this->format      = $format;
      $this->directory   = $directory;
      if (is_null($directory))
         $this->directory = MyFX::getConfigPath('myfx.data_directory').'/history/mt4/MyFX-Dukascopy';
      mkDirWritable($this->directory);

      $this->history[PERIOD_M1 ] = array();                             // Timeframes initialisieren
      $this->history[PERIOD_M5 ] = array();
      $this->history[PERIOD_M15] = array();
      $this->history[PERIOD_M30] = array();
      $this->history[PERIOD_H1 ] = array();
      $this->history[PERIOD_H4 ] = array();
      $this->history[PERIOD_D1 ] = array();
      $this->history[PERIOD_W1 ] = array();
      $this->history[PERIOD_MN1] = array();

      // neuen HistoryHeader initialisieren
      $hh = MT4::createHistoryHeader();
      $hh['format'     ] = $this->format;
      $hh['description'] = $this->description;
      $hh['symbol'     ] = $this->symbol;
      $hh['digits'     ] = $this->digits;
      $hh['timezoneId' ] = TIMEZONE_ID_FXT;

      // alle HistoryFiles erzeugen bzw. zurücksetzen und Header neuschreiben
      foreach ($this->history as $timeframe => $data) {
         $file  = $this->directory.'/'.$symbol.$timeframe.'.hst';
         $hFile = fOpen($file, 'wb');
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
      foreach ($this->history as $timeframe => &$data) {                      // data by reference
         if (isSet($data['hFile']) && is_resource($hFile=&$data['hFile'])) {  // hFile by reference
            $this->flushBars($timeframe);
            $hTmp=$hFile; $hFile=null;
            fClose($hTmp);
         }
      }
   }


   /**
    * Fügt dem HistorySet M1-Bardaten hinzu. Die Bardaten werden am Ende der Timeframes gespeichert.
    *
    * @param  MYFX_BAR[] $bars - Array von MyFX-Bars
    */
   public function addM1Bars(array $bars) {
      foreach ($bars as $bar) {
         $this->addToM1 ($bar);
         $this->addToM5 ($bar);
         $this->addToM15($bar);
         $this->addToM30($bar);
         $this->addToH1 ($bar);
         $this->addToH4 ($bar);
         $this->addToD1 ($bar);
         $this->addToW1 ($bar);
         $this->addToMN1($bar);
      }
   }


   /**
    * Fügt der M1-History des Sets eine Bar hinzu.
    *
    * @param  MYFX_BAR $bar
    */
   private function addToM1(array $bar) {
      static $i=-1, $timeframe=PERIOD_M1;
      $this->history[$timeframe]['bars'][] = $bar; $i++;

      if ($i >= $this->flushLimit)
         $i -= $this->flushBars($timeframe, $this->flushLimit);
   }


   /**
    * Fügt der M5-History des Sets die Daten einer M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar
    */
   private function addToM5(array $bar) {
      static $i=-1, $currentOpenTime, $currentCloseTime=PHP_INT_MIN, $timeframe=PERIOD_M5;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($time >= $currentCloseTime) {
         // neue Bar beginnen
         $currentOpenTime  = $time - $time%5*MINUTES;
         $currentCloseTime = $currentOpenTime + 5*MINUTES;
         $bar['time']      = $currentOpenTime;
         $this->history[$timeframe]['bars'][] = $bar; $i++;

         if ($i >= $this->flushLimit) {
            //exit();
            $i -= $this->flushBars($timeframe, $this->flushLimit);
         }
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[$timeframe]['bars'][$i];
         if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
         if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                 $currentBar['close']  = $bar['close'];
                                                 $currentBar['ticks'] += $bar['ticks'];
      }
   }


   /**
    * Fügt der M15-History des Sets die Daten einer M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar
    */
   private function addToM15(array $bar) {
      static $i=-1, $currentOpenTime, $currentCloseTime=PHP_INT_MIN, $timeframe=PERIOD_M15;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($time >= $currentCloseTime) {
         // neue Bar beginnen
         $currentOpenTime  = $time - $time%15*MINUTES;
         $currentCloseTime = $currentOpenTime + 15*MINUTES;
         $bar['time']      = $currentOpenTime;
         $this->history[$timeframe]['bars'][] = $bar; $i++;

         if ($i >= $this->flushLimit)
            $i -= $this->flushBars($timeframe, $this->flushLimit);
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[$timeframe]['bars'][$i];
         if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
         if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                 $currentBar['close']  = $bar['close'];
                                                 $currentBar['ticks'] += $bar['ticks'];
      }
   }


   /**
    * Fügt der M30-History des Sets die Daten einer M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar
    */
   private function addToM30(array $bar) {
      static $i=-1, $currentOpenTime, $currentCloseTime=PHP_INT_MIN, $timeframe=PERIOD_M30;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($time >= $currentCloseTime) {
         // neue Bar beginnen
         $currentOpenTime  = $time - $time%30*MINUTES;
         $currentCloseTime = $currentOpenTime + 30*MINUTES;
         $bar['time']      = $currentOpenTime;
         $this->history[$timeframe]['bars'][] = $bar; $i++;

         if ($i >= $this->flushLimit)
            $i -= $this->flushBars($timeframe, $this->flushLimit);
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[$timeframe]['bars'][$i];
         if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
         if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                 $currentBar['close']  = $bar['close'];
                                                 $currentBar['ticks'] += $bar['ticks'];
      }
   }


   /**
    * Fügt der H1-History des Sets die Daten einer M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar
    */
   private function addToH1(array $bar) {
      static $i=-1, $currentOpenTime, $currentCloseTime=PHP_INT_MIN, $timeframe=PERIOD_H1;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($time >= $currentCloseTime) {
         // neue Bar beginnen
         $currentOpenTime  = $time - $time%HOURS;
         $currentCloseTime = $currentOpenTime + 1*HOUR;
         $bar['time']      = $currentOpenTime;
         $this->history[$timeframe]['bars'][] = $bar; $i++;

         if ($i >= $this->flushLimit)
            $i -= $this->flushBars($timeframe, $this->flushLimit);
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[$timeframe]['bars'][$i];
         if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
         if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                 $currentBar['close']  = $bar['close'];
                                                 $currentBar['ticks'] += $bar['ticks'];
      }
   }


   /**
    * Fügt der H4-History des Sets die Daten einer M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar
    */
   private function addToH4(array $bar) {
      static $i=-1, $currentOpenTime, $currentCloseTime=PHP_INT_MIN, $timeframe=PERIOD_H4;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($time >= $currentCloseTime) {
         // neue Bar beginnen
         $currentOpenTime  = $time - $time%4*HOURS;
         $currentCloseTime = $currentOpenTime + 4*HOURS;
         $bar['time']      = $currentOpenTime;
         $this->history[$timeframe]['bars'][] = $bar; $i++;

         if ($i >= $this->flushLimit)
            $i -= $this->flushBars($timeframe, $this->flushLimit);
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[$timeframe]['bars'][$i];
         if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
         if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                 $currentBar['close']  = $bar['close'];
                                                 $currentBar['ticks'] += $bar['ticks'];
      }
   }


   /**
    * Fügt der D1-History des Sets die Daten einer M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar
    */
   private function addToD1(array $bar) {
      static $i=-1, $currentOpenTime, $currentCloseTime=PHP_INT_MIN, $timeframe=PERIOD_D1;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($time >= $currentCloseTime) {
         // neue Bar beginnen
         $currentOpenTime  = $time - $time%DAYS;
         $currentCloseTime = $currentOpenTime + 1*DAY;
         $bar['time']      = $currentOpenTime;
         $this->history[$timeframe]['bars'][] = $bar; $i++;

         if ($i >= $this->flushLimit)
            $i -= $this->flushBars($timeframe, $this->flushLimit);
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[$timeframe]['bars'][$i];
         if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
         if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                 $currentBar['close']  = $bar['close'];
                                                 $currentBar['ticks'] += $bar['ticks'];
      }
   }


   /**
    * Fügt der W1-History des Sets die Daten einer M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar
    */
   private function addToW1(array $bar) {
      static $i=-1, $currentOpenTime, $currentCloseTime=PHP_INT_MIN, $timeframe=PERIOD_W1;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($time >= $currentCloseTime) {
         // neue Bar beginnen
         $dow = iDate('w', $time);
         $currentOpenTime  = $time - $time%DAYS - (($dow+6)%7)*DAYS; // 00:00, Montag
         $currentCloseTime = $currentOpenTime + 1*WEEK;
         $bar['time']      = $currentOpenTime;
         $this->history[$timeframe]['bars'][] = $bar; $i++;

         if ($i >= $this->flushLimit)
            $i -= $this->flushBars($timeframe, $this->flushLimit);
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[$timeframe]['bars'][$i];
         if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
         if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                 $currentBar['close']  = $bar['close'];
                                                 $currentBar['ticks'] += $bar['ticks'];
      }
   }


   /**
    * Fügt der MN1-History des Sets die Daten einer M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar
    */
   private function addToMN1(array $bar) {
      static $i=-1, $currentOpenTime, $currentCloseTime=PHP_INT_MIN, $timeframe=PERIOD_MN1;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($time >= $currentCloseTime) {
         // neue Bar beginnen
         $dom = iDate('d', $time);
         $m   = iDate('m', $time);
         $y   = iDate('Y', $time);
         $currentOpenTime  = $time - $time%DAYS - ($dom-1)*DAYS;     // 00:00, 1. des Monats
         $currentCloseTime = gmMkTime(0, 0, 0, $m+1, 1, $y);         // 00:00, 1. des nächsten Monats
         $bar['time']      = $currentOpenTime;
         $this->history[$timeframe]['bars'][] = $bar; $i++;

         if ($i >= $this->flushLimit)
            $i -= $this->flushBars($timeframe, $this->flushLimit);
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[$timeframe]['bars'][$i];
         if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
         if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                 $currentBar['close']  = $bar['close'];
                                                 $currentBar['ticks'] += $bar['ticks'];
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
      if (!is_int($timeframe))                throw new IllegalTypeException('Illegal type of parameter $timeframe: '.getType($timeframe));
      if (!isSet($this->history[$timeframe])) throw new plInvalidArgumentException('Invalid parameter $timeframe: '.$timeframe);
      if (is_null($count)) $count = PHP_INT_MAX;
      if (!is_int($count))                    throw new IllegalTypeException('Illegal type of parameter $count: '.getType($count));
      if ($count < 0)                         throw new plInvalidArgumentException('Invalid parameter $count: '.$count);

      $size = sizeOf($this->history[$timeframe]['bars']);
      $todo = min($size, $count);
      if (!$todo)
         return 0;

      $hFile   = $this->history[$timeframe]['hFile'];
      $divisor = pow(10, $this->digits);
      $i = 0;

      foreach ($this->history[$timeframe]['bars'] as $i => $bar) {
         $T = $bar['time' ];
         $O = $bar['open' ]/$divisor;
         $H = $bar['high' ]/$divisor;
         $L = $bar['low'  ]/$divisor;
         $C = $bar['close']/$divisor;
         $V = $bar['ticks'];

         MT4::addHistoryBar400($hFile, $T, $O, $H, $L, $C, $V);
         if (++$i >= $todo)
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
            $firstBar = $size ? date('d-M-Y H:i', $bars[0      ]['time']):null;
            $lastBar  = $size ? date('d-M-Y H:i', $bars[$size-1]['time']):null;
            echoPre('history['. str_pad(MyFX::timeframeDescription($timeframe), 3, ' ', STR_PAD_RIGHT).'] => '.str_pad($size, 5, ' ', STR_PAD_LEFT).' bar'.($size==1?' ':'s').($firstBar?'  from='.$firstBar:'').($size>1?'  to='.$lastBar:''));
         }
      }
      echoPre(NL);
   }
}
