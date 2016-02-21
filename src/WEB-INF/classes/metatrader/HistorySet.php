<?php
/**
 * Ein HistorySet zur Erzeugung einer vollständigen MetaTrader-History aus M1-Daten.
 */
class HistorySet extends Object {


   protected /*string*/  $symbol;
   protected /*string*/  $description;
   protected /*int   */  $digits;
   protected /*int   */  $format;
   protected /*string*/  $directory;

   protected /*array[]*/ $history;
   protected /*int    */ $flushLimit = 10000;         // maximale Anzahl von ungespeicherten Bars


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

      $this->history[PERIOD_M1 ]['bars']             = array();      // Timeframes initialisieren
      $this->history[PERIOD_M5 ]['bars']             = array();
      $this->history[PERIOD_M15]['bars']             = array();
      $this->history[PERIOD_M30]['bars']             = array();
      $this->history[PERIOD_H1 ]['bars']             = array();
      $this->history[PERIOD_H4 ]['bars']             = array();
      $this->history[PERIOD_D1 ]['bars']             = array();
      $this->history[PERIOD_W1 ]['bars']             = array();
      $this->history[PERIOD_MN1]['bars']             = array();

      $this->history[PERIOD_M1 ]['currentCloseTime'] = PHP_INT_MIN;
      $this->history[PERIOD_M5 ]['currentCloseTime'] = PHP_INT_MIN;
      $this->history[PERIOD_M15]['currentCloseTime'] = PHP_INT_MIN;
      $this->history[PERIOD_M30]['currentCloseTime'] = PHP_INT_MIN;
      $this->history[PERIOD_H1 ]['currentCloseTime'] = PHP_INT_MIN;
      $this->history[PERIOD_H4 ]['currentCloseTime'] = PHP_INT_MIN;
      $this->history[PERIOD_D1 ]['currentCloseTime'] = PHP_INT_MIN;
      $this->history[PERIOD_W1 ]['currentCloseTime'] = PHP_INT_MIN;
      $this->history[PERIOD_MN1]['currentCloseTime'] = PHP_INT_MIN;

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
      // Ein Destructor darf während des Shutdowns keine Exception werfen.
      try {
         foreach ($this->history as $timeframe => &$data) {                      // data by reference
            if (isSet($data['hFile']) && is_resource($hFile=&$data['hFile'])) {  // hFile by reference
               $this->flushBars($timeframe);
               $hTmp=$hFile; $hFile=null;
               fClose($hTmp);
            }
         }
      }
      catch (Exception $ex) {
         Logger::handleException($ex, $inShutdownOnly=true);
         throw $ex;
      }
   }


   /**
    * Fügt dem HistorySet M1-Bardaten hinzu. Die Bardaten werden am Ende der Timeframes gespeichert.
    *
    * @param  MYFX_BAR[] $bars - Array von MyFX-Bars
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
    * @param  MYFX_BAR[] $bars
    */
   private function addToM1(array $bars) {
      $this->history[PERIOD_M1]['bars'] = array_merge($this->history[PERIOD_M1]['bars'], $bars);

      $sizeM1Bars = sizeOf($this->history[PERIOD_M1]['bars']);
      $lastBar    = $this->history[PERIOD_M1]['bars'][$sizeM1Bars-1];
      $this->history[PERIOD_M1]['currentCloseTime'] = $lastBar['time'] + 1*MINUTE;

      if ($sizeM1Bars > $this->flushLimit)
         $sizeM1Bars -= $this->flushBars(PERIOD_M1, $this->flushLimit);
   }


   /**
    * Fügt der M5-History des Sets weitere M1-Daten hinzu.
    *
    * @param  MYFX_BAR[] $bars
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

            if ($sizeM5Bars > $this->flushLimit)
               $sizeM5Bars -= $this->flushBars(PERIOD_M5, $this->flushLimit);
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
    * @param  MYFX_BAR[] $bars
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

            if ($sizeM15Bars > $this->flushLimit)
               $sizeM15Bars -= $this->flushBars(PERIOD_M15, $this->flushLimit);
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
    * @param  MYFX_BAR[] $bars
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

            if ($sizeM30Bars > $this->flushLimit)
               $sizeM30Bars -= $this->flushBars(PERIOD_M30, $this->flushLimit);
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
    * @param  MYFX_BAR[] $bars
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

            if ($sizeH1Bars > $this->flushLimit)
               $sizeH1Bars -= $this->flushBars(PERIOD_H1, $this->flushLimit);
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
    * @param  MYFX_BAR[] $bars
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

            if ($sizeH4Bars > $this->flushLimit)
               $sizeH4Bars -= $this->flushBars(PERIOD_H4, $this->flushLimit);
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
    * @param  MYFX_BAR[] $bars
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

            if ($sizeD1Bars > $this->flushLimit)
               $sizeD1Bars -= $this->flushBars(PERIOD_D1, $this->flushLimit);
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
    * @param  MYFX_BAR[] $bars
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
            $dow = iDate('w', $bar['time']);
            $bar['time']                                 -= $bar['time']%DAY + (($dow+6)%7)*DAYS;  // 00:00, Montag (Operator-Precedence beachten)
            $this->history[PERIOD_W1]['currentCloseTime'] = $bar['time'] + 1*WEEK;
            $this->history[PERIOD_W1]['bars'          ][] = $bar;
            $currentBar =& $this->history[PERIOD_W1]['bars'][$sizeW1Bars++];

            if ($sizeW1Bars > $this->flushLimit)
               $sizeW1Bars -= $this->flushBars(PERIOD_W1, $this->flushLimit);
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
    * @param  MYFX_BAR[] $bars
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
            $dom = iDate('d', $bar['time']);
            $m   = iDate('m', $bar['time']);
            $y   = iDate('Y', $bar['time']);
            $bar['time']                                  -= $bar['time']%DAYS + ($dom-1)*DAYS;    // 00:00, 1. des Monats (Operator-Precedence beachten)
            $this->history[PERIOD_MN1]['currentCloseTime'] = gmMkTime(0, 0, 0, $m+1, 1, $y);       // 00:00, 1. des nächsten Monats
            $this->history[PERIOD_MN1]['bars'          ][] = $bar;
            $currentBar =& $this->history[PERIOD_MN1]['bars'][$sizeMN1Bars++];

            if ($sizeMN1Bars > $this->flushLimit)
               $sizeMN1Bars -= $this->flushBars(PERIOD_MN1, $this->flushLimit);
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
      if (!is_int($timeframe))                throw new IllegalTypeException('Illegal type of parameter $timeframe: '.getType($timeframe));
      if (!isSet($this->history[$timeframe])) throw new plInvalidArgumentException('Invalid parameter $timeframe: '.$timeframe);
      if (is_null($count)) $count = PHP_INT_MAX;
      if (!is_int($count))                    throw new IllegalTypeException('Illegal type of parameter $count: '.getType($count));
      if ($count < 0)                         throw new plInvalidArgumentException('Invalid parameter $count: '.$count);

      if (!isSet($this->history[$timeframe]['bars']))
         return 0;

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
                  $firstBar = '  from='.date('d-M-Y H:i', $bars[0      ]['time']);
                  $lastBar  = '  to='  .date('d-M-Y H:i', $bars[$size-1]['time']);
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
