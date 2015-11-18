<?php
/**
 * MT4/MQL related functionality
 */
class MT4 extends StaticClass {

   /**
    * History-Header
    *
    * @see  Definition in Expander.dll::Expander.h
    */
   private static $tpl_HistoryHeader = array('format'      => 0,
                                             'description' => "\0",
                                             'symbol'      => "\0",
                                             'period'      => 0,
                                             'digits'      => 0,
                                             'syncMark'    => 0,
                                             'lastSync'    => 0,
                                             'timezoneId'  => 0,
                                             'reserved'    => 0);

   /**
    * struct HISTORY_BAR_400 {
    *    uint   time;                     //     4             // open time
    *    double open;                     //     8
    *    double low;                      //     8
    *    double high;                     //     8
    *    double close;                    //     8
    *    double ticks;                    //     8             // immer Ganzzahl
    * };                                  //  = 44 bytes
    */
   private static $tpl_HistoryBar400 = array('time'  => 0,
                                             'open'  => 0,
                                             'high'  => 0,
                                             'low'   => 0,
                                             'close' => 0,
                                             'ticks' => 0);

   /**
    * struct HISTORY_BAR_401 {
    *    int64  time;                     //     8             // open time
    *    double open;                     //     8
    *    double high;                     //     8
    *    double low;                      //     8
    *    double close;                    //     8
    *    uint64 ticks;                    //     8
    *    int    spread;                   //     4             // unbenutzt
    *    uint64 volume;                   //     8             // unbenutzt
    * };                                  //  = 60 bytes
    */
   private static $tpl_HistoryBar401 = array('time'   => 0,
                                             'open'   => 0,
                                             'high'   => 0,
                                             'low'    => 0,
                                             'close'  => 0,
                                             'ticks'  => 0,
                                             'spread' => 0,
                                             'volume' => 0);

   /**
    * Erzeugt eine mit Defaultwerten gefüllte HistoryHeader-Struktur und gibt sie zurück.
    *
    * @return array - struct HISTORY_HEADER
    */
   public static function createHistoryHeader() {
      return self ::$tpl_HistoryHeader;
   }


   /**
    * Schreibt einen HistoryHeader mit den angegebenen Daten in die zum Handle gehörende Datei.
    *
    * @param  resource $hFile - File-Handle eines History-Files, muß Schreibzugriff erlauben
    * @param  mixed[]  $hh    - zu setzende Headerdaten (nicht angegebene Werte werden durch Defaultwerte ergänzt)
    *
    * @return int - Anzahl der geschriebenen Bytes
    */
   public static function writeHistoryHeader($hFile, array $hh) {
      if (!is_resource($hFile)) throw new IllegalTypeException('Illegal type of parameter $hFile: '.$hFile.' ('.getType($hFile).')');
      if (!$hh)                 throw new plInvalidArgumentException('Invalid parameter $hh: '.print_r($hh, true));

      $hh = array_merge(self::$tpl_HistoryHeader, $hh);

      // TODO: Struct-Daten validieren

      fSeek($hFile, 0);
      return fWrite($hFile, pack('Va64a12VVVVVa48', $hh['format'     ],    // V
                                                    $hh['description'],    // a64
                                                    $hh['symbol'     ],    // a12
                                                    $hh['period'     ],    // V
                                                    $hh['digits'     ],    // V
                                                    $hh['syncMark'   ],    // V
                                                    $hh['lastSync'   ],    // V
                                                    $hh['timezoneId' ],    // V
                                                    $hh['reserved'   ]));  // a48
   }


   /**
    * Fügt eine einzelne Bar an die zum Handle gehörende Datei an.
    *
    * @param  resource $hFile - File-Handle eines History-Files, muß Schreibzugriff erlauben
    * @param  int      $time  - Timestamp der Bar
    * @param  float    $open
    * @param  float    $high
    * @param  float    $low
    * @param  float    $close
    * @param  int      $ticks
    *
    * @return int - Anzahl der geschriebenen Bytes
    */
   public static function addHistoryBar($hFile, $time, $open, $high, $low, $close, $ticks) {
      if (!is_resource($hFile)) throw new IllegalTypeException('Illegal type of parameter $hFile: '.$hFile.' ('.getType($hFile).')');

      return fWrite($hFile, pack('Vddddd', $time,     // V
                                           $open,     // d
                                           $low,      // d
                                           $high,     // d
                                           $close,    // d
                                           $ticks));  // d
   }


