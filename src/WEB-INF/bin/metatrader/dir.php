#!/usr/bin/php
<?php
/**
 * Verzeichnislisting für MetaTrader-Historydateien
 */
require(dirName(realPath(__FILE__)).'/../../config.php');


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenparameter auswerten
$args = array_slice($_SERVER['argv'], 1);
!$args && ($args[]='.');                                          // Historydateien des aktuellen Verzeichnis
$expandedArgs = array();

foreach ($args as $arg) {
   $value = $arg;
   strIsQuoted($value) && ($value=strLeft(strRight($value, -1), -1));

   if (file_exists($value)) {
      // explizites Argument oder Argument von Shell expandiert
      if (is_file($value)) {                                      // existierende Datei beliebigen Typs (alle werden analysiert)
         $expandedArgs[] = dirName($value).'/'.baseName($value);  // durch dirName() haben wir immer ein Verzeichnis für die Ausgabe (ggf. '.')
         continue;
      }
      // Verzeichnis, Glob-Pattern bereitstellen (siehe unten)
      $globPattern = $value.'/*.[Hh][Ss][Tt]';                    // *.hst in beliebiger Groß/Kleinschreibung
   }
   else {
      // Argument existiert nicht, Namen selbst expandieren und auf Existenz prüfen (z.B. immer unter Windows)
      strEndsWith($value, array('/', '\\')) && ($value.='*');
      $dirName  = dirName($value);
      $baseName = baseName($value); strEndsWith($baseName, '*') && ($baseName.='.hst');

      // um Groß-/Kleinschreibung von Symbolen ignorieren zu können, wird $baseName modifiziert
      $len = strLen($baseName); $s = ''; $inBrace = $inBracket = false;
      for ($i=0; $i < $len; $i++) {
         $char = $baseName[$i];                                   // angegebene Expansion-Pattern werden berücksichtigt: {a,b,c}, [0-9] etc.
         if ($inBrace  ) { $inBrace   = ($char!='}'); $s .= $char; continue; }
         if ($inBracket) { $inBracket = ($char!=']'); $s .= $char; continue; }
         if (($inBrace=($char=='{')) || ($inBracket=($char=='[')) || !ctype_alpha($char)) {
            $s .= $char;
            continue;
         }
         $s .= '['.strToUpper($char).strToLower($char).']';
      }
      $globPattern = $dirName.'/'.$s;                             // $baseName=eu*.hst  =>  $s=[Ee][Uu]*.[Hh][Ss][Tt]
   }

   // Glob-Pattern einlesen und gefundene Dateien speichern
   $entries = glob($globPattern, GLOB_NOESCAPE|GLOB_BRACE|GLOB_ERR);
   foreach ($entries as $entry) if (is_file($entry))
      $expandedArgs[] = $entry;
}
!$expandedArgs && echoPre('no history files found') & exit(1);
sort($expandedArgs);                                              // alles sortieren (Dateien im aktuellen Verzeichnis ans Ende)


// (2) gefundene Dateien verzeichnisweise verarbeiten
$files   = array();
$formats = $symbols = $symbolsU = $periods = $digits = $syncMarks = $lastSyncs = $timezoneIds = array();
$bars    = $barsFrom = $barsTo = $errors = array();
$dirName = $lastDir = null;

