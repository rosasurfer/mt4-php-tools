<?php
/**
 * MT4HistoryFile
 *
 * Helferklasse zum Lesen/Schreiben von HST-Files.
 */
class MT4HistoryFile extends StaticClass {



























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
