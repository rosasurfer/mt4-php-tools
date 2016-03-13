#!/usr/bin/php
<?php
/**
 * Listet die Symbol-Informationen einer MetaTrader-Datei "symbols.raw" auf.
 *
 * @see Struct-Formate in MT4Expander.dll::Expander.h
 */
require(dirName(realPath(__FILE__)).'/../../config.php');


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$options = array();
$fields  = array();


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und auswerten
$args = array_slice($_SERVER['argv'], 1);

// Hilfe
foreach ($args as $i => $arg) {
   if ($arg == '-h') help() & exit(1);
}

// Optionen und Argumente parsen
foreach ($args as $i => $arg) {
   // -f=FILE
   if (strStartsWith($arg, '-f=')) {
      if (isSet($options['symbol'])) help('invalid/multiple file arguments: -f='.$arg) & exit(1);
      $value = $arg = strRight($arg, -3);
      if (strIsQuoted($value)) $value = strLeft(strRight($value, -1), 1);
      if (!is_file($value)) help('file not found: '.$arg) & exit(1);
      $options['file'    ] = $value;
      $options['fullFile'] = realPath($value);
      continue;
   }

   // count symbols
   if ($arg == '-c') {
      $options['countSymbols'] = true;
      continue;
   }

   // list available fields
   if ($arg == '-l') {
      $options['listFields'] = true;
      break;
   }

   // display all fields
   if ($arg == '++') {
      $fields = array('++');
      continue;
   }

   // display specific field
   if (strStartsWith($arg, '+')) {
      $key = subStr($arg, 1);
      if (!strLen($key)) help('invalid field specifier: '.$arg) & exit(1);
      unset($fields['-'.$key]);                                               // drops element if it exists
      if (!in_array('++', $fields) && !in_array('+'.$key, $fields)) {
         $fields[] = '+'.$key;
      }
      continue;
   }

   // do not display specific field
   if (strStartsWith($arg, '-')) {
      $key = subStr($arg, 1);
      if (!strLen($key)) help('invalid field specifier: '.$arg) & exit(1);
      unset($fields['+'.$key]);                                               // drops element if it exists
      if (in_array('++', $fields) && !in_array('-'.$key, $fields)) {
         $fields[] = '-'.$key;
      }
      continue;
   }
}


// (2) ggf. verfügbare Felder anzeigen und abbrechen
if (isSet($options['listFields'])) {
   echoPre($s='Available fields:');
   echoPre(str_repeat('-', strLen($s)));

   $fields = MT4::SYMBOL_getFields();                                // Feld 'leverage' dynamisch hinzufügen
   //array_splice($fields, array_search('marginDivider', $fields)+1, 0, array('leverage'));

   foreach ($fields as $field) {
      echoPre(ucFirst($field));
   }
   exit(0);
}


// (3) Default-Parameter setzen
if (!isSet($options['file'])) {
   $file = 'symbols.raw';
   if (!is_file($file)) help('No file "symbols.raw" found in current directory') & exit(1);
   $options['file'    ] = $file;
   $options['fullFile'] = realPath($file);
}


// (4) Symbolinformationen auflisten
if (!listMT4Symbols($options, $fields))
   exit(1);


// (5) erfolgreiches Programm-Ende
exit(0);


// --- Funktionen ---------------------------------------------------------------------------------------------------------------------------------------------


/**
 * Listet die Informationen einer Symboldatei auf.
 *
 * @param  array $options - Optionen
 * @param  array $fields  - anzuzeigende Felder
 *
 * @return bool - Erfolgsstatus
 */
