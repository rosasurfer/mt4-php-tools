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
 *    int timeDelta;               4            0        // Zeitdifferenz in Sekunden seit 00:00 GMT
 *    int open;                    4            4        // in Points
 *    int close;                   4            8        // in Points
 *    int low;                     4           12        // in Points
 *    int high;                    4           16        // in Points
 *    int interest                 4           20        // vermutlich Gesamtvolumen einer Marktseite in Units
 * };                           = 24 byte
 */
class Dukascopy extends StaticClass {


   // Start der M1-History der Dukascopy-Instrumente
   public static $historyStart_M1 = null;                // @see static initializer at end of file

   // Start der Tick-History der Dukascopy-Instrumente
   public static $historyStart_Ticks = null;             // @see static initializer at end of file


   /**
    * Dekomprimiert den Inhalt einer komprimierten Dukascopy-Kursdatei und gibt ihn zurück. Wird ein Dateiname angegeben,
    * wird der dekomprimierte Inhalt zusätzlich in dieser Datei gespeichert.
    *
    * @param  string $data   - komprimierter Inhalt einer Dukascopy-Kursdatei
    * @param  string $saveAs - Name der Datei, in der der dekomprimierte Inhalt zusätzlich gespeichert wird
    *
    * @return string - dekomprimierter Inhalt
    */
   public static function decompressBarData($data, $saveAs=null) {
      if (!is_string($data))      throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));
      if (!is_null($saveAs)) {
         if (!is_string($saveAs)) throw new IllegalTypeException('Illegal type of parameter $saveAs: '.getType($saveAs));
         if (!strLen($saveAs))    throw new plInvalidArgumentException('Invalid parameter $saveAs: ""');
      }

      $rawData = LZMA ::decompressData($data);

      if (!is_null($saveAs)) {
         mkDirWritable(dirName($saveAs));
         $tmpFile = tempNam(dirName($saveAs), baseName($saveAs));
         $hFile   = fOpen($tmpFile, 'wb');
         fWrite($hFile, $rawData);
         fClose($hFile);
         if (is_file($saveAs)) unlink($saveAs);
         rename($tmpFile, $saveAs);                               // So kann eine existierende Datei niemals korrupt sein.
      }
      return $rawData;
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
   public static function decompressBarFile($compressedFile, $saveAsFile=null) {
      if (!is_string($compressedFile)) throw new IllegalTypeException('Illegal type of parameter $compressedFile: '.getType($compressedFile));

      return self::decompressBarData(file_get_contents($compressedFile), $saveAsFile);
   }


   /**
    * Interpretiert die Dukascopy-Bardaten eines Strings und liest sie in ein Array ein.
    *
    * @param  string $data - String mit Dukascopy-Bardaten
    *
    * @return DUKASCOPY_BAR[] - Array mit Bardaten
    */
   public static function readBarData($data) {
      if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));

      $size   = strLen($data); if ($size % DUKASCOPY_BAR_SIZE) throw new plRuntimeException('Odd length of passed $data: '.$size.' (not an even DUKASCOPY_BAR_SIZE)');
      $offset = 0;
      $bars   = array();

      while ($offset < $size) {
         $bars[]  = unpack("@$offset/NtimeDelta/Nopen/Nclose/Nlow/Nhigh/Ninterest", $data);
         $offset += DUKASCOPY_BAR_SIZE;
      }
      return $bars;
   }


   /**
    * Interpretiert die Dukascopy-Bardaten einer Datei und liest sie in ein Array ein.
    *
    * @param  string $fileName - Name der Datei mit Dukascopy-Bardaten
    *
    * @return DUKASCOPY_BAR[] - Array mit Bardaten
    */
   public static function readBarFile($fileName) {
      if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.getType($fileName));

      return self::readBarData(file_get_contents($fileName));
   }
}


/**
 * Workaround für in PHP nicht existierende Static Initializer
 */

// Start der M1-History der Dukascopy-Instrumente
Dukascopy::$historyStart_M1 = array('AUDUSD' => strToTime('2003-08-03 00:00:00 GMT'),
                                    'EURUSD' => strToTime('2003-05-04 00:00:00 GMT'),
                                    'GBPUSD' => strToTime('2003-05-04 00:00:00 GMT'),
                                    'NZDUSD' => strToTime('2003-08-03 00:00:00 GMT'),
                                    'USDCAD' => strToTime('2003-08-03 00:00:00 GMT'),
                                    'USDCHF' => strToTime('2003-05-04 00:00:00 GMT'),
                                    'USDJPY' => strToTime('2003-05-04 00:00:00 GMT'),
                                    'USDNOK' => strToTime('2003-08-05 00:00:00 GMT'),  // TODO: !!! Start ist der 04.08.2003
                                    'USDSEK' => strToTime('2003-08-05 00:00:00 GMT'),  // TODO: !!! Start ist der 04.08.2003
                                    'USDSGD' => strToTime('2004-11-17 00:00:00 GMT'),  // TODO: !!! Start ist der 16.11.2004

                                  //'USDHUF' => strToTime('2007-03-13 00:00:00 GMT'),
                                  //'USDMXN' => strToTime('2007-03-13 00:00:00 GMT'),
                                  //'USDPLN' => strToTime('2007-03-13 00:00:00 GMT'),
                                  //'USDTRY' => strToTime('2007-03-13 00:00:00 GMT'),
                                  //'USDZAR' => strToTime('1997-10-13 00:00:00 GMT'),
);

// Start der Tick-History der Dukascopy-Instrumente
Dukascopy::$historyStart_Ticks = array();
