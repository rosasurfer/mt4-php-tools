<?php
/**
 * Dukascopy related functionality
 */
class Dukascopy extends StaticClass {


   /**
    * Verarbeitet eine komprimierte Dukascopy-Kursdatei.
    *
    * @param  string $filename - vollständiger Name der LZMA-gepackten Datei
    *
    * Format der entpackten Datei:
    *                                        size        offset
    * struct big-endian DUKASCOPY_BAR {      ----        ------
    *    int timeDelta;                        4            0        // Zeitdifferenz in Sekunden seit 00:00 GMT
    *    int open;                             4            4        // in Points
    *    int close;                            4            8        // in Points
    *    int low;                              4           12        // in Points
    *    int high;                             4           16        // in Points
    *    int volume;                           4           20
    * };                                    = 24 byte
    */
   public static function processBarFile($filename) {
      if (!is_string($filename)) throw new IllegalTypeException('Illegal type of parameter $filename: '.getType($filename));

      if (!fileSize($filename)) {
         // TODO: Gap speichern
         Logger ::log("Skipping zero sized file \"$filename\"", L_NOTICE, __CLASS__);
         return;
      }

      // Datei entpacken
      $binString = LZMA ::decompress($filename);

      // Inhalt lesen
      $size   = strLen($binString);
      $size  -= $size % DUKASCOPY_BAR_SIZE;
      $offset = 0;

      while ($offset < $size) {
         $d = unpack("@$offset/NtimeDelta/Nopen/Nclose/Nlow/Nhigh/Nvol", $binString);
         $offset += DUKASCOPY_BAR_SIZE;

         echoPre("timeDelta=$d[timeDelta]  O=$d[open]  H=$d[high]  L=$d[low]  C=$d[close]  V=$d[vol]");
         if ($offset > 112056)
            break;
      }
   }
}
?>
