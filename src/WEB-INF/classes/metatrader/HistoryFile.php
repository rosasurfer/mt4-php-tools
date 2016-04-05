<?php
/**
 * Object-Wrapper für eine MT4-History-Datei ("*.hst")
 */
class HistoryFile extends Object {

   protected /*int          */ $hFile;                      // File-Handle einer geöffneten Datei
   protected /*string       */ $fileName;                   // einfacher Dateiname
   protected /*string       */ $serverName;                 // einfacher Servername
   protected /*string       */ $serverDirectory;            // vollständiger Name des Serververzeichnisses
   protected /*bool         */ $closed = false;             // ob die Instanz geschlossen und seine Resourcen freigegeben sind

   protected /*HistoryHeader*/ $hstHeader;
   protected /*int          */ $lastSyncTime;               // Zeitpunkt, bis zu dem die Datei synchronisiert wurde
   protected /*int          */ $barSize;                    // Größe einer Bar entsprechend dem Datenformat
   protected /*MYFX_BAR[]   */ $barBuffer  = array();
   protected /*int          */ $bufferSize = 10000;         // Default-Größe des Buffers für ungespeicherte Bars

   // Metadaten: gespeichert
   protected /*int          */ $stored_size;                // Größe der gespeicherten Datei
   protected /*int          */ $stored_bars;                // Anzahl der gespeicherten Bars der Datei
   protected /*int          */ $stored_from_offset;         // Offset der ersten gespeicherten Bar der Datei
   protected /*int          */ $stored_from_openTime;       // OpenTime der ersten gespeicherten Bar der Datei
   protected /*int          */ $stored_from_closeTime;      // CloseTime der ersten gespeicherten Bar der Datei
   protected /*int          */ $stored_to_offset;           // Offset der letzten gespeicherten Bar der Datei
   protected /*int          */ $stored_to_openTime;         // OpenTime der letzten gespeicherten Bar der Datei
   protected /*int          */ $stored_to_closeTime;        // CloseTime der letzten gespeicherten Bar der Datei

   // Metadaten: gespeichert + ungespeichert
   protected /*int          */ $full_size;                  // Größe der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_bars;                  // Anzahl der Bars der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_from_offset;           // Offset der ersten Bar der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_from_openTime;         // OpenTime der ersten Bar der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_from_closeTime;        // CloseTime der ersten Bar der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_to_offset;             // Offset der letzten Bar der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_to_openTime;           // OpenTime der letzten Bar der Datei inkl. ungespeicherter Daten im Schreibpuffer
   protected /*int          */ $full_to_closeTime;          // CloseTime der letzten Bar der Datei inkl. ungespeicherter Daten im Schreibpuffer


   // Getter
   public function getFileName()        { return $this->fileName;                   }
   public function getServerName()      { return $this->serverName;                 }
   public function getServerDirectory() { return $this->serverDirectory;            }
   public function isClosed()           { return (bool)$this->closed;               }

   public function getFormat()          { return $this->hstHeader->getFormat();     }
   public function getSymbol()          { return $this->hstHeader->getSymbol();     }
   public function getTimeframe()       { return $this->hstHeader->getPeriod();     }
   public function getPeriod()          { return $this->hstHeader->getPeriod();     }  // Alias
   public function getDigits()          { return $this->hstHeader->getDigits();     }
   public function getSyncMarker()      { return $this->hstHeader->getSyncMarker(); }  // wird vom Terminal u.U. überschrieben
   public function getLastSyncTime()    { return $this->lastSyncTime;               }  // wird vom Terminal nicht überschrieben


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
      else throw new plInvalidArgumentException('Invalid number of arguments: '.$argc);

      // Metadaten einlesen
      $this->lastSyncTime = $this->hstHeader->getLastSyncTime();


