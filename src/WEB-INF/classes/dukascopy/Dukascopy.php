<?
/**
 * Dukascopy related functionality
 */
class Dukascopy extends StaticClass {


   /**
    * Verarbeitet eine Dukascopy-Bar-Datei.
    *
    * @param  string $filename - vollständiger Dateiname
    */
   public static function processBarFile($filename) {
      if (!is_string($filename)) throw new IllegalTypeException('Illegal type of argument $filename: '.getType($filename));

      if (!fileSize($filename)) {
         // TODO: Gap speichern
         Logger ::log("Skipping zero sized file \"$filename\"", L_NOTICE, __CLASS__);
         return;
      }

      // Datei entpacken
      $binary = LZMA ::decompress($filename);

      // Inhalt lesen
      $size   = strLen($binary);
      $size  -= $size % DUKASCOPY_BAR_SIZE;
      $offset = 0;

      while ($offset < $size) {
         $d = unpack("@$offset/NdeltaTime/Nopen/Nclose/Nlow/Nhigh/Nvol", $binary);
         $offset += DUKASCOPY_BAR_SIZE;

         echoPre("deltaTime=$d[deltaTime]  O=$d[open]  H=$d[high]  L=$d[low]  C=$d[close]  V=$d[vol]");
         if ($offset > 112056)
            break;
      }
   }
}
?>
