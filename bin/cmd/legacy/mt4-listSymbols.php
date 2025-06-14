#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * TODO: replace by Ministruts console command
 *
 *
 * Listet die Symbol-Informationen einer MetaTrader-Datei "symbols.raw" auf.
 *
 * @see Struct-Formate in MT4Expander.dll::Expander.h
 */
namespace rosasurfer\rt\cmd\mt4_list_symbols;

use rosasurfer\rt\lib\metatrader\MT4;
use rosasurfer\rt\lib\metatrader\Symbol;

use function rosasurfer\ministruts\echof;
use function rosasurfer\ministruts\numf;
use function rosasurfer\ministruts\pluralize;
use function rosasurfer\ministruts\stderr;
use function rosasurfer\ministruts\strEndsWith;
use function rosasurfer\ministruts\strIsQuoted;
use function rosasurfer\ministruts\strLeft;
use function rosasurfer\ministruts\strLeftTo;
use function rosasurfer\ministruts\strRight;
use function rosasurfer\ministruts\strStartsWith;
use function rosasurfer\ministruts\strRightFrom;

use const rosasurfer\ministruts\NL;

require(__DIR__.'/../../../app/init.php');


// -- Konfiguration ---------------------------------------------------------------------------------------------------------


$files     = [];
$options   = [];
$fieldArgs = [];


// -- Start -----------------------------------------------------------------------------------------------------------------


// Befehlszeilenargumente auswerten
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// Hilfe
foreach ($args as $arg) {
    if ($arg == '-h') {
        help();
        exit(1);
    }
}

// Optionen und Argumente parsen
foreach ($args as $arg) {
    // -f=FILE
    if (strStartsWith($arg, '-f=')) {
        if ($files) {
            stderr('invalid/multiple file arguments: -f='.$arg);
            exit(1);
        }
        $value = $arg = strRight($arg, -3);
        strIsQuoted($value) && ($value=strLeft(strRight($value, -1), 1));

        if (file_exists($value)) {              // existierende Datei oder Verzeichnis
            if (is_dir($value) && !is_file(($value.=(strEndsWith($value, '/') ? '':'/').'symbols.raw'))) {
                stderr('file not found: '.$value);
                exit(1);
            }
            $files[] = $value;
        }
        else {                                  // Argument existiert nicht, Wildcards expandieren und Ergebnisse pruefen (z.B. unter Windows)
            strEndsWith($value, '/', '\\') && ($value.='symbols.raw');
            $entries = glob($value, GLOB_NOESCAPE|GLOB_BRACE|GLOB_ERR);
            $matchesDir = false;
            foreach ($entries as $entry) {
                if (is_dir($entry)) {
                    $matchesDir = true;
                    continue;
                }
                $files[] = $entry;              // nur Dateien uebernehmen
            }
            if (!$files) {
                stderr('file(s) not found: '.$arg.($matchesDir ? ' (enter a trailing slash "/" to search directories)':''));
                exit(1);
            }
            usort($files, function($a, $b) {    // Datei-/Verzeichnisnamen lassen sich mit den existierenden Funktionen nicht natuerlich sortieren
                return compareFileNames($a, $b);
            });
        }
        continue;
    }

    // count symbols
    if ($arg == '-c') {
        $options['countSymbols'] = true;
        continue;
    }

    // list fields
    if ($arg == '-l') {
        $options['listFields'] = true;
        break;
    }

    // include all fields
    if ($arg == '++') {
        $fieldArgs['++'] = 1;
        continue;
    }

    // include specific field
    if (strStartsWith($arg, '+')) {
        $key = substr($arg, 1);
        if (!strlen($key)) {
            help('invalid field specifier: '.$arg);
            exit(1);
        }
        unset($fieldArgs['-'.$key]);                                            // drops element if it exists
        if (!isset($fieldArgs['++']) && !isset($fieldArgs['+'.$key])) {
            $fieldArgs['+'.$key] = 1;
        }
        continue;
    }

    // exclude specific field
    if (strStartsWith($arg, '-')) {
        $key = substr($arg, 1);
        if (!strlen($key)) {
            help('invalid field specifier: '.$arg);
            exit(1);
        }
        unset($fieldArgs['+'.$key]);                                            // drops element if it exists
        if (isset($fieldArgs['++']) && !isset($fieldArgs['-'.$key])) {
            $fieldArgs['-'.$key] = 1;
        }
        continue;
    }

    // unrecognized arguments
    help('invalid argument: '.$arg);
    exit(1);
}
$fieldArgs = \array_keys($fieldArgs);


// ggf. verfuegbare Felder anzeigen und danach abbrechen
$allFields = MT4::SYMBOL_getFields();               // TODO: Feld 'leverage' dynamisch hinzufuegen
                                                    //       array_splice($fields, array_search('marginDivider', $fields)+1, 0, ['leverage']);
if (isset($options['listFields'])) {
    echof($s='Symbol fields:');
    echof(str_repeat('-', strlen($s)));
    foreach ($allFields as $field) {
        echof(ucfirst($field));
    }
    exit(0);
}

