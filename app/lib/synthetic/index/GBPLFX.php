<?php
namespace rosasurfer\rt\lib\synthetic\index;

use rosasurfer\console\io\Output;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\UnimplementedFeatureException;

use rosasurfer\rt\lib\synthetic\AbstractSynthesizer;
use rosasurfer\rt\lib\synthetic\ISynthesizer;

use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\PERIOD_M1;


/**
 * GBPLFX synthesizer
 *
 * A {@link ISynthesizer} for calculating the synthetic LiteForex Great Britain Pound index.
 *
 * <pre>
 * Formulas:
 * ---------
 * GBPLFX = USDLFX * GBPUSD
 * GBPLFX = pow(GBPAUD * GBPCAD * GBPCHF * GBPJPY * GBPUSD / EURGBP, 1/7)
 * </pre>
 */
class GBPLFX extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['GBPUSD', 'USDLFX'],
        'crosses' => ['EURGBP', 'GBPAUD', 'GBPCAD', 'GBPCHF', 'GBPJPY', 'GBPUSD'],
    ];


    /**
     * {@inheritdoc}
     */
    public function calculateHistory($period, $time) {
        if (!is_int($period))     throw new IllegalTypeException('Illegal type of parameter $period: '.gettype($period));
        if ($period != PERIOD_M1) throw new UnimplementedFeatureException(__METHOD__.'('.periodToStr($period).') not implemented');
        if (!is_int($time))       throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));

        if (!$symbols = $this->getComponents(first($this->components)))
            return [];
        if (!$time && !($time=$this->findCommonHistoryStartM1($symbols)))       // if no time was specified find the oldest available history
            return [];
        if (!$this->symbol->isTradingDay($time))                                // skip non-trading days
            return [];
        if (!$quotes = $this->getComponentsHistory($symbols, $time))
            return [];

        /** @var Output $output */
        $output = $this->di(Output::class);
        $output->out('[Info]    '.str_pad($this->symbolName, 6).'  calculating M1 history for '.gmdate('D, d-M-Y', $time));

        // calculate quotes
        $GBPUSD = $quotes['GBPUSD'];
        $USDLFX = $quotes['USDLFX'];
        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPointValue();
        $bars   = [];

        // GBPLFX = USDLFX * GBPUSD
        foreach ($GBPUSD as $i => $bar) {
            $gbpusd = $GBPUSD[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = round($usdlfx * $gbpusd, $digits);
            $iOpen  = (int) round($open/$point);

            $gbpusd = $GBPUSD[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = round($usdlfx * $gbpusd, $digits);
            $iClose = (int) round($close/$point);

            $bars[$i]['time' ] = $bar['time'];
            $bars[$i]['open' ] = $open;
            $bars[$i]['high' ] = $iOpen > $iClose ? $open : $close;         // no min()/max() for performance
            $bars[$i]['low'  ] = $iOpen < $iClose ? $open : $close;
            $bars[$i]['close'] = $close;
            $bars[$i]['ticks'] = $iOpen==$iClose ? 1 : (abs($iOpen-$iClose) << 1);
        }
        return $bars;
    }
}
