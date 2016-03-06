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

   // list available fields
   if ($arg == '-l') {
      $options['listFields'] = true;
      if (isSet($options['file']))
         break;
      continue;
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
      if (($index=array_search('-'.$key, $fields)) !== false) {
         array_splice($fields, $index, 1);
      }
      if (!in_array('++', $fields) && !in_array('+'.$key, $fields)) {
         $fields[] = '+'.$key;
      }
      continue;
   }

   // do not display specific field
   if (strStartsWith($arg, '-')) {
      $key = subStr($arg, 1);
      if (!strLen($key)) help('invalid field specifier: '.$arg) & exit(1);
      if (($index=array_search('+'.$key, $fields)) !== false) {
         array_splice($fields, $index, 1);
      }
      if (in_array('++', $fields) && !in_array('-'.$key, $fields)) {
         $fields[] = '-'.$key;
      }
      continue;
   }
}

// Default-Parameter setzen
if (!isSet($options['file'])) {
   $file = 'symbols.raw';
   if (!is_file($file)) help('No file "symbols.raw" in current directory') & exit(1);
   $options['file'    ] = $file;
   $options['fullFile'] = realPath($file);
}


// (2) Informationen auflisten
if (!listMT4Symbols($options, $fields))
   exit(1);


// (3) erfolgreiches Programm-Ende
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
      $symbols[] = unpack(MT4::SYMBOL_unpackFormat(), fRead($hFile, MT4::SYMBOL_SIZE));
   }
   fClose($hFile);

   // ggf. verfügbare Felder anzeigen und abbrechen
   if (isSet($options['listFields'])) {
      echoPre($s='In "'.$file.'" available fields:');
      echoPre(str_repeat('-', strLen($s)));
      foreach ($symbols[0] as $field => $value)
         echoPre($field);
      return true;
   }

   // anzuzeigende Felder bestimmen
   $availableFields      = array_keys($symbols[0]);                                          // (int)      => real-name
   $availableFieldsLower = array_change_key_case(array_flip($availableFields), CASE_LOWER);  // lower-name => (int)
   $displayedFields      = array();

   foreach ($fieldArgs as $arg) {
      if ($arg == '++') {
         $displayedFields = array_flip($availableFields);                  // real-name => (int)
         foreach ($displayedFields as $realName => &$value)
            $value = $realName;                                            // real-name => real-name
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
               $displayedFields[$realName] = null;                         // real-name => (null)     // isSet() returns FALSE
         }
      }
   }

   // Tabellen-Header ausgeben
   $tableHeader = 'Symbol';
   foreach ($displayedFields as $name => $value) {
      if (isSet($displayedFields[$name])) {
         $tableHeader .= '  '.ucFirst($name);
      }
   }
   $tableSeparator = str_repeat('-', strLen($tableHeader));
   echoPre($tableHeader);
   echoPre($tableSeparator);

   // Daten anzeigen
   foreach ($symbols as $symbol) {
      $line = 'symbol='.$symbol['name'].'  ';
      foreach ($symbol as $field => $value) {
         if (isSet($displayedFields[$field])) {
            if (is_double($value) && $value < 1 && $value > -1) {
               $s = (string) $value;
               if ($e=(int) strRightFrom($s, 'E-')) {
                  $decimals = strLeftTo(strRightFrom($s, '.'), 'E');
                  $decimals = ($decimals=='0' ? 0 : strLen($decimals)) + $e;
                  if ($decimals <= 8)                                      // ab 9 Dezimalstellen wissenschaftliche Anzeige
                     $value = number_format($value, $decimals);
               }
            }
            $line .= $field.'='.$value.'  ';
         }
      }
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
      $message = 'Displays symbol informations contained in a MetaTrader "symbols.raw" file.';
   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END
$message

  Syntax:  $self [-f=FILE] [OPTIONS]

            -f=FILE  Source file of the displayed information (default: "symbols.raw" in current directory).

  Options:  -l     List the available fields of the specified file.
            ++     Display all fields.
            +NAME  Include the named field in list of displayed fields.
            -NAME  Exclude the named field from the list of displayed fields.
            -h     This help screen.


END;
}
