<?php
namespace rosasurfer\rt\metatrader;

use rosasurfer\core\Object;
use rosasurfer\debug\ErrorHandler;
use rosasurfer\exception\IllegalStateException;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\log\Logger;

use rosasurfer\rt\metatrader\MT4;

use function rosasurfer\rt\timeframeDescription;

use const rosasurfer\rt\PERIOD_M1;
use const rosasurfer\rt\PERIOD_M5;
use const rosasurfer\rt\PERIOD_M15;
use const rosasurfer\rt\PERIOD_M30;
use const rosasurfer\rt\PERIOD_H1;
use const rosasurfer\rt\PERIOD_H4;
use const rosasurfer\rt\PERIOD_D1;
use const rosasurfer\rt\PERIOD_W1;
use const rosasurfer\rt\PERIOD_MN1;


/**
 * Ein HistorySet zur Verwaltung der MetaTrader-History eines Instruments. Die Formate der einzelnen Dateien
 * eines HistorySets koennen gemischt sein.
 */
class HistorySet extends Object {


    /** @var string */
    protected $symbol;

    /** @var int */
    protected $digits;

    /** @var string - einfacher Servername */
    protected $serverName;

    /** @var string - vollstaendiger Verzeichnisname */
    protected $serverDirectory;

    /** @var bool - ob das Set geschlossen und seine Resourcen freigegeben sind */
    protected $closed = false;

    /** @var HistoryFile[] */
    protected $historyFiles = [
        PERIOD_M1  => null,
        PERIOD_M5  => null,
        PERIOD_M15 => null,
        PERIOD_M30 => null,
        PERIOD_H1  => null,
        PERIOD_H4  => null,
        PERIOD_D1  => null,
        PERIOD_W1  => null,
        PERIOD_MN1 => null
    ];

    /** @var HistorySet[] - alle Instanzen dieser Klasse */
    private static $instances = [];


    /**
     * Ueberladener Constructor.
     *
     * Signaturen:
     * -----------
     * new HistorySet(string $symbol, int $digits, int $format, string $serverDirectory)
     * new HistorySet(HistoryFile $file)
     *
     * @param  HistoryFile|string $arg1
     * @param  int                $digits
     * @param  int                $format
     * @param  string             $serverDirectory
     */
    private function __construct($arg1, $digits=null, $format=null, $serverDirectory=null) {
        $argc = func_num_args();
        if ($argc == 4) {
            /** @var string */
            $symbol = $arg1;
            $this->__construct_1($symbol, $digits, $format, $serverDirectory);
        }
        else if ($argc == 1) {
            /** @var HistoryFile */
            $file = $arg1;
            $this->__construct_2($file);
        }
        else throw new InvalidArgumentException('Invalid number of arguments: '.$argc);
    }


    /**
     * Constructor 1
     *
     * Erzeugt eine neue Instanz und legt alle Historydateien neu an. Vorhandene Daten werden geloescht. Mehrfachaufrufe
     * dieser Funktion fuer dasselbe Symbol desselben Servers geben jeweils eine neue Instanz zurueck, weitere existierende
     * Instanzen werden als ungueltig markiert.
     *
     * @param  string $symbol - Symbol der HistorySet-Daten
     * @param  int    $digits - Digits der Datenreihe
     * @param  int    $format - Speicherformat der Datenreihen:
     *                          400: MetaTrader <= Build 509
     *                          401: MetaTrader  > Build 509
     * @param  string $serverDirectory - Serververzeichnis der Historydateien des Sets
     */
    private function __construct_1($symbol, $digits, $format, $serverDirectory) {
        if (!is_string($symbol))                      throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
        if (!strLen($symbol))                         throw new InvalidArgumentException('Invalid parameter $symbol: ""');
        if (strLen($symbol) > MT4::MAX_SYMBOL_LENGTH) throw new InvalidArgumentException('Invalid parameter $symbol: "'.$symbol.'" (max '.MT4::MAX_SYMBOL_LENGTH.' characters)');
        if (!is_int($digits))                         throw new IllegalTypeException('Illegal type of parameter $digits: '.getType($digits));
        if ($digits < 0)                              throw new InvalidArgumentException('Invalid parameter $digits: '.$digits);
        if (!is_string($serverDirectory))             throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.getType($serverDirectory));
        if (!is_dir($serverDirectory))                throw new InvalidArgumentException('Directory "'.$serverDirectory.'" not found');