// Default-Parameter setzen
if (!$files) {
    $file = 'symbols.raw';
    if (!is_file($file)) {
        help('file not found: '.$file);
        exit(1);
    }
    $files[] = $file;
}

// anzuzeigende Felder bestimmen
$allFieldsLower = array_change_key_case(array_flip($allFields), CASE_LOWER);    // lower-name => (int)
$usedFields     = array_flip($allFields);                                       // real-name  => (int)
foreach ($usedFields as &$value) {
    $value = null;                                                              // real-name  => (null)       default: alle Felder OFF
}
unset($value);

foreach ($fieldArgs as $arg) {
    if ($arg == '++') {
        foreach ($usedFields as $name => &$value) {
            $value = $name;                                                     // real-name  => print-name   alle Felder ON
        }
        unset($value);
        continue;
    }
    if ($arg[0] == '+') {
        $name = strtolower(strRight($arg, -1));
        if (isset($allFieldsLower[$name])) {
            $realName = $allFields[$allFieldsLower[$name]];
            $usedFields[$realName] = $realName;                                 // real-name => print-name    Feld ON
        }
    }
    else if ($arg[0] == '-') {
        $name = strtolower(strRight($arg, -1));
        if (isset($allFieldsLower[$name])) {
            $realName = $allFields[$allFieldsLower[$name]];
            $usedFields[$realName] = null;                                      // real-name => (null)        Feld OFF
        }
    }
}
$usedFields['name'] = 'symbol';                                                 // Symbol ist immer ON (kann nicht ausgeschaltet werden)

foreach ($usedFields as $name => $value) {
    if (is_null($value)) {                                                      // verbliebene NULL-Felder loeschen
        unset($usedFields[$name]);
        continue;
    }
    $usedFields[$name] = null;
    $usedFields[$name]['printName'] = ucfirst($value);                          // [real-name][printName] => print-name
    $usedFields[$name]['length'   ] = strlen($value);                           // [real-name][length]    => (int)
}

// Symbolinformationen erfassen und ausgeben (getrennt, damit Spalten uebergreifend formatiert werden koennen)
$data = [];
foreach ($files as $file) {
    $countOnly = $options['countSymbols'] ?? false;
    collectData($file, $usedFields, $data, $countOnly) || exit(1);
}
printData($files, $usedFields, $data, $options) || exit(1);

exit(0);


// --- Funktionen -----------------------------------------------------------------------------------------------------------


/**
 * Erfasst die Informationen einer Symboldatei.
 *
 * @param  string                                    $file      [in]     - Name der Symboldatei
 * @param  array<string, array<string, int|string>>  $fields    [in_out] - reference to zu erfassende Felder (Laengen werden im Array gespeichert)
 * @param  array<string, mixed>                      $data      [in_out] - reference to Array zum Zwischenspeichern der erfassten Daten
 * @param  bool                                      $countOnly [in]     - whether symbols should only be counted
 *
 * @return bool - Erfolgsstatus
 */
function collectData(string $file, array &$fields, array &$data, bool $countOnly): bool {
    // Dateigroesse pruefen
    $fileSize = filesize($file);
    if ($fileSize < Symbol::SIZE) {
        $data[$file]['meta:error'] = 'invalid or unsupported format, file size ('.$fileSize.') < MinFileSize ('.Symbol::SIZE.')';
        return true;
    }
    if ($fileSize % Symbol::SIZE) {
        $data[$file]['meta:warn'][] = 'file contains '.($fileSize % Symbol::SIZE).' trailing bytes';
    }

    // Laenge des laengsten Dateinamens speichern
    $data['meta:maxFileLength'] = max(strlen($file), $data['meta:maxFileLength'] ?? 0);

    // Anzahl der Symbole ermitteln und speichern
    $symbolsSize = (int)($fileSize/Symbol::SIZE);                   // Die Meta-Daten liegen in derselben Arrayebene wie
    $data[$file]['meta:symbolsSize'] = $symbolsSize;                // die Symboldaten und muessen Namen haben, die mit den
    if ($countOnly) return true;                                    // Feldnamen der Symbole nicht kollidieren koennen.

    // Daten auslesen
    $hFile = fopen($file, 'rb');
    $symbols = [];
    for ($i=0; $i < $symbolsSize; $i++) {
        $symbols[] = unpack('@0'.Symbol::unpackFormat(), fread($hFile, Symbol::SIZE));
    }
    fclose($hFile);

    // Daten auslesen und maximale Feldlaengen speichern
    $values = [];
    foreach ($symbols as $i => $symbol) {
        foreach ($fields as $name => $v) {
            $value = $symbol[$name] ?? '?';                                             // typenlose Felder (x) werden markiert
            if (is_float($value) && ($e=(int) strRightFrom($s=(string)$value, 'E-'))) {
                $decimals = strLeftTo(strRightFrom($s, '.'), 'E');
                $decimals = ($decimals=='0' ? 0 : strlen($decimals)) + $e;
                if ($decimals <= 14) {                                                  // ab 15 Dezimalstellen wissenschaftliche Anzeige
                    $value = numf($value, $decimals);
                }
            }
            $values[$name][]         = $value;                                          // real-name[n]      => value
            $fields[$name]['length'] = max(strlen($value), $fields[$name]['length']);   // real-name[length] => (int)
        }
    }
    $data[$file] = array_merge($data[$file], $values);

    return true;
}


