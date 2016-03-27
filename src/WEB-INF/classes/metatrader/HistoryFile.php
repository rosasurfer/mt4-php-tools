<?php
/**
 * Object-Wrapper für eine MT4-History-Datei ("*.hst")
 */
class HistoryFile extends Object {

   protected /*int   */ $format;
   protected /*string*/ $symbol;
   protected /*int   */ $timeframe;
   protected /*int   */ $digits;
   protected /*int   */ $syncMark;
   protected /*int   */ $lastSync;

   protected /*string*/ $fileName;
   protected /*string*/ $serverName;
   protected /*string*/ $serverDirectory;

   // Getter
   public function getFormat()          { return $this->format;          }
   public function getSymbol()          { return $this->symbol;          }
   public function getTimeframe()       { return $this->timeframe;       }
   public function getDigits()          { return $this->digits;          }
   public function getSyncMark()        { return $this->syncMark;        }
   public function getLastSync()        { return $this->lastSync;        }

   public function getFileName()        { return $this->fileName;        }
   public function getServerName()      { return $this->serverName;      }
   public function getServerDirectory() { return $this->serverDirectory; }


   /**
    * Überladener Constructor.
    *
    * Signaturen:
    * -----------
    * new HistoryFile($fileName)
    * new HistoryFile($symbol, $digits, $format, $serverDirectory)
    */
   public function __construct($arg1=null, $arg2=null, $arg3=null, $arg4=null, $arg5=null) {
      $argc = func_num_args();

      if      ($argc == 1) $this->__construct_1($arg1);
      else if ($argc == 5) $this->__construct_2($arg1, $arg2, $arg3, $arg4, $arg5);

      else throw new plInvalidArgumentException('Invalid number of arguments: '.$argc);
   }


   /**
    * Constructor 1
    *
    * Erzeugt anhand einer vorhandenen History-Datei eine Instanz. Existierende Daten werden nicht gelöscht.
    *
    * @param  string $fileName - Name einer History-Datei
    */
   private function __construct_1($fileName) {
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
      $hFile  = fOpen($fileName, 'rb');                  // FILE_READ
      $header = unpack(HistoryHeader::unpackFormat(), fRead($hFile, HistoryHeader::STRUCT_SIZE));
      fClose($hFile);

      // Header-Daten validieren
      if ($header['format']!=400 && $header['format']!=401)                          throw new MetaTraderException('version.unknown: Invalid or unsupported history format version of "'.$fileName.'": '.$header['format']);
      if (!strCompareI($this->fileName, $header['symbol'].$header['period'].'.hst')) throw new MetaTraderException('filename.mis-match: File name/header mis-match of "'.$fileName.'": header="'.$header['symbol'].','.MyFX::periodDescription($header['period']).'"');
      $barSize = ($header['format']==400) ? MT4::HISTORY_BAR_400_SIZE : MT4::HISTORY_BAR_401_SIZE;
      if ($trailing=($fileSize-HistoryHeader::STRUCT_SIZE) % $barSize)               throw new MetaTraderException('filesize.trailing: Corrupted file "'.$fileName.'": '.$trailing.' trailing bytes');

      // Header-Daten speichern
      $this->format    = $header['format'  ];
      $this->symbol    = $header['symbol'  ];
      $this->timeframe = $header['period'  ];
      $this->digits    = $header['digits'  ];
      $this->syncMark  = $header['syncMark'];
      $this->lastSync  = $header['lastSync'];

      // TODO: count(Bars) und From/To einlesen
   }
}
