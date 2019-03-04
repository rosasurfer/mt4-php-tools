<?php
namespace rosasurfer\rt\lib\synthetic\index;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\UnimplementedFeatureException;

use rosasurfer\rt\lib\synthetic\AbstractSynthesizer;
use rosasurfer\rt\lib\synthetic\SynthesizerInterface as Synthesizer;

use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\PERIOD_M1;


/**
 * GBPFXI synthesizer
 *
 * A {@link Synthesizer} for calculating the Great Britain Pound currency index.
 *
 * <pre>
 * Formulas:
 * ---------
 * GBPFXI = pow(USDLFX * GBPUSD, 7/6)
 * GBPFXI = pow(USDCAD * USDCHF * USDJPY / ($AUDUSD * EURUSD), 1/6) * GBPUSD
 * GBPFXI = pow(GBPAUD * GBPCAD * GBPCHF * GBPJPY * GBPUSD / EURGBP, 1/6)
 * </pre>
 */
class GBPFXI extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['GBPUSD', 'USDLFX'],
        'majors'  => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'crosses' => ['EURGBP', 'GBPAUD', 'GBPCAD', 'GBPCHF', 'GBPJPY', 'GBPUSD'],
    ];


    /**
     * {@inheritdoc}
     */
    public function getHistory($timeframe, $time) {
        if (!is_int($timeframe))     throw new IllegalTypeException('Illegal type of parameter $timeframe: '.gettype($timeframe));
        if (!is_int($time))          throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));
        if ($timeframe != PERIOD_M1) throw new UnimplementedFeatureException(__METHOD__.'('.periodToStr($timeframe).') not implemented');

        if (!$symbols = $this->loadComponents(first($this->components)))
            return [];
        if (!$time && !($time = $this->findCommonHistoryStartM1($symbols)))     // if no time was specified find the oldest available history
            return [];
        if (!$this->symbol->isTradingDay($time))                                // skip non-trading days
            return [];
        if (!$quotes = $this->loadComponentHistory($symbols, $time))
            return [];

        // calculate quotes
        echoPre('[Info]    '.str_pad($this->symbolName, 6).'  calculating M1 history for '.gmdate('D, d-M-Y', $time));
    $GBPUSD = $quotes['GBPUSD'];
        $USDLFX = $quotes['USDLFX'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPoint();
        $bars   = [];

        // GBPFXI = pow(USDLFX * GBPUSD, 7/6)
        foreach ($GBPUSD as $i => $bar) {
            $gbpusd = $GBPUSD[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = pow($usdlfx * $gbpusd, 7/6);
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $gbpusd = $GBPUSD[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = pow($usdlfx * $gbpusd, 7/6);
            $close  = round($close, $digits);
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
