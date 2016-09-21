<?php
use rosasurfer\core\Object;

use rosasurfer\exception\IllegalStateException;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;

use rosasurfer\util\Logger;
use rosasurfer\util\System;


/**
 * Ein HistorySet zur Verwaltung der MetaTrader-History eines Instruments. Die Formate der einzelnen Dateien
 * eines HistorySets können gemischt sein.
 */
class HistorySet extends Object {

   protected /*string*/ $symbol;
   protected /*int   */ $digits;
   protected /*string*/ $serverName;                  // einfacher Servername
   protected /*string*/ $serverDirectory;             // vollständiger Verzeichnisname
   protected /*bool  */ $closed = false;              // ob das Set geschlossen und seine Resourcen freigegeben sind

   // Getter
   public function getSymbol()          { return       $this->symbol;          }
   public function getDigits()          { return       $this->digits;          }
   public function getServerName()      { return       $this->serverName;      }
   public function getServerDirectory() { return       $this->serverDirectory; }
   public function isClosed()           { return (bool)$this->closed;          }

   protected /*HistoryFile[]*/ $historyFiles = array(PERIOD_M1  => null,
                                                     PERIOD_M5  => null,
                                                     PERIOD_M15 => null,
                                                     PERIOD_M30 => null,
                                                     PERIOD_H1  => null,
                                                     PERIOD_H4  => null,
                                                     PERIOD_D1  => null,
                                                     PERIOD_W1  => null,
                                                     PERIOD_MN1 => null);

   private static /*HistorySet[]*/ $instances = array(); // alle Instanzen dieser Klasse


   /**
    * Überladener Constructor.
    *
    * Signaturen:
    * -----------
    * new HistorySet(string $symbol, int $digits, int $format, string $serverDirectory)
    * new HistorySet(HistoryFile $file)
    */
   private function __construct($arg1=null, $arg2=null, $arg3=null, $arg4=null) {
      $argc = func_num_args();
      if      ($argc == 4) $this->__construct_1($arg1, $arg2, $arg3, $arg4);
      else if ($argc == 1) $this->__construct_2($arg1);
      else throw new InvalidArgumentException('Invalid number of arguments: '.$argc);
   }


   /**
    * Constructor 1
    *
    * Erzeugt eine neue Instanz und legt alle Historydateien neu an. Vorhandene Daten werden gelöscht. Mehrfachaufrufe
    * dieser Funktion für dasselbe Symbol desselben Servers geben jeweils eine neue Instanz zurück, weitere existierende
    * Instanzen werden als ungültig markiert.
    *
    * @param  string $symbol          - Symbol der HistorySet-Daten
    * @param  int    $digits          - Digits der Datenreihe
    * @param  int    $format          - Speicherformat der Datenreihen:
    *                                   • 400: MetaTrader <= Build 509
    *                                   • 401: MetaTrader  > Build 509
    * @param  string $serverDirectory - Serververzeichnis der Historydateien des Sets
    */
   private function __construct_1($symbol, $digits, $format, $serverDirectory) {
      if (!is_string($symbol))                      throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
      if (!strLen($symbol))                         throw new InvalidArgumentException('Invalid parameter $symbol: ""');
      if (strLen($symbol) > MT4::MAX_SYMBOL_LENGTH) throw new InvalidArgumentException('Invalid parameter $symbol: "'.$symbol.'" (max '.MT4::MAX_SYMBOL_LENGTH.' characters)');
      if (!is_int($digits))                         throw new IllegalTypeException('Illegal type of parameter $digits: '.getType($digits));
      if ($digits < 0)                              throw new InvalidArgumentException('Invalid parameter $digits: '.$digits);
      if (!is_string($serverDirectory))             throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.getType($serverDirectory));
      if (!is_dir($serverDirectory))                throw new InvalidArgumentException('Directory "'.$serverDirectory.'" not found');

