<?php
/**
 * MT4Helper
 */
class MT4Helper extends StaticClass {

   /**
    * Erweiterter History-Header:
    *
    * typedef struct _HISTORY_HEADER {
    *   int  version;            //     4      => hh[ 0]    // database version
    *   char description[64];    //    64      => hh[ 1]    // ie. copyright info
    *   char symbol[12];         //    12      => hh[17]    // symbol name
    *   int  period;             //     4      => hh[20]    // symbol timeframe
    *   int  digits;             //     4      => hh[21]    // amount of digits after decimal point
    *   int  syncMark;           //     4      => hh[22]    // server database sync marker (timestamp)
    *   int  prevSyncMark;       //     4      => hh[23]    // previous server database sync marker (timestamp)
    *   int  periodFlag;         //     4      => hh[24]    // whether hh.period is a minute or a seconds timeframe
    *   int  timezone;           //     4      => hh[25]    // timezone id
    *   int  reserved[12];       //    44      => hh[26]
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
    * @param  resource $hFile - File-Handle, muß Schreibzugriff erlauben
    * @param  mixed[]  $hh    - zu setzende Headerdaten (fehlende Werte werden ggf. durch Defaultwerte ergänzt)
    *
    * @return int - Anzahl der geschriebenen Bytes
    */
   public static function writeHistoryHeader($hFile, array $hh) {
      if (getType($hFile) != 'resource') {
         if (getType($hFile) == 'unknown type') throw new InvalidArgumentException('Invalid file handle in parameter $hFile: '.(int)$hFile);
                                                throw new IllegalTypeException('Illegal type of argument $hFile: '.getType($hFile));
      }
      if (!$hh) throw new InvalidArgumentException('Invalid parameter $hh: '.print_r($hh, true));

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
}
?>
