<?php
/**
 * Dukascopy related functionality
 *
 *
 * Datenformat M1-Bardaten:
 * ------------------------
 * • Big-Endian
 *                               size        offset
 * struct DUKASCOPY_BAR {        ----        ------
 *    int timeDelta;               4            0        // Zeitdifferenz in Sekunden seit der letzten Mitternacht (00:00 GMT)
 *    int open;                    4            4        // in Points
 *    int close;                   4            8        // in Points
 *    int low;                     4           12        // in Points
 *    int high;                    4           16        // in Points
 *    int volume;                  4           20        // in Units
 * };                           = 24 byte
 */
class Dukascopy extends StaticClass {


   /**
    * Dekomprimiert den Inhalt einer komprimierten Dukascopy-Kursdatei und gibt ihn zurück. Wird ein Dateiname angegeben,
    * wird der dekomprimierte Inhalt zusätzlich in dieser Datei gespeichert.
    *
    * @param  string $string     - komprimierter Inhalt einer Dukascopy-Kursdatei
    * @param  string $saveAsFile - Name der Datei, in der der dekomprimierte Inhalt zusätzlich gespeichert wird
    *
    * @return string - dekomprimierter Inhalt
    */
   public static function decompressBars($string, $saveAsFile) {
      if (!is_string($string))        throw new IllegalTypeException('Illegal type of parameter $string: '.getType($string));
      if (!is_null($saveAsFile)) {
         if (!is_string($saveAsFile)) throw new IllegalTypeException('Illegal type of parameter $saveAsFile: '.getType($saveAsFile));
         if (!strLen($saveAsFile))    throw new plInvalidArgumentException('Invalid parameter $saveAsFile: ""');
      }

      $content = LZMA ::decompress($string);

      if (!is_null($saveAsFile)) {
         mkDirWritable(dirName($saveAsFile));
         $tmpFile = tempNam(dirName($saveAsFile), baseName($saveAsFile));
         $hFile   = fOpen($tmpFile, 'wb');
         fWrite($hFile, $content);
         fClose($hFile);
         if (is_file($saveAsFile)) unlink($saveAsFile);
         rename($tmpFile, $saveAsFile);                              // So kann eine existierende Datei niemals korrupt sein.
      }
      return $content;
   }


   /**
    * Dekomprimiert eine komprimierte Dukascopy-Kursdatei und gibt ihren Inhalt zurück. Wird ein zusätzlicher Dateiname
    * angegeben, wird der dekomprimierte Inhalt zusätzlich in dieser Datei gespeichert.
    *
    * @param  string $compressedFile - Name der komprimierten Dukascopy-Kursdatei
    * @param  string $saveAsFile     - Name der Datei, in der der dekomprimierte Inhalt zusätzlich gespeichert wird
    *
    * @return string - dekomprimierter Inhalt der Datei
    */
   public static function decompressBarsFile($compressedFile, $saveAsFile) {
      if (!is_string($compressedFile)) throw new IllegalTypeException('Illegal type of parameter $compressedFile: '.getType($compressedFile));

      return self::decompressBars(file_get_contents($compressedFile), $saveAsFile);
   }


   /**
    * Interpretiert die Dukascopy-Bardaten in einem String und liest sie in ein Array ein.
    *
    * @param  string $data - String mit Dukascopy-Bardaten
    *
    * @return DUKASCOPY_BAR[] - Array mit Bardaten
    */
   public static function readBars($data) {
      if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));

      $size   = strLen($data); if ($size % DUKASCOPY_BAR_SIZE) throw new plRuntimeException('Odd size of passed $data: '.$size.' (not an even DUKASCOPY_BAR_SIZE)');
      $offset = 0;
      $bars   = array();

      while ($offset < $size) {
         $bars[] = unpack("@$offset/NtimeDelta/Nopen/Nclose/Nlow/Nhigh/Nvol", $data);
         $offset += DUKASCOPY_BAR_SIZE;
      }
      return $bars;
   }


   /**
    * Interpretiert die Dukascopy-Bardaten in einer Datei und liest sie in ein Array ein.
    *
    * @param  string $fileName - Name der Datei mit Dukascopy-Bardaten
    *
    * @return DUKASCOPY_BAR[] - Array mit Bardaten
    */
   public static function readBarsFile($fileName) {
      if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.getType($fileName));

      return self::readBars(file_get_contents($fileName));
   }
}
?>