   /**
    * Aktualisiert die Daten-Files des angegebenen Signals (Datenbasis für MT4-Terminals).
    *
    * @param  Signal $signal        - Signalalias
    * @param  bool   $openUpdates   - ob beim letzten Abgleich der Datenbank Änderungen an den offenen Positionen festgestellt wurden
    * @param  bool   $closedUpdates - ob beim letzten Abgleich der Datenbank Änderungen an der Trade-History festgestellt wurden
    */
   public static function updateDataFiles(Signal $signal, $openUpdates, $closedUpdates) {
      if (!is_bool($openUpdates))   throw new IllegalTypeException('Illegal type of parameter $openUpdates: '.getType($openUpdates));
      if (!is_bool($closedUpdates)) throw new IllegalTypeException('Illegal type of parameter $closedUpdates: '.getType($closedUpdates));


      // (1) Datenverzeichnis bestimmen
      static $dataDirectory = null;
      if (is_null($dataDirectory)) $dataDirectory = MyFX ::getConfigPath('myfx.data_directory');


      // (2) Prüfen, ob OpenTrades- und History-Datei existieren
      $alias          = $signal->getAlias();
      $openFileName   = $dataDirectory.'/simpletrader/'.$alias.'_open.ini';
      $closedFileName = $dataDirectory.'/simpletrader/'.$alias.'_closed.ini';
      $isOpenFile     = is_file($openFileName);    //$isOpenFile   = false;
      $isClosedFile   = is_file($closedFileName);  //$isClosedFile = false;


      // (3) Open-Datei neu schreiben, wenn die offenen Positionen modifiziert wurden oder die Datei nicht existiert
      if ($openUpdates || !$isOpenFile) {
         $positions = OpenPosition ::dao()->listBySignal($signal);   // aufsteigend sortiert nach {OpenTime,Ticket}

         // Verzeichnis ggf. erzeugen
         $directory = dirName($openFileName);
         if (is_file($directory))                                   throw new plInvalidArgumentException('Cannot write to directory "'.$directory.'" (is a file)');
         if (!is_dir($directory) && !mkDir($directory, 0755, true)) throw new plInvalidArgumentException('Cannot create directory "'.$directory.'"');
         if (!is_writable($directory))                              throw new plInvalidArgumentException('Cannot write to directory "'.$directory.'"');

         // Datei schreiben
         $hFile = $ex = null;
         try {
            $hFile = fOpen($openFileName, 'wb');
            // (3.1) Header schreiben
            fWrite($hFile, "[SimpleTrader.$alias]\n");
            fWrite($hFile, ";Symbol.Ticket   = Type,  Lots, OpenTime           , OpenPrice, TakeProfit, StopLoss, Commission, Swap, MagicNumber, Comment\n");

            // (3.2) Daten schreiben
            foreach ($positions as $position) {
               /*
               ;Symbol.Ticket   = Type,  Lots, OpenTime           , OpenPrice, TakeProfit, StopLoss, Commission, Swap, MagicNumber, Comment
               AUDUSD.428259953 = Sell,  1.20, 2014.04.10 07:08:46,   1.62166,           ,         ,          0,    0,            ,
               AUDUSD.428256273 = Buy , 10.50, 2014.04.23 11:51:32,     1.605,           ,         ,        0.1,    0,            ,
               AUDUSD.428253857 = Buy ,  1.50, 2014.04.24 08:00:25,   1.60417,           ,         ,          0,    0,            ,
               */
               $format      = "%-16s = %-4s, %5.2F, %s, %9s, %10s, %8s, %10s, %4s, %11s, %s\n";
               $key         = $position->getSymbol().'.'.$position->getTicket();
               $type        = $position->getTypeDescription();
               $lots        = $position->getLots();
               $openTime    = $position->getOpenTime('Y.m.d H:i:s');
               $openPrice   = $position->getOpenPrice();
               $takeProfit  = $position->getTakeProfit();
               $stopLoss    = $position->getStopLoss();
               $commission  = $position->getCommission();
               $swap        = $position->getSwap();
               $magicNumber = $position->getMagicNumber();
               $comment     = $position->getComment();
               fWrite($hFile, sprintf($format, $key, $type, $lots, $openTime, $openPrice, $takeProfit, $stopLoss, $commission, $swap, $magicNumber, $comment));
            }
            fClose($hFile);
         }
         catch (Exception $ex) {
            if (is_resource($hFile)) fClose($hFile);                 // Unter Windows kann die Datei u.U. (versionsabhängig) nicht im Exception-Handler gelöscht werden
         }                                                           // (gesperrt, da das Handle im Exception-Kontext dupliziert wird). Das Handle muß daher innerhalb UND
         if ($ex) {                                                  // außerhalb des Handlers geschlossen werden, erst dann läßt sich die Datei unter Windows löschen.
            if (is_resource($hFile))                    fClose($hFile);
            if (!$isOpenFile && is_file($openFileName)) unlink($openFileName);
            throw $ex;
         }
      }


      $isClosedFile = false;     // vorerst schreiben wir die History jedesmal komplett neu


      // (4) TradeHistory-Datei neu schreiben, wenn die TradeHistory modifiziert wurde oder die Datei nicht existiert
      if ($closedUpdates || !$isClosedFile) {
         if ($isClosedFile) {
            // (4.1) History-Datei aktualisieren
         }
         else {
            // (4.2) History-Datei komplett neuschreiben
            $positions = ClosedPosition ::dao()->listBySignal($signal); // aufsteigend sortiert nach {CloseTime,OpenTime,Ticket}

            // Verzeichnis ggf. erzeugen
            $directory = dirName($closedFileName);
            if (is_file($directory))                                   throw new plInvalidArgumentException('Cannot write to directory "'.$directory.'" (is a file)');
            if (!is_dir($directory) && !mkDir($directory, 0755, true)) throw new plInvalidArgumentException('Cannot create directory "'.$directory.'"');
            if (!is_writable($directory))                              throw new plInvalidArgumentException('Cannot write to directory "'.$directory.'"');

            // Datei schreiben
            $hFile = $ex = null;
            try {
               $hFile = fOpen($closedFileName, 'wb');
               // (4.2.1) Header schreiben
               fWrite($hFile, "[SimpleTrader.$alias]\n");
               fWrite($hFile, ";Symbol.Ticket   = Type,  Lots, OpenTime           , OpenPrice, CloseTime          , ClosePrice, TakeProfit, StopLoss, Commission, Swap,   Profit, MagicNumber, Comment\n");

               // (4.2.2) Daten schreiben
               foreach ($positions as $position) {
                  /*
                  ;Symbol.Ticket   = Type,  Lots, OpenTime           , OpenPrice, CloseTime          , ClosePrice, TakeProfit, StopLoss, Commission, Swap,   Profit, MagicNumber, Comment
                  AUDUSD.428259953 = Sell,  1.20, 2014.04.10 07:08:46,   1.62166, 2014.04.10 07:08:46,    1.62166,           ,         ,          0,    0, -1234.55,            ,
                  AUDUSD.428256273 = Buy , 10.50, 2014.04.23 11:51:32,     1.605, 2014.04.23 11:51:32,      1.605,           ,         ,        0.1,    0,      0.1,            ,
                  AUDUSD.428253857 = Buy ,  1.50, 2014.04.24 08:00:25,   1.60417, 2014.04.24 08:00:25,    1.60417,           ,         ,          0,    0,        0,            ,
                  */
                  $format      = "%-16s = %-4s, %5.2F, %s, %9s, %s, %10s, %10s, %8s, %10s, %4s, %8s, %11s, %s\n";
                  $key         = $position->getSymbol().'.'.$position->getTicket();
                  $type        = $position->getTypeDescription();
                  $lots        = $position->getLots();
                  $openTime    = $position->getOpenTime('Y.m.d H:i:s');
                  $openPrice   = $position->getOpenPrice();
                  $closeTime   = $position->getCloseTime('Y.m.d H:i:s');
                  $closePrice  = $position->getClosePrice();
                  $takeProfit  = $position->getTakeProfit();
                  $stopLoss    = $position->getStopLoss();
                  $commission  = $position->getCommission();
                  $swap        = $position->getSwap();
                  $profit      = $position->getProfit();
                  $magicNumber = $position->getMagicNumber();
                  $comment     = $position->getComment();
                  fWrite($hFile, sprintf($format, $key, $type, $lots, $openTime, $openPrice, $closeTime, $closePrice, $takeProfit, $stopLoss, $commission, $swap, $profit, $magicNumber, $comment));
               }
               fClose($hFile);
            }
            catch (Exception $ex) {
               if (is_resource($hFile)) fClose($hFile);              // Unter Windows kann die Datei u.U. (versionsabhängig) nicht im Exception-Handler gelöscht werden
            }                                                        // (gesperrt, da das Handle im Exception-Kontext dupliziert wird). Das Handle muß daher innerhalb UND
            if ($ex) {                                               // außerhalb des Handlers geschlossen werden, erst dann läßt sich die Datei unter Windows löschen.
               if (is_resource($hFile))                        fClose($hFile);
               if (!$isClosedFile && is_file($closedFileName)) unlink($closedFileName);
               throw $ex;
            }
         }
      }
   }


