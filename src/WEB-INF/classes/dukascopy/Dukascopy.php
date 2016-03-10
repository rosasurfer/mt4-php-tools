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
 *    float lots;                         4           20        kumulierte Angebotsseite in Lots (siehe DUKASCOPY_TICK)
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

      $lenData = strLen($data); if (!$lenData || $lenData%DUKASCOPY_BAR_SIZE) throw new plRuntimeException('Odd length of passed data: '.$lenData.' (not an even DUKASCOPY_BAR_SIZE)');
      $offset  = 0;
      $bars    = array();
      $i       = -1;

      static $isLittleEndian = null; is_null($isLittleEndian) && $isLittleEndian=isLittleEndian();

      // unpack() unterstützt keinen expliziten Big-Endian-Float, die Byte-Order von 'lots' muß ggf. manuell reversed werden.
      while ($offset < $lenData) {
         $i++;
         $bars[] = unpack("@$offset/NtimeDelta/Nopen/Nclose/Nlow/Nhigh", $data);
         $s      = subStr($data, $offset+20, 4);
         $lots   = unpack('f', $isLittleEndian ? strRev($s) : $s);
         $bars[$i]['lots'] = round($lots[1], 2);
         $offset += DUKASCOPY_BAR_SIZE;

         // Bar validieren
         if ($bars[$i]['open' ] > $bars[$i]['high'] ||      // aus (H >= O && O >= L) folgt (H >= L)
             $bars[$i]['open' ] < $bars[$i]['low' ] ||      // nicht mit min()/max(), da nicht performant
             $bars[$i]['close'] > $bars[$i]['high'] ||
             $bars[$i]['close'] < $bars[$i]['low' ]) {

            Logger::log("Illegal data for bar[$i]: O={$bars[$i]['open']} H={$bars[$i]['high']} L={$bars[$i]['low']} C={$bars[$i]['close']}, adjusting...", L_WARN, __CLASS__);

            $bars[$i]['high'] = max($bars[$i]['open'], $bars[$i]['high'], $bars[$i]['low'], $bars[$i]['close']);
            $bars[$i]['low' ] = min($bars[$i]['open'], $bars[$i]['high'], $bars[$i]['low'], $bars[$i]['close']);

            /*
            [Info]    Mon, 21-Feb-2011   url: http://www.dukascopy.com/datafeed/XAUUSD/2011/01/21/BID_candles_min_1.bi5
            [FATAL] Uncaught plRuntimeException: Illegal data for bar[1383]: O=1407.37'2 H=1407.55'0 L=1407.37'2 C=1407.37'0
            */
            //throw new plRuntimeException("Illegal data for bar[$i]: O={$bars[$i]['open']} H={$bars[$i]['high']} L={$bars[$i]['low']} C={$bars[$i]['close']}");
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

      $lenData = strLen($data); if (!$lenData || $lenData%DUKASCOPY_TICK_SIZE) throw new plRuntimeException('Odd length of passed data: '.$lenData.' (not an even DUKASCOPY_TICK_SIZE)');
      $offset  = 0;
      $ticks   = array();
      $i       = -1;

      static $isLittleEndian = null; is_null($isLittleEndian) && $isLittleEndian=isLittleEndian();

      // unpack() unterstützt keinen expliziten Big-Endian-Float, die Byte-Order von 'bidSize' und 'askSize' muß ggf. manuell
      // reversed werden.
      while ($offset < $lenData) {
         $i++;
         $ticks[] = unpack("@$offset/NtimeDelta/Nask/Nbid", $data);
         $s1      = subStr($data, $offset+12, 4);
         $s2      = subStr($data, $offset+16, 4);
         $size    = unpack('fask/fbid', $isLittleEndian ? strRev($s1).strRev($s2) : $s1.$s2);
         $ticks[$i]['askSize'] = round($size['ask'], 2);
         $ticks[$i]['bidSize'] = round($size['bid'], 2);
         $offset += DUKASCOPY_TICK_SIZE;
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