/**
 * Gibt die eingelesenen Informationen aller Symboldateien aus.
 *
 * @param  string[]                                 $files   - Symboldateien
 * @param  array<string, array<string, int|string>> $fields  - auszugebende Felder
 * @param  array<string, mixed>                     $data    - auszugebende Daten
 * @param  array<string, bool>                      $options - Programmoptionen
 *
 * @return bool - Erfolgsstatus
 */
function printData(array $files, array $fields, array $data, array $options): bool {
    $tableHeader = $tableSeparator = $fileSeparator = '';

    // (1) Tabellen-Header definieren
    foreach ($fields as $name => $value) {
        $tableHeader .= str_pad($value['printName'], $value['length'], ' ',  STR_PAD_RIGHT).'  ';
    }
    $tableHeader  = strLeft($tableHeader, -2);
    $countSymbols = isset($options['countSymbols']);
    $sizeFiles    = sizeof($files);

    foreach ($files as $i => $file) {
        // (2) Table-Header ausgeben
        $symbolsSize    = $data[$file]['meta:symbolsSize'];
        $sizeMsg        = $symbolsSize.' symbol'.pluralize($symbolsSize);
        $tableSeparator = str_repeat('-', max(strlen($file), strlen($tableHeader), strlen($tableSeparator)));
        $fileSeparator  = str_repeat('=', strlen($tableSeparator));

        if ($countSymbols) {
            echof(str_pad($file.':', $data['meta:maxFileLength']+1, ' ',  STR_PAD_RIGHT).' '.$symbolsSize.' symbols');
            continue;
        }
        echof($file.':');
        echof($tableHeader);
        echof($tableSeparator);

        // (3) Daten ausgeben
        for ($n=0; $n < $symbolsSize; $n++) {
            $line = '';
            foreach ($fields as $name => $v) {
                $line .= str_pad($data[$file][$name][$n], $fields[$name]['length'], ' ',  STR_PAD_RIGHT).'  ';
            }
            $line = strLeft($line, -2);
            echof($line);
        }

        // (4) Table-Footer ausgeben
        echof($tableSeparator);
        echof($sizeMsg);
        if (++$i < $sizeFiles)
            echof($fileSeparator.NL.NL);
    }
    return true;
}


/**
 * Comparator, der zwei Dateinamen vergleicht. Mit den existierenden Funktionen lassen sich Datei- und Verzeichnisnamen
 * nicht natuerlich sortieren (z.B. wie im Windows Explorer).
 *
 * @param  string $fileA
 * @param  string $fileB
 *
 * @return int - positiver Wert, wenn $fileA nach $fileB einsortiert wird;
 *               negativer Wert, wenn $fileA vor $fileB einsortiert wird;
 *               0, wenn beide Dateinamen gleich sind
 */
function compareFileNames($fileA, $fileB) {
    if ($fileA === $fileB)
        return 0;
    $lenA = strlen($fileA);
    $lenB = strlen($fileB);

    // beide Strings haben eine Laenge > 0
    $fileALower = strtolower(str_replace('\\', '/', $fileA));
    $fileBLower = strtolower(str_replace('\\', '/', $fileB));
    $len = min($lenA, $lenB);

    for ($i=0; $i < $len; $i++) {
        $charA = $fileALower[$i];
        $charB = $fileBLower[$i];

        if ($charA != $charB) {
            if ($charA == '/') return -1;
            if ($charB == '/') return +1;
            return ($charA > $charB) ? +1 : -1;
        }
    }

    // Kleinschreibung ist soweit identisch, Laengen vergleichen
    if ($lenA == $lenB)
        return ($fileA > $fileB)       ? +1 : -1;   // gleiche Laenge, Originalnamen vergleichen
    return ($fileALower > $fileBLower) ? +1 : -1;   // unterschiedliche Laenge, Lower-Names vergleichen
}


/**
 * Hilfefunktion
 *
 * @param  ?string $message [optional] - zusaetzlich zur Syntax anzuzeigende Message (default: keine)
 *
 * @return void
 */
function help($message = null) {
    if (is_null($message))
        $message = 'List symbol metadata contained in MetaTrader "symbols.raw" files.';
    $self = basename($_SERVER['PHP_SELF']);

echo <<<HELP
$message

  Syntax:  $self [-f=FILE] [OPTIONS]

          -f=FILE  File(s) to analyze (default: "symbols.raw").
                   If FILE contains wildcards all matching files will be analyzed.
                   If FILE is a directory the file "symbols.raw" in that directory will be analyzed.

  Options:  -c     Count symbols in the specified file(s).
            -l     List available symbol fields.
            +NAME  Include the named field in the output.
            -NAME  Exclude the named field from the output.
            ++     Include all fields in the output.
            -h     This help screen.


HELP;
}
