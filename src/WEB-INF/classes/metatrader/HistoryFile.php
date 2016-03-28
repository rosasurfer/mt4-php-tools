<?php
/**
 * Object-Wrapper für eine MT4-History-Datei ("*.hst")
 */
class HistoryFile extends Object {

   protected /*int       */ $format;
   protected /*string    */ $symbol;
   protected /*int       */ $timeframe;
   protected /*int       */ $digits;
   protected /*int       */ $syncMarker;
   protected /*int       */ $lastSyncTime;

   protected /*int       */ $hFile;                            // File-Handle einer geöffneten Datei
   protected /*string    */ $fileName;                         // einfacher Dateiname
   protected /*string    */ $serverName;                       // einfacher Servername
   protected /*string    */ $serverDirectory;                  // vollständiger Name des Serververzeichnisses

   protected /*MYFX_BAR[]*/ $barBuffer        = array();
   protected /*int       */ $bufferSize       = 10000;         // Default-Size des Barbuffers (ungespeicherte Bars)
   protected /*int       */ $currentCloseTime = PHP_INT_MIN;
   protected /*bool      */ $disposed         = false;         // ob die Resourcen dieser Instanz freigegeben sind


   // Getter
   public function getFormat()          { return $this->format;           }
   public function getSymbol()          { return $this->symbol;           }
   public function getTimeframe()       { return $this->timeframe;        }
   public function getDigits()          { return $this->digits;           }
   public function getSyncMarker()      { return $this->syncMarker;       }
   public function getLastSyncTime()    { return $this->lastSyncTime;     }

   public function getFileName()        { return $this->fileName;         }
   public function getServerName()      { return $this->serverName;       }
   public function getServerDirectory() { return $this->serverDirectory;  }

