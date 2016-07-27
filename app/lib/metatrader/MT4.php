<?php
use rosasurfer\ministruts\core\StaticClass;

use rosasurfer\ministruts\exception\IllegalTypeException;
use rosasurfer\ministruts\exception\InvalidArgumentException;
use rosasurfer\ministruts\exception\RuntimeException;


/**
 * MetaTrader related functionality
 */
class MT4 extends StaticClass {

   /**
    * Struct-Size des FXT-Headers (Tester-Tickdateien "*.fxt")
    */
   const FXT_HEADER_SIZE = 728;

   /**
    * Struct-Size einer History-Bar Version 400 (History-Dateien "*.hst")
    */
   const HISTORY_BAR_400_SIZE = 44;

   /**
    * Struct-Size einer History-Bar Version 401 (History-Dateien "*.hst")
    */
   const HISTORY_BAR_401_SIZE = 60;

   /**
    * Struct-Size eines Symbols (Symboldatei "symbols.raw")
    */
   const SYMBOL_SIZE = 1936;

   /**
    * Struct-Size eine Symbolgruppe (Symbolgruppendatei "symgroups.raw")
    */
   const SYMBOL_GROUP_SIZE = 80;

   /**
    * Struct-Size eines SelectedSymbol (Symboldatei "symbols.sel")
    */
   const SYMBOL_SELECTED_SIZE = 128;

   /**
    * Höchstlänge eines MetaTrader-Symbols
    */
   const MAX_SYMBOL_LENGTH = 11;


   /**
    * MetaTrader Standard-Timeframes
    */
   public static $timeframes = array(PERIOD_M1, PERIOD_M5, PERIOD_M15, PERIOD_M30, PERIOD_H1, PERIOD_H4, PERIOD_D1, PERIOD_W1, PERIOD_MN1);


   /**
    * History-Bar v400
    *
    * @see  Definition in MT4Expander.dll::Expander.h
    */
   private static $tpl_HistoryBar400 = array('time'  => 0,
                                             'open'  => 0,
                                             'high'  => 0,
                                             'low'   => 0,
                                             'close' => 0,
                                             'ticks' => 0);

   /**
    * History-Bar v401
    *
    * @see  Definition in MT4Expander.dll::Expander.h
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
    * Formatbeschreibung eines struct SYMBOL.
    *
    * @see  Definition in MT4Expander.dll::Expander.h
    * @see  MT4::SYMBOL_getUnpackFormat() zum Verwenden als unpack()-Formatstring
    */
   private static $SYMBOL_formatStr = '
      /a12   name                      // szchar
      /a54   description               // szchar
      /a10   origin                    // szchar (custom)
      /a12   altName                   // szchar
      /a12   baseCurrency              // szchar
      /V     group                     // uint
      /V     digits                    // uint
      /V     tradeMode                 // uint
      /V     backgroundColor           // uint
      /V     arrayKey                  // uint
      /V     id                        // uint
      /x32   unknown1:char32
      /x208  unknownM1:char208
      /x208  unknownM5:char208
      /x208  unknownM15:char208
      /x208  unknownM30:char208
      /x208  unknownH1:char208
      /x208  unknownH4:char208
      /x208  unknownD1:char208
      /x16   unknown2:char16
      /V     unknown3:int
      /V     unknown4:int
      /x4    _alignment1
      /d     unknown5:double
      /H24   unknown6:char12
      /V     spread                    // uint
      /H16   unknown7:char8
      /V     swapEnabled               // bool
      /V     swapType                  // uint
      /d     swapLongValue             // double
      /d     swapShortValue            // double
      /V     swapTripleRolloverDay     // uint
      /x4    _alignment2
      /d     contractSize              // double
      /x16   unknown8:char16
      /V     stopDistance              // uint
      /x8    unknown9:char8
      /x4    _alignment3
      /d     marginInit                // double
      /d     marginMaintenance         // double
      /d     marginHedged              // double
      /d     marginDivider             // double
      /d     pointSize                 // double
      /d     pointsPerUnit             // double
      /x24   unknown10:char24
      /a12   marginCurrency            // szchar
      /x104  unknown11:char104
      /V     unknown12:int
   ';


   /**
    * Formatbeschreibung eines struct HISTORY_BAR_400.
    *
    * @see  Definition in MT4Expander.dll::Expander.h
    * @see  MT4::BAR_getUnpackFormat() zum Verwenden als unpack()-Formatstring
    */
   private static $BAR_400_formatStr = '
      /V   time            // uint
      /d   open            // double
      /d   low             // double
      /d   high            // double
      /d   close           // double
      /d   ticks           // double
   ';


   /**
    * Formatbeschreibung eines struct HISTORY_BAR_401.
    *
    * @see  Definition in MT4Expander.dll::Expander.h
    * @see  MT4::BAR_getUnpackFormat() zum Verwenden als unpack()-Formatstring
    */
   private static $BAR_401_formatStr = '
      /V   time            // uint (int64)
      /x4
      /d   open            // double
      /d   high            // double
      /d   low             // double
      /d   close           // double
      /V   ticks           // uint (uint64)
      /x4
      /V   spread          // uint
      /V   volume          // uint (uint64)
      /x4
   ';


