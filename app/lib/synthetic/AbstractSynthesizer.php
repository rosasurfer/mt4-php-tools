<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib\synthetic;

use rosasurfer\ministruts\core\CObject;
use rosasurfer\ministruts\core\proxy\Output;

use rosasurfer\rt\model\RosaSymbol;

use const rosasurfer\ministruts\DAY;


/**
 * AbstractSynthesizer
 *
 * @phpstan-import-type RT_POINT_BAR from \rosasurfer\rt\phpstan\CustomTypes
 * @phpstan-import-type RT_PRICE_BAR from \rosasurfer\rt\phpstan\CustomTypes
 */
abstract class AbstractSynthesizer extends CObject implements ISynthesizer {


    /** @var RosaSymbol */
    protected RosaSymbol $symbol;

    /** @var string */
    protected string $symbolName;

    /** @var string[][] - one or more sets of component names */
    protected array $components = [];

    /** @var RosaSymbol[] - loaded symbols */
    protected array $loadedSymbols = [];

    /**
     * @var         array<array<array<string, array<array<int|float>>>>> - cached bars
     * @phpstan-var array<array<array<string, array<RT_POINT_BAR|RT_PRICE_BAR>>>>
     *
     * @see \rosasurfer\rt\phpstan\RT_POINT_BAR
     * @see \rosasurfer\rt\phpstan\RT_PRICE_BAR
     */
    private array $cache = [];


    /**
     *
     */
    public function __construct(RosaSymbol $symbol) {
        $this->symbol     = $symbol;
        $this->symbolName = $symbol->getName();
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartTick(string $format = 'Y-m-d H:i:s'): string {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartM1(string $format = 'Y-m-d H:i:s'): string {
        return '0';
    }


    /**
     * Calculate history for the specified bar period and time.
     *
     * @param  int $period - bar period identifier: PERIOD_M1 | PERIOD_M5 | PERIOD_M15 etc.
     * @param  int $time   - FXT time to return prices for. If 0 (zero) the oldest available history for the requested bar
     *                       period is returned.
     *
     * @return         array[] - price bar array or an empty array, if the requested history is not available
     * @phpstan-return RT_PRICE_BAR[]
     *
     * @see \rosasurfer\rt\phpstan\RT_PRICE_BAR
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
     * @param  RosaSymbol[] $symbols
     * @param  int          $day
     *
     * @return         array<string, array<array<int|float>>> - timeseries per symbol
     * @phpstan-return array<string, RT_PRICE_BAR[]>
     *
     * @see \rosasurfer\rt\phpstan\RT_PRICE_BAR
     */
    protected function getComponentsHistory(array $symbols, int $day): array {
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
     * @phpstan-return ($compact is true ? RT_POINT_BAR[] : RT_PRICE_BAR[])
     *
     * @see \rosasurfer\rt\phpstan\RT_POINT_BAR
     * @see \rosasurfer\rt\phpstan\RT_PRICE_BAR
     */
    public final function getHistory(int $period, int $time, bool $compact = true): array {
        $time -= $time % DAY;

        // clear cache on different bar period or time
        if (!isset($this->cache[$period][$time])) {
            unset($this->cache);
        }

        // calculate PRICE_BARs
        if (!isset($this->cache[$period][$time]['price'])) {
            $this->cache[$period][$time]['price'] = $this->calculateHistory($period, $time);
        }
        $priceBars = $this->cache[$period][$time]['price'];

        if (!$compact) {
            return $priceBars;
        }

        if (!isset($this->cache[$period][$time]['point'])) {
            // calculate RT_POINT_BARs
            $pointBars = $priceBars;
            $point   = $this->symbol->getPointValue();
            foreach ($pointBars as $i => $bar) {
                $pointBars[$i]['open' ] = (int) round($bar['open' ]/$point);
                $pointBars[$i]['high' ] = (int) round($bar['high' ]/$point);
                $pointBars[$i]['low'  ] = (int) round($bar['low'  ]/$point);
                $pointBars[$i]['close'] = (int) round($bar['close']/$point);
            }
            $this->cache[$period][$time]['point'] = $pointBars;
        }
        return $this->cache[$period][$time]['point'];
    }
}