<?php
/*
$dstGmt       = $dstLocal    = $stdGmt    = $stdLocal    = '                               ';
$dstGmtMql    = $dstLocalMql = $stdGmtMql = $stdLocalMql = '-1,  -1,                    ';
$dstOffsetMql = $stdOffsetMql = '            0';
$dstSet       = $stdSet       = false;
$year         = $lastYear     = 1970;
$tsMin = strToTime('1970-01-01 00:00:00 GMT');


$tzName      = 'Europe/Minsk';
$timezone    = new DateTimeZone($tzName);
$transitions = $timezone->getTransitions();


echoPre("Timezone transitions for '$tzName'\n\n");


foreach ($transitions as $transition) {
   $ts     = $transition['ts'    ];
   $offset = $transition['offset'];
   $isDST  = $transition['isdst' ];

   if ($ts >= $tsMin) {
      date_default_timezone_set('GMT');
      $year = iDate('Y', $ts);
      if ($year != $lastYear) {
         printYear();
         while (++$lastYear < $year) {
            echoPre($lastYear);
         }
      }

      if ($isDST) {
         if ($dstSet)
            printYear();
         $dstGmt      = date(DATE_RSS,         $ts);
         $dstGmtMql   = strToUpper(date('D, ', $ts)).date("\D'Y.m.d H:i:s',", $ts);

         date_default_timezone_set($tzName);
         $dstLocal    = date(DATE_RSS,         $ts);
         $dstLocalMql = strToUpper(date('D, ', $ts)).date("\D'Y.m.d H:i:s',", $ts);

         $dstOffsetMql = (!$offset ? '            0':(($offset<0?'MINUS_':' PLUS_').(abs($offset)/HOURS).'_HOURS'));
         $dstSet = true;
      }
      else {
         if ($stdSet)
            printYear();
         $stdGmt      = date(DATE_RSS,         $ts);
         $stdGmtMql   = strToUpper(date('D, ', $ts)).date("\D'Y.m.d H:i:s',", $ts);

         date_default_timezone_set($tzName);
         $stdLocal    = date(DATE_RSS,         $ts);
         $stdLocalMql = strToUpper(date('D, ', $ts)).date("\D'Y.m.d H:i:s',", $ts);

         $stdOffsetMql = (!$offset ? '            0':(($offset<0?'MINUS_':' PLUS_').(abs($offset)/HOURS).'_HOURS'));
         $stdSet = true;
      }
   }
}
if ($dstSet || $stdSet) {
   printYear();
}


echoPre($transitions);


function printYear() {
   global $lastYear, $dstGmt, $dstLocal, $stdGmt, $stdLocal, $dstGmtMql, $dstLocalMql, $stdGmtMql, $stdLocalMql, $dstOffsetMql, $stdOffsetMql, $dstSet, $stdSet;

   echoPre("$lastYear    DST: $dstGmt    $dstLocal        STD: $stdGmt    $stdLocal        $dstGmtMql $dstLocalMql $dstOffsetMql,    $stdGmtMql $stdLocalMql $stdOffsetMql,");
   $dstGmt       = $dstLocal     = $stdGmt    = $stdLocal    = '                               ';
   $dstGmtMql    = $dstLocalMql  = $stdGmtMql = $stdLocalMql = '-1,  -1,                    ';
   $dstOffsetMql = $stdOffsetMql                             = '            0';
   $dstSet       = $stdSet                                   = false;
}
*/
?>
