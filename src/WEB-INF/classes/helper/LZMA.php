<?php
/**
 * LZMA related functionality
 */
class LZMA extends StaticClass {


   /**
    * Entpackt einen LZMA-komprimierten binären String und gibt seinen Inhalt zurück.
    *
    * @param  string $string - kompromierter String
    *
    * @return string - unkompromierter String
    */
   public static function decompress($string) {
      if (!is_string($string)) throw new IllegalTypeException('Illegal type of parameter $string: '.getType($string));
      if (!strLen($string))
         return '';

      // Unter Windows blockiert das Schreiben nach STDIN bei Datenmengen ab 8193 Bytes, stream_set_blocking() scheint dort
      // jedoch nicht zu funktionieren (Windows 7). Daher wird der String in eine temporäre Datei geschrieben und diese
      // decodiert.

      $tmpFile = tempNam(null, 'php');
      $hFile   = fOpen($tmpFile, 'wb');
      fWrite($hFile, $string);
      fClose($hFile);

      $content = self::decompressFile($tmpFile);
      unlink($tmpFile);

      return $content;
   }


   /**
    * Entpackt eine LZMA-komprimierte Datei und gibt ihren Inhalt zurück.
    *
    * @param  string $file - vollständiger Dateiname
    *
    * @return string - unkomprimierter Dateiinhalt
    */
   public static function decompressFile($file) {
      if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
      if (!is_file($file))   throw new FileNotFoundException('File not found "'.$file.'"');

      if (!fileSize($file))
         return '';

      $cmd     = self::getDecompressFileCmd();
      $file    = str_replace('/', DIRECTORY_SEPARATOR, str_replace('\\', '/', $file));
      $cmdLine = sprintf($cmd, $file);
      $stdout  = '';

      if (WINDOWS) $stdout = shell_exec_fix($cmdLine);      // Workaround für Windows-Bug in shell_exec(), siehe dort
      else         $stdout = shell_exec    ($cmdLine);

      if (!strLen($stdout)) throw new plRuntimeException("Decoding of file \"$file\" failed (decoded size=0)");

      return $stdout;
   }


   /**
    * Sucht einen verfügbaren LZMA-Decoder und gibt die Befehlszeile zum Dekomprimieren einer Datei nach STDOUT zurück.
    *
    * @return string
    */
   private static function getDecompressFileCmd() {
      static $cmd = null;

      if (!$cmd) {
         $output = array();

         if (WINDOWS) {
            !$cmd && exec(APPLICATION_ROOT.'/../bin/lzmadec -V 2> nul', $output);   // lzmadec im Projekt suchen
            !$cmd && $output && ($cmd=APPLICATION_ROOT.'/../bin/lzmadec "%s"');

            !$cmd && exec(PHPLIB_ROOT.'/../bin/xz/lzmadec -V 2> nul', $output);     // lzmadec in PHPLib suchen
            !$cmd && $output && ($cmd=PHPLIB_ROOT.'/../bin/xz/lzmadec "%s"');

            !$cmd && exec('lzmadec -V 2> nul', $output);                            // lzmadec im Suchpfad suchen
            !$cmd && $output && ($cmd='lzmadec "%s"');

            !$cmd && exec(APPLICATION_ROOT.'/../bin/xz -V 2> nul', $output);        // xz im Projekt suchen
            !$cmd && $output && ($cmd=APPLICATION_ROOT.'/../bin/xz -dc "%s"');

            !$cmd && exec(PHPLIB_ROOT.'/../bin/xz/xz -V 2> nul', $output);          // xz in PHPLib suchen
            !$cmd && $output && ($cmd=PHPLIB_ROOT.'/../bin/xz/xz -dc "%s"');

            !$cmd && exec('xz -V 2> nul', $output);                                 // xz im Suchpfad suchen
            !$cmd && $output && ($cmd='xz -dc "%s"');
         }
         else /*NON-WINDOWS*/ {
            !$cmd && exec('lzmadec -V 2> /dev/null', $output);                      // lzmadec im Suchpfad suchen
            !$cmd && $output && ($cmd='lzmadec "%s"');

            !$cmd && exec('xzdec -V 2> /dev/null', $output);                        // xzdec im Suchpfad suchen
            !$cmd && $output && ($cmd='xzdec "%s"');

            !$cmd && exec('lzma -V 2> /dev/null', $output);                         // lzma im Suchpfad suchen
            !$cmd && $output && ($cmd='lzma -dc "%s"');

            !$cmd && exec('xz -V 2> /dev/null', $output);                           // xz im Suchpfad suchen
            !$cmd && $output && ($cmd='xz -dc "%s"');
         }
         if (!$cmd) throw new InfrastructureException('No LZMA decoder found.');
      }
      return $cmd;
   }
}
?>