   /**
    * Gibt die Namen der Felder eines struct SYMBOL zurück.
    *
    * @return string[] - Array mit SYMBOL-Feldern
    */
   public static function SYMBOL_getFields() {
      static $fields = null;

      if (is_null($fields)) {
         $lines = explode("\n", self::$SYMBOL_formatStr);
         foreach ($lines as $i => &$line) {
            $line = strLeftTo($line, '//');                             // Kommentare entfernen
            $line = trim(strRightFrom(trim($line), ' '));               // Format-Code entfernen
            if (!strLen($line) || strStartsWith($line, '_alignment'))   // Leerzeilen und Alignment-Felder löschen
               unset($lines[$i]);
         } unset($line);
         $fields = array_values($lines);                                // Indizes neuordnen
      }
      return $fields;
   }


   /**
    * Gibt den Formatstring zum Entpacken eines struct SYMBOL zurück.
    *
    * @return string - unpack()-Formatstring
    */
   public static function SYMBOL_getUnpackFormat() {
      static $format = null;

      if (is_null($format)) {
         $lines = explode("\n", self::$SYMBOL_formatStr);
         foreach ($lines as $i => &$line) {
            $line = strLeftTo($line, '//');                          // Kommentare entfernen
         } unset($line);
         $format = join('', $lines);

         // since PHP 5.5.0: The 'a' code now retains trailing NULL bytes, 'Z' replaces the former 'a'.
         if (PHP_VERSION >= '5.5.0') $format = str_replace('/a', '/Z', $format);

         $format = preg_replace('/\s/', '', $format);                // remove white space
         if ($format[0] == '/') $format = strRight($format, -1);     // remove leading format separator
      }
      return $format;
   }


   /**
    * Gibt den Formatstring zum Packen eines struct HISTORY_BAR_400 oder HISTORY_BAR_401 zurück.
    *
    * @param  int $version - Barversion: 400 oder 401
    *
    * @return string - pack()-Formatstring
    */
   public static function BAR_getPackFormat($version) {
      if (!is_int($version))              throw new IllegalTypeException('Illegal type of parameter $version: '.getType($version));
      if ($version!=400 && $version!=401) throw new MetaTraderException('version.unsupported: Invalid parameter $version: '.$version.' (must be 400 or 401)');

      static $format_400 = null;
      static $format_401 = null;

      if (is_null(${'format_'.$version})) {
         $lines = explode("\n", self::${'BAR_'.$version.'_formatStr'});
         foreach ($lines as &$line) {
            $line = strLeftTo($line, '//');                          // Kommentare entfernen
         } unset($line);

         $values = explode('/', join('', $lines));                   // in Format-Codes zerlegen

         foreach ($values as $i => &$value) {
            $value = trim($value);
            $value = strLeftTo($value, ' ');                         // dem Code folgende Bezeichner entfernen
            if (!strLen($value))
               unset($values[$i]);
         } unset($value);
         $format = join('', $values);
         ${'format_'.$version} = $format;
      }
      return ${'format_'.$version};
   }


   /**
    * Gibt den Formatstring zum Entpacken eines struct HISTORY_BAR_400 oder HISTORY_BAR_401 zurück.
    *
    * @param  int $version - Barversion: 400 oder 401
    *
    * @return string - unpack()-Formatstring
    */
   public static function BAR_getUnpackFormat($version) {
      if (!is_int($version))              throw new IllegalTypeException('Illegal type of parameter $version: '.getType($version));
      if ($version!=400 && $version!=401) throw new MetaTraderException('version.unsupported: Invalid parameter $version: '.$version.' (must be 400 or 401)');

      static $format_400 = null;
      static $format_401 = null;

      if (is_null(${'format_'.$version})) {
         $lines = explode("\n", self::${'BAR_'.$version.'_formatStr'});
         foreach ($lines as $i => &$line) {
            $line = strLeftTo($line, '//');                          // Kommentare entfernen
         } unset($line);
         $format = join('', $lines);

         // since PHP 5.5.0: The 'a' code now retains trailing NULL bytes, 'Z' replaces the former 'a'.
         if (PHP_VERSION >= '5.5.0') $format = str_replace('/a', '/Z', $format);

         $format = preg_replace('/\s/', '', $format);                // remove white space
         if ($format[0] == '/') $format = strRight($format, -1);     // remove leading format separator
         ${'format_'.$version} = $format;
      }
      return ${'format_'.$version};
   }