   /**
    * Gibt die lesbare Konstante eines Timeframe-Codes zurück.
    *
    * @param  int period - Timeframe-Code bzw. Anzahl der Minuten je Chart-Bar (default: aktuelle Periode)
    *
    * @return string
    */
   public static function periodToStr($period) {
      if (!is_int($period)) throw new IllegalTypeException('Illegal type of parameter $period: '.getType($period));

      switch ($period) {
         case PERIOD_M1 : return "PERIOD_M1";       // 1 minute
         case PERIOD_M5 : return "PERIOD_M5";       // 5 minutes
         case PERIOD_M15: return "PERIOD_M15";      // 15 minutes
         case PERIOD_M30: return "PERIOD_M30";      // 30 minutes
         case PERIOD_H1 : return "PERIOD_H1";       // 1 hour
         case PERIOD_H4 : return "PERIOD_H4";       // 4 hour
         case PERIOD_D1 : return "PERIOD_D1";       // 1 day
         case PERIOD_W1 : return "PERIOD_W1";       // 1 week
         case PERIOD_MN1: return "PERIOD_MN1";      // 1 month
         case PERIOD_Q1 : return "PERIOD_Q1";       // 1 quarter
      }
      return "$period";
   }


   /**
    * Gibt die Beschreibung eines Timeframe-Codes zurück.
    *
    * @param  int period - Timeframe-Code bzw. Anzahl der Minuten je Chart-Bar (default: aktuelle Periode)
    *
    * @return string
    */
   public static function periodDescription($period) {
      if (!is_int($period)) throw new IllegalTypeException('Illegal type of parameter $period: '.getType($period));

      switch ($period) {
         case PERIOD_M1 : return "M1";      //      1  1 minute
         case PERIOD_M5 : return "M5";      //      5  5 minutes
         case PERIOD_M15: return "M15";     //     15  15 minutes
         case PERIOD_M30: return "M30";     //     30  30 minutes
         case PERIOD_H1 : return "H1";      //     60  1 hour
         case PERIOD_H4 : return "H4";      //    240  4 hour
         case PERIOD_D1 : return "D1";      //   1440  daily
         case PERIOD_W1 : return "W1";      //  10080  weekly
         case PERIOD_MN1: return "MN1";     //  43200  monthly
         case PERIOD_Q1 : return "Q1";      // 129600  3 months (a quarter)
      }
      return "$period";
   }
}
?>
