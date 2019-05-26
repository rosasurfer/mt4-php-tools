<?php
namespace rosasurfer\rt\lib\synthetic;

use rosasurfer\console\io\Output;
use rosasurfer\core\CObject;

use rosasurfer\rt\model\RosaSymbol;


/**
 * AbstractSynthesizer
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
     * Calculate history for the specified bar period and time.
     *
     * @param  int $period - bar period identifier: PERIOD_M1 | PERIOD_M5 | PERIOD_M15 etc.
     * @param  int $time   - FXT time to return prices for. If 0 (zero) the oldest available history for the requested bar
     *                       period is returned.
     *
     * @return array - An empty array if history for the specified bar period and time is not available. Otherwise a
     *                 timeseries array with each element describing a single price bar as follows:
     * <pre>
     * Array(
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (float),          // open value in real terms
     *     'high'  => (float),          // high value in real terms
     *     'low'   => (float),          // low value in real terms
     *     'close' => (float),          // close value in real terms
     *     'ticks' => (int),            // volume (if available) or number of synthetic ticks
     * )
     * </pre>
     */
    abstract public function calculateHistory($period, $time);


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
                /** @var Output $output */
                $output = $this->di(Output::class);
                $output->error('[Error]   '.str_pad($this->symbolName, 6).'  required symbol '.$name.' not available');
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
        /** @var Output $output */
        $output = $this->di(Output::class);

        $day = 0;
        foreach ($symbols as $symbol) {
            $historyStart = (int) $symbol->getHistoryStartM1('U');      // 00:00 FXT of the first stored day
            if (!$historyStart) {
                $output->error('[Error]   '.str_pad($this->symbolName, 6).'  required M1 history for '.$symbol->getName().' not available');
                return 0;                                               // no history stored
            }
            $day = max($day, $historyStart);
        }
        $output->out('[Info]    '.str_pad($this->symbolName, 6).'  available M1 history for all components starts at '.gmdate('D, d-M-Y', $day));

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
            if (!$quotes[$name] = $symbol->getHistoryM1($day)) {
                /** @var Output $output */
                $output = $this->di(Output::class);
                $output->error('[Error]   '.str_pad($this->symbolName, 6).'  required '.$name.' history for '.gmdate('D, d-M-Y', $day).' not available');
                return [];
            }
        }
        return $quotes;
    }


    /**
     * {@inheritdoc}
     */
    public final function getHistory($period, $time, $optimized = false) {
        $time -= $time%DAY;

        // clear cache on different bar period or time
        if (!isset($this->cache[$period][$time]))
            unset($this->cache);

        // calculate real prices
        if (!isset($this->cache[$period][$time]['real']))
            $this->cache[$period][$time]['real'] = $this->calculateHistory($period, $time);
        $realBars = $this->cache[$period][$time]['real'];

        if (!$optimized)
            return $realBars;

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
