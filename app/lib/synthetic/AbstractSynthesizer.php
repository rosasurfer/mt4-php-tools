<?php
namespace rosasurfer\rt\lib\synthetic;

use rosasurfer\core\Object;
use rosasurfer\rt\model\RosaSymbol;


/**
 * AbstractSynthesizer
 */
abstract class AbstractSynthesizer extends Object implements SynthesizerInterface {


    /** @var RosaSymbol */
    protected $symbol;

    /** @var string */
    protected $symbolName;

    /** @var string[][] - one or more sets of component names */
    protected $components = [];

    /** @var RosaSymbol[] - loaded symbols */
    protected $loadedSymbols = [];


    /**
     * {@inheritdoc}
     */
    public function __construct(RosaSymbol $symbol) {
        $this->symbol     = $symbol;
        $this->symbolName = $symbol->getName();
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartTick($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * Load the components required to calculate the synthetic instrument.
     *
     * @param string[] $names
     *
     * @return RosaSymbol[] - loaded symbols or an empty value if one of the symbols was not found
     */
    protected function loadComponents(array $names) {
        $symbols = [];
        foreach ($names as $name) {
            if (isset($this->loadedSymbols[$name])) {
                $symbol = $this->loadedSymbols[$name];
            }
            else if (!$symbol = RosaSymbol::dao()->findByName($name)) {
                echoPre('[Error]   '.str_pad($this->symbolName, 6).'  required M1 history for '.$name.' not available');
                return [];
            }
            $symbols[] = $symbol;
        }
        return $symbols;
    }


    /**
     * Look-up the oldest available common history for all specified symbols.
     *
     * @param RosaSymbol[] $symbols
     *
     * @return int - history start time for all symbols (FXT) or 0 (zero) if no common history is available
     */
    protected function findCommonHistoryStartM1(array $symbols) {
        $day = 0;
        foreach ($symbols as $symbol) {
            $historyStart = (int) $symbol->getHistoryStartM1('U');      // 00:00 FXT of the first stored day
            if (!$historyStart) {
                echoPre('[Error]   '.str_pad($this->symbolName, 6).'  required M1 history for '.$symbol->getName().' not available');
                return 0;                                               // no history stored
            }
            $day = max($day, $historyStart);
        }
        echoPre('[Info]    '.str_pad($this->symbolName, 6).'  available M1 history for all sources starts at '.gmdate('D, d-M-Y', $day));
        return $day;
    }


    /**
     * Load the history of all components for the specified day.
     *
     * @param RosaSymbol[] $symbols
     * @param int          $day
     *
     * @return array[] - associative array of timeseries with the symbol name as key
     */
    protected function loadComponentHistory($symbols, $day) {
        $quotes = [];
        foreach ($symbols as $symbol) {
            $name = $symbol->getName();
            if (!$quotes[$name] = $symbol->getHistoryM1($day)) {
                echoPre('[Error]   '.str_pad($this->symbolName, 6).'  required '.$name.' history for '.gmdate('D, d-M-Y', $day).' not available');
                return [];
            }
        }
        return $quotes;
    }
}
