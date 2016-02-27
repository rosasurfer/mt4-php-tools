<?php
/**
 * Dukascopy related functionality
 *
 *                                      size        offset      description
 * struct big-endian DUKASCOPY_BAR {    ----        ------      --------------------------------------------------
 *    uint  timeDelta;                    4            0        Zeitdifferenz in Sekunden seit 00:00 GMT
 *    uint  open;                         4            4        in Points
 *    uint  close;                        4            8        in Points
 *    uint  low;                          4           12        in Points
 *    uint  high;                         4           16        in Points
 *    float lots                          4           20        kumulierte Angebotsseite in Lots (siehe DUKASCOPY_TICK)
 * };                                  = 24 byte
 *
 *
 *                                      size        offset      description
 * struct big-endian DUKASCOPY_TICK {   ----        ------      -------------------------------------------------
 *    uint  timeDelta;                    4            0        Zeitdifferenz in Millisekunden seit Stundenbeginn
 *    uint  ask;                          4            4        in Points
 *    uint  bid;                          4            8        in Points
 *    float askSize;                      4           12        Angebotsgröße in Lots. Da Dukascopy als MarketMaker
 *    float bidSize;                      4           16        auftritt, ist der Mindestwert immer 1 Lot.
 * };                                  = 20 byte
 */
class Dukascopy extends StaticClass {


   // Start der M1-History der Dukascopy-Instrumente
   public static $historyStart_M1    = null;             // @see static initializer at end of file

   // Start der Tick-History der Dukascopy-Instrumente
   public static $historyStart_Ticks = null;             // @see static initializer at end of file


   /**
    * Dekomprimiert einen komprimierten String mit Dukascopy-Historydaten und gibt ihn zurück. Wird ein Dateiname angegeben,
    * wird der dekomprimierte Inhalt zusätzlich in dieser Datei gespeichert.
    *
    * @param  string $data   - String mit komprimierten Historydaten (Bars oder Ticks)
    * @param  string $saveAs - Name der Datei, in der der dekomprimierte Inhalt zusätzlich gespeichert wird
    *
    * @return string - dekomprimierter String
    */
   public static function decompressHistoryData($data, $saveAs=null) {
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
    * Dekomprimiert eine komprimierte Dukascopy-Historydatei und gibt ihren Inhalt zurück. Wird ein zusätzlicher Dateiname
    * angegeben, wird der dekomprimierte Inhalt zusätzlich in dieser Datei gespeichert.
    *
    * @param  string $compressedFile - Name der komprimierten Dukascopy-Datei
    * @param  string $saveAsFile     - Name der Datei, in der der dekomprimierte Inhalt zusätzlich gespeichert wird
    *
    * @return string - dekomprimierter Inhalt der Datei
    */
   public static function decompressHistoryFile($compressedFile, $saveAsFile=null) {
      if (!is_string($compressedFile)) throw new IllegalTypeException('Illegal type of parameter $compressedFile: '.getType($compressedFile));

      return self::decompressHistoryData(file_get_contents($compressedFile), $saveAsFile);
   }


   /**
    * Interpretiert die in einem String enthaltenen Dukascopy-Bardaten und liest sie in ein Array ein.
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
      $i      = -1;

      // unpack() unterstützt keinen expliziten Big-Endian-Float, die Byteorder des Elements 'lots' muß manuell reversed werden.
      if (PHP_VERSION < '5.5.0') {
         // Es gibt keinen Format-Code, der einzelne Zeichen oder binäre Strings ungekürzt entpackt ('a' und 'A' kürzen).
         while ($offset < $size) {
            $i++;
            $bars[] = unpack("@$offset/NtimeDelta/Nopen/Nclose/Nlow/Nhigh", $data);
            $char   = unpack('@'.($offset+20).'/C4', $data);
            $lots   = unpack('f', pack('C4', $char[4], $char[3], $char[2], $char[1]));
            $bars[$i]['lots'] = round($lots[1], 2);
            $offset += DUKASCOPY_BAR_SIZE;
         }
      }
      else {
         // Der Format-Code 'a' schneidet an NULL-Bytes nicht mehr ab.
         while ($offset < $size) {
            $i++;
            $bars[] = unpack("@$offset/NtimeDelta/Nopen/Nclose/Nlow/Nhigh/a4lots", $data);
            $lots   = unpack('f', strRev($bars[$i]['lots']));
            $bars[$i]['lots'] = round($lots[1], 2);
            $offset += DUKASCOPY_BAR_SIZE;
         }
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


   /**
    * Interpretiert die in einem String enthaltenen Dukascopy-Tickdaten und liest sie in ein Array ein.
    *
    * @param  string $data - String mit Dukascopy-Tickdaten
    *
    * @return DUKASCOPY_TICK[] - Array mit Tickdaten
    */
   public static function readTickData($data) {
      if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));

      $size   = strLen($data); if ($size % DUKASCOPY_TICK_SIZE) throw new plRuntimeException('Odd length of passed $data: '.$size.' (not an even DUKASCOPY_TICK_SIZE)');
      $offset = 0;
      $ticks  = array();
      $i      = -1;

