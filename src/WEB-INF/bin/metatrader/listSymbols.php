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
      echoPre('Available symbol fields:');
      echoPre('------------------------');
      foreach ($symbols[0] as $field => $value) {
         echoPre($field);
      }
      return true;
   }

   // anzuzeigende Felder bestimmen
   $availableFields = array_flip(array_keys($symbols[0]));
   $displayedFields = array();

   foreach ($fieldArgs as $arg) {
      if ($arg == '++') {
         $displayedFields = $availableFields;
         continue;
      }
      if ($arg[0] == '+') {
         $name = subStr($arg, 1);
         if (isSet($availableFields[$name]) && !isSet($displayedFields[$name])) {
            $displayedFields[$name] = null;
         }
      }
      else if ($arg[0] == '-') {
         $name = subStr($arg, 1);
         if (isSet($displayedFields[$name])) {
            $names = array_flip($displayedFields);
            array_splice($names, array_search($name, $names), 1);
            $displayedFields = array_flip($names);
         }
      }
   }
   foreach ($displayedFields as &$value) {
      $value = true;
   }

   // Daten anzeigen
   foreach ($symbols as $i => $symbol) {
      $line = 'symbol='.$symbol['name'].'  ';
      foreach ($symbol as $field => $value) {
         if (isSet($displayedFields[$field])) {
            if (is_double($value) && $value < 1) {
               $s = (string)$value;
               $i = strPos($s, 'E');
               if ($i !== false) {
                  $dot      = strPos($s, '.');
                  $decimals = subStr($s, $dot+1, $i-$dot-1);
                  $decimals = ($decimals=='0') ? 0:strLen($decimals);
                  $decimals = $decimals + subStr($s, $i+2);
                  $value = number_format($value, $decimals);
               }
            }
            $line .= $field.'='.$value.'  ';
         }
      }
      echoPre($line);
   }


   /*
   Array (
      [name] => GBPLFX
      [description] => GBP Index (LiteForex FX6 index)
      [origin] =>
      [altName] =>
      [group] => 1
      [id] => 505
      [baseCurrency] => GBP
      [digits] => 5
      [backgroundColor] => 13959039
      [undocumented_1] => 1
      [undocumented_3] => 1000
      [undocumented_5] => 0.001
      [spread] => 0
      [swapLong] => 0
      [swapShort] => 0
      [undocumented_8] => 3
      [undocumented_9] => 0
      [lotSize] => 100000
      [orderStopsLevel] => 0
      [marginInit] => 0
      [marginMaintenance] => 0
      [marginHedged] => 50000
      [undocumented_12] => 1
      [pointSize] => 1.0E-5
      [pointsPerUnit] => 100000
      [marginCurrency] => GBP
      [undocumented_15] => 0
   )
   */
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

  Options:  ++     Display all fields.
            +NAME  Include the named field in the display.
            -NAME  Exclude the named field from displaying.
            -l     List available fields.
            -h     This help screen.


END;
}
