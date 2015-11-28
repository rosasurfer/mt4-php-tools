<?php
/**
 * ChainedHistorySet
 *
 * Ein optimiertes HistorySet zur Erzeugung einer vollständigen MetaTrader-History aus M1-Daten.
 */
class ChainedHistorySet extends Object {


   protected /*string    */ $symbol;
   protected /*MYFX_BAR[]*/ $history;


   /**
    * Constructor
    *
    * Erzeugt ein neues HistorySet.
    *
    * @param  string $symbol - Symbol der HistorySet-Daten
    */
   public function __construct($symbol) {
      if (!is_string($symbol))                 throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
      if (!strLen($symbol))                    throw new plInvalidArgumentException('Invalid parameter $symbol: ""');
      if (strLen($symbol) > MAX_SYMBOL_LENGTH) throw new plInvalidArgumentException('Invalid parameter $symbol: "'.$symbol.'" (max '.MAX_SYMBOL_LENGTH.' characters)');

      $this->symbol = $symbol;

      $this->history[PERIOD_M1 ] = array();                          // Timeframes initialisieren
      $this->history[PERIOD_M5 ] = array();
      $this->history[PERIOD_M15] = array();
      $this->history[PERIOD_M30] = array();
      $this->history[PERIOD_H1 ] = array();
      $this->history[PERIOD_H4 ] = array();
      $this->history[PERIOD_D1 ] = array();
      $this->history[PERIOD_W1 ] = array();
      $this->history[PERIOD_MN1] = array();
   }


   /**
    * Fügt dem HistorySet M1-Bardaten hinzu. Die Bardaten werden am Ende der Timeframes gespeichert.
    *
    * @param  MYFX_BAR[] $bars - Array von MyFX-Bars
    *
    * @return bool - Erfolgsstatus
    */
   public function addM1Bars(array $bars) {
      foreach ($bars as $i => &$bar) {
         $this->addM1Bar($bar);
      }
   }


   /**
    * Fügt dem HistorySet M1-Bardaten hinzu. Die Bardaten werden am Ende der Timeframes gespeichert.
    *
    * @param  MYFX_BAR $bar - einzelne MyFX-Bar
    *
    * @return bool - Erfolgsstatus
    */
   private function addM1Bar(array &$bar) {
      if (!$this->addToM1 ($bar)) return false;
      if (!$this->addToM5 ($bar)) return false;
      if (!$this->addToM15($bar)) return false;
      if (!$this->addToM30($bar)) return false;
      if (!$this->addToH1 ($bar)) return false;
      if (!$this->addToH4 ($bar)) return false;
      if (!$this->addToD1 ($bar)) return false;
      if (!$this->addToW1 ($bar)) return false;
      if (!$this->addToMN1($bar)) return false;

      // Daten ab bestimmter Baranzahl in Datei speichern
      return true;
   }


   /**
    * Fügt der M1-History eine Bar hinzu.
    *
    * @param  MYFX_BAR $bar - einzelne MyFX-Bar
    *
    * @return bool - Erfolgsstatus
    */
   private function addToM1(array $bar) {
      $this->history[PERIOD_M1][] = $bar;
      return true;
   }


   /**
    * Fügt der M5-History eine M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar - einzelne MyFX-Bar
    *
    * @return bool - Erfolgsstatus
    */
   private function addToM5(array $bar) {
      static $currentOpenTime, $currentCloseTime, $i=-1;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($i==-1 || $time>=$currentCloseTime) {
         // neue Bar beginnen
         $currentOpenTime  = $time - $time%5*MINUTES;
         $currentCloseTime = $currentOpenTime + 5*MINUTES;
         $bar['time']      = $currentOpenTime;
         $this->history[PERIOD_M5][] = $bar; $i++;
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[PERIOD_M5][$i];
         $currentBar['high' ]  = max($currentBar['high'], $bar['high']);
         $currentBar['low'  ]  = min($currentBar['low' ], $bar['low' ]);
         $currentBar['close']  = $bar['close'];
         $currentBar['ticks'] += $bar['ticks'];
      }
      return true;
   }