      // unpack() unterstützt keinen expliziten Big-Endian-Float, die Byteorder der Elemente 'bidSize' und 'askSize' muß manuell
      // reversed werden.
      if (PHP_VERSION < '5.5.0') {
         // Es gibt keinen Format-Code, der einzelne Zeichen oder binäre Strings ungekürzt entpackt ('a' und 'A' kürzen).
         while ($offset < $size) {
            $i++;
            $ticks[] = unpack("@$offset/NtimeDelta/Nask/Nbid", $data);
            $char    = unpack('@'.($offset+12).'/C8', $data);
            $size    = unpack('fask/fbid', pack('C8', $char[4], $char[3], $char[2], $char[1], $char[8], $char[7], $char[6], $char[5]));
            $ticks[$i]['askSize'] = round($size['ask'], 2);
            $ticks[$i]['bidSize'] = round($size['bid'], 2);
            $offset += DUKASCOPY_TICK_SIZE;
         }
      }
      else {
         // Der Format-Code 'a' schneidet an NULL-Bytes nicht mehr ab.
         while ($offset < $size) {
            $i++;
            $ticks[] = unpack("@$offset/NtimeDelta/Nask/Nbid/a4askSize/a4bidSize", $data);
            $size    = unpack('fask/fbid', strRev($ticks[$i]['askSize']).strRev($ticks[$i]['bidSize']));
            $ticks[$i]['askSize'] = round($size['ask'], 2);
            $ticks[$i]['bidSize'] = round($size['bid'], 2);
            $offset += DUKASCOPY_TICK_SIZE;
         }
      }
      return $ticks;
   }


   /**
    * Interpretiert die Dukascopy-Tickdaten einer Datei und liest sie in ein Array ein.
    *
    * @param  string $fileName - Name der Datei mit Dukascopy-Tickdaten
    *
    * @return DUKASCOPY_TICK[] - Array mit Tickdaten
    */
   public static function readTickFile($fileName) {
      if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.getType($fileName));

      return self::readTickData(file_get_contents($fileName));
   }
}


/**
 * Workaround für in PHP nicht existierende static initializer
 */

// Start der M1-History der Dukascopy-Instrumente
Dukascopy::$historyStart_M1    = array('AUDUSD' => strToTime('2003-08-03 00:00:00 GMT'),
                                       'EURUSD' => strToTime('2003-05-04 00:00:00 GMT'),
                                       'GBPUSD' => strToTime('2003-05-04 00:00:00 GMT'),
                                       'NZDUSD' => strToTime('2003-08-03 00:00:00 GMT'),
                                       'USDCAD' => strToTime('2003-08-03 00:00:00 GMT'),
                                       'USDCHF' => strToTime('2003-05-04 00:00:00 GMT'),
                                       'USDJPY' => strToTime('2003-05-04 00:00:00 GMT'),
                                       'USDNOK' => strToTime('2003-08-05 00:00:00 GMT'),  // TODO: Start ist der 04.08.2003
                                       'USDSEK' => strToTime('2003-08-05 00:00:00 GMT'),  // TODO: Start ist der 04.08.2003
                                       'USDSGD' => strToTime('2004-11-17 00:00:00 GMT'),  // TODO: Start ist der 16.11.2004
                                       'USDZAR' => strToTime('1997-10-14 00:00:00 GMT'),  // TODO: Start ist der 13.11.1997
);

// Start der Tick-History der Dukascopy-Instrumente
Dukascopy::$historyStart_Ticks = array('AUDJPY' => strToTime('2007-03-30 16:01:15 GMT'),  // TODO: Werte sind nicht mehr aktuell
                                       'AUDNZD' => strToTime('2008-12-22 16:16:02 GMT'),
                                       'AUDUSD' => strToTime('2007-03-30 16:01:16 GMT'),
                                       'CADJPY' => strToTime('2007-03-30 16:01:16 GMT'),
                                       'CHFJPY' => strToTime('2007-03-30 16:01:15 GMT'),
                                       'EURAUD' => strToTime('2007-03-30 16:01:19 GMT'),
                                       'EURCAD' => strToTime('2008-09-23 11:32:09 GMT'),
                                       'EURCHF' => strToTime('2007-03-30 16:01:15 GMT'),
                                       'EURGBP' => strToTime('2007-03-30 16:01:17 GMT'),
                                       'EURJPY' => strToTime('2007-03-30 16:01:16 GMT'),
                                       'EURNOK' => strToTime('2007-03-30 16:01:19 GMT'),
                                       'EURSEK' => strToTime('2007-03-30 16:01:31 GMT'),
                                       'EURUSD' => strToTime('2007-03-30 16:01:15 GMT'),
                                       'GBPCHF' => strToTime('2007-03-30 16:01:15 GMT'),
                                       'GBPJPY' => strToTime('2007-03-30 16:01:15 GMT'),
                                       'GBPUSD' => strToTime('2007-03-30 16:01:15 GMT'),
                                       'NZDUSD' => strToTime('2007-03-30 16:01:53 GMT'),
                                       'USDCAD' => strToTime('2007-03-30 16:01:16 GMT'),
                                       'USDCHF' => strToTime('2007-03-30 16:01:15 GMT'),
                                       'USDJPY' => strToTime('2007-03-30 16:01:15 GMT'),
                                       'USDNOK' => strToTime('2008-09-28 22:04:55 GMT'),
                                       'USDSEK' => strToTime('2008-09-28 23:30:31 GMT'),
);
