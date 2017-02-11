<?php
use rosasurfer\core\Object;

use rosasurfer\debug\ErrorHandler;

use rosasurfer\exception\FileNotFoundException;
use rosasurfer\exception\IllegalStateException;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;


/**
 * Object-Wrapper für eine MT4-History-Datei ("*.hst")
 */
class HistoryFile extends Object {

   protected /*int          */ $hFile;                            // File-Handle einer geöffneten Datei
   protected /*string       */ $fileName;                         // einfacher Dateiname
   protected /*string       */ $serverName;                       // einfacher Servername
   protected /*string       */ $serverDirectory;                  // vollständiger Name des Serververzeichnisses
   protected /*bool         */ $closed = false;                   // ob die Instanz geschlossen und seine Resourcen freigegeben sind

   protected /*HistoryHeader*/ $hstHeader;
   protected /*int          */ $period;                           // Timeframe der Datei
   protected /*int          */ $pointsPerUnit;                    // Preisumrechnung, z.B.: Digits=2 => pointsPerUnit=100
   protected /*float        */ $pointSize;                        // Preisumrechnung, z.B.: Digits=2 => pointSize=0.01

   protected /*string       */ $barPackFormat;                    // Formatstring für pack()
   protected /*string       */ $barUnpackFormat;                  // Formatstring für unpack()
   protected /*int          */ $barSize       = 0;                // Größe einer Bar entsprechend dem Datenformat
   protected /*MYFX_BAR[]   */ $barBuffer     = [];               // Schreibbuffer
   protected /*int          */ $barBufferSize = 10000;            // Default-Größe des Schreibbuffers

   // Metadaten: gespeichert
   protected /*int          */ $stored_bars           =  0;       // Anzahl der gespeicherten Bars der Datei
   protected /*int          */ $stored_from_offset    = -1;       // Offset der ersten gespeicherten Bar der Datei
   protected /*int          */ $stored_from_openTime  =  0;       // OpenTime der ersten gespeicherten Bar der Datei
   protected /*int          */ $stored_from_closeTime =  0;       // CloseTime der ersten gespeicherten Bar der Datei
   protected /*int          */ $stored_to_offset      = -1;       // Offset der letzten gespeicherten Bar der Datei
   protected /*int          */ $stored_to_openTime    =  0;       // OpenTime der letzten gespeicherten Bar der Datei
   protected /*int          */ $stored_to_closeTime   =  0;       // CloseTime der letzten gespeicherten Bar der Datei
   protected /*int          */ $stored_lastSyncTime   =  0;       // Zeitpunkt, bis zu dem die gespeicherten Daten der Datei synchronisiert wurden

   // Metadaten: gespeichert + ungespeichert
   protected /*int          */ $full_bars             =  0;       // Anzahl der Bars der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_from_offset      = -1;       // Offset der ersten Bar der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_from_openTime    =  0;       // OpenTime der ersten Bar der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_from_closeTime   =  0;       // CloseTime der ersten Bar der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_to_offset        = -1;       // Offset der letzten Bar der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_to_openTime      =  0;       // OpenTime der letzten Bar der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_to_closeTime     =  0;       // CloseTime der letzten Bar der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_lastSyncTime     =  0;       // Zeitpunkt, bis zu dem die kompletten Daten der Datei synchronisiert wurden

   /**
    * OpenTime der letzten angefügten M1-Daten zur Validierung in $this->append*()
    */
   protected /*int*/ $lastM1DataTime = 0;


   // Getter
   public function getFileName()        { return $this->fileName;                   }
   public function getServerName()      { return $this->serverName;                 }
   public function getServerDirectory() { return $this->serverDirectory;            }
   public function isClosed()           { return (bool)$this->closed;               }

   public function getVersion()         { return $this->hstHeader->getFormat();     }
   public function getSymbol()          { return $this->hstHeader->getSymbol();     }
   public function getPeriod()          { return $this->hstHeader->getPeriod();     }
   public function getTimeframe()       { return $this->hstHeader->getPeriod();     }  // Alias
   public function getDigits()          { return $this->hstHeader->getDigits();     }
   public function getSyncMarker()      { return $this->hstHeader->getSyncMarker(); }
   public function getLastSyncTime()    { return $this->full_lastSyncTime;          }

   public function getPointSize()       { return $this->pointSize;                  }
   public function getPointsPerUnit()   { return $this->pointsPerUnit;              }


   /**
    * Überladener Constructor.
    *
    * Signaturen:
    * -----------
    * new HistoryFile($symbol, $timeframe, $digits, $format, $serverDirectory)
    * new HistoryFile($fileName)
    */
   public function __construct($arg1=null, $arg2=null, $arg3=null, $arg4=null, $arg5=null) {
      $argc = func_num_args();
      if      ($argc == 5) $this->__construct_1($arg1, $arg2, $arg3, $arg4, $arg5);
      else if ($argc == 1) $this->__construct_2($arg1);
      else throw new InvalidArgumentException('Invalid number of arguments: '.$argc);
   }


   /**
    * Constructor 1
    *
    * Erzeugt eine neue Instanz und setzt eine existierende Datei zurück. Vorhandene Daten werden dabei gelöscht.
    *
    * @param  string $symbol          - Symbol
    * @param  int    $timeframe       - Timeframe
    * @param  int    $digits          - Digits
    * @param  int    $format          - Speicherformat der Datenreihe:
    *                                   • 400 - MetaTrader <= Build 509
    *                                   • 401 - MetaTrader  > Build 509
    * @param  string $serverDirectory - Speicherort der Datei
    */
   private function __construct_1($symbol, $timeframe, $digits, $format, $serverDirectory) {
      if (!is_string($symbol))                      throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
      if (!strLen($symbol))                         throw new InvalidArgumentException('Invalid parameter $symbol: ""');
      if (strLen($symbol) > MT4::MAX_SYMBOL_LENGTH) throw new InvalidArgumentException('Invalid parameter $symbol: "'.$symbol.'" (max '.MT4::MAX_SYMBOL_LENGTH.' characters)');
      if (!is_int($timeframe))                      throw new IllegalTypeException('Illegal type of parameter $timeframe: '.getType($timeframe));
      if (!MT4::isStdTimeframe($timeframe))         throw new InvalidArgumentException('Invalid parameter $timeframe: '.$timeframe.' (not a MetaTrader standard timeframe)');
      if (!is_string($serverDirectory))             throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.getType($serverDirectory));
      if (!is_dir($serverDirectory))                throw new InvalidArgumentException('Directory "'.$serverDirectory.'" not found');