   /**
    * Fügt der M15-History eine M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar - einzelne MyFX-Bar
    *
    * @return bool - Erfolgsstatus
    */
   private function addToM15(array $bar) {
      static $currentOpenTime, $currentCloseTime, $i=-1;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($i==-1 || $time>=$currentCloseTime) {
         // neue Bar beginnen
         $currentOpenTime  = $time - $time%15*MINUTES;
         $currentCloseTime = $currentOpenTime + 15*MINUTES;
         $bar['time']      = $currentOpenTime;
         $this->history[PERIOD_M15][] = $bar; $i++;
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[PERIOD_M15][$i];
         $currentBar['high' ]  = max($currentBar['high'], $bar['high']);
         $currentBar['low'  ]  = min($currentBar['low' ], $bar['low' ]);
         $currentBar['close']  = $bar['close'];
         $currentBar['ticks'] += $bar['ticks'];
      }
      return true;
   }


   /**
    * Fügt der M30-History eine M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar - einzelne MyFX-Bar
    *
    * @return bool - Erfolgsstatus
    */
   private function addToM30(array $bar) {
      static $currentOpenTime, $currentCloseTime, $i=-1;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($i==-1 || $time>=$currentCloseTime) {
         // neue Bar beginnen
         $currentOpenTime  = $time - $time%30*MINUTES;
         $currentCloseTime = $currentOpenTime + 30*MINUTES;
         $bar['time']      = $currentOpenTime;
         $this->history[PERIOD_M30][] = $bar; $i++;
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[PERIOD_M30][$i];
         $currentBar['high' ]  = max($currentBar['high'], $bar['high']);
         $currentBar['low'  ]  = min($currentBar['low' ], $bar['low' ]);
         $currentBar['close']  = $bar['close'];
         $currentBar['ticks'] += $bar['ticks'];
      }
      return true;
   }


   /**
    * Fügt der H1-History eine M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar - einzelne MyFX-Bar
    *
    * @return bool - Erfolgsstatus
    */
   private function addToH1(array $bar) {
      static $currentOpenTime, $currentCloseTime, $i=-1;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($i==-1 || $time>=$currentCloseTime) {
         // neue Bar beginnen
         $currentOpenTime  = $time - $time%HOURS;
         $currentCloseTime = $currentOpenTime + 1*HOUR;
         $bar['time']      = $currentOpenTime;
         $this->history[PERIOD_H1][] = $bar; $i++;
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[PERIOD_H1][$i];
         $currentBar['high' ]  = max($currentBar['high'], $bar['high']);
         $currentBar['low'  ]  = min($currentBar['low' ], $bar['low' ]);
         $currentBar['close']  = $bar['close'];
         $currentBar['ticks'] += $bar['ticks'];
      }
      return true;
   }


   /**
    * Fügt der H4-History eine M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar - einzelne MyFX-Bar
    *
    * @return bool - Erfolgsstatus
    */
   private function addToH4(array $bar) {
      static $currentOpenTime, $currentCloseTime, $i=-1;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($i==-1 || $time>=$currentCloseTime) {
         // neue Bar beginnen
         $currentOpenTime  = $time - $time%4*HOURS;
         $currentCloseTime = $currentOpenTime + 4*HOURS;
         $bar['time']      = $currentOpenTime;
         $this->history[PERIOD_H4][] = $bar; $i++;
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[PERIOD_H4][$i];
         $currentBar['high' ]  = max($currentBar['high'], $bar['high']);
         $currentBar['low'  ]  = min($currentBar['low' ], $bar['low' ]);
         $currentBar['close']  = $bar['close'];
         $currentBar['ticks'] += $bar['ticks'];
      }
      return true;
   }


