#!/usr/bin/php
<?php
/**
 * Verzeichnislisting für MetaTrader-Historydateien
 */
require(dirName(realPath(__FILE__)).'/../../config.php');


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenparameter auswerten
$args = array_slice($_SERVER['argv'], 1);
!$args && ($args[0]='*');
$arg0 = $args[0];                                                                // TODO: Noch wird nur der erste Parameter ausgewertet.
realPath($arg0) && ($arg0=realPath($arg0));
if (is_dir($arg0)) { $dirName = $arg0;          $baseName = '';              }   // glob() wurde hier nicht verwendet, da es beim
else               { $dirName = dirName($arg0); $baseName = baseName($arg0); }   // Patternmatching Groß-/Kleinschreibung unterscheidet.

!$baseName && ($baseName='*');
$baseName = str_replace('*', '.*', str_replace('.', '\.', $baseName));           // für RegExp in (4.1): '*' erweitern, '.' escapen


// (2) Source-Verzeichnis(se) bestimmen
$dirs = is_dir($dirName) ? array($dirName) : glob($dirName, GLOB_NOESCAPE|GLOB_ONLYDIR);
!$dirs && echoPre('directory not found "'.$args[0].'"') & exit(1);


$foundFiles = 0;


// (3) alle Verzeichnisse durchlaufen
foreach ($dirs as $dirName) {
   $files   = array();
   $formats = $symbols = $periods = $aDigits = $syncMarks = $lastSyncs = $timezoneIds = array();
   $bars    = $barsFrom = $barsTo = $errors = array();

   // (4.1) Verzeichnis öffnen, Dateinamen einlesen und filtern
   $dir = Dir($dirName);

   while (($fileName=$dir->read()) !== false) {
      if (preg_match("/^$baseName$/i", $fileName) && preg_match('/^(.+)\.hst$/i', $fileName, $match)) {
         // (4.2) Daten auslesen und zum Sortieren zwischenspeichern
         $files[]  = $fileName;
         $fileSize = fileSize($dir->path.'/'.$fileName);

         if ($fileSize < MT4::HISTORY_HEADER_SIZE) {
            // Fehlermeldung zwischenspeichern
            $formats    [] = null;
            $symbols    [] = strToUpper($match[1]);
            $periods    [] = null;
            $aDigits    [] = null;
            $syncMarks  [] = null;
            $lastSyncs  [] = null;
            $timezoneIds[] = null;
            $bars       [] = null;
            $barsFrom   [] = null;
            $barsTo     [] = null;
            $errors     [] = 'invalid or unsupported file format: file size of '.$fileSize.' < minFileSize of '.MT4::HISTORY_HEADER_SIZE;
            continue;
         }

         $hFile  = fOpen($dir->path.'/'.$fileName, 'rb');
         $header = unpack(MT4::HISTORY_HEADER_getUnpackFormat(), fRead($hFile, MT4::HISTORY_HEADER_SIZE));

         if ($header['format']==400 || $header['format']==401) {
            // Daten zwischenspeichern
            $formats    [] =            $header['format'    ];
            $symbols    [] = strToUpper($header['symbol'    ]);
            $periods    [] =            $header['period'    ];
            $aDigits    [] =            $header['digits'    ];
            $syncMarks  [] =            $header['syncMark'  ] ? gmDate('Y.m.d H:i:s', $header['syncMark']) : null;
            $lastSyncs  [] =            $header['lastSync'  ] ? gmDate('Y.m.d H:i:s', $header['lastSync']) : null;
            $timezoneIds[] =            $header['timezoneId'];

            if ($header['format'] == 400) { $barSize = MT4::HISTORY_BAR_400_SIZE; $barFormat = 'Vtime/dopen/dlow/dhigh/dclose/dticks';                          }
            else                   /*401*/{ $barSize = MT4::HISTORY_BAR_401_SIZE; $barFormat = 'Vtime/x4/dopen/dhigh/dlow/dclose/Vticks/x4/lspread/Vvolume/x4'; }

            $iBars    = floor(($fileSize-MT4::HISTORY_HEADER_SIZE)/$barSize);
            $barFrom = $barTo = array();
            if ($iBars) {
               $barFrom  = unpack($barFormat, fRead($hFile, $barSize));
               if ($iBars > 1) {
                  fSeek($hFile, MT4::HISTORY_HEADER_SIZE + $barSize*($iBars-1));
                  $barTo = unpack($barFormat, fRead($hFile, $barSize));
               }
            }

            $bars    [] = $iBars;
            $barsFrom[] = $barFrom ? gmDate('Y.m.d H:i:s', $barFrom['time']) : null;
            $barsTo  [] = $barTo   ? gmDate('Y.m.d H:i:s', $barTo  ['time']) : null;

            if (!strCompareI($fileName, $header['symbol'].$header['period'].'.hst')) {
               $formats[sizeOf($formats)-1] = null;
               $symbols[sizeOf($symbols)-1] = strToUpper($match[1]);
               $periods[sizeOf($periods)-1] = null;
               $error = 'file name/data mis-match: data='.$header['symbol'].','.MyFX::periodDescription($header['period']);
            }
            else {
               $trailingBytes = ($fileSize-MT4::HISTORY_HEADER_SIZE) % $barSize;
               $error = !$trailingBytes ? null : 'corrupted ('.$trailingBytes.' trailing bytes)';
            }
            $errors[] = $error;
         }
         else {
            // Fehlermeldung zwischenspeichern
            $formats    [] = null;
            $symbols    [] = strToUpper($match[1]);
            $periods    [] = null;
            $aDigits    [] = null;
            $syncMarks  [] = null;
            $lastSyncs  [] = null;
            $timezoneIds[] = null;
            $bars       [] = null;
            $barsFrom   [] = null;
            $barsTo     [] = null;
            $errors     [] = 'invalid or unsupported history file format: '.$header['format'];
         }
         fClose($hFile);
      }
   }
   $dir->close();
   if (!$files) continue;
   $foundFiles += sizeOf($files);

   // (4.3) Daten sortieren: ORDER by Symbol, Periode (ASC ist default); alle anderen "Spalten" mitsortieren
   array_multisort($symbols, SORT_ASC, $periods, SORT_ASC/*bis_hier*/, array_keys($symbols), $files, $formats, $aDigits, $syncMarks, $lastSyncs, $timezoneIds, $bars, $barsFrom, $barsTo, $errors);

   // (4.4) Tabellen-Format definieren und Header ausgeben
   $tableHeader    = 'Symbol           Digits  SyncMark             LastSync                  Bars  From                 To                   Format';
   $tableSeparator = '------------------------------------------------------------------------------------------------------------------------------';
   $tableRowFormat = '%-15s    %d     %-19s  %-19s  %9s  %-19s  %-19s    %s  %s';
   echoPre(NL);
   if (sizeOf($dirs) > 1)
      echoPre($dir->path.':');
   echoPre($tableHeader);

   // (4.5) sortierte Daten ausgeben
   $lastSymbol = null;
   foreach ($files as $i => $fileName) {
      if ($symbols[$i] != $lastSymbol)
         echoPre($tableSeparator);

      if ($formats[$i]) {
         $period = MyFX::periodDescription($periods[$i]);
         echoPre(sprintf($tableRowFormat, $symbols[$i].','.$period, $aDigits[$i], $syncMarks[$i], $lastSyncs[$i], number_format($bars[$i]), $barsFrom[$i], $barsTo[$i], $formats[$i], $errors[$i]));
      }
      else {
         echoPre(str_pad($fileName, 18).' '.$errors[$i]);
      }
      $lastSymbol = $symbols[$i];
   }
   echoPre($tableSeparator);
}


// (5) Gesamtzusammenfassung für alle Verzeichnisse
!$foundFiles && echoPre('no history files found for "'.$args[0].'"') & exit(1);


// (6) reguläres Programm-Ende
exit(0);


// --- Funktionen ---------------------------------------------------------------------------------------------------------------------------------------------


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (!is_null($message))
      echo($message.NL.NL);

   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END

  Syntax: $self  [file-pattern [...]]


END;
}
