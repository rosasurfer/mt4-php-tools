<?php
namespace rosasurfer\rt\lib\metatrader;

use rosasurfer\core\Object;
use rosasurfer\debug\ErrorHandler;
use rosasurfer\exception\IllegalStateException;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\log\Logger;

use rosasurfer\rt\lib\metatrader\MT4;

use const rosasurfer\rt\PERIOD_M1;
use const rosasurfer\rt\PERIOD_M5;
use const rosasurfer\rt\PERIOD_M15;
use const rosasurfer\rt\PERIOD_M30;
use const rosasurfer\rt\PERIOD_H1;
use const rosasurfer\rt\PERIOD_H4;
use const rosasurfer\rt\PERIOD_D1;
use const rosasurfer\rt\PERIOD_W1;
use const rosasurfer\rt\PERIOD_MN1;
use rosasurfer\rt\model\RosaSymbol;


/**
 * HistorySet
 *
 * Represents a set of {@link HistoryFile}s for all nine MetaTrader standard timeframes. Data formats in a set may be mixed,
 * e.g. a set may contain files in format HISTORY_BAR_400 (until terminal build 509) and files in format HISTORY_BAR_401
 * (since terminal build > 509).
 */
class HistorySet extends Object {


    /** @var string */
    protected $symbol;

    /** @var int */
    protected $digits;

    /** @var string - short server name */
    protected $serverName;

    /** @var string - full path of the server directory */
    protected $serverDirectory;

    /** @var bool - whether the set is closed and resources are disposed */
    protected $closed = false;

    /** @var HistoryFile[] - all history files a set manages */
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

    /** @var HistorySet[] - all instances of this class */
    private static $instances = [];


    /**
     * Overloaded constructor
     *
     * <pre>
     * Signatures:
     * -----------
     * new HistorySet(HistoryFile $file)
     * new HistorySet(RosaSymbol $symbol, int $format, string $serverDirectory)
     * </pre>
     *
     * @param  array ...$params
     */
    protected function __construct(...$params) {
        $argc = sizeof($params);
        if      ($argc == 1) $this->__construct1(...$params);
        else if ($argc == 3) $this->__construct2(...$params);
        else                 throw new InvalidArgumentException('Invalid number of arguments: '.$argc);
    }