   public function getBufferSize()      { return $this->bifferSize;       }
   public function isDisposed()         { return (bool)$this->disposed;   }


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
      if (!is_int($digits))                         throw new IllegalTypeException('Illegal type of parameter $digits: '.getType($digits));
      if ($digits < 0)                              throw new plInvalidArgumentException('Invalid parameter $digits: '.$digits);
      if (!is_int($format))                         throw new IllegalTypeException('Illegal type of parameter $format: '.getType($format));
      if ($format!=400 && $format!=401)             throw new plInvalidArgumentException('Invalid parameter $format: '.$format.' (can be 400 or 401)');
      if (!is_string($serverDirectory))             throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.getType($serverDirectory));
      if (!is_dir($serverDirectory))                throw new plInvalidArgumentException('Directory "'.$serverDirectory.'" not found');

      $this->symbol          = $symbol;
      $this->timeframe       = $timeframe;
      $this->digits          = $digits;
      $this->format          = $format;
      $this->serverDirectory = realPath($serverDirectory);
      $this->serverName      = baseName($this->serverDirectory);
      $this->fileName        = $symbol.$timeframe.'.hst';
      mkDirWritable($this->serverDirectory);

      // neuen HistoryHeader initialisieren
      $hh = MT4::createHistoryHeader();
      $hh['format'   ] = $this->format;
      $hh['copyright'] = MyFX::$symbols[strToUpper($symbol)]['description'];
      $hh['symbol'   ] = $this->symbol;
      $hh['period'   ] = $this->timeframe;
      $hh['digits'   ] = $this->digits;

      // HistoryFile erzeugen bzw. zurücksetzen und Header neuschreiben
      $fileName    = $this->serverDirectory.'/'.$this->fileName;
      $this->hFile = fOpen($fileName, 'wb');             // FILE_WRITE
      MT4::writeHistoryHeader($this->hFile, $hh);
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
      if ($fileSize < HistoryHeader::STRUCT_SIZE) throw new MetaTraderException('filesize.insufficient: Invalid or unsupported format of "'.$fileName.'": fileSize='.$fileSize.', minFileSize='.HistoryHeader::STRUCT_SIZE);

      // Datei öffnen und Header einlesen
      $this->hFile = fOpen($fileName, 'r+b');               // FILE_READ|FILE_WRITE
      $header      = unpack(HistoryHeader::unpackFormat(), fRead($this->hFile, HistoryHeader::STRUCT_SIZE));

      // Header-Daten validieren
      if ($header['format']!=400 && $header['format']!=401)                          throw new MetaTraderException('version.unknown: Invalid or unsupported history format version of "'.$fileName.'": '.$header['format']);
      if (!strCompareI($this->fileName, $header['symbol'].$header['period'].'.hst')) throw new MetaTraderException('filename.mis-match: File name/header mis-match of "'.$fileName.'": header="'.$header['symbol'].','.MyFX::periodDescription($header['period']).'"');
      $barSize = ($header['format']==400) ? MT4::HISTORY_BAR_400_SIZE : MT4::HISTORY_BAR_401_SIZE;
      if ($trailing=($fileSize-HistoryHeader::STRUCT_SIZE) % $barSize)               throw new MetaTraderException('filesize.trailing: Corrupted file "'.$fileName.'": '.$trailing.' trailing bytes');

      // Header-Daten speichern
      $this->format       = $header['format'      ];
      $this->symbol       = $header['symbol'      ];
      $this->timeframe    = $header['period'      ];
      $this->digits       = $header['digits'      ];
      $this->syncMarker   = $header['syncMarker'  ];
      $this->lastSyncTime = $header['lastSyncTime'];

      // TODO: count(Bars) und From/To einlesen
   }


   /**
    * Destructor
    *
    * Sorgt bei Zerstörung des Objekts dafür, daß der Schreibbuffer einer offenen Historydatei geleert und die Datei geschlossen wird.
    */
   public function __destruct() {
      // Ein Destructor darf während des Shutdowns keine Exception werfen.
      try {
         $this->dispose();
      }
      catch (Exception $ex) {
         Logger::handleException($ex, $inShutdownOnly=true);
         throw $ex;
      }
   }


   /**
    * Gibt die Resourcen dieser Instanz frei. Nach dem Aufruf kann die Instanz nicht mehr verwendet werden.
    *
    * @return bool - Erfolgsstatus; FALSE, wenn die Instanz bereits disposed war
    */
   public function dispose() {
      if ($this->isDisposed())
         return false;

      if (is_resource($this->hFile)) {
         $this->flushBars();
         $hTmp=$this->hFile; $this->hFile=null;
         fClose($hTmp);
      }
      return $this->disposed=true;
   }


   /**
    * Setzt die Buffergröße für vor dem Schreiben zwischenzuspeichernde Bars dieser Instanz.
    *
    * @param  int $size - Buffergröße
    */
   public function setBufferSize($size) {
      if ($this->disposed) throw new IllegalStateException('cannot modify a disposed '.__CLASS__.' instance');
      if (!is_int($size))  throw new IllegalTypeException('Illegal type of parameter $size: '.getType($size));
      if ($size < 0)       throw new plInvalidArgumentException('Invalid parameter $size: '.$size);

      $this->bufferSize = $size;
   }


   /**
    * Fügt dieser Instanz M1-Bardaten hinzu. Die Bardaten werden am Ende der Zeitreihe gespeichert.
    *
    * @param  array $bars - Array mit MYFX_BAR-Daten der Periode M1
    */
   public function addM1Bars(array $bars) {
      if ($this->disposed) throw new IllegalStateException('cannot modify a disposed '.__CLASS__.' instance');

      switch ($this->timeframe) {
         case PERIOD_M1 : $this->addToM1 ($bars); break;
         case PERIOD_M5 : $this->addToM5 ($bars); break;
         case PERIOD_M15: $this->addToM15($bars); break;
         case PERIOD_M30: $this->addToM30($bars); break;
         case PERIOD_H1 : $this->addToH1 ($bars); break;
         case PERIOD_H4 : $this->addToH4 ($bars); break;
         case PERIOD_D1 : $this->addToD1 ($bars); break;
         case PERIOD_W1 : $this->addToW1 ($bars); break;
         case PERIOD_MN1: $this->addToMN1($bars); break;

         default: throw new plRuntimeException('unsupported timeframe '.$this->timeframe);
      }
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
      $this->currentCloseTime = $lastBar['time'] + 1*MINUTE;

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
         if ($bar['time'] >= $this->currentCloseTime) {
            // neue Bar beginnen
            $bar['time']           -=  $bar['time'] % 5*MINUTES;
            $this->currentCloseTime =  $bar['time'] + 5*MINUTES;
            $this->barBuffer[]      =  $bar;
            $currentBar             =& $this->barBuffer[$size++];

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
         if ($bar['time'] >= $this->currentCloseTime) {
            // neue Bar beginnen
            $bar['time']           -=  $bar['time'] % 15*MINUTES;
            $this->currentCloseTime =  $bar['time'] + 15*MINUTES;
            $this->barBuffer[]      =  $bar;
            $currentBar             =& $this->barBuffer[$size++];

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
         if ($bar['time'] >= $this->currentCloseTime) {
            // neue Bar beginnen
            $bar['time']           -=  $bar['time'] % 30*MINUTES;
            $this->currentCloseTime =  $bar['time'] + 30*MINUTES;
            $this->barBuffer[]      =  $bar;
            $currentBar             =& $this->barBuffer[$size++];

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
         if ($bar['time'] >= $this->currentCloseTime) {
            // neue Bar beginnen
            $bar['time']           -=  $bar['time'] % HOUR;
            $this->currentCloseTime =  $bar['time'] + 1*HOUR;
            $this->barBuffer[]      =  $bar;
            $currentBar             =& $this->barBuffer[$size++];

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
         if ($bar['time'] >= $this->currentCloseTime) {
            // neue Bar beginnen
            $bar['time']           -=  $bar['time'] % 4*HOURS;
            $this->currentCloseTime =  $bar['time'] + 4*HOURS;
            $this->barBuffer[]      =  $bar;
            $currentBar             =& $this->barBuffer[$size++];

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
         if ($bar['time'] >= $this->currentCloseTime) {
            // neue Bar beginnen
            $bar['time']           -=  $bar['time'] % DAY;
            $this->currentCloseTime =  $bar['time'] + 1*DAY;
            $this->barBuffer[]      =  $bar;
            $currentBar             =& $this->barBuffer[$size++];

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
         if ($bar['time'] >= $this->currentCloseTime) {
            // neue Bar beginnen
            $dow = (int) gmDate('w', $bar['time']);
            $bar['time']           -=  $bar['time']%DAY + (($dow+6)%7)*DAYS;  // 00:00, Montag (Operator-Precedence beachten)
            $this->currentCloseTime =  $bar['time'] + 1*WEEK;
            $this->barBuffer[]      =  $bar;
            $currentBar             =& $this->barBuffer[$size++];

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
         if ($bar['time'] >= $this->currentCloseTime) {
            // neue Bar beginnen
            $dom = (int) gmDate('d', $bar['time']);
            $m   = (int) gmDate('m', $bar['time']);
            $y   = (int) gmDate('Y', $bar['time']);
            $bar['time']           -=  $bar['time']%DAYS + ($dom-1)*DAYS;    // 00:00, 1. des Monats (Operator-Precedence beachten)
            $this->currentCloseTime =  gmMkTime(0, 0, 0, $m+1, 1, $y);       // 00:00, 1. des nächsten Monats
            $this->barBuffer[]      =  $bar;
            $currentBar             =& $this->barBuffer[$size++];

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
    * Schreibt eine Anzahl Bars der Instanz in die entsprechende History-Datei und löscht sie aus dem Barbuffer.
    *
    * @param  int $count - Anzahl zu schreibender Bars (default: alle Bars)
    *
    * @return int - Anzahl der geschriebenen und aus dem Buffer gelöschten Bars
    */
   private function flushBars($count=PHP_INT_MAX) {
      if (!is_int($count)) throw new IllegalTypeException('Illegal type of parameter $count: '.getType($count));
      if ($count < 0)      throw new plInvalidArgumentException('Invalid parameter $count: '.$count);

      $size = sizeOf($this->barBuffer);
      $todo = min($size, $count);
      if (!$todo)
         return 0;

      $divider = pow(10, $this->digits);
      $i = 0;

      foreach ($this->barBuffer as $i => $bar) {
         $T = $bar['time' ];
         $O = $bar['open' ]/$divider;
         $H = $bar['high' ]/$divider;
         $L = $bar['low'  ]/$divider;
         $C = $bar['close']/$divider;
         $V = $bar['ticks'];

         MT4::addHistoryBar400($this->hFile, $this->digits, $T, $O, $H, $L, $C, $V);
         if ($i+1 == $todo)
            break;
      }

      if ($todo == $size) $this->barBuffer = array();
      else                $this->barBuffer = array_slice($this->barBuffer, $todo);

      return $todo;
   }
}