      if ($this->getPeriod() == PERIOD_M1) {
         //echoPre(__METHOD__.'()  lastSyncTime='.gmDate('D, d-M-Y H:i:s', $this->lastSyncTime));
      }
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
      if (!strLen($symbol))                         throw new plInvalidArgumentException('Invalid parameter $symbol: ""');
      if (strLen($symbol) > MT4::MAX_SYMBOL_LENGTH) throw new plInvalidArgumentException('Invalid parameter $symbol: "'.$symbol.'" (max '.MT4::MAX_SYMBOL_LENGTH.' characters)');
      if (!is_int($timeframe))                      throw new IllegalTypeException('Illegal type of parameter $timeframe: '.getType($timeframe));
      if (!MT4::isBuiltinTimeframe($timeframe))     throw new plInvalidArgumentException('Invalid parameter $timeframe: '.$timeframe);
      if (!is_string($serverDirectory))             throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.getType($serverDirectory));
      if (!is_dir($serverDirectory))                throw new plInvalidArgumentException('Directory "'.$serverDirectory.'" not found');

      $this->hstHeader       = new HistoryHeader($format, null, $symbol, $timeframe, $digits, null, null);
      $this->serverDirectory = realPath($serverDirectory);
      $this->serverName      = baseName($this->serverDirectory);
      $this->fileName        = $symbol.$timeframe.'.hst';

      // HistoryFile erzeugen bzw. zurücksetzen und Header neuschreiben
      mkDirWritable($this->serverDirectory);
      $fileName    = $this->serverDirectory.'/'.$this->fileName;
      $this->hFile = fOpen($fileName, 'wb');                      // FILE_WRITE
      $this->writeHistoryHeader($restoreFilePointer=false);       // der FilePointer steht jetzt hinter dem Header (an der ersten Bar)
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
      $barSize = ($this->getFormat()==400) ? MT4::HISTORY_BAR_400_SIZE : MT4::HISTORY_BAR_401_SIZE;
      if ($trailing=($fileSize-HistoryHeader::SIZE) % $barSize)                           throw new MetaTraderException('filesize.trailing: Corrupted file "'.$fileName.'": '.$trailing.' trailing bytes');
   }


   /**
    * Destructor
    *
    * Sorgt bei Zerstörung des Objekts dafür, daß der Schreibbuffer einer offenen Historydatei geleert und die Datei geschlossen wird.
    */
   public function __destruct() {
      // Ein Destructor darf während des Shutdowns keine Exception werfen.
      try {
         $this->close();
      }
      catch (Exception $ex) {
         Logger::handleException($ex, $inShutdownOnly=true);
         throw $ex;
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
         $this->flushBars();
      }

      // LastSyncTime aktualisieren
      if ($this->lastSyncTime) {
         if ($this->lastSyncTime != $this->hstHeader->getLastSyncTime()) {
            $this->hstHeader->setLastSyncTime($this->lastSyncTime);
            $this->writeHistoryHeader($restoreFilePointer=false);
         }
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
   public function setBufferSize($size) {
      if ($this->closed)  throw new IllegalStateException('Cannot process a closed '.__CLASS__);
      if (!is_int($size)) throw new IllegalTypeException('Illegal type of parameter $size: '.getType($size));
      if ($size < 0)      throw new plInvalidArgumentException('Invalid parameter $size: '.$size);

      $this->bufferSize = $size;
   }


   /**
    * Synchronisiert die Historydatei dieser Instanz mit den übergebenen Daten. Vorhandene Bars, die nach dem letzten
    * Synchronisationszeitpunkt der Datei hinzugefügt wurden und sich mit den übergebenen Daten überschneiden, werden
    * ersetzt. Vorhandene Bars, die sich mit den übergebenen Daten nicht überschneiden, bleiben unverändert.
    *
    * @param  MYFX_BAR[] $bars - Bardaten der Periode M1 (werden automatisch in die Periode der Historydatei konvertiert)
    *
    * @return bool - Erfolgsstatus
    */
   public function synchronize(array $bars) {
      if ($this->closed) throw new IllegalStateException('Cannot process a closed '.__CLASS__);
      if (!$bars) return false;

      return true;
   }


   /**
    * Fügt der Historydatei dieser Instanz Bardaten hinzu. Die Daten werden ans Ende der Zeitreihe angefügt.
    *
    * @param  MYFX_BAR[] $bars - Bardaten der Periode M1
    */
   public function addBars(array $bars) {
      if ($this->closed) throw new IllegalStateException('Cannot process a closed '.__CLASS__);
      if (!$bars) return;

      switch ($this->getTimeframe()) {
         case PERIOD_M1 : $this->addToM1 ($bars); break;
         case PERIOD_M5 : $this->addToM5 ($bars); break;
         case PERIOD_M15: $this->addToM15($bars); break;
         case PERIOD_M30: $this->addToM30($bars); break;
         case PERIOD_H1 : $this->addToH1 ($bars); break;
         case PERIOD_H4 : $this->addToH4 ($bars); break;
         case PERIOD_D1 : $this->addToD1 ($bars); break;
         case PERIOD_W1 : $this->addToW1 ($bars); break;
         case PERIOD_MN1: $this->addToMN1($bars); break;

         default: throw new plRuntimeException('unsupported timeframe '.$this->getTimeframe());
      }

      $size = sizeOf($bars);
      $this->lastSyncTime = $bars[$size-1]['time'] + 1*MINUTE;          // Zeitpunkt ist das Barende
   }


   /**
    * Fügt der M1-History dieser Instanz weitere Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToM1(array $bars) {
      $this->barBuffer = array_merge($this->barBuffer, $bars);

      $size    = sizeOf($this->barBuffer);
      $lastBar = $this->barBuffer[$size-1];
      $this->full_to_closeTime = $lastBar['time'] + 1*MINUTE;

      if ($size > $this->bufferSize)
         $this->flushBars($this->bufferSize);
   }


   /**
    * Fügt der M5-History dieser Instanz weitere Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToM5(array $bars) {
      $size       = sizeOf($this->barBuffer);
      $currentBar = null;
      if ($size)
         $currentBar =& $this->barBuffer[$size-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten M5-Bar erkennen
         if ($bar['time'] >= $this->full_to_closeTime) {
            // neue Bar beginnen
            $bar['time']            -=  $bar['time'] % 5*MINUTES;
            $this->full_to_closeTime =  $bar['time'] + 5*MINUTES;
            $this->barBuffer[]       =  $bar;
            $currentBar              =& $this->barBuffer[$size++];

            if ($size > $this->bufferSize)
               $size -= $this->flushBars($this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der M15-History dieser Instanz weitere Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToM15(array $bars) {
      $size       = sizeOf($this->barBuffer);
      $currentBar = null;
      if ($size)
         $currentBar =& $this->barBuffer[$size-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten M15-Bar erkennen
         if ($bar['time'] >= $this->full_to_closeTime) {
            // neue Bar beginnen
            $bar['time']            -=  $bar['time'] % 15*MINUTES;
            $this->full_to_closeTime =  $bar['time'] + 15*MINUTES;
            $this->barBuffer[]       =  $bar;
            $currentBar              =& $this->barBuffer[$size++];

            if ($size > $this->bufferSize)
               $size -= $this->flushBars($this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der M30-History dieser Instanz weitere Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToM30(array $bars) {
      $size       = sizeOf($this->barBuffer);
      $currentBar = null;
      if ($size)
         $currentBar =& $this->barBuffer[$size-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten M30-Bar erkennen
         if ($bar['time'] >= $this->full_to_closeTime) {
            // neue Bar beginnen
            $bar['time']            -=  $bar['time'] % 30*MINUTES;
            $this->full_to_closeTime =  $bar['time'] + 30*MINUTES;
            $this->barBuffer[]       =  $bar;
            $currentBar              =& $this->barBuffer[$size++];

            if ($size > $this->bufferSize)
               $size -= $this->flushBars($this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der H1-History dieser Instanz weitere Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToH1(array $bars) {
      $size       = sizeOf($this->barBuffer);
      $currentBar = null;
      if ($size)
         $currentBar =& $this->barBuffer[$size-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten H1-Bar erkennen
         if ($bar['time'] >= $this->full_to_closeTime) {
            // neue Bar beginnen
            $bar['time']            -=  $bar['time'] % HOUR;
            $this->full_to_closeTime =  $bar['time'] + 1*HOUR;
            $this->barBuffer[]       =  $bar;
            $currentBar              =& $this->barBuffer[$size++];

            if ($size > $this->bufferSize)
               $size -= $this->flushBars($this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der H4-History dieser Instanz weitere Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToH4(array $bars) {
      $size       = sizeOf($this->barBuffer);
      $currentBar = null;
      if ($size)
         $currentBar =& $this->barBuffer[$size-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten H4-Bar erkennen
         if ($bar['time'] >= $this->full_to_closeTime) {
            // neue Bar beginnen
            $bar['time']            -=  $bar['time'] % 4*HOURS;
            $this->full_to_closeTime =  $bar['time'] + 4*HOURS;
            $this->barBuffer[]       =  $bar;
            $currentBar              =& $this->barBuffer[$size++];

            if ($size > $this->bufferSize)
               $size -= $this->flushBars($this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der D1-History dieser Instanz weitere Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToD1(array $bars) {
      $size       = sizeOf($this->barBuffer);
      $currentBar = null;
      if ($size)
         $currentBar =& $this->barBuffer[$size-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten D1-Bar erkennen
         if ($bar['time'] >= $this->full_to_closeTime) {
            // neue Bar beginnen
            $bar['time']            -=  $bar['time'] % DAY;
            $this->full_to_closeTime =  $bar['time'] + 1*DAY;
            $this->barBuffer[]       =  $bar;
            $currentBar              =& $this->barBuffer[$size++];

            if ($size > $this->bufferSize)
               $size -= $this->flushBars($this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der W1-History dieser Instanz weitere Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToW1(array $bars) {
      $size       =  sizeOf($this->barBuffer);
      $currentBar = null;
      if ($size)
         $currentBar =& $this->barBuffer[$size-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten W1-Bar erkennen
         if ($bar['time'] >= $this->full_to_closeTime) {
            // neue Bar beginnen
            $dow = (int) gmDate('w', $bar['time']);
            $bar['time']            -=  $bar['time']%DAY + (($dow+6)%7)*DAYS;  // 00:00, Montag (Operator-Precedence beachten)
            $this->full_to_closeTime =  $bar['time'] + 1*WEEK;
            $this->barBuffer[]       =  $bar;
            $currentBar              =& $this->barBuffer[$size++];

            if ($size > $this->bufferSize)
               $size -= $this->flushBars($this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Fügt der MN1-History dieser Instanz weitere Daten hinzu.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten
    */
   private function addToMN1(array $bars) {
      $size       =  sizeOf($this->barBuffer);
      $currentBar = null;
      if ($size)
         $currentBar =& $this->barBuffer[$size-1];

      foreach ($bars as $bar) {
         // Wechsel zur nächsten MN1-Bar erkennen
         if ($bar['time'] >= $this->full_to_closeTime) {
            // neue Bar beginnen
            $dom = (int) gmDate('d', $bar['time']);
            $m   = (int) gmDate('m', $bar['time']);
            $y   = (int) gmDate('Y', $bar['time']);
            $bar['time']            -=  $bar['time']%DAYS + ($dom-1)*DAYS;    // 00:00, 1. des Monats (Operator-Precedence beachten)
            $this->full_to_closeTime =  gmMkTime(0, 0, 0, $m+1, 1, $y);       // 00:00, 1. des nächsten Monats
            $this->barBuffer[]       =  $bar;
            $currentBar              =& $this->barBuffer[$size++];

            if ($size > $this->bufferSize)
               $size -= $this->flushBars($this->bufferSize);
         }
         else {
            // letzte Bar aktualisieren ('time' und 'open' unverändert)
            if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
            if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                    $currentBar['close']  = $bar['close'];
                                                    $currentBar['ticks'] += $bar['ticks'];
         }
      }
   }


   /**
    * Schreibt eine Anzahl Bars aus dem Barbuffer in die entsprechende History-Datei.
    *
    * @param  int $count - Anzahl zu schreibender Bars (default: alle Bars)
    *
    * @return int - Anzahl der geschriebenen und aus dem Buffer gelöschten Bars
    */
   public function flushBars($count=PHP_INT_MAX) {
      if ($this->closed)   throw new IllegalStateException('Cannot process a closed '.__CLASS__);
      if (!is_int($count)) throw new IllegalTypeException('Illegal type of parameter $count: '.getType($count));
      if ($count < 0)      throw new plInvalidArgumentException('Invalid parameter $count: '.$count);

      $size = sizeOf($this->barBuffer);
      $todo = min($size, $count);
      if (!$todo)
         return 0;

      $divider = pow(10, $this->getDigits());
      $i = 0;

      foreach ($this->barBuffer as $i => $bar) {
         $T = $bar['time' ];
         $O = $bar['open' ]/$divider;
         $H = $bar['high' ]/$divider;
         $L = $bar['low'  ]/$divider;
         $C = $bar['close']/$divider;
         $V = $bar['ticks'];

         MT4::addHistoryBar400($this->hFile, $this->getDigits(), $T, $O, $H, $L, $C, $V);
         if ($i+1 == $todo)
            break;
      }

      if ($todo == $size) $this->barBuffer = array();
      else                $this->barBuffer = array_slice($this->barBuffer, $todo);

      return $todo;
   }


   /**
    * Schreibt den HistoryHeader in die Datei.
    *
    * @param  bool $restoreFilePointer - ob der FilePointer nach dem Schreiben restauriert werden soll
    *
    * @return int - Anzahl der geschriebenen Bytes
    */
   private function writeHistoryHeader($restoreFilePointer) {
      if (!is_bool($restoreFilePointer)) throw new IllegalTypeException('Illegal type of parameter $restoreFilePointer: '.getType($restoreFilePointer));

      $offset  = fTell($this->hFile);
      $written = 0;
      try {
         fSeek($this->hFile, 0);
         $format  = HistoryHeader::packFormat();
         $written = fWrite($this->hFile, pack($format, $this->hstHeader->getFormat(),           // V
                                                       $this->hstHeader->getCopyright(),        // a64
                                                       $this->hstHeader->getSymbol(),           // a12
                                                       $this->hstHeader->getPeriod(),           // V
                                                       $this->hstHeader->getDigits(),           // V
                                                       $this->hstHeader->getSyncMarker(),       // V
                                                       $this->hstHeader->getLastSyncTime()));   // V
         $restoreFilePointer && fSeek($this->hFile, $offset);                                   // x52
      }
      catch (Exception $ex) {
         $restoreFilePointer && fSeek($this->hFile, $offset);
         throw $ex;
      }

      return $written;
   }
}
