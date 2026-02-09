<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib;

use rosasurfer\ministruts\Application;
use rosasurfer\ministruts\core\StaticClass;
use rosasurfer\ministruts\core\exception\FileNotFoundException;
use rosasurfer\ministruts\core\exception\InfrastructureException;
use rosasurfer\ministruts\core\exception\InvalidValueException;
use rosasurfer\ministruts\core\exception\RuntimeException;
use rosasurfer\ministruts\util\PHP;

use const rosasurfer\ministruts\NL;
use const rosasurfer\ministruts\NUL_DEVICE;
use const rosasurfer\ministruts\WINDOWS;


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
    public static function decompressData(string $data): string {
        if (!strlen($data)) throw new InvalidValueException('Invalid parameter $data: "" (empty)');

        // Unter Windows blockiert das Schreiben nach STDIN bei Datenmengen ab 8193 Bytes, stream_set_blocking() scheint dort
        // jedoch nicht zu funktionieren (Windows 7). Daher wird der String in eine temporaere Datei geschrieben und diese
        // decodiert.

        $tmpFile = tempnam('', 'php');
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
    public static function decompressFile(string $file): string {
        if (!is_file($file))  throw new FileNotFoundException('File not found: "'.$file.'"');
        if (!filesize($file)) throw new InvalidValueException('Invalid file "'.$file.'" (size: 0 byte)');

        $cmd     = self::getDecompressFileCmd();
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
                $appRoot = str_replace('\\', '/', Application::service('config')['app.dir.root']);

                exec('"'.$appRoot.'/bin/win32/lzmadec" -V 2> '.NUL_DEVICE, $output, $error);    // search lzmadec in project
                if (!$error) return $cmd = '"'.$appRoot.'/bin/win32/lzmadec" "%s"';

                exec('"'.$appRoot.'/bin/win32/xz" -V 2> '.NUL_DEVICE, $output, $error);         // search xz in project
                if (!$error) return $cmd = '"'.$appRoot.'/bin/win32/xz" -dc "%s"';
            }
            throw new InfrastructureException('LZMA decoder not found.');
        }
        return $cmd;
    }
}
