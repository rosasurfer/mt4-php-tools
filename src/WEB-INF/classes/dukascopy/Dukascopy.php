<?php
/**
 * Dukascopy related functionality
 *
 *
 * Datenformat von Bardaten:
 * -------------------------
 * • Big-Endian
 *                               size        offset
 * struct DUKASCOPY_BAR {        ----        ------
 *    int timeDelta;               4            0        // Zeitdifferenz in Sekunden seit 00:00 GMT
 *    int open;                    4            4        // in Points
 *    int close;                   4            8        // in Points
 *    int low;                     4           12        // in Points
 *    int high;                    4           16        // in Points
 *    int volume;                  4           20        // vermutlich in Units
 * };                           = 24 byte
 */
class Dukascopy extends StaticClass {


   /**
    * Verarbeitet eine LZMA-komprimierte Dukascopy-Kursdatei.
    *
    * @param  string $fileName - vollständiger Name der Datei
    */
   public static function processBarFile($fileName) {
      if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.getType($fileName));

      if (!fileSize($fileName)) {                                    // TODO: Gap speichern
         Logger ::log("Skipping zero sized file \"$fileName\"", L_NOTICE, __CLASS__);
         return;
      }

      // Datei entpacken
      $binString = LZMA ::decodeFile($fileName);

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