    /**
     * Overloaded constructor
     *
     * Erzeugt eine neue Instanz. Vorhandene Daten werden nicht geloescht.
     *
     * @param  HistoryFile $file - existierende History-Datei
     */
    protected function __construct1(HistoryFile $file) {
        $this->symbol          = $file->getSymbol();
        $this->digits          = $file->getDigits();
        $this->serverName      = $file->getServerName();
        $this->serverDirectory = realpath($file->getServerDirectory());

        $this->historyFiles[$file->getTimeframe()] = $file;

        $symbolU = strtoupper($this->symbol);

        foreach (self::$instances as $instance) {
            if (!$instance->closed && strtoupper($instance->getSymbol()==$symbolU) && $instance->getServerDirectory()==$this->serverDirectory)
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
                        if ($ex->getCode() == $ex::ERR_FILESIZE_INSUFFICIENT) {
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
     * Overloaded constructor
     *
     * Erzeugt eine neue Instanz und legt alle Historydateien neu an. Vorhandene Daten werden geloescht. Mehrfachaufrufe
     * dieser Funktion fuer dasselbe Symbol desselben Servers geben jeweils eine neue Instanz zurueck, weitere existierende
     * Instanzen werden als ungueltig markiert.
     *
     * @param  RosaSymbol $symbol          - Symbol der HistorySet-Daten
     * @param  int        $format          - Speicherformat der Datenreihen:
     *                                       400: MetaTrader <= Build 509
     *                                       401: MetaTrader  > Build 509
     * @param  string     $serverDirectory - Serververzeichnis der Historydateien des Sets
     */
    protected function __construct2(RosaSymbol $symbol, $format, $serverDirectory) {
        if (!is_string($serverDirectory)) throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.gettype($serverDirectory));
        if (!is_dir($serverDirectory))    throw new InvalidArgumentException('Directory "'.$serverDirectory.'" not found');

        $this->symbol          = $symbol->getName();
        $this->digits          = $symbol->getDigits();
        $this->serverDirectory = realpath($serverDirectory);
        $this->serverName      = basename($this->serverDirectory);

        // offene Sets durchsuchen und Sets desselben Symbols schliessen
        $symbolU = strtoupper($this->symbol);

        foreach (self::$instances as $set) {
            if (strtoupper($set->getSymbol()==$symbolU) && $set->getServerDirectory()==$serverDirectory) {
                $set->close();
            }
        }

        // alle HistoryFiles initialisieren
        foreach ($this->historyFiles as $timeframe => &$file) {
            $file = new HistoryFile($this->symbol, $timeframe, $this->digits, $format, $serverDirectory);
        }; unset($file);

        // Instanz speichern
        self::$instances[] = $this;
    }


    /**
     * Destructor
     *
     * Sorgt bei Zerstoerung der Instanz dafuer, dass alle gehaltenen Resourcen freigegeben werden.
     */
    public function __destruct() {
        try {
            $this->close();
        }
        catch (\Exception $ex) {
            throw ErrorHandler::handleDestructorException($ex);
        }
    }


    /**
     * Erzeugt eine neue Instanz und legt alle Historydateien neu an. Vorhandene Daten werden geloescht. Mehrfachaufrufe
     * dieser Funktion fuer dasselbe Symbol desselben Servers geben jeweils eine neue Instanz zurueck, weitere existierende
     * Instanzen werden als ungueltig markiert.
     *
     * @param  RosaSymbol $symbol          - Symbol der HistorySet-Daten
     * @param  int        $format          - Speicherformat der Datenreihen:
     *                                       400: MetaTrader <= Build 509
     *                                       401: MetaTrader  > Build 509
     * @param  string     $serverDirectory - Serververzeichnis der Historydateien des Sets
     */
    public static function create($symbol, $format, $serverDirectory) {
        return new self($symbol, $format, $serverDirectory);
    }


    /**
     * Oeffentlicher Zugriff auf constructor 2
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
    public static function open($symbol, $serverDirectory) {
        if (!is_string($symbol))          throw new IllegalTypeException('Illegal type of parameter $symbol: '.gettype($symbol));
        if (!strlen($symbol))             throw new InvalidArgumentException('Invalid parameter $symbol: ""');
        if (!is_string($serverDirectory)) throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.gettype($serverDirectory));
        if (!is_dir($serverDirectory))    throw new InvalidArgumentException('Directory "'.$serverDirectory.'" not found');

        // existierende Instanzen durchsuchen und bei Erfolg die entsprechende Instanz zurueckgeben
        $symbolU = strtoupper($symbol);
        $serverDirectory = realpath($serverDirectory);
        foreach (self::$instances as $instance) {
            if (!$instance->closed && strtoupper($instance->getSymbol()==$symbolU) && $serverDirectory==$instance->getServerDirectory())
                return $instance;
        }

        // das erste existierende HistoryFile an den Constructor uebergeben, das Set liest die weiteren dann selbst ein
        /** @var HistorySet $set */
        $set = null;

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
     * Schliesst dieses HistorySet. Gibt alle Resourcen dieser Instanz frei. Nach dem Aufruf kann die Instanz nicht mehr verwendet werden.
     *
     * @return bool - Erfolgsstatus; FALSE, wenn die Instanz bereits geschlossen war
     */
    public function close() {
        if ($this->closed)
            return false;

        foreach ($this->historyFiles as $file) {
            $file && $file->close();
        }
        return $this->closed = true;
    }


    /**
     * Gibt das HistoryFile des angegebenen Timeframes zurueck. Existiert es nicht, wird es erzeugt.
     *
     * @param  int $timeframe
     *
     * @return HistoryFile
     */
    private function getFile($timeframe) {
        if (!isset($this->historyFiles[$timeframe])) {
            $fileName = $this->serverDirectory.'/'.$this->symbol.$timeframe.'.hst';

            $file = null;
            if (is_file($fileName)) {
                try {
                    $file = new HistoryFile($fileName);
                }
                catch (MetaTraderException $ex) {
                    if ($ex->getCode() != $ex::ERR_FILESIZE_INSUFFICIENT) throw $ex;
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
}
