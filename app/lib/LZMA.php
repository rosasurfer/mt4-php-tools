<?php
namespace rosasurfer\rt\lib;

use rosasurfer\core\StaticClass;
use rosasurfer\core\assert\Assert;
use rosasurfer\core\exception\FileNotFoundException;
use rosasurfer\core\exception\InfrastructureException;
use rosasurfer\core\exception\InvalidArgumentException;
use rosasurfer\core\exception\RuntimeException;
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
        Assert::string($data);
        if (!strlen($data)) throw new InvalidArgumentException('Invalid parameter $data: "" (not compressed)');

        // Unter Windows blockiert das Schreiben nach STDIN bei Datenmengen ab 8193 Bytes, stream_set_blocking() scheint dort
        // jedoch nicht zu funktionieren (Windows 7). Daher wird der String in eine temporaere Datei geschrieben und diese
        // decodiert.

        $tmpFile = tempnam(null, 'php');
        file_put_contents($tmpFile, $data);

        $content = static::decompressFile($tmpFile);
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
        Assert::string($file);
        if (!is_file($file))  throw new FileNotFoundException('File not found "'.$file.'"');
        if (!filesize($file)) throw new InvalidArgumentException('Invalid file "'.$file.'" (not compressed)');

        $cmd     = static::getDecompressFileCmd();
        $file    = str_replace('/', DIRECTORY_SEPARATOR, str_replace('\\', '/', $file));
        $cmdLine = sprintf($cmd, $file);
        $stderr  = null;
        $stdout  = PHP::execProcess($cmdLine, $stderr);

        if (!strlen($stdout)) throw new RuntimeException('Decoding of file "'.$file.'" failed (decoded size=0),'.NL.'STDERR: '.$stderr);

        return $stdout;
    }


    /**
     * Search an available LZMA decoder and return the command to decompress a file to STDOUT.
     *
     * @return string
     */
    public static function getDecompressFileCmd() {
        static $cmd = null;

        if (!$cmd) {
            $output = $error = null;

            exec('lzmadec -V 2> '.NUL_DEVICE, $output, $error);                                 // search lzmadec in PATH
            if (!$error) return $cmd = 'lzmadec "%s"';

            exec('lzma -V 2> '.NUL_DEVICE, $output, $error);                                    // search lzma in PATH
            if (!$error) return $cmd = 'lzma -dc "%s"';

            exec('xz -V 2> '.NUL_DEVICE, $output, $error);                                      // search xz in PATH
            if (!$error) return $cmd = 'xz -dc "%s"';

            exec('xzdec -V 2> '.NUL_DEVICE, $output, $error);                                   // search xzdec in PATH
            if (!$error) return $cmd = 'xzdec "%s"';

            if (WINDOWS) {
                $appRoot = str_replace('\\', '/', self::di('config')['app.dir.root']);

                exec('"'.$appRoot.'/bin/win32/lzmadec" -V 2> '.NUL_DEVICE, $output, $error);    // search lzmadec in project
                if (!$error) return $cmd = '"'.$appRoot.'/bin/win32/lzmadec" "%s"';

                exec('"'.$appRoot.'/bin/win32/xz" -V 2> '.NUL_DEVICE, $output, $error);         // search xz in project
                if (!$error) return $cmd = '"'.$appRoot.'/bin/win32/xz" -dc "%s"';
            }
            throw new InfrastructureException('No LZMA decoder found.');
        }
        return $cmd;
    }
}