      $this->hstHeader       = new HistoryHeader($format, null, $symbol, $timeframe, $digits, null, null);
      $this->serverDirectory = realPath($serverDirectory);
      $this->serverName      = baseName($this->serverDirectory);
      $this->fileName        = $symbol.$timeframe.'.hst';

      // HistoryFile erzeugen bzw. zurücksetzen und Header neuschreiben
      mkDirWritable($this->serverDirectory);
      $fileName    = $this->serverDirectory.'/'.$this->fileName;
      $this->hFile = fOpen($fileName, 'wb');                      // FILE_WRITE
      $this->writeHistoryHeader();

      // Metadaten einlesen und initialisieren
      $this->initMetaData();
   }


   /**
    * Constructor 2
    *
    * Erzeugt eine neue Instanz anhand einer existierenden Datei. Vorhandene Daten werden nicht gelöscht.
    *
    * @param  string $fileName - Name einer History-Datei
    */
   private function __construct_2($fileName) {
      if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.getType($fileName));
      if (!is_file($fileName))   throw new FileNotFoundException('Invalid parameter $fileName: "'.$fileName.'" (file not found)');

      // Verzeichnis- und Dateinamen speichern
      $realName              = realPath($fileName);
      $this->fileName        = baseName($realName);
      $this->serverDirectory = dirname ($realName);
      $this->serverName      = baseName($this->serverDirectory);

      // Dateigröße validieren
      $fileSize = fileSize($fileName);
      if ($fileSize < HistoryHeader::SIZE) throw new MetaTraderException('filesize.insufficient: Invalid or unsupported format of "'.$fileName.'": fileSize='.$fileSize.' (minFileSize='.HistoryHeader::SIZE.')');

      // Datei öffnen, Header einlesen und validieren
      $this->hFile     = fOpen($fileName, 'r+b');               // FILE_READ|FILE_WRITE
      $this->hstHeader = new HistoryHeader(fRead($this->hFile, HistoryHeader::SIZE));

      if (!strCompareI($this->fileName, $this->getSymbol().$this->getTimeframe().'.hst')) throw new MetaTraderException('filename.mis-match: File name/symbol mis-match of "'.$fileName.'": header="'.$this->getSymbol().','.MyFX::periodDescription($this->getTimeframe()).'"');
      $barSize = $this->getVersion()==400 ? MT4::HISTORY_BAR_400_SIZE : MT4::HISTORY_BAR_401_SIZE;
      if ($trailing=($fileSize-HistoryHeader::SIZE) % $barSize)                           throw new MetaTraderException('filesize.trailing: Corrupted file "'.$fileName.'": '.$trailing.' trailing bytes');

      // Metadaten einlesen und initialisieren
      $this->initMetaData();
   }


   /**
    * Liest die Metadaten der Datei aus und initialisiert die lokalen Variablen. Aufruf nur aus einem Constructor.
    */
   private function initMetaData() {
      $this->period          = $this->hstHeader->getPeriod();
      $this->barSize         = $this->getVersion()==400 ? MT4::HISTORY_BAR_400_SIZE : MT4::HISTORY_BAR_401_SIZE;
      $this->barPackFormat   = MT4::BAR_getPackFormat($this->getVersion());
      $this->barUnpackFormat = MT4::BAR_getUnpackFormat($this->getVersion());

      $this->pointsPerUnit = pow(10, $this->getDigits());
      $this->pointSize     = 1/$this->pointsPerUnit;

      $fileSize = fileSize($this->serverDirectory.'/'.$this->fileName);
      if ($fileSize > HistoryHeader::SIZE) {
         $bars    = ($fileSize-HistoryHeader::SIZE) / $this->barSize;
         fFlush($this->hFile);
         $barFrom = $barTo = unpack($this->barUnpackFormat, fRead($this->hFile, $this->barSize));
         if ($bars > 1) {
            fSeek($this->hFile, HistoryHeader::SIZE + ($bars-1)*$this->barSize);
            $barTo = unpack($this->barUnpackFormat, fRead($this->hFile, $this->barSize));
         }
         $from_offset    = 0;
         $from_openTime  = $barFrom['time'];
         $from_closeTime = MyFX::periodCloseTime($from_openTime, $this->period);

         $to_offset      = $bars-1;
         $to_openTime    = $barTo['time'];
         $to_closeTime   = MyFX::periodCloseTime($to_openTime, $this->period);

         // Metadaten: gespeicherte Bars
         $this->stored_bars           = $bars;
         $this->stored_from_offset    = $from_offset;
         $this->stored_from_openTime  = $from_openTime;
         $this->stored_from_closeTime = $from_closeTime;
         $this->stored_to_offset      = $to_offset;
         $this->stored_to_openTime    = $to_openTime;
         $this->stored_to_closeTime   = $to_closeTime;
         $this->stored_lastSyncTime   = $this->hstHeader->getLastSyncTime();

         // Metadaten: gespeicherte + gepufferte Bars
         $this->full_bars             = $this->stored_bars;
         $this->full_from_offset      = $this->stored_from_offset;
         $this->full_from_openTime    = $this->stored_from_openTime;
         $this->full_from_closeTime   = $this->stored_from_closeTime;
         $this->full_to_offset        = $this->stored_to_offset;
         $this->full_to_openTime      = $this->stored_to_openTime;
         $this->full_to_closeTime     = $this->stored_to_closeTime;
         $this->full_lastSyncTime     = $this->stored_lastSyncTime;

         $this->lastM1DataTime = max($to_closeTime, $this->stored_lastSyncTime) - 1*MINUTE;    // die letzte Bar kann noch offen sein
      }
   }


   /**
    * Destructor
    *
    * Sorgt bei Zerstörung des Objekts dafür, daß der Schreibbuffer einer offenen Historydatei geleert und die Datei geschlossen wird.
    */
   public function __destruct() {
      // Attempting to throw an exception from a destructor during script shutdown causes a fatal error.
      // @see http://php.net/manual/en/language.oop5.decon.php
      try {
         !$this->isClosed() && $this->close();
      }
      catch (\Exception $ex) {
         throw ErrorHandler::handleDestructorException($ex);
      }
   }


   /**
    * Schließt dieses HistoryFile. Gibt die Resourcen dieser Instanz frei. Nach dem Aufruf kann die Instanz nicht mehr verwendet werden.
    *
    * @return bool - Erfolgsstatus; FALSE, wenn die Instanz bereits geschlossen war
    */
   public function close() {
      if ($this->isClosed())
         return false;

      // Barbuffer leeren
      if ($this->barBuffer) {
         $this->flush();
      }

      // Datei schließen
      if (is_resource($this->hFile)) {
         $hTmp=$this->hFile; $this->hFile=null;
         fClose($hTmp);
      }
      return $this->closed=true;
   }


   /**
    * Setzt die Buffergröße für vor dem Schreiben zwischenzuspeichernde Bars dieser Instanz.
    *
    * @param  int $size - Buffergröße
    */
   public function setBarBufferSize($size) {
      if ($this->closed)  throw new IllegalStateException('Cannot process a closed '.__CLASS__);
      if (!is_int($size)) throw new IllegalTypeException('Illegal type of parameter $size: '.getType($size));
      if ($size < 0)      throw new InvalidArgumentException('Invalid parameter $size: '.$size);

      $this->barBufferSize = $size;
   }


   /**
    * Gibt die Bar am angegebenen Offset der Historydatei zurück.
    *
    * @param  int $offset
    *
    * @return array - • MYFX_BAR,    wenn die Bar im Schreibbuffer liegt
    *                 • HISTORY_BAR, wenn die Bar aus der Datei gelesen wurde
    *                 • NULL,        wenn keine solche Bar existiert (Offset ist größer als die Anzahl der Bars der Datei)
    *
    * @see    HistoryFile::getMyfxBar(), HistoryFile::getHistoryBar()
    */
   public function getBar($offset) {
      if (!is_int($offset)) throw new IllegalTypeException('Illegal type of parameter $offset: '.getType($offset));
      if ($offset < 0)      throw new InvalidArgumentException('Invalid parameter $offset: '.$offset);

      if ($offset >= $this->full_bars)                                           // bar[$offset] existiert nicht
         return null;

      if ($offset > $this->stored_to_offset)                                     // bar[$offset] liegt in buffered Bars (MYFX_BAR)
         return $this->barBuffer[$offset-$this->stored_to_offset-1];

      fFlush($this->hFile);
      fSeek($this->hFile, HistoryHeader::SIZE + $offset*$this->barSize);         // bar[$offset] liegt in stored Bars (HISTORY_BAR)
      return unpack($this->barUnpackFormat, fRead($this->hFile, $this->barSize));
   }


   /**
    * Gibt den Offset eines Zeitpunktes innerhalb dieser Historydatei zurück. Dies ist die Position (Index), an der eine Bar
    * mit der angegebenen OpenTime in dieser Historydatei einsortiert werden würde.
    *
    * @param  int $time - Zeitpunkt
    *
    * @return int - Offset oder -1, wenn der Zeitpunkt jünger als die jüngste Bar ist. Zum Schreiben einer Bar mit dieser
    *               Zeit muß die Datei vergrößert werden.
    */
   public function findTimeOffset($time) {
      if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

      $size    = $this->full_bars; if (!$size)                 return -1;
      $iFrom   = 0;
      $iTo     = $size-1; if ($this->full_to_openTime < $time) return -1;
      $barFrom = ['time' => $this->full_from_openTime];
      $barTo   = ['time' => $this->full_to_openTime  ];
      $i       = -1;

      while (true) {                                                       // Zeitfenster von Beginn- und Endbar rekursiv bis zum
         if ($barFrom['time'] >= $time) {                                  // gesuchten Zeitpunkt verkleinern
            $i = $iFrom;
            break;
         }
         if ($barTo['time']==$time || $size==2) {
            $i = $iTo;
            break;
         }

         $midSize = (int) ceil($size/2);                                   // Fenster halbieren
         $iMid    = $iFrom + $midSize - 1;
         $barMid  = $this->getBar($iMid);

         if ($barMid['time'] <= $time) { $barFrom = $barMid; $iFrom = $iMid; }
         else                          { $barTo   = $barMid; $iTo   = $iMid; }
         $size = $iTo - $iFrom + 1;
      }
      return $i;
   }


   /**
    * Gibt den Offset der Bar dieser Historydatei zurück, die den angegebenen Zeitpunkt exakt abdeckt.
    *
    * @param  int $time - Zeitpunkt
    *
    * @return int - Offset oder -1, wenn keine solche Bar existiert
    */
   public function findBarOffset($time) {
      if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

      $size = sizeOf($this->full_bars);
      if (!$size)
         return -1;

      $offset = $this->findTimeOffset($time);

      if ($offset < 0) {                                                         // Zeitpunkt liegt nach der jüngsten bar[openTime]
         $closeTime = $this->full_to_closeTime;
         if ($time < $closeTime)                                                 // Zeitpunkt liegt innerhalb der jüngsten Bar
            return $size-1;
         return -1;
      }

      if ($offset == 0) {
         if ($this->full_from_openTime == $time)                                 // Zeitpunkt liegt exakt auf der ältesten Bar
            return 0;
         return -1;                                                              // Zeitpunkt ist älter die älteste Bar
      }

      $bar = $this->getBar($offset);
      if ($bar['time'] == $time)                                                 // Zeitpunkt liegt exakt auf der jeweiligen Bar
         return $offset;
      $offset--;

      $bar       = $this->getBar($offset);
      $closeTime = self::periodCloseTime($bar['time'], $this->period);

      if ($time < $closeTime)                                                    // Zeitpunkt liegt in der vorhergehenden Bar
         return $offset;
      return -1;                                                                 // Zeitpunkt liegt nicht in der vorhergehenden Bar,
   }                                                                             // also Lücke zwischen der vorhergehenden und der
                                                                                 // folgenden Bar

   /**
    * Gibt den Offset der Bar dieser Historydatei zurück, die den angegebenen Zeitpunkt abdeckt. Existiert keine solche Bar,
    * wird der Offset der letzten vorhergehenden Bar zurückgegeben.
    *
    * @param  int $time - Zeitpunkt
    *
    * @return int - Offset oder -1, wenn keine solche Bar existiert (der Zeitpunkt ist älter als die älteste Bar)
    */
   public function findBarOffsetPrevious($time) {
      if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

      $size = $this->full_bars;
      if (!$size)
         return -1;

      $offset = $this->findTimeOffset($time);
      if ($offset < 0)                                                           // Zeitpunkt liegt nach der jüngsten bar[openTime]
         return $size-1;

      $bar = $this->getBar($offset);

      if ($bar['time'] == $time)                                                 // Zeitpunkt liegt exakt auf der jeweiligen Bar
         return $offset;
      return $offset - 1;                                                        // Zeitpunkt ist älter als die Bar desselben Offsets
   }


   /**
    * Gibt den Offset der Bar dieser Historydatei zurück, die den angegebenen Zeitpunkt abdeckt. Existiert keine solche Bar,
    * wird der Offset der nächstfolgenden Bar zurückgegeben.
    *
    * @param  int $time - Zeitpunkt
    *
    * @return int - Offset oder -1, wenn keine solche Bar existiert (der Zeitpunkt ist jünger als das Ende der jüngsten Bar)
    */
   public function findBarOffsetNext($time) {
      if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

      $size = $this->full_bars;
      if (!$size)
         return -1;

      $offset = $this->findTimeOffset($time);

      if ($offset < 0) {                                                         // Zeitpunkt liegt nach der jüngsten bar[openTime]
         $closeTime = $this->full_to_closeTime;
         return ($closeTime > $time) ? $size-1 : -1;
      }
      if ($offset == 0)                                                          // Zeitpunkt liegt vor oder exakt auf der ersten Bar
         return 0;

      $bar = $this->getBar($offset);
      if ($bar['time'] == $time)                                                 // Zeitpunkt stimmt mit bar[openTime] überein
         return $offset;

      $offset--;                                                                 // Zeitpunkt liegt in der vorherigen oder zwischen der
      $bar = $this->getBar($offset);                                             // vorherigen und der TimeOffset-Bar

      $closeTime = MyFX::periodCloseTime($bar['time'], $this->period);
      if ($closeTime > $time)                                                    // Zeitpunkt liegt innerhalb dieser vorherigen Bar
         return $offset;
      return ($offset+1 < $size) ? $offset+1 : -1;                               // Zeitpunkt liegt nach bar[closeTime], also Lücke...
   }                                                                             // zwischen der vorherigen und der folgenden Bar


   /**
    * Entfernt einen Teil der Historydatei und ersetzt ihn mit den übergebenen Bardaten. Die Größe der Datei wird entsprechend angepaßt.
    *
    * @param  int        $offset - If offset is zero or positive then the start of the removed bars is at that bar offset from the beginning
    *                              of the history. If offset is negative then removing starts that far from the end of the history.
    *
    * @param  int        $length - If length is omitted everything from offset to the end of the history is removed. If length is specified
    *                              and is positive then that many bars will be removed. If length is specified and is negative then length
    *                              bars at the end of the history  will be left.
    *
    * @param  MYFX_BAR[] $bars   - If replacement bars are specified then the removed bars are replaced with bars from this array. If offset
    *                              and length are such that nothing is removed then the bars from the replacement array are inserted at the
    *                              specified offset. If offset is one greater than the greatest existing offset the replacement array is
    *                              appended.
    *
    * Examples: • HistoryFile->spliceBars(0, 1)   removes the first bar
    *           • HistoryFile->spliceBars(-1)     removes the last bar (to be exact: everything from the last bar to the end)
    *           • HistoryFile->spliceBars(0, -2)  removes everything from the beginning to the end except the last two bars
    */
   public function spliceBars($offset, $length=0, array $bars=[]) {
      if (!is_int($offset)) throw new IllegalTypeException('Illegal type of parameter $offset: '.getType($offset));
      if (!is_int($length)) throw new IllegalTypeException('Illegal type of parameter $length: '.getType($length));

      // absoluten Startoffset ermitteln: für appendBars() gültiger Wert bis zu ein Element hinterm History-Ende
      if ($offset >= 0) {
         if ($offset > $this->full_bars)    throw new InvalidArgumentException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
         $fromOffset = $offset;
      }
      else if ($offset < -$this->full_bars) throw new InvalidArgumentException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
      else $fromOffset = $this->full_bars + $offset;

      // absoluten Endoffset ermitteln
      $argc = func_num_args();
      if ($argc <= 1) {
         $toOffset = $this->full_to_offset;
      }
      else if ($length >= 0) {
         $toOffset = $fromOffset + $length - 1;
         if ($toOffset > $this->full_to_offset)  throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
      }
      else if ($fromOffset == $this->full_bars)  throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
      else if ($length < $offset && $offset < 0) throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
      else {
         $toOffset = $this->full_to_offset + $length;
         if ($toOffset+1 < $fromOffset)          throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
      }

      // absolute Länge ermitteln
      $length = $toOffset - $fromOffset + 1;
      if (!$length) $toOffset = -1;
      if (!$length && !$bars) {                                         // nothing to do
         echoPre(__METHOD__.'()  $fromOffset='.$fromOffset.'  $toOffset='.$toOffset.'  $length='.$length.'  $bars=0  (nothing to do)');
         return;
      }

      echoPre(__METHOD__.'()  $fromOffset='.$fromOffset.'  $toOffset='.$toOffset.'  $length='.$length);
      $this->showMetaData(false, true, false);


      // History bearbeiten
      if      (!$bars)   $this->removeBars($fromOffset, $length);
      else if (!$length) $this->insertBars($fromOffset, $bars);
      else {
         $hstFromBar = $this->getBar($fromOffset);
         $hstToBar   = $this->getBar($toOffset);
         echoPre(__METHOD__.'()  replacing '.$length.' bar(s) from offset '.$fromOffset.' ('.gmDate('d-M-Y H:i:s', $hstFromBar['time']).') to offset '.$toOffset.' ('.gmDate('d-M-Y H:i:s', $hstToBar['time']).') with '.($size=sizeOf($bars)).' bars from '.gmDate('d-M-Y H:i:s', $bars[0]['time']).' to '.gmDate('d-M-Y H:i:s', $bars[$size-1]['time']));
         $this->removeBars($fromOffset, $length);
         $this->insertBars($fromOffset, $bars);
      }
   }


   /**
    * Entfernt einen Teil der Historydatei. Die Größe der Datei wird entsprechend gekürzt.
    *
    * @param  int $offset - If offset is zero or positive then the start of the removed bars is at that bar offset from the beginning
    *                       of the history. If offset is negative then removing starts that far from the end of the history.
    *
    * @param  int $length - If length is omitted everything from offset to the end of the history is removed. If length is specified
    *                       and is positive then that many bars will be removed. If length is specified and is negative then length bars
    *                       at the end of the history  will be left.
    */
   public function removeBars($offset, $length=0) {
      if (!is_int($offset)) throw new IllegalTypeException('Illegal type of parameter $offset: '.getType($offset));
      if (!is_int($length)) throw new IllegalTypeException('Illegal type of parameter $length: '.getType($length));

      // absoluten Startoffset ermitteln
      if ($offset >= 0) {
         if ($offset >= $this->full_bars)   throw new InvalidArgumentException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
         $fromOffset = $offset;
      }
      else if ($offset < -$this->full_bars) throw new InvalidArgumentException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
      else $fromOffset = $this->full_bars + $offset;

      // Endoffset ermitteln
      $argc = func_num_args();
      if ($argc <= 1) {
         $toOffset = $this->full_to_offset;
      }
      else if ($length >= 0) {
         $toOffset = $fromOffset + $length - 1;
         if ($toOffset > $this->full_to_offset)  throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
      }
      else if ($length < $offset && $offset < 0) throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
      else {
         $toOffset = $this->full_to_offset + $length;
         if ($toOffset+1 < $fromOffset)          throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
      }

      // absolute Länge ermitteln
      $length = $toOffset - $fromOffset + 1;
      if (!$length) {                                         // nothing to do
         echoPre(__METHOD__.'()  $fromOffset='.$fromOffset.'  $toOffset='.$toOffset.'  $length='.$length.'  (nothing to do)');
         return;
      }

      $hstFromBar = $this->getBar($fromOffset);
      $hstToBar   = $this->getBar($toOffset);
      echoPre(__METHOD__.'()  removing '.$length.' bar(s) from offset '.$fromOffset.' ('.gmDate('d-M-Y H:i:s', $hstFromBar['time']).') to offset '.$toOffset.' ('.gmDate('d-M-Y H:i:s', $hstToBar['time']).')');
   }


   /**
    * Fügt Bardaten am angebenen Offset einer Historydatei ein. Die Datei wird entsprechend vergrößert.
    *
    * @param  int         $offset - If offset is zero or positive then the insertion point is at that bar offset from the beginning
    *                               of the history. If offset is negative then the insertion point is that far from the end of the history.
    *
    * @param  MYFX_BARS[] $bars   - einzufügende Bardaten
    */
   public function insertBars($offset, array $bars) {
      if (!is_int($offset)) throw new IllegalTypeException('Illegal type of parameter $offset: '.getType($offset));

      // absoluten Offset ermitteln
      if ($offset >= 0) {
         if ($offset > $this->full_bars)   throw new InvalidArgumentException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
         $fromOffset = $offset;
      }
      else if ($offset < -$this->full_bars) throw new InvalidArgumentException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
      else $fromOffset = $this->full_bars + $offset;

      if (!$bars) {                                            // nothing to do
         echoPre(__METHOD__.'()  $fromOffset='.$fromOffset.'  $bars=0  (nothing to do)');
         return;
      }

      $hstFromBar = $this->getBar($fromOffset);
      echoPre(__METHOD__.'()  inserting '.($size=sizeOf($bars)).' bar(s) from '.gmDate('d-M-Y H:i:s', $bars[0]['time']).' to '.gmDate('d-M-Y H:i:s', $bars[$size-1]['time']).' at offset '.$fromOffset.' ('.gmDate('d-M-Y H:i:s', $hstFromBar['time']).')');

      /*
      $array = [0, 1, 2, 3, 4, 5];
      echoPre($array);
      array_splice($array, 7, 2, [6, 7]);
      echoPre($array);

      M1::full_bars             = 101381
      M1::full_from_offset      = 0
      M1::full_from_openTime    = Mon, 04-Aug-2003 00:00:00
      M1::full_from_closeTime   = Mon, 04-Aug-2003 00:01:00
      M1::full_to_offset        = 101380
      M1::full_to_openTime      = Mon, 10-Nov-2003 09:40:00
      M1::full_to_closeTime     = Mon, 10-Nov-2003 09:41:00
      M1::full_lastSyncTime     = Fri, 07-Nov-2003 10:40:00

      http://stackoverflow.com/questions/103593/using-php-how-to-insert-text-without-overwriting-to-the-beginning-of-a-text-fil
      */
   }


   /**
    * Ersetzt einen Teil der Historydatei durch andere Bardaten. Die Größe der Datei wird entsprechend angepaßt.
    *
    * @param  int         $offset - If offset is zero or positive then the start of the removed bars is at that bar offset from the beginning
    *                               of the history. If offset is negative then removing starts that far from the end of the history.
    *
    * @param  int         $length - If length is omitted everything from offset to the end of the history is removed. If length is specified
    *                               and is positive then that many bars will be removed. If length is specified and is negative then the end
    *                               of the removed part will be that many bars from the end of the history.
    *
    * @param  MYFX_BARS[] $bars   - die ersetzenden Bardaten
    */
   public function replaceBars($offset, $length=null, array $bars) {
   }


   /**
    * Synchronisiert die Historydatei dieser Instanz mit den übergebenen Daten. Vorhandene Bars, die nach dem letzten
    * Synchronisationszeitpunkt der Datei hinzugefügt wurden und sich mit den übergebenen Daten überschneiden, werden
    * ersetzt. Vorhandene Bars, die sich mit den übergebenen Daten nicht überschneiden, bleiben unverändert.
    *
    * @param  MYFX_BAR[] $bars - Bardaten der Periode M1 (werden automatisch in die Periode der Historydatei konvertiert)
    */
   public function synchronize(array $bars) {
      switch ($this->period) {
         case PERIOD_M1:  $this->synchronizeM1 ($bars); break;
         case PERIOD_M5:  $this->synchronizeM5 ($bars); break;
         case PERIOD_M15: $this->synchronizeM15($bars); break;
         case PERIOD_M30: $this->synchronizeM30($bars); break;
         case PERIOD_H1:  $this->synchronizeH1 ($bars); break;
         case PERIOD_H4:  $this->synchronizeH4 ($bars); break;
         case PERIOD_D1:  $this->synchronizeD1 ($bars); break;
         case PERIOD_W1:  $this->synchronizeW1 ($bars); break;
         case PERIOD_MN1: $this->synchronizeMN1($bars); break;
         default:
            throw new RuntimeException('Unsupported timeframe $this->period='.$this->period);
      }
   }


   /**
    * Synchronisiert die M1-History dieser Instanz.
    *
    * @param  MYFX_BAR[] $bars - Bardaten der Periode M1
    */
   private function synchronizeM1(array $bars) {
      if ($this->closed) throw new IllegalStateException('Cannot process a closed '.__CLASS__);
      if (!$bars) return false;

      // Offset der Bar, die den Zeitpunkt abdeckt, ermitteln
      $lastSyncTime = $this->full_lastSyncTime;
      $offset       = MyFX::findBarOffsetNext($bars, PERIOD_M1, $lastSyncTime);

      // Bars vor Offset verwerfen
      if ($offset == -1)                                                      // alle Bars liegen vor $lastSyncTime
         return;
      $bars = array_slice($bars, $offset);
      $size = sizeof($bars);

      // History-Offsets für die verbliebene Bar-Range ermitteln
      $hstOffsetFrom = $this->findBarOffsetNext($bars[0]['time']);
      if ($hstOffsetFrom == -1) {                                             // Zeitpunkt ist jünger als die jüngste Bar
         $this->appendBars($bars);
      }
      else {
         // History-Range mit Bar-Range ersetzen
         $hstOffsetTo = $this->findBarOffsetPrevious($bars[$size-1]['time']);
         $length      = $hstOffsetTo - $hstOffsetFrom + 1;
         $this->spliceBars($hstOffsetFrom, $length, $bars);
      }
   }


   /**
    * Fügt der Historydatei dieser Instanz Bardaten hinzu. Die Daten werden ans Ende der Zeitreihe angefügt.
    *
    * @param  MYFX_BAR[] $bars - Bardaten der Periode M1
    */
   public function appendBars(array $bars) {
      switch ($this->period) {
         case PERIOD_M1:  $this->appendToM1($bars); break;
         case PERIOD_M5:
         case PERIOD_M15:
         case PERIOD_M30:
         case PERIOD_H1:
         case PERIOD_H4:
         case PERIOD_D1:  $this->appendToTimeframe($bars); break;
         case PERIOD_W1:  $this->appendToW1       ($bars); break;
         case PERIOD_MN1: $this->appendToMN1      ($bars); break;
         default:
            throw new RuntimeException('Unsupported timeframe $this->period='.$this->period);
      }
   }


   /**
    * Fügt der M1-History dieser Instanz weitere Daten hinzu.
    *
    * @param  MYFX_BAR[] $bars - Bardaten der Periode M1
    */
   private function appendToM1(array $bars) {
      if ($this->closed)                             throw new IllegalStateException('Cannot process a closed '.__CLASS__);
      if (!$bars) return;
      if ($bars[0]['time'] <= $this->lastM1DataTime) throw new IllegalStateException('Cannot append bar(s) of '.gmDate('D, d-M-Y H:i:s', $bars[0]['time']).' to history ending at '.gmDate('D, d-M-Y H:i:s', $this->lastM1DataTime));

      $this->barBuffer = array_merge($this->barBuffer, $bars);
      $bufferSize      = sizeOf($this->barBuffer);

      if (!$this->full_bars) {                                          // History ist noch leer
         $this->full_from_offset    = 0;
         $this->full_from_openTime  = $this->barBuffer[0]['time'];
         $this->full_from_closeTime = $this->barBuffer[0]['time'] + 1*MINUTE;
      }
      $this->full_bars         = $this->stored_bars + $bufferSize;
      $this->full_to_offset    = $this->full_bars - 1;
      $this->full_to_openTime  = $this->barBuffer[$bufferSize-1]['time'];
      $this->full_to_closeTime = $this->barBuffer[$bufferSize-1]['time'] + 1*MINUTE;

      $this->lastM1DataTime    = $bars[sizeOf($bars)-1]['time'];
      $this->full_lastSyncTime = $this->lastM1DataTime + 1*MINUTE;

      if ($bufferSize > $this->barBufferSize)
         $this->flush($this->barBufferSize);
   }


   /**
    * Fügt der History dieser Instanz weitere Daten hinzu.
    *
    * @param  MYFX_BAR[] $bars - Bardaten der Periode M1
    */
   private function appendToTimeframe(array $bars) {
      if ($this->closed)                             throw new IllegalStateException('Cannot process a closed '.__CLASS__);
      if (!$bars) return;
      if ($bars[0]['time'] <= $this->lastM1DataTime) throw new IllegalStateException('Cannot append bar(s) of '.gmDate('D, d-M-Y H:i:s', $bars[0]['time']).' to history ending at '.gmDate('D, d-M-Y H:i:s', $this->lastM1DataTime));

      $currentBar = null;
      $bufferSize = sizeOf($this->barBuffer);
      if ($bufferSize)
         $currentBar =& $this->barBuffer[$bufferSize-1];

      foreach ($bars as $bar) {
         if ($bar['time'] < $this->full_to_closeTime) {                       // Wechsel zur nächsten M5-Bar erkennen
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
         else {
            // neue Bar beginnen
            $openTime           =  $bar['time'] - $bar['time'] % $this->period*MINUTES;
            $this->barBuffer[]  =  $bar;
            $currentBar         =& $this->barBuffer[$bufferSize++];
            $currentBar['time'] =  $openTime;
            $closeTime          =  $openTime + $this->period*MINUTES;

            // Metadaten aktualisieren
            if (!$this->full_bars) {                                          // History ist noch leer
               $this->full_from_offset    = 0;
               $this->full_from_openTime  = $openTime;
               $this->full_from_closeTime = $closeTime;
            }
            $this->full_bars         = $this->stored_bars + $bufferSize;
            $this->full_to_offset    = $this->full_bars - 1;
            $this->full_to_openTime  = $openTime;
            $this->full_to_closeTime = $closeTime;
         }
         $this->lastM1DataTime    = $bar['time'];
         $this->full_lastSyncTime = $this->lastM1DataTime + 1*MINUTE;

         // ggf. Buffer flushen
         if ($bufferSize > $this->barBufferSize)
            $bufferSize -= $this->flush($this->barBufferSize);
      }
   }


   /**
    * Fügt der W1-History dieser Instanz weitere Daten hinzu.
    *
    * @param  MYFX_BAR[] $bars - Bardaten der Periode M1
    */
   private function appendToW1(array $bars) {
      if ($this->closed)                             throw new IllegalStateException('Cannot process a closed '.__CLASS__);
      if (!$bars) return;
      if ($bars[0]['time'] <= $this->lastM1DataTime) throw new IllegalStateException('Cannot append bar(s) of '.gmDate('D, d-M-Y H:i:s', $bars[0]['time']).' to history ending at '.gmDate('D, d-M-Y H:i:s', $this->lastM1DataTime));

      $currentBar = null;
      $bufferSize =  sizeOf($this->barBuffer);
      if ($bufferSize)
         $currentBar =& $this->barBuffer[$bufferSize-1];

      foreach ($bars as $i => $bar) {
         if ($bar['time'] < $this->full_to_closeTime) {                       // Wechsel zur nächsten W1-Bar erkennen
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
         else {
            // neue Bar beginnen
            $dow                = (int) gmDate('w', $bar['time']);            // 00:00, Montag
            $openTime           =  $bar['time'] - $bar['time']%DAY - (($dow+6)%7)*DAYS;
            $this->barBuffer[]  =  $bar;
            $currentBar         =& $this->barBuffer[$bufferSize++];
            $currentBar['time'] =  $openTime;
            $closeTime          =  $openTime + 1*WEEK;

            // Metadaten aktualisieren
            if (!$this->full_bars) {                                          // History ist noch leer
               $this->full_from_offset    = 0;
               $this->full_from_openTime  = $openTime;
               $this->full_from_closeTime = $closeTime;
            }
            $this->full_bars         = $this->stored_bars + $bufferSize;
            $this->full_to_offset    = $this->full_bars - 1;
            $this->full_to_openTime  = $openTime;
            $this->full_to_closeTime = $closeTime;
         }
         $this->lastM1DataTime    = $bar['time'];
         $this->full_lastSyncTime = $this->lastM1DataTime + 1*MINUTE;

         // ggf. Buffer flushen
         if ($bufferSize > $this->barBufferSize)
            $bufferSize -= $this->flush($this->barBufferSize);
      }
   }


   /**
    * Fügt der MN1-History dieser Instanz weitere Daten hinzu.
    *
    * @param  MYFX_BAR[] $bars - Bardaten der Periode M1
    */
   private function appendToMN1(array $bars) {
      if ($this->closed)                             throw new IllegalStateException('Cannot process a closed '.__CLASS__);
      if (!$bars) return;
      if ($bars[0]['time'] <= $this->lastM1DataTime) throw new IllegalStateException('Cannot append bar(s) of '.gmDate('D, d-M-Y H:i:s', $bars[0]['time']).' to history ending at '.gmDate('D, d-M-Y H:i:s', $this->lastM1DataTime));

      $currentBar = null;
      $bufferSize =  sizeOf($this->barBuffer);
      if ($bufferSize)
         $currentBar =& $this->barBuffer[$bufferSize-1];

      foreach ($bars as $bar) {
         if ($bar['time'] < $this->full_to_closeTime) {                       // Wechsel zur nächsten MN1-Bar erkennen
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
         else {
            // neue Bar beginnen
            $dom = (int) gmDate('d', $bar['time']);
            $m   = (int) gmDate('m', $bar['time']);
            $y   = (int) gmDate('Y', $bar['time']);                           // 00:00, 1. des Monats
            $openTime           = $bar['time'] - $bar['time']%DAYS - ($dom-1)*DAYS;
            $this->barBuffer[]  =  $bar;
            $currentBar         =& $this->barBuffer[$bufferSize++];
            $currentBar['time'] =  $openTime;
            $closeTime          =  gmMkTime(0, 0, 0, $m+1, 1, $y);            // 00:00, 1. des nächsten Monats

            // Metadaten aktualisieren
            if (!$this->full_bars) {                                          // History ist noch leer
               $this->full_from_offset    = 0;
               $this->full_from_openTime  = $openTime;
               $this->full_from_closeTime = $closeTime;
            }
            $this->full_bars         = $this->stored_bars + $bufferSize;
            $this->full_to_offset    = $this->full_bars - 1;
            $this->full_to_openTime  = $openTime;
            $this->full_to_closeTime = $closeTime;
         }
         $this->lastM1DataTime    = $bar['time'];
         $this->full_lastSyncTime = $this->lastM1DataTime + 1*MINUTE;

         // ggf. Buffer flushen
         if ($bufferSize > $this->barBufferSize)
            $bufferSize -= $this->flush($this->barBufferSize);
      }
   }


   /**
    * Schreibt eine Anzahl MyFXBars aus dem Barbuffer in die History-Datei.
    *
    * @param  int $count - Anzahl zu schreibender Bars (default: alle Bars)
    *
    * @return int - Anzahl der geschriebenen und aus dem Buffer gelöschten Bars
    */
   public function flush($count=PHP_INT_MAX) {
      if ($this->closed)   throw new IllegalStateException('Cannot process a closed '.__CLASS__);
      if (!is_int($count)) throw new IllegalTypeException('Illegal type of parameter $count: '.getType($count));
      if ($count < 0)      throw new InvalidArgumentException('Invalid parameter $count: '.$count);

      $bufferSize = sizeOf($this->barBuffer);
      $todo       = min($bufferSize, $count);
      if (!$todo) return 0;


      // (1) FilePointer setzen
      fSeek($this->hFile, HistoryHeader::SIZE + ($this->stored_to_offset+1)*$this->barSize);


      // (2) Bars schreiben
      $i = 0;
      foreach ($this->barBuffer as $i => $bar) {
         $T = $bar['time' ];
         $O = $bar['open' ]/$this->pointsPerUnit;
         $H = $bar['high' ]/$this->pointsPerUnit;
         $L = $bar['low'  ]/$this->pointsPerUnit;
         $C = $bar['close']/$this->pointsPerUnit;
         $V = $bar['ticks'];

         MT4::writeHistoryBar400($this->hFile, $this->getDigits(), $T, $O, $H, $L, $C, $V);
         if ($i+1 == $todo)
            break;
      }
      //if ($this->period==PERIOD_M1) echoPre(__METHOD__.'()  wrote '.$todo.' bars, lastBar.time='.gmDate('D, d-M-Y H:i:s', $this->barBuffer[$todo-1]['time']));


      // (3) Metadaten aktualisieren
      if (!$this->stored_bars) {                                           // Datei war vorher leer
         $this->stored_from_offset    = 0;
         $this->stored_from_openTime  = $this->barBuffer[0]['time'];
         $this->stored_from_closeTime = MyFX::periodCloseTime($this->stored_from_openTime, $this->period);
      }
      $this->stored_bars         = $this->stored_bars + $todo;
      $this->stored_to_offset    = $this->stored_bars - 1;
      $this->stored_to_openTime  = $this->barBuffer[$todo-1]['time'];
      $this->stored_to_closeTime = MyFX::periodCloseTime($this->stored_to_openTime, $this->period);

      // lastSyncTime je nachdem setzen, ob noch weitere Daten im Buffer sind
      $this->stored_lastSyncTime = ($todo < $bufferSize) ? $this->stored_to_closeTime : $this->lastM1DataTime + 1*MINUTE;

      //$this->full* ändert sich nicht


      // (4) HistoryHeader aktualisieren
      $this->hstHeader->setLastSyncTime($this->stored_lastSyncTime);
      $this->writeHistoryHeader();


      // (5) Barbuffer um die geschriebenen Bars kürzen
      if ($todo == $bufferSize) $this->barBuffer = [];
      else                      $this->barBuffer = array_slice($this->barBuffer, $todo);

      return $todo;
   }


   /**
    * Schreibt den HistoryHeader in die Datei.
    *
    * @return int - Anzahl der geschriebenen Bytes
    */
   private function writeHistoryHeader() {
      fSeek($this->hFile, 0);
      $format  = HistoryHeader::packFormat();
      $written = fWrite($this->hFile, pack($format, $this->hstHeader->getFormat(),           // V
                                                    $this->hstHeader->getCopyright(),        // a64
                                                    $this->hstHeader->getSymbol(),           // a12
                                                    $this->hstHeader->getPeriod(),           // V
                                                    $this->hstHeader->getDigits(),           // V
                                                    $this->hstHeader->getSyncMarker(),       // V
                                                    $this->hstHeader->getLastSyncTime()));   // V
                                                                                             // x52
      //if ($this->period==PERIOD_M1 && $this->hstHeader->getLastSyncTime()) $this->showMetaData();
      return $written;
   }


   /**
    * Nur zum Debuggen
    */
   public function showMetaData($showStored=true, $showFull=true, $showFile=true) {
      $Pxx = MyFX::periodDescription($this->period);

      ($showStored || $showFull || $showFile) && echoPre(NL);
      if ($showStored) {
         echoPre($Pxx.'::stored_bars           = '. $this->stored_bars);
         echoPre($Pxx.'::stored_from_offset    = '. $this->stored_from_offset);
         echoPre($Pxx.'::stored_from_openTime  = '.($this->stored_from_openTime  ? gmDate('D, d-M-Y H:i:s', $this->stored_from_openTime ) : 0));
         echoPre($Pxx.'::stored_from_closeTime = '.($this->stored_from_closeTime ? gmDate('D, d-M-Y H:i:s', $this->stored_from_closeTime) : 0));
         echoPre($Pxx.'::stored_to_offset      = '. $this->stored_to_offset);
         echoPre($Pxx.'::stored_to_openTime    = '.($this->stored_to_openTime    ? gmDate('D, d-M-Y H:i:s', $this->stored_to_openTime   ) : 0));
         echoPre($Pxx.'::stored_to_closeTime   = '.($this->stored_to_closeTime   ? gmDate('D, d-M-Y H:i:s', $this->stored_to_closeTime  ) : 0));
         echoPre($Pxx.'::stored_lastSyncTime   = '.($this->stored_lastSyncTime   ? gmDate('D, d-M-Y H:i:s', $this->stored_lastSyncTime  ) : 0));
      }
      if ($showFull) {
         $showStored && echoPre(NL);
         echoPre($Pxx.'::full_bars             = '. $this->full_bars);
         echoPre($Pxx.'::full_from_offset      = '. $this->full_from_offset);
         echoPre($Pxx.'::full_from_openTime    = '.($this->full_from_openTime    ? gmDate('D, d-M-Y H:i:s', $this->full_from_openTime   ) : 0));
         echoPre($Pxx.'::full_from_closeTime   = '.($this->full_from_closeTime   ? gmDate('D, d-M-Y H:i:s', $this->full_from_closeTime  ) : 0));
         echoPre($Pxx.'::full_to_offset        = '. $this->full_to_offset);
         echoPre($Pxx.'::full_to_openTime      = '.($this->full_to_openTime      ? gmDate('D, d-M-Y H:i:s', $this->full_to_openTime     ) : 0));
         echoPre($Pxx.'::full_to_closeTime     = '.($this->full_to_closeTime     ? gmDate('D, d-M-Y H:i:s', $this->full_to_closeTime    ) : 0));
         echoPre($Pxx.'::full_lastSyncTime     = '.($this->full_lastSyncTime     ? gmDate('D, d-M-Y H:i:s', $this->full_lastSyncTime    ) : 0));
      }
      if ($showFile) {
         ($showStored || $showFull) && echoPre(NL);
         echoPre($Pxx.'::lastM1DataTime        = '.($this->lastM1DataTime        ? gmDate('D, d-M-Y H:i:s', $this->lastM1DataTime       ) : 0));
         echoPre($Pxx.'::fp                    = '.($fp=fTell($this->hFile)).' (bar offset '.(($fp-HistoryHeader::SIZE)/$this->barSize).')');
      }
   }
}