   /**
    * Fügt der D1-History eine M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar - einzelne MyFX-Bar
    *
    * @return bool - Erfolgsstatus
    */
   private function addToD1(array $bar) {
      static $currentOpenTime, $currentCloseTime, $i=-1;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($i==-1 || $time>=$currentCloseTime) {
         // neue Bar beginnen
         $currentOpenTime  = $time - $time%DAYS;
         $currentCloseTime = $currentOpenTime + 1*DAY;
         $bar['time']      = $currentOpenTime;
         $this->history[PERIOD_D1][] = $bar; $i++;
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[PERIOD_D1][$i];
         $currentBar['high' ]  = max($currentBar['high'], $bar['high']);
         $currentBar['low'  ]  = min($currentBar['low' ], $bar['low' ]);
         $currentBar['close']  = $bar['close'];
         $currentBar['ticks'] += $bar['ticks'];
      }
      return true;
   }


   /**
    * Fügt der W1-History eine M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar - einzelne MyFX-Bar
    *
    * @return bool - Erfolgsstatus
    */
   private function addToW1(array $bar) {
      static $currentOpenTime, $currentCloseTime, $i=-1;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($i==-1 || $time>=$currentCloseTime) {
         // neue Bar beginnen
         $dow = iDate('w', $time);
         $currentOpenTime  = $time - $time%DAYS - (($dow+6)%7)*DAYS; // 00:00, Montag
         $currentCloseTime = $currentOpenTime + 1*WEEK;
         $bar['time']      = $currentOpenTime;
         $this->history[PERIOD_W1][] = $bar; $i++;
      }
      else {
         // letzte Bar aktualisieren ('time' und 'open' unverändert)
         $currentBar =& $this->history[PERIOD_W1][$i];
         $currentBar['high' ]  = max($currentBar['high'], $bar['high']);
         $currentBar['low'  ]  = min($currentBar['low' ], $bar['low' ]);
         $currentBar['close']  = $bar['close'];
         $currentBar['ticks'] += $bar['ticks'];
      }
      return true;
   }


   /**
    * Fügt der MN1-History eine M1-Bar hinzu.
    *
    * @param  MYFX_BAR $bar - einzelne MyFX-Bar
    *
    * @return bool - Erfolgsstatus
    */
   private function addToMN1(array $bar) {
      static $currentOpenTime, $currentCloseTime, $i=-1;
      $time = $bar['time'];

      // Wechsel zur nächsten Bar erkennen
      if ($i==-1 || $time>=$currentCloseTime) {
         // neue Bar beginnen
         $dom = iDate('d', $time);
         $m   = iDate('m', $time);
         $y   = iDate('Y', $time);
         $currentOpenTime  = $time - $time%DAYS - ($dom-1)*DAYS;     // 00:00, 1. des Monats
         $currentCloseTime = gmMkTime(0, 0, 0, $m+1, 1, $y);         // 00:00, 1. des nächsten Monats
         $bar['time']      = $currentOpenTime;
         $this->history[PERIOD_MN1][] = $bar; $i++;
      }
      else {
         // letzte Bar aktualisieren
         $currentBar =& $this->history[PERIOD_MN1][$i];
       //$currentBar['time' ]  = ...                                 // unverändert
       //$currentBar['open' ]  = ...                                 // unverändert
         $currentBar['high' ]  = max($currentBar['high'], $bar['high']);
         $currentBar['low'  ]  = min($currentBar['low' ], $bar['low' ]);
         $currentBar['close']  = $bar['close'];
         $currentBar['ticks'] += $bar['ticks'];
      }
      return true;
   }


   /**
    *
    */
   public function showBuffer() {
      echoPre(NL);
      foreach ($this->history as $timeframe => &$bars) {
         $size = sizeOf($bars);
         $firstBar = $size ? date('d-M-Y H:i', $bars[0      ]['time']):null;
         $lastBar  = $size ? date('d-M-Y H:i', $bars[$size-1]['time']):null;
         echoPre('history['. str_pad(MyFX::timeframeDescription($timeframe), 3, ' ', STR_PAD_RIGHT).'] => '.str_pad($size, 5, ' ', STR_PAD_LEFT).' bar'.($size==1?' ':'s').($firstBar?'  from='.$firstBar:'').($size>1?'  to='.$lastBar:''));
      }
      echoPre(NL);
   }
}
