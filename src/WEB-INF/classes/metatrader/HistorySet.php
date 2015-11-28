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
         echoPre('history['. str_pad(MyFX::timeframeToStr($timeframe), 10, ' ', STR_PAD_RIGHT).'] => '.str_pad($size, 5, ' ', STR_PAD_LEFT).' bar'.($size==1?'':'s').($firstBar?'  from='.$firstBar:'').($size>1?'  to='.$lastBar:''));
      }
      echoPre(NL);
   }
}