        $this->symbol          = $symbol;
        $this->digits          = $digits;
        $this->serverDirectory = realPath($serverDirectory);
        $this->serverName      = baseName($this->serverDirectory);

        // offene Sets durchsuchen und Sets desselben Symbols schliessen
        $symbolUpper = strToUpper($this->symbol);
        foreach (self::$instances as $instance) {
            if (!$instance->isClosed() && $symbolUpper==strToUpper($instance->getSymbol()) && $this->serverDirectory==$instance->getServerDirectory())
                $instance->close();
        }

        // alle HistoryFiles zuruecksetzen
        foreach ($this->historyFiles as $timeframe => &$file) {
            $file = new HistoryFile($symbol, $timeframe, $digits, $format, $serverDirectory);
        }; unset($file);

        // Instanz speichern
        self::$instances[] = $this;
    }


    /**
     * Constructor 2
     *
     * Erzeugt eine neue Instanz. Vorhandene Daten werden nicht geloescht.
     *
     * @param  HistoryFile $file - existierende History-Datei
     */
    private function __construct_2(HistoryFile $file) {
        $this->symbol          =          $file->getSymbol();
        $this->digits          =          $file->getDigits();
        $this->serverName      =          $file->getServerName();
        $this->serverDirectory = realPath($file->getServerDirectory());

        $this->historyFiles[$file->getTimeframe()] = $file;

        $symbolUpper = strToUpper($this->symbol);
        foreach (self::$instances as $instance) {
            if (!$instance->isClosed() && $symbolUpper==strToUpper($instance->getSymbol()) && $this->serverDirectory==$instance->getServerDirectory())
                throw new RuntimeException('Multiple open HistorySets for "'.$this->serverName.'::'.$this->symbol.'"');
        }

        // alle uebrigen existierenden HistoryFiles oeffnen und validieren (nicht existierende Dateien werden erst bei Bedarf erstellt)
        foreach ($this->historyFiles as $timeframe => &$file) {
            if (!$file) {
                $fileName = $this->serverDirectory.'/'.$this->symbol.$timeframe.'.hst';
                if (is_file($fileName)) {
                    try {
                        $file = new HistoryFile($fileName);
                    }
                    catch (MetaTraderException $ex) {
                        if (strStartsWith($ex->getMessage(), 'filesize.insufficient')) {
                            Logger::log($ex->getMessage(), L_WARN);            // eine zu kurze Datei wird spaeter ueberschrieben
                            continue;
                        }
                        throw $ex;
                    }
                    if ($file->getDigits() != $this->getDigits()) throw new RuntimeException('Digits mis-match in "'.$fileName.'": file.digits='.$file->getDigits().' instead of set.digits='.$this->getDigits());
                }
            }
        }; unset($file);

        self::$instances[] = $this;
    }


    /**
     * Destructor
     *
     * Sorgt bei Zerstoerung der Instanz dafuer, dass alle gehaltenen Resourcen freigegeben werden.
     */
    public function __destruct() {
        // Attempting to throw an exception from a destructor during script shutdown causes a fatal error.
        // @see http://php.net/manual/en/language.oop5.decon.php
        try {
            $this->close();
        }
        catch (\Exception $ex) {
            throw ErrorHandler::handleDestructorException($ex);
        }
    }


    /**
     * @return string
     */
    public function getSymbol() {
        return $this->symbol;
    }


    /**
     * @return int
     */
    public function getDigits() {
        return $this->digits;
    }


    /**
     * @return string
     */
    public function getServerName() {
        return $this->serverName;
    }


    /**
     * @return string
     */
    public function getServerDirectory() {
        return $this->serverDirectory;
    }


    /**
     * @return bool
     */
    public function isClosed() {
        return (bool)$this->closed;
    }


    /**
     * Schliesst dieses HistorySet. Gibt alle Resourcen dieser Instanz frei. Nach dem Aufruf kann die Instanz nicht mehr verwendet werden.
     *
     * @return bool - Erfolgsstatus; FALSE, wenn die Instanz bereits geschlossen war
     */
    public function close() {
        if ($this->isClosed())
            return false;

        foreach ($this->historyFiles as $file) {
            $file && !$file->isClosed() && $file->close();
        }
        return $this->closed=true;
    }


    /**
     * Erzeugt eine neue Instanz und legt alle Historydateien neu an. Vorhandene Daten werden geloescht. Mehrfachaufrufe
     * dieser Funktion fuer dasselbe Symbol desselben Servers geben jeweils eine neue Instanz zurueck, weitere existierende
     * Instanzen werden als ungueltig markiert.
     *
     * @param  string $symbol - Symbol der HistorySet-Daten
     * @param  int    $digits - Digits der Datenreihe
     * @param  int    $format - Speicherformat der Datenreihen:
     *                          400: MetaTrader <= Build 509
     *                          401: MetaTrader  > Build 509
     * @param  string $serverDirectory - Serververzeichnis der Historydateien des Sets
     */
    public static function create($symbol, $digits, $format, $serverDirectory) {
        return new static($symbol, $digits, $format, $serverDirectory);
    }


    /**
     * Oeffentlicher Zugriff auf Constructor 2
     *
     * Gibt eine Instanz fuer bereits vorhandene Historydateien zurueck. Vorhandene Daten werden nicht geloescht. Es muss mindestens
     * ein HistoryFile des Symbols existieren. Nicht existierende HistoryFiles werden beim Speichern der ersten hinzugefuegten
     * Daten angelegt. Mehrfachaufrufe dieser Funktion fuer dasselbe Symbol desselben Servers geben dieselbe Instanz zurueck.
     *
     * @param  string $symbol          - Symbol der Historydateien
     * @param  string $serverDirectory - Serververzeichnis, in dem die Historydateien gespeichert sind
     *
     * @return self - Instanz oder NULL, wenn keine entsprechenden Historydateien gefunden wurden oder die
     *                gefundenen Dateien korrupt sind.
     */
    public static function get($symbol, $serverDirectory) {
        if (!is_string($symbol))          throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
        if (!strLen($symbol))             throw new InvalidArgumentException('Invalid parameter $symbol: ""');
        if (!is_string($serverDirectory)) throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.getType($serverDirectory));
        if (!is_dir($serverDirectory))    throw new InvalidArgumentException('Directory "'.$serverDirectory.'" not found');

        // existierende Instanzen durchsuchen und bei Erfolg die entsprechende Instanz zurueckgeben
        $symbolUpper     = strToUpper($symbol);
        $serverDirectory = realPath($serverDirectory);
        foreach (self::$instances as $instance) {
            if (!$instance->isClosed() && $symbolUpper==strToUpper($instance->getSymbol()) && $serverDirectory==$instance->getServerDirectory())
                return $instance;
        }

        // das erste existierende HistoryFile an den Constructor uebergeben, das Set liest die weiteren dann selbst ein
        /** @var HistorySet $set */
        $set  = null;
        $file = null;

        foreach (MT4::$timeframes as $timeframe) {
            $fileName = $serverDirectory.'/'.$symbol.$timeframe.'.hst';
            if (is_file($fileName)) {
                try {
                    $file = new HistoryFile($fileName);
                }
                catch (MetaTraderException $ex) {
                    Logger::log($ex->getMessage(), L_WARN);
                    continue;
                }
                $set = new static($file);
                break;
            }
        }
        return $set;
    }


    /**
     * Gibt das HistoryFile des angegebenen Timeframes zurueck. Existiert es nicht, wird es erzeugt.
     *
     * @param  int $timeframe
     *
     * @return HistoryFile
     */
    private function getFile($timeframe) {
        if (!isSet($this->historyFiles[$timeframe])) {
            $fileName = $this->serverDirectory.'/'.$this->symbol.$timeframe.'.hst';

            $file = null;
            if (is_file($fileName)) {
                try {
                    $file = new HistoryFile($fileName);
                }
                catch (MetaTraderException $ex) {
                    if (!strStartsWith($ex->getMessage(), 'filesize.insufficient')) throw $ex;
                    Logger::log($ex->getMessage(), L_WARN);                     // eine zu kurze Datei wird mit einer neuen Datei ueberschrieben
                }
                if ($file->getDigits() != $this->getDigits()) throw new RuntimeException('Digits mis-match in "'.$fileName.'": file.digits='.$file->getDigits().' instead of set.digits='.$this->getDigits());
            }

            if (!$file) $file = new HistoryFile($this->symbol, $timeframe, $this->digits, $format=400, $this->serverDirectory);
            $this->historyFiles[$timeframe] = $file;
        }
        return $this->historyFiles[$timeframe];
    }


    /**
     * Gibt den letzten fuer alle HistoryFiles des Sets gueltigen Synchronisationszeitpunkt zurueck.
     * Dies ist der aelteste Synchronisationszeitpunkt der einzelnen HistoryFiles.
     *
     * @return int - Timestamp (in jeweiliger Serverzeit)
     *               0, wenn mindestens eines der HistoryFiles noch gar nicht synchronisiert wurde
     *
     * TODO: needs to return standard time (i.e. FXT)
     */
    public function getLastSyncTime() {
        $minTime = PHP_INT_MAX;

        foreach ($this->historyFiles as $timeframe => $file) {
            $time = $file ? $file->getLastSyncTime() : 0;   // existiert die Datei nicht, wurde sie auch noch nicht synchronisiert
            $minTime = min($minTime, $time);

            if ($timeframe == PERIOD_M1)                    // TODO: vorerst nur PERIOD_M1
                break;
        }

        return $minTime==PHP_INT_MAX ? 0 : $minTime;
    }


    /**
     * Fuegt dem Ende der Zeitreihen des Sets weitere Bardaten hinzu. Vorhandene Daten werden nicht geaendert.
     *
     * @param  int[][] $bars - ROST_PRICE_BAR Daten der Periode M1
     *
     * @return bool - Erfolgsstatus
     */
    public function appendBars(array $bars) {
        if ($this->closed) throw new IllegalStateException('Cannot process a closed '.__CLASS__);
        if (!$bars) return false;

        foreach ($this->historyFiles as $timeframe => $file) {
            !$file && $file=$this->getFile($timeframe);
            $file->appendBars($bars);
        }
        return true;
    }


    /**
     * Synchronisiert die Zeitreihen des Sets mit den uebergebenen Bardaten. Vorhandene Bars, die nach dem letzten
     * Synchronisationszeitpunkt der Zeitreihe geschrieben wurden und die sich mit den uebergebenen Bars ueberschneiden,
     * werden ersetzt. Vorhandene Bars, die sich mit den uebergebenen Bars nicht ueberschneiden, bleiben unveraendert.
     *
     * @param  int[][] $bars - ROST_PRICE_BAR Daten der Periode M1
     */
    public function synchronize(array $bars) {
        if ($this->closed) throw new IllegalStateException('Cannot process a closed '.__CLASS__);
        if (!$bars) return;

        $historyM1 = $this->getFile(PERIOD_M1);
        $historyM1->synchronize($bars);
    }


    /**
     * Nur zum Debuggen
     */
    public function showBuffer() {
        echoPre(NL);
        foreach ($this->historyFiles as $timeframe => $file) {
            if ($file) {
                $bars = $file->barBuffer;
                $size = sizeOf($bars);
                $firstBar = $lastBar = null;
                if ($size) {
                    if (isSet($bars[0]['time']) && $bars[$size-1]['time']) {
                        $firstBar = '  from='.gmDate('d-M-Y H:i', $bars[0      ]['time']);
                        $lastBar  = '  to='  .gmDate('d-M-Y H:i', $bars[$size-1]['time']);
                    }
                    else {
                        $firstBar = $lastBar = '  invalid';
                        echoPre($bars);
                    }
                }
                echoPre(get_class($file).'['. str_pad(timeframeDescription($file->getTimeframe()), 3, ' ', STR_PAD_RIGHT).'] => '.str_pad($size, 5, ' ', STR_PAD_LEFT).' bar'.pluralize($size, ' ').$firstBar.($size>1? $lastBar:''));
            }
            else {
                echoPre('HistoryFile['. str_pad(timeframeDescription($timeframe), 3, ' ', STR_PAD_RIGHT).'] => mull');
            }
        }
        echoPre(NL);
    }
}