foreach ($expandedArgs as $fileName) {
   $dirName  = dirName($fileName);
   $baseName = baseName($fileName);
   if ($dirName!=$lastDir && $files) {                            // bei jedem neuen Verzeichnis vorherige angesammelte Daten anzeigen
      showDirResults($dirName, $files, $formats, $symbols, $symbolsU, $periods, $digits, $syncMarks, $lastSyncs, $timezoneIds, $bars, $barsFrom, $barsTo, $errors);
      $files   = array();
      $formats = $symbols = $symbolsU = $periods = $digits = $syncMarks = $lastSyncs = $timezoneIds = array();
      $bars    = $barsFrom = $barsTo = $errors = array();
   }
   $lastDir = $dirName;

   // Daten auslesen und für Anzeige zwischenspeichern
   $files[]  = $baseName;
   $fileSize = fileSize($fileName);

   if ($fileSize < MT4::HISTORY_HEADER_SIZE) {
      // Fehlermeldung zwischenspeichern
      $formats    [] = null;
      $symbols    [] = ($name=strLeftTo($baseName, '.hst'));
      $symbolsU   [] = strToUpper($name);
      $periods    [] = null;
      $digits     [] = null;
      $syncMarks  [] = null;
      $lastSyncs  [] = null;
      $timezoneIds[] = null;
      $bars       [] = null;
      $barsFrom   [] = null;
      $barsTo     [] = null;
      $errors     [] = 'invalid or unsupported file format: file size of '.$fileSize.' < minFileSize of '.MT4::HISTORY_HEADER_SIZE;
      continue;
   }

   $hFile  = fOpen($fileName, 'rb');
   $header = unpack(MT4::HISTORY_HEADER_getUnpackFormat(), fRead($hFile, MT4::HISTORY_HEADER_SIZE));

   if ($header['format']==400 || $header['format']==401) {
      // Daten zwischenspeichern
      $formats    [] =            $header['format'    ];
      $symbols    [] =            $header['symbol'    ];
      $symbolsU   [] = strToUpper($header['symbol'    ]);
      $periods    [] =            $header['period'    ];
      $digits     [] =            $header['digits'    ];
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

      if (!strCompareI($baseName, $header['symbol'].$header['period'].'.hst')) {
         $formats [sizeOf($formats )-1] = null;
         $symbols [sizeOf($symbols )-1] = ($name=strLeftTo($baseName, '.hst'));
         $symbolsU[sizeOf($symbolsU)-1] = strToUpper($name);
         $periods [sizeOf($periods )-1] = null;
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
      $symbols    [] = ($name=strLeftTo($baseName, '.hst'));
      $symbolsU   [] = strToUpper($name);
      $periods    [] = null;
      $digits     [] = null;
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

// abschließende Ausgabe für das letzte Verzeichnis
if ($files) {
   showDirResults($dirName, $files, $formats, $symbols, $symbolsU, $periods, $digits, $syncMarks, $lastSyncs, $timezoneIds, $bars, $barsFrom, $barsTo, $errors);
}


// (4) reguläres Programm-Ende
exit(0);


// --- Funktionen ---------------------------------------------------------------------------------------------------------------------------------------------


/**
 * Zeigt das Listing eines Verzeichnisses an.
 *
 * @param  string $dirName
 * @param  array  ...
 */
function showDirResults($dirName, array $files, array $formats, array $symbols, array $symbolsU, array $periods, array $digits, array $syncMarks, array $lastSyncs, array $timezoneIds, array $bars, array $barsFrom, array $barsTo, array $errors) {
   // Daten sortieren: ORDER by Symbol, Periode (ASC ist default); alle anderen "Spalten" mitsortieren
   array_multisort($symbolsU, SORT_ASC, $periods, SORT_ASC/*bis_hierher*/, array_keys($symbolsU), $symbols, $files, $formats, $digits, $syncMarks, $lastSyncs, $timezoneIds, $bars, $barsFrom, $barsTo, $errors);

   // Tabellen-Format definieren und Header ausgeben
   $tableHeader    = 'Symbol           Digits  SyncMark             LastSync                  Bars  From                 To                   Format';
   $tableSeparator = '------------------------------------------------------------------------------------------------------------------------------';
   $tableRowFormat = '%-15s    %d     %-19s  %-19s  %9s  %-19s  %-19s    %s  %s';
   echoPre(NL);
   echoPre($dirName.':');
   echoPre($tableHeader);

   // sortierte Daten ausgeben
   $lastSymbol = null;
   foreach ($files as $i => $fileName) {
      if ($symbols[$i] != $lastSymbol)
         echoPre($tableSeparator);

      if ($formats[$i]) {
         $period = MyFX::periodDescription($periods[$i]);
         echoPre(trim(sprintf($tableRowFormat, $symbols[$i].','.$period, $digits[$i], $syncMarks[$i], $lastSyncs[$i], number_format($bars[$i]), $barsFrom[$i], $barsTo[$i], $formats[$i], $errors[$i])));
      }
      else {
         echoPre(str_pad($fileName, 18).' '.$errors[$i]);
      }
      $lastSymbol = $symbols[$i];
   }
   echoPre($tableSeparator);
}


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