function listMT4Symbols(array $options, array $fieldArgs) {
   $file     = $options['file'];
   $fileSize = fileSize($file);

   if ($fileSize < MT4::SYMBOL_SIZE) {
      echoPre('invalid or unsupported file format: file size of '.$fileSize.' < MinFileSize of '.MT4::SYMBOL_SIZE);
      return false;
   }

   // Dateigröße prüfen
   $symbols     = array();
   $symbolsSize = (int)($fileSize/MT4::SYMBOL_SIZE);
   if ($fileSize % MT4::SYMBOL_SIZE) {
      echoPre('warn: corrupted file "'.$file.'" contains '.($fileSize % MT4::SYMBOL_SIZE).' trailing bytes');
   }

   // Symbole auslesen
   $hFile = fOpen($file, 'rb');
   for ($i=0; $i < $symbolsSize; $i++) {
      $symbols[] = unpack(MT4::SYMBOL_getUnpackFormat(), fRead($hFile, MT4::SYMBOL_SIZE));
   }
   fClose($hFile);

   // ggf. Anzahl der Symbole anzeigen und abbrechen
   if (isSet($options['countSymbols'])) {
      echoPre(($size=sizeof($symbols)).' symbol'.($size==1 ? '':'s').' ('.$options['file'].')');
      return true;
   }

   // anzuzeigende Felder bestimmen
 //$availableFields         = MT4::SYMBOL_getFields();
   $availableFields         = array_keys($symbols[0]);                                          // (int)      => real-name
   $availableFieldsLower    = array_change_key_case(array_flip($availableFields), CASE_LOWER);  // lower-name => (int)
   $displayedFields['name'] = 'symbol';                                                         // wird immer und an 1. Stelle angezeigt

   foreach ($fieldArgs as $arg) {
      if ($arg == '++') {
         $displayedFields = array_flip($availableFields);                  // real-name => (int)
         foreach ($displayedFields as $realName => &$value) {
            $value = $realName;                                            // real-name => real-name
         } unset($value);
         $displayedFields['name'] = 'symbol';                              // real-name => display-name
         continue;
      }
      if ($arg[0] == '+') {
         $name = strToLower(strRight($arg, -1));
         if (array_key_exists($name, $availableFieldsLower)) {
            $realName = $availableFields[$availableFieldsLower[$name]];    // real-name
            if (!isSet($displayedFields[$realName]))
               $displayedFields[$realName] = $realName;                    // real-name => real-name
         }
      }
      else if ($arg[0] == '-') {
         $name = strToLower(strRight($arg, -1));
         if (array_key_exists($name, $availableFieldsLower)) {
            $realName = $availableFields[$availableFieldsLower[$name]];    // real-name
            if (isSet($displayedFields[$realName]))
               $displayedFields[$realName] = null;                         // real-name => (null)        isSet() returns FALSE
         }
      }
   }
   $displayedFields['name'] = 'symbol';                                    // Symbol immer anzeigen (falls -name angegeben wurde)
   $fieldLengths            = $displayedFields;
   foreach ($fieldLengths as &$value) {
      $value = strLen($value);                                             // real-name => (int)
   } unset($value);

   // Daten einlesen und maximale Feldlängen bestimmen
   foreach ($symbols as $symbol) {
      foreach ($symbol as $field => $value) {
         if (isSet($displayedFields[$field])) {
            if (is_double($value) && ($e=(int) strRightFrom($s=(string)$value, 'E-'))) {
               $decimals = strLeftTo(strRightFrom($s, '.'), 'E');
               $decimals = ($decimals=='0' ? 0 : strLen($decimals)) + $e;
               if ($decimals <= 14)                                        // ab 15 Dezimalstellen wissenschaftliche Anzeige
                  $value = number_format($value, $decimals);
            }
            $fieldValues [$field][] = $value;
            $fieldLengths[$field]   = max(strLen($value), $fieldLengths[$field]);
         }
      }
   }

   // Tabellen-Header anzeigen
   $tableHeader = '';
   foreach ($displayedFields as $name => $value) {
      if (isSet($displayedFields[$name]))
         $tableHeader .= str_pad(ucFirst($value), $fieldLengths[$name], ' ',  STR_PAD_RIGHT).'  ';
   }
   $tableHeader    = strLeft($tableHeader, -2);
   $tableSeparator = str_repeat('-', strLen($tableHeader));
   echoPre($tableHeader);
   echoPre($tableSeparator);

   // Daten anzeigen
   foreach ($symbols as $i => $symbol) {
      $line = '';
      foreach ($displayedFields as $name => $value) {
         if (isSet($displayedFields[$name]))
            $line .= str_pad($fieldValues[$name][$i], $fieldLengths[$name], ' ',  STR_PAD_RIGHT).'  ';
      }
      $line = strLeft($line, -2);
      echoPre($line);
   }

   return true;
}


/**
 * Hilfefunktion
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (is_null($message))
      $message = 'Displays symbol informations contained in MetaTrader "symbols.raw" files.';
   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END
$message

  Syntax:  $self [-f=FILE] [OPTIONS]

            -f=FILE  File(s) of the displayed information. If FILE contains wildcards symbol information
                     of all matching files will be displayed (default: "symbols.raw").

  Options:  -c     Count symbols of the specified file(s).
            -l     List available SYMBOL fields.
            +NAME  Include the named field in the output.
            ++     Include all fields in the output.
            -NAME  Exclude the named field from the output.
            -h     This help screen.


END;
}
