<?php
/**
 * MT4/MQL related functionality
 */
class MT4 extends StaticClass {

   /**
    * Erweiterter History-Header:
    *
    * typedef struct _HISTORY_HEADER {
    *   int  version;            //     4      => hh[ 0]    // database version
    *   char description[64];    //    64      => hh[ 1]    // copyright info
    *   char symbol[12];         //    12      => hh[17]    // symbol name
    *   int  period;             //     4      => hh[20]    // symbol timeframe
    *   int  digits;             //     4      => hh[21]    // amount of digits after decimal point
    *   int  syncMark;           //     4      => hh[22]    // server database sync marker (timestamp)
    *   int  prevSyncMark;       //     4      => hh[23]    // previous server database sync marker (timestamp)
    *   int  periodFlag;         //     4      => hh[24]    // whether hh.period is a minutes or a seconds timeframe
    *   int  timezone;           //     4      => hh[25]    // timezone id
    *   int  reserved[11];       //    44      => hh[26]
    * } HISTORY_HEADER, hh;      // = 148 byte = int[37]
    */

   /**
    * typedef struct _RATEINFO {
    *   int    time;             //     4      =>  ri[0]    // bar time
    *   double open;             //     8      =>  ri[1]
    *   double low;              //     8      =>  ri[3]
    *   double high;             //     8      =>  ri[5]
    *   double close;            //     8      =>  ri[7]
    *   double vol;              //     8      =>  ri[9]
    * } RATEINFO, ri;            //  = 44 byte = int[11]
    */

   private static $tpl_HistoryHeader = array('version'      => 400,
                                             'description'  => 'mt4.rosasurfer.com',
                                             'symbol'       => "\0",
                                             'period'       => 0,
                                             'digits'       => 0,
                                             'syncMark'     => 0,
                                             'prevSyncMark' => 0,
                                             'periodFlag'   => 0,
                                             'timezone'     => 0,
                                             'reserved'     => "\0");

   private static $tpl_RateInfo = array('time'  => 0,
                                        'open'  => 0,
                                        'high'  => 0,
                                        'low'   => 0,
                                        'close' => 0,
                                        'vol'   => 0);

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
    * @param  mixed[]  $hh    - zu setzende Headerdaten (fehlende Werte werden ggf. durch Defaultwerte ergänzt)
    *
    * @return int - Anzahl der geschriebenen Bytes
    */
   public static function writeHistoryHeader($hFile, array $hh) {
      if (getType($hFile) != 'resource') throw new IllegalTypeException('Illegal type of parameter $hFile: '.$hFile.' ('.getType($hFile).')');
      if (!$hh)                          throw new plInvalidArgumentException('Invalid parameter $hh: '.print_r($hh, true));

      $hh = array_merge(self::$tpl_HistoryHeader, $hh);
      $hh['timezone'] = 0;
      // TODO: Struct-Daten validieren

      fSeek($hFile, 0);
      return fWrite($hFile, pack('Va64a12VVVVVVa44', $hh['version'     ],     // V
                                                     $hh['description' ],     // a64
                                                     $hh['symbol'      ],     // a12
                                                     $hh['period'      ],     // V
                                                     $hh['digits'      ],     // V
                                                     $hh['syncMark'    ],     // V
                                                     $hh['prevSyncMark'],     // V
                                                     $hh['periodFlag'  ],     // V
                                                     $hh['timezone'    ],     // V
                                                     $hh['reserved'    ]));   // a44
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
    * @param  int      $vol
    *
    * @return int - Anzahl der geschriebenen Bytes
    */
   public static function addHistoryBar($hFile, $time, $open, $high, $low, $close, $vol) {
      if (getType($hFile) != 'resource') throw new IllegalTypeException('Illegal type of parameter $hFile: '.$hFile.' ('.getType($hFile).')');

      return fWrite($hFile, pack('Vddddd', $time, $open, $low, $high, $close, $vol));
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
            fWrite($hFile, ";Symbol.Ticket   = Type, Lots, OpenTime           , OpenPrice, TakeProfit, StopLoss, Commission, Swap, MagicNumber, Comment\n");

            // (3.2) Daten schreiben
            foreach ($positions as $position) {
               /*
               ;Symbol.Ticket   = Type, Lots, OpenTime           , OpenPrice, TakeProfit, StopLoss, Commission, Swap, MagicNumber, Comment
               AUDUSD.428259953 = Sell, 1.20, 2014.04.10 07:08:46,   1.62166,           ,         ,          0,    0,            ,
               AUDUSD.428256273 = Buy , 1.50, 2014.04.23 11:51:32,     1.605,           ,         ,        0.1,    0,            ,
               AUDUSD.428253857 = Buy , 1.50, 2014.04.24 08:00:25,   1.60417,           ,         ,          0,    0,            ,
               */
               $format      = "%-16s = %-4s, %4.2F, %s, %9s, %10s, %8s, %10s, %4s, %11s, %s\n";
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
               fWrite($hFile, ";Symbol.Ticket   = Type, Lots, OpenTime           , OpenPrice, CloseTime          , ClosePrice, TakeProfit, StopLoss, Commission, Swap,   Profit, MagicNumber, Comment\n");

               // (4.2.2) Daten schreiben
               foreach ($positions as $position) {
                  /*
                  ;Symbol.Ticket   = Type, Lots, OpenTime           , OpenPrice, CloseTime          , ClosePrice, TakeProfit, StopLoss, Commission, Swap,   Profit, MagicNumber, Comment
                  AUDUSD.428259953 = Sell, 1.20, 2014.04.10 07:08:46,   1.62166, 2014.04.10 07:08:46,    1.62166,           ,         ,          0,    0, -1234.55,            ,
                  AUDUSD.428256273 = Buy , 1.50, 2014.04.23 11:51:32,     1.605, 2014.04.23 11:51:32,      1.605,           ,         ,        0.1,    0,      0.1,            ,
                  AUDUSD.428253857 = Buy , 1.50, 2014.04.24 08:00:25,   1.60417, 2014.04.24 08:00:25,    1.60417,           ,         ,          0,    0,        0,            ,
                  */
                  $format      = "%-16s = %-4s, %4.2F, %s, %9s, %s, %10s, %10s, %8s, %10s, %4s, %8s, %11s, %s\n";
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
}
?>
