<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib\synthetic;

use rosasurfer\ministruts\core\CObject;
use rosasurfer\ministruts\core\di\proxy\Output;

use rosasurfer\rt\model\RosaSymbol;

use const rosasurfer\ministruts\DAY;


/**
 * AbstractSynthesizer
 *
 * @phpstan-import-type  POINT_BAR from \rosasurfer\rt\Rosatrader
 * @phpstan-import-type  PRICE_BAR from \rosasurfer\rt\Rosatrader
 */
abstract class AbstractSynthesizer extends CObject implements ISynthesizer {


    /** @var RosaSymbol */
    protected $symbol;

    /** @var string */
    protected $symbolName;

    /** @var string[][] - one or more sets of component names */
    protected $components = [];

    /** @var RosaSymbol[] - loaded symbols */
    protected $loadedSymbols = [];

    /** @var array[] - cached bars */
    private $cache = [];


    /**
     *
     */
    public function __construct(RosaSymbol $symbol) {
        $this->symbol     = $symbol;
        $this->symbolName = $symbol->getName();
    }


    /**
     * {@inheritdoc}
     *
     * @param  string $format [optional]
     *
     * @return string
     */
    public function getHistoryStartTick($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     *
     * @param  string $format [optional]
     *
     * @return string
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * Calculate history for the specified bar period and time.
     *
     * @param  int $period - bar period identifier: PERIOD_M1 | PERIOD_M5 | PERIOD_M15 etc.
     * @param  int $time   - FXT time to return prices for. If 0 (zero) the oldest available history for the requested bar
     *                       period is returned.
     *
     * @return array[] - PRICE_BAR array or an empty array, if the requested history is not available
     * @phpstan-return PRICE_BAR[]
     *
     * @see \rosasurfer\rt\PRICE_BAR
     */
    abstract public function calculateHistory(int $period, int $time): array;


    /**
     * Get the components required to calculate the synthetic instrument.
     *
     * @param string[] $names
     *
     * @return RosaSymbol[] - array of symbols or an empty value if at least one of the symbols was not found
     */
    protected function getComponents(array $names) {
        $symbols = [];
        foreach ($names as $name) {
            if (isset($this->loadedSymbols[$name])) {
                $symbol = $this->loadedSymbols[$name];
            }
            else if (!$symbol = RosaSymbol::dao()->findByName($name)) {
                Output::error('[Error]   '.str_pad($this->symbolName, 6).'  required symbol '.$name.' not available');
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
                Output::error('[Error]   '.str_pad($this->symbolName, 6).'  required M1 history for '.$symbol->getName().' not available');
                return 0;                                               // no history stored
            }
            $day = max($day, $historyStart);
        }
        Output::out('[Info]    '.str_pad($this->symbolName, 6).'  available M1 history for all components starts at '.gmdate('D, d-M-Y', $day));

        return $day;
    }


    /**
     * Get the history of all components for the specified day.
     *
     * @param RosaSymbol[] $symbols
     * @param int          $day
     *
     * @return array[] - array of history timeseries per symbol:
     *
     * <pre>
     * Array(
     *     {symbol-name} => {timeseries},
     *     {symbol-name} => {timeseries},
     *     ...
     * )
     * </pre>
     */
    protected function getComponentsHistory($symbols, $day) {
        $quotes = [];
        foreach ($symbols as $symbol) {
            $name = $symbol->getName();
            if (!$quotes[$name] = $symbol->getHistoryM1($day, false)) {
                Output::error('[Error]   '.str_pad($this->symbolName, 6).'  required '.$name.' history for '.gmdate('D, d-M-Y', $day).' not available');
                return [];
            }
        }
        return $quotes;
    }


    /**
     * {@inheritdoc}
     *
     * @param  int  $period
     * @param  int  $time
     * @param  bool $compact [optional]
     *
     * @return array[]
     * @phpstan-return ($compact is true ? POINT_BAR[] : PRICE_BAR[])
     *
     * @see \rosasurfer\rt\POINT_BAR
     * @see \rosasurfer\rt\PRICE_BAR
     */
    public final function getHistory(int $period, int $time, bool $compact = true): array {
        $time -= $time % DAY;

        // clear cache on different bar period or time
        if (!isset($this->cache[$period][$time])) {
            unset($this->cache);
        }

        // calculate real prices
        if (!isset($this->cache[$period][$time]['real'])) {
            $this->cache[$period][$time]['real'] = $this->calculateHistory($period, $time);
        }
        $realBars = $this->cache[$period][$time]['real'];

        if (!$compact) {
            return $realBars;
        }

        if (!isset($this->cache[$period][$time]['optimized'])) {
            // calculate optimized prices
            $optBars = $realBars;
            $point   = $this->symbol->getPointValue();
            foreach ($optBars as $i => $bar) {
                $optBars[$i]['open' ] = (int) round($bar['open' ]/$point);
                $optBars[$i]['high' ] = (int) round($bar['high' ]/$point);
                $optBars[$i]['low'  ] = (int) round($bar['low'  ]/$point);
                $optBars[$i]['close'] = (int) round($bar['close']/$point);
            }
            $this->cache[$period][$time]['optimized'] = $optBars;
        }
        return $this->cache[$period][$time]['optimized'];
    }
}
