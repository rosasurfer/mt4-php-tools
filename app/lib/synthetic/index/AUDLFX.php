<?php
namespace rosasurfer\rt\lib\synthetic\index;

use rosasurfer\console\io\Output;
use rosasurfer\core\assert\Assert;
use rosasurfer\core\exception\UnimplementedFeatureException;

use rosasurfer\rt\lib\synthetic\AbstractSynthesizer;
use rosasurfer\rt\lib\synthetic\ISynthesizer;

use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\PERIOD_M1;


/**
 * AUDLFX synthesizer
 *
 * A {@link ISynthesizer} for calculating the synthetic LiteForex Australian Dollar index.
 *
 * <pre>
 * Formulas:
 * ---------
 * AUDLFX = USDLFX * AUDUSD
 * AUDLFX = pow(AUDCAD * AUDCHF * AUDJPY * AUDUSD / (EURAUD * GBPAUD), 1/7)
 * </pre>
 */
class AUDLFX extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['AUDUSD', 'USDLFX'],
        'crosses' => ['AUDCAD', 'AUDCHF', 'AUDJPY', 'AUDUSD', 'EURAUD', 'GBPAUD'],
    ];


    /**
     * {@inheritdoc}
     */
    public function calculateHistory($period, $time) {
        Assert::int($period, '$period');
        if ($period != PERIOD_M1) throw new UnimplementedFeatureException(__METHOD__.'('.periodToStr($period).') not implemented');
        Assert::int($time, '$time');

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
        $AUDUSD = $quotes['AUDUSD'];
        $USDLFX = $quotes['USDLFX'];
        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPointValue();
        $bars   = [];

        // AUDLFX = USDLFX * AUDUSD
        foreach ($AUDUSD as $i => $bar) {
            $audusd = $AUDUSD[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = round($usdlfx * $audusd, $digits);
            $iOpen  = (int) round($open/$point);

            $audusd = $AUDUSD[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = round($usdlfx * $audusd, $digits);
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
