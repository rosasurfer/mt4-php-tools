<?php
/**
 * LZMA related functionality
 */
class LZMA extends StaticClass {


   /**
    * Entpackt die angegebene Datei und gibt ihren Inhalt zurück.
    *
    * @param  string $file - vollständiger Dateiname
    *
    * @return string - Dateiinhalt
    */
   public static function decompress($file) {
      if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
      if (!is_file($file))   throw new FileNotFoundException('File not found "'.$file.'"');

      if (!fileSize($file))
         return "";

      $cmd     = self::getDecompressorCmd();
      $file    = str_replace('/', DIRECTORY_SEPARATOR, str_replace('\\', '/', $file));
      $cmdLine = sprintf($cmd, $file);
      $stdout  = '';

      if (WINDOWS) $stdout = shell_exec_fix($cmdLine);      // Workaround für Windows-Bug in shell_exec(), siehe dort
      else         $stdout = shell_exec    ($cmdLine);

      if (!strLen($stdout)) throw new plRuntimeException("Decompression of file \"$file\" failed (size=0)");

      return $stdout;
   }


   /**
    * Sucht einen verfügbaren LZMA-Dekompressor und gibt die Befehlszeile zum Entpacken einer Datei nach stdout zurück.
    *
    * @return string
    */
   public static function getDecompressorCmd() {
      static $cmd = null;

      if (!$cmd) {
         $output = array();

         if (WINDOWS) {
            if (!$cmd) {
               exec(APPLICATION_ROOT.'/../bin/lzmadec -V 2> nul', $output);
               if ($output) $cmd = APPLICATION_ROOT.'/../bin/lzmadec "%s"';
            }
            if (!$cmd) {
               exec(APPLICATION_ROOT.'/../bin/xz -V 2> nul', $output);
               if ($output) $cmd = APPLICATION_ROOT.'/../bin/xz -dc "%s"';
            }
            if (!$cmd) {
               exec('lzmadec -V 2> nul', $output);
               if ($output) $cmd = 'lzmadec "%s"';
            }
            if (!$cmd) {
               exec('xz -V 2> nul', $output);
               if ($output) $cmd = 'xz -dc "%s"';
            }
         }
         else /*NON-WINDOWS*/ {
            if (!$cmd) {
               exec('lzmadec -V 2> /dev/null', $output);
               if ($output) $cmd = 'lzmadec "%s"';
            }
            if (!$cmd) {
               exec('lzma -V 2> /dev/null', $output);
               if ($output) $cmd = 'lzma -kdc -S bi5 "%s"';
            }
            if (!$cmd) {
               exec('xzdec -V 2> /dev/null', $output);
               if ($output) $cmd = 'xzdec "%s"';
            }
            if (!$cmd) {
               exec('xz -V 2> /dev/null', $output);
               if ($output) $cmd = 'xz -dc "%s"';
            }
         }
         if (!$cmd) throw new InfrastructureException('No LZMA decompressor found.');
      }

      return $cmd;
   }
}
?>
