<?php
/**
 * MT4HistoryFileHelper
 *
 * Helferklasse zum Lesen/Schreiben von HST-Files.
 */
class MT4HistoryFileHelper extends StaticClass {

   /*
   typedef struct _HISTORY_HEADER {
     int  version;            //     4      => hh[ 0]    // database version
     char description[64];    //    64      => hh[ 1]    // ie. copyright info
     char symbol[12];         //    12      => hh[17]    // symbol name
     int  period;             //     4      => hh[20]    // symbol timeframe
     int  digits;             //     4      => hh[21]    // amount of digits after decimal point
     int  timesign;           //     4      => hh[22]    // creation timestamp
     int  lastsync;           //     4      => hh[23]    // last synchronization timestamp
     int  reserved[13];       //    52      => hh[24]    // to be used in future
   } HISTORY_HEADER, hh;      // = 148 byte = int[37]

   typedef struct _RATEINFO {
     int    time;             //     4      =>  ri[0]    // bar time
     double open;             //     8      =>  ri[1]
     double low;              //     8      =>  ri[3]
     double high;             //     8      =>  ri[5]
     double close;            //     8      =>  ri[7]
     double vol;              //     8      =>  ri[9]
   } RATEINFO, ri;            //  = 44 byte = int[11]
   */

   private static $historyHeaderTemplate = array('version'     => 400,
                                                 'description' => '(C)opyright 2003, MetaQuotes Software Corp.',
                                                 'symbol'      => null,
                                                 'period'      => null,
                                                 'digits'      => null,
                                                 'timesign'    => 0,
                                                 'lastsync'    => 0,
                                                 'reserved'    => "\0");


   /**
    * Schreibt einen HistoryHeader mit den angegebenen Daten in die zum Dateihandle gehörende Datei.
    *
    * @param  Resource $hFile      - File-Handle, muß Schreibzugriff erlauben
    * @param  mixed[]  $headerData - Array mit zu setzenden Headerdaten (im Array fehlende Werte werden nach Möglichkeit durch Defaultwerte ergänzt)
    *
    * @return int - Anzahl der geschriebenen Bytes
    */
   public static function writeHeader($hFile, array $headerData) {
      if (getType($hFile) != 'resource') {
         if (getType($hFile) == 'unknown') throw new InvalidArgumentException('Invalid file handle in parameter $hFile: '.(int)$hFile);
                                           throw new IllegalTypeException('Illegal type of argument $hFile: '.getType($hFile));
      }
      if (!$headerData)                    throw new InvalidArgumentException('Invalid parameter $headerData: '.print_r($headerData, true));

      $version = $description = $symbol = $period = $digits = $timesign = $lastsync = $reserved = null;
      extract(array_merge(self::$historyHeaderTemplate, array('timesign' => time()), $headerData));

      fSeek($hFile, 0);
      return fWrite($hFile, pack('Va64a12VVVVa52', $version, $description, $symbol, $period, $digits, $timesign, $lastsync, $reserved));
   }









   //private /*string*/   $name;         // filename
   //private /*Resource*/ $hFile;
   //private /*string*/   $accessMode;

   //private /*int*/      $version;
   //private /*string*/   $description;
   //private /*string*/   $symbol;
   //private /*int*/      $period;
   //private /*int*/      $digits;
   //private /*int*/      $timesign;
   //private /*int*/      $lastsync;
   //private /*int*/      $barCount;
   //private /*int*/      $startTime;
   //private /*int*/      $endTime;
   //private /*string*/   $timezone;


   /**
    * Geschützter Constructor
    *
    * @param string $filename - Name der Datei
    * @param string $mode     - Access-Mode, identisch zu fOpen()
   private function __construct($filename, $mode) {
      $this->name       = is_file($filename) ? realPath($filename) : $filename;
      $this->hFile      = fOpen($this->name, $mode);
      $this->accessMode = $mode;
   }
    */


   /**
    * Öffnet eine History-Datei.
    *
    * @param string $filename - Name der Datei
    * @param string $mode     - Access-Mode, identisch zu fOpen()
    *
    * @return MT4HistoryFile
   public static function open($filename, $mode) {
      if (!is_string($filename)) throw new IllegalTypeException('Illegal type of argument $filename: '.getType($filename));
      if (!is_string($mode))     throw new IllegalTypeException('Illegal type of argument $mode: '.getType($mode));

      return new self($filename, $mode);
   }
    */


   /**
    * Schließt die History-Datei.
    *
    * @return bool - Erfolgsstatus
   public function close() {
      return (is_resource($this->hFile) && fClose($this->hFile));
   }
    */


   /**
    * Gibt den Namen der Datei zurück.
    *
    * @return string
   public function getName() {
      return $this->name;
   }
    */


   /**
    * Gibt die aktuelle Größe der Datei in Byte zurück.
    *
    * @return int - Dateigröße
   public function getSize() {
      return fileSize($this->name);
   }
    */


   /**
    * Destructor
   public function __destruct() {
      try {
         $this->close();
      }
      catch (Exception $ex) {
         Logger ::handleException($ex, true);
         throw $ex;
      }
   }
    */
}
?>
