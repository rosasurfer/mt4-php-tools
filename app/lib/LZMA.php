<?php
namespace rosasurfer\xtrade;

use rosasurfer\core\StaticClass;

use rosasurfer\exception\FileNotFoundException;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InfrastructureException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;

use rosasurfer\util\PHP;
use rosasurfer\config\Config;


/**
 * LZMA related functionality
 */
class LZMA extends StaticClass {


    /**
     * Entpackt einen LZMA-komprimierten binären String und gibt seinen Inhalt zurück.
     *
     * @param  string $data - kompromierter String
     *
     * @return string - unkompromierter String
     */
    public static function decompressData($data) {
        if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));
        if (!strLen($data))    throw new InvalidArgumentException('Invalid parameter $data: "" (not compressed)');

        // Unter Windows blockiert das Schreiben nach STDIN bei Datenmengen ab 8193 Bytes, stream_set_blocking() scheint dort
        // jedoch nicht zu funktionieren (Windows 7). Daher wird der String in eine temporäre Datei geschrieben und diese
        // decodiert.

        $tmpFile = tempNam(null, 'php');
        $hFile   = fOpen($tmpFile, 'wb');
        fWrite($hFile, $data);
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
        if (!fileSize($file))  throw new InvalidArgumentException('Invalid file "'.$file.'" (not compressed)');

        $cmd     = self::getDecompressFileCmd();
        $file    = str_replace('/', DIRECTORY_SEPARATOR, str_replace('\\', '/', $file));
        $cmdLine = sprintf($cmd, $file);
        $stdout  = PHP::shellExec($cmdLine);

        if (!strLen($stdout)) throw new RuntimeException('Decoding of file "'.$file.'" failed (decoded size=0)');

        return $stdout;
    }


    /**
     * Sucht einen verfügbaren LZMA-Decoder und gibt die Befehlszeile zum Dekomprimieren einer Datei nach STDOUT zurück.
     *
     * @return string
     */
    private static function getDecompressFileCmd() {
        static $cmd = null;

        if ($cmd === null) {
            $output = [];

            if (WINDOWS) {
                /** @var string $appRoot */
                $appRoot = Config::getDefault()->get('app.dir.root');

                !$cmd && exec($appRoot.'/etc/bin/xz/lzmadec -V 2> nul', $output);   // lzmadec im Projekt suchen
                !$cmd && $output && ($cmd=$appRoot.'/etc/bin/xz/lzmadec "%s"');

                !$cmd && exec('lzmadec -V 2> nul', $output);                        // lzmadec im Suchpfad suchen
                !$cmd && $output && ($cmd='lzmadec "%s"');

                !$cmd && exec($appRoot.'/etc/bin/xz/xz -V 2> nul', $output);        // xz im Projekt suchen
                !$cmd && $output && ($cmd=$appRoot.'/etc/bin/xz/xz -dc "%s"');

                !$cmd && exec('xz -V 2> nul', $output);                             // xz im Suchpfad suchen
                !$cmd && $output && ($cmd='xz -dc "%s"');
            }
            else /*NON-WINDOWS*/ {
                !$cmd && exec('lzmadec -V 2> /dev/null', $output);                  // lzmadec im Suchpfad suchen
                !$cmd && $output && ($cmd='lzmadec "%s"');

                !$cmd && exec('xzdec -V 2> /dev/null', $output);                    // xzdec im Suchpfad suchen
                !$cmd && $output && ($cmd='xzdec "%s"');

                !$cmd && exec('lzma -V 2> /dev/null', $output);                     // lzma im Suchpfad suchen
                !$cmd && $output && ($cmd='lzma -dc "%s"');

                !$cmd && exec('xz -V 2> /dev/null', $output);                       // xz im Suchpfad suchen
                !$cmd && $output && ($cmd='xz -dc "%s"');
            }
            if ($cmd === null) throw new InfrastructureException('No LZMA decoder found.');
        }
        return $cmd;
    }
}