      $this->symbol          = $symbol;
      $this->digits          = $digits;
      $this->serverDirectory = realPath($serverDirectory);
      $this->serverName      = baseName($this->serverDirectory);

      // offene Sets durchsuchen und Sets desselben Symbols schließen
      $symbolUpper = strToUpper($this->symbol);
      foreach (self::$instances as $instance) {
         if (!$instance->isClosed() && $symbolUpper==strToUpper($instance->getSymbol()) && $this->serverDirectory==$instance->getServerDirectory())
            $instance->close();
      }

      // alle HistoryFiles zurücksetzen
      foreach ($this->historyFiles as $timeframe => &$file) {
         $file = new HistoryFile($symbol, $timeframe, $digits, $format, $serverDirectory);
      } unset($file);

      // Instanz speichern
      self::$instances[] = $this;
   }


   /**
    * Constructor 2
    *
    * Erzeugt eine neue Instanz. Vorhandene Daten werden nicht gelöscht.
    *
    * @param  HistoryFile $file - existierende History-Datei
    */
   private function __construct_2(HistoryFile $file) {
      $this->symbol          =          $file->getSymbol();
      $this->digits          =          $file->getDigits();
      $this->serverName      =          $file->getServerName();
      $this->serverDirectory = realPath($file->getServerDirectory());

      $this->historyFiles[$file->getTimeframe()] = $file;

      $symbolUpper = strToUpper($this->symbol);
      foreach (self::$instances as $instance) {
         if (!$instance->isClosed() && $symbolUpper==strToUpper($instance->getSymbol()) && $this->serverDirectory==$instance->getServerDirectory())
            throw RuntimeException('Multiple open HistorySets for "'.$this->serverName.'::'.$this->symbol.'"');
      }

      // alle übrigen existierenden HistoryFiles öffnen und validieren (nicht existierende Dateien werden erst bei Bedarf erstellt)
      foreach ($this->historyFiles as $timeframe => &$file) {
         if (!$file) {
            $fileName = $this->serverDirectory.'/'.$this->symbol.$timeframe.'.hst';
            if (is_file($fileName)) {
               try {
                  $file = new HistoryFile($fileName);
               }
               catch (MetaTraderException $ex) {
                  if (strStartsWith($ex->getMessage(), 'filesize.insufficient')) {
                     Logger::warn($ex->getMessage(), __CLASS__);           // eine zu kurze Datei wird später überschrieben
                     continue;
                  }
                  throw $ex;
               }
               if ($file->getDigits() != $this->getDigits()) throw new RuntimeException('Digits mis-match in "'.$fileName.'": file.digits='.$file->getDigits().' instead of set.digits='.$this->getDigits());
            }
         }
      } unset($file);

      self::$instances[] = $this;
   }


   /**
    * Destructor
    *
    * Sorgt bei Zerstörung der Instanz dafür, daß alle gehaltenen Resourcen freigegeben werden.
    */
   public function __destruct() {
      // Attempting to throw an exception from a destructor during script shutdown causes a fatal error.
      // @see http://php.net/manual/en/language.oop5.decon.php
      try {
         $this->close();
      }
      catch (\Exception $ex) {
         System::handleDestructorException($ex);
         throw $ex;
      }
   }


   /**
    * Schließt dieses HistorySet. Gibt alle Resourcen dieser Instanz frei. Nach dem Aufruf kann die Instanz nicht mehr verwendet werden.
    *
    * @return bool - Erfolgsstatus; FALSE, wenn die Instanz bereits geschlossen war
    */
   public function close() {
      if ($this->isClosed())
         return false;

      foreach ($this->historyFiles as $file) {
         $file && !$file->isClosed() && $file->close();
      }
      return $this->closed=true;
   }


   /**
    * Erzeugt eine neue Instanz und legt alle Historydateien neu an. Vorhandene Daten werden gelöscht. Mehrfachaufrufe
    * dieser Funktion für dasselbe Symbol desselben Servers geben jeweils eine neue Instanz zurück, weitere existierende
    * Instanzen werden als ungültig markiert.
    *
    * @param  string $symbol          - Symbol der HistorySet-Daten
    * @param  int    $digits          - Digits der Datenreihe
    * @param  int    $format          - Speicherformat der Datenreihen:
    *                                   • 400: MetaTrader <= Build 509
    *                                   • 401: MetaTrader  > Build 509
    * @param  string $serverDirectory - Serververzeichnis der Historydateien des Sets
    */
   public static function create($symbol, $digits, $format, $serverDirectory) {
      return new self($symbol, $digits, $format, $serverDirectory);
   }


   /**
    * Öffentlicher Zugriff auf Constructor 2
    *
    * Gibt eine Instanz für bereits vorhandene Historydateien zurück. Vorhandene Daten werden nicht gelöscht. Es muß mindestens
    * ein HistoryFile des Symbols existieren. Nicht existierende HistoryFiles werden beim Speichern der ersten hinzugefügten
    * Daten angelegt. Mehrfachaufrufe dieser Funktion für dasselbe Symbol desselben Servers geben dieselbe Instanz zurück.
    *
    * @param  string $symbol          - Symbol der Historydateien
    * @param  string $serverDirectory - Serververzeichnis, in dem die Historydateien gespeichert sind
    *
    * @return HistorySet - Instanz oder NULL, wenn keine entsprechenden Historydateien gefunden wurden oder die
    *                      gefundenen Dateien korrupt sind.
    */
   public static function get($symbol, $serverDirectory) {
      if (!is_string($symbol))          throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
      if (!strLen($symbol))             throw new InvalidArgumentException('Invalid parameter $symbol: ""');
      if (!is_string($serverDirectory)) throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.getType($serverDirectory));
      if (!is_dir($serverDirectory))    throw new InvalidArgumentException('Directory "'.$serverDirectory.'" not found');

      // existierende Instanzen durchsuchen und bei Erfolg die entsprechende Instanz zurückgeben
      $symbolUpper     = strToUpper($symbol);
      $serverDirectory = realPath($serverDirectory);
      foreach (self::$instances as $instance) {
         if (!$instance->isClosed() && $symbolUpper==strToUpper($instance->getSymbol()) && $serverDirectory==$instance->getServerDirectory())
            return $instance;
      }

      // das erste existierende HistoryFile an den Constructor übergeben, das Set liest die weiteren dann selbst ein
      $set = $file = null;
      foreach (MT4::$timeframes as $timeframe) {
         $fileName = $serverDirectory.'/'.$symbol.$timeframe.'.hst';
         if (is_file($fileName)) {
            try {
               $file = new HistoryFile($fileName);
            }
            catch (MetaTraderException $ex) {
               Logger::warn($ex->getMessage(), __CLASS__);
               continue;
            }
            $set = new self($file);
            break;
         }
      }
      return $set;
   }


   /**
    * Gibt das HistoryFile des angegebenen Timeframes zurück. Existiert es nicht, wird es erzeugt.
    *
    * @param  int $timeframe
    *
    * @return HistoryFile
    */
   private function getFile($timeframe) {
      if (!isSet($this->historyFiles[$timeframe])) {
         $fileName = $this->serverDirectory.'/'.$this->symbol.$timeframe.'.hst';

         $file = null;
         if (is_file($fileName)) {
            try {
               $file = new HistoryFile($fileName);
            }
            catch (MetaTraderException $ex) {
               if (!strStartsWith($ex->getMessage(), 'filesize.insufficient')) throw $ex;
               Logger::warn($ex->getMessage(), __CLASS__);              // eine zu kurze Datei wird mit einer neuen Datei überschrieben
            }
            if ($file->getDigits() != $this->getDigits()) throw new RuntimeException('Digits mis-match in "'.$fileName.'": file.digits='.$file->getDigits().' instead of set.digits='.$this->getDigits());
         }

         if (!$file) $file = new HistoryFile($this->symbol, $timeframe, $this->digits, $format=400, $this->serverDirectory);
         $this->historyFiles[$timeframe] = $file;
      }
      return $this->historyFiles[$timeframe];
   }


   /**
    * Gibt den letzten für alle HistoryFiles des Sets gültigen Synchronisationszeitpunkt zurück.
    * Dies ist der älteste Synchronisationszeitpunkt der einzelnen HistoryFiles.
    *
    * @return int - Timestamp (in jeweiliger Serverzeit)
    *               0, wenn mindestens eines der HistoryFiles noch gar nicht synchronisiert wurde
    */
   public function getLastSyncTime() {
      $minTime = null;
      foreach ($this->historyFiles as $timeframe => $file) {
         $time = $file ? $file->getLastSyncTime() : 0;   // existiert die Datei nicht, wurde sie auch noch nicht synchronisiert
         if (is_null($minTime))
            $minTime = $time;
         $minTime = min($minTime, $time);

         if ($timeframe == PERIOD_M1)                    // TODO: vorerst nur PERIOD_M1
            break;
      }
      return $minTime;
   }


   /**
    * Fügt dem Ende der Zeitreihen des Sets weitere Bardaten hinzu. Vorhandene Daten werden nicht geändert.
    *
    * @param  MYFX_BAR[] $bars - Bardaten der Periode M1
    *
    * @return bool - Erfolgsstatus
    */
   public function appendBars(array $bars) {
      if ($this->closed) throw new IllegalStateException('Cannot process a closed '.__CLASS__);
      if (!$bars) return false;

      foreach ($this->historyFiles as $timeframe => $file) {
         !$file && $file=$this->getFile($timeframe);
         $file->appendBars($bars);
      }
      return true;
   }


   /**
    * Synchronisiert die Zeitreihen des Sets mit den übergebenen Bardaten. Vorhandene Bars, die nach dem letzten
    * Synchronisationszeitpunkt der Zeitreihe geschrieben wurden und die sich mit den übergebenen Bars überschneiden,
    * werden ersetzt. Vorhandene Bars, die sich mit den übergebenen Bars nicht überschneiden, bleiben unverändert.
    *
    * @param  MYFX_BAR[] $bars - Bardaten der Periode M1
    */
   public function synchronize(array $bars) {
      if ($this->closed) throw new IllegalStateException('Cannot process a closed '.__CLASS__);
      if (!$bars) return;

      $historyM1 = $this->getFile(PERIOD_M1);
      $historyM1->synchronize($bars);
   }


   /**
    * Nur zum Debuggen
    */
   public function showBuffer() {
      echoPre(NL);
      foreach ($this->historyFile as $timeframe => $file) {
         if ($file) {
            $bars = $file->barBuffer;
            $size = sizeOf($bars);
            $firstBar = $lastBar = null;
            if ($size) {
               if (isSet($bars[0]['time']) && $bars[$size-1]['time']) {
                  $firstBar = '  from='.gmDate('d-M-Y H:i', $bars[0      ]['time']);
                  $lastBar  = '  to='  .gmDate('d-M-Y H:i', $bars[$size-1]['time']);
               }
               else {
                  $firstBar = $lastBar = '  invalid';
                  echoPre($bars);
               }
            }
            echoPre(get_class($file).'['. str_pad(MyFX::timeframeDescription($file->getTimeframe()), 3, ' ', STR_PAD_RIGHT).'] => '.str_pad($size, 5, ' ', STR_PAD_LEFT).' bar'.pluralize($size, ' ').$firstBar.($size>1? $lastBar:''));
         }
         else {
            echoPre('HistoryFile['. str_pad(MyFX::timeframeDescription($timeframe), 3, ' ', STR_PAD_RIGHT).'] => mull');
         }
      }
      echoPre(NL);
   }
}