   /**
    * Schreibt eine einzelne Bar in die zum Handle gehörende Datei. Die Bardaten werden vorm Schreiben validiert.
    *
    * @param  resource $hFile  - File-Handle eines History-Files, muß Schreibzugriff erlauben
    * @param  int      $digits - Digits des Symbols (für Normalisierung)
    * @param  int      $time   - Timestamp der Bar
    * @param  double   $open
    * @param  double   $high
    * @param  double   $low
    * @param  double   $close
    * @param  int      $ticks
    *
    * @return int - Anzahl der geschriebenen Bytes
    */
   public static function writeHistoryBar400($hFile, $digits, $time, $open, $high, $low, $close, $ticks) {
      // Bardaten normalisieren...
      $open  = round($open , $digits);
      $high  = round($high , $digits);
      $low   = round($low  , $digits);
      $close = round($close, $digits);

      // ...vorm Schreiben nochmals prüfen (nicht mit min()/max(), da nicht performant)
      if ($open  > $high ||
          $open  < $low  ||                  // aus (H >= O && O >= L) folgt (H >= L)
          $close > $high ||
          $close < $low  ||
         !$ticks) throw new RuntimeException('Illegal history bar of '.gmDate('D, d-M-Y', $time).": O=$open H=$high L=$low C=$close V=$ticks");

      // Bardaten in Binärstring umwandeln
      $data = pack('Vddddd', $time,    // V
                             $open,    // d
                             $low,     // d
                             $high,    // d
                             $close,   // d
                             $ticks);  // d

      // pack() unterstützt keinen expliziten Little-Endian-Double, die Byte-Order der Doubles muß ggf. manuell reversed werden.
      static $isLittleEndian = null; is_null($isLittleEndian) && $isLittleEndian=isLittleEndian();
      if (!$isLittleEndian) {
         $time  =        substr($data,  0, 4);
         $open  = strRev(substr($data,  4, 8));
         $low   = strRev(substr($data, 12, 8));
         $high  = strRev(substr($data, 20, 8));
         $close = strRev(substr($data, 28, 8));
         $ticks = strRev(substr($data, 36, 8));
         $data  = $time.$open.$low.$high.$close.$ticks;
      }
      return fWrite($hFile, $data);
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
         $positions = OpenPosition::dao()->listBySignal($signal);   // aufsteigend sortiert nach {OpenTime,Ticket}

         // Datei schreiben
         mkDirWritable(dirName($openFileName), 0755);
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
         catch (\Exception $ex) {
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
            $positions = ClosedPosition::dao()->listBySignal($signal); // aufsteigend sortiert nach {CloseTime,OpenTime,Ticket}

            // Datei schreiben
            mkDirWritable(dirName($closedFileName), 0755);
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
            catch (\Exception $ex) {
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
    * Ob ein String ein gültiges MetaTrader-Symbol darstellt. Insbesondere darf ein Symbol keine Leerzeichen enthalten.
    *
    * @return bool
    */
   public static function isValidSymbol($string) {
      static $pattern = '/^[a-z0-9_.#&\'~-]+$/i';
      return is_string($string) && strLen($string) && strLen($string) <= self::MAX_SYMBOL_LENGTH && preg_match($pattern, $string);
   }


   /**
    * Ob der angegebene Wert einen MetaTrader-Standard-Timeframe darstellt.
    *
    * @param  int $value - Timeframe-ID
    *
    * @return bool
    */
   public static function isStdTimeframe($value) {
      if (is_int($value)) {
         switch ($value) {
            case PERIOD_M1 : return true;
            case PERIOD_M5 : return true;
            case PERIOD_M15: return true;
            case PERIOD_M30: return true;
            case PERIOD_H1 : return true;
            case PERIOD_H4 : return true;
            case PERIOD_D1 : return true;
            case PERIOD_W1 : return true;
            case PERIOD_MN1: return true;
         }
      }
      return false;
   }


   /**
    * Ob der angegebene Wert die gültige Beschreibung eines MetaTrader-Timeframes darstellt.
    *
    * @param  string $value - Beschreibung
    *
    * @return bool
    */
   public static function isTimeframeDescription($value) {
      if (is_string($value)) {
         if (strStartsWith($value, 'PERIOD_'))
            $value = strRight($value, -7);

         switch ($value) {
            case 'M1' : return true;
            case 'M5' : return true;
            case 'M15': return true;
            case 'M30': return true;
            case 'H1' : return true;
            case 'H4' : return true;
            case 'D1' : return true;
            case 'W1' : return true;
            case 'MN1': return true;
         }
      }
      return false;
   }


   /**
    * Konvertiert den angegebenen Timeframe-Bezeichner in die entsprechende Timeframe-ID.
    *
    * @param  string $value - Bezeichner
    *
    * @return int - Timeframe-ID
    */
   public static function timeframeToId($value) {
      if (!is_string($value)) throw new IllegalTypeException('Illegal type of parameter $value: '.$value.' ('.getType($value).')');

      if (strStartsWith($value, 'PERIOD_'))
         $value = strRight($value, -7);

      switch ($value) {
         case 'M1' : return PERIOD_M1;
         case 'M5' : return PERIOD_M5;
         case 'M15': return PERIOD_M15;
         case 'M30': return PERIOD_M30;
         case 'H1' : return PERIOD_H1;
         case 'H4' : return PERIOD_H4;
         case 'D1' : return PERIOD_D1;
         case 'W1' : return PERIOD_W1;
         case 'MN1': return PERIOD_MN1;
      }
      throw new InvalidArgumentException('Invalid argument $value: '.$value.' (not a timeframe)');
   }
}
