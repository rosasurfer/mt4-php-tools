<?php
namespace rosasurfer\rt;

use rosasurfer\core\StaticClass;
use rosasurfer\exception\FileNotFoundException;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InfrastructureException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\util\PHP;


/**
 * LZMA related functionality
 */
class LZMA extends StaticClass {


    /**
     * Entpackt einen LZMA-komprimierten binaeren String und gibt seinen Inhalt zurueck.
     *
     * @param  string $data - kompromierter String
     *
     * @return string - unkompromierter String
     */
    public static function decompressData($data) {
        if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));
        if (!strlen($data))    throw new InvalidArgumentException('Invalid parameter $data: "" (not compressed)');

        // Unter Windows blockiert das Schreiben nach STDIN bei Datenmengen ab 8193 Bytes, stream_set_blocking() scheint dort
        // jedoch nicht zu funktionieren (Windows 7). Daher wird der String in eine temporaere Datei geschrieben und diese
        // decodiert.

        $tmpFile = tempnam(null, 'php');
        file_put_contents($tmpFile, $data);

        $content = self::decompressFile($tmpFile);
        unlink($tmpFile);

        return $content;
    }


    /**
     * Entpackt eine LZMA-komprimierte Datei und gibt ihren Inhalt zurueck.
     *
     * @param  string $file - vollstaendiger Dateiname
     *
     * @return string - unkomprimierter Dateiinhalt
     */
    public static function decompressFile($file) {
        if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.gettype($file));
        if (!is_file($file))   throw new FileNotFoundException('File not found "'.$file.'"');
        if (!filesize($file))  throw new InvalidArgumentException('Invalid file "'.$file.'" (not compressed)');

        $cmd     = self::getDecompressFileCmd();
        $file    = str_replace('/', DIRECTORY_SEPARATOR, str_replace('\\', '/', $file));
        $cmdLine = sprintf($cmd, $file);
        $stderr  = null;
        $stdout  = PHP::execProcess($cmdLine, $stderr);

        if (!strlen($stdout)) throw new RuntimeException('Decoding of file "'.$file.'" failed (decoded size=0),'.NL.'STDERR: '.$stderr);

        return $stdout;
    }


    /**
     * Sucht einen verfuegbaren LZMA-Decoder und gibt die Befehlszeile zum Dekomprimieren einer Datei nach STDOUT zurueck.
     *
     * @return string
     */
    public static function getDecompressFileCmd() {
        static $cmd = null;

        if (!$cmd) {
            $output = $error = null;

            !$cmd && exec('lzmadec -V 2> '.NUL_DEVICE, $output, $error);                            // search lzmadec in PATH
            !$cmd && !$error && ($cmd='lzmadec "%s"');

            !$cmd && exec('lzma -V 2> '.NUL_DEVICE, $output, $error);                               // search lzma in PATH
            !$cmd && !$error && ($cmd='lzma -dc "%s"');

            !$cmd && exec('xz -V 2> '.NUL_DEVICE, $output, $error);                                 // search xz in PATH
            !$cmd && !$error && ($cmd='xz -dc "%s"');

            !$cmd && exec('xzdec -V 2> '.NUL_DEVICE, $output, $error);                              // search xzdec in PATH
            !$cmd && !$error && ($cmd='xzdec "%s"');

            if (!$cmd && WINDOWS) {
                $appRoot = str_replace('\\', '/', self::di()['config']['app.dir.root']);

                !$cmd && exec('"'.$appRoot.'/bin/xz/lzmadec" -V 2> '.NUL_DEVICE, $output, $error);  // search lzmadec in project
                !$cmd && !$error && ($cmd='"'.$appRoot.'/bin/xz/lzmadec" "%s"');

                !$cmd && exec('"'.$appRoot.'/bin/xz/xz" -V 2> '.NUL_DEVICE, $output, $error);       // search xz in project
                !$cmd && !$error && ($cmd='"'.$appRoot.'/bin/xz/xz" -dc "%s"');
            }
            if (!$cmd) throw new InfrastructureException('No LZMA decoder found.');
        }
        return $cmd;
    }
}
