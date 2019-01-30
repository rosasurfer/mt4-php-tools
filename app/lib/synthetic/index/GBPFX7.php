<?php
namespace rosasurfer\rt\synthetic\index;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rt\synthetic\AbstractSynthesizer;
use rosasurfer\rt\synthetic\SynthesizerInterface as Synthesizer;


/**
 * GBPFX7 synthesizer
 *
 * A {@link Synthesizer} for calculating the Great Britain Pound FX7 index.
 *
 * <pre>
 * Formulas:
 * ---------
 * GBPFX7 = USDFX7 * pow(GBPUSD, 8/7)
 * GBPFX7 = pow(USDCAD * USDCHF * USDJPY / (AUDUSD * EURUSD * NZDUSD), 1/7) * GBPUSD
 * GBPFX7 = pow(GBPAUD * GBPCAD * GBPCHF * GBPJPY * GBPNZD * GBPUSD / EURGBP, 1/7)
 * </pre>
 */
class GBPFX7 extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['GBPUSD', 'USDFX7'],
        'majors'  => ['AUDUSD', 'EURUSD', 'GBPUSD', 'NZDUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'crosses' => ['EURGBP', 'GBPAUD', 'GBPCAD', 'GBPCHF', 'GBPJPY', 'GBPNZD', 'GBPUSD'],
    ];


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($day) {
        if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

        if (!$symbols = $this->loadComponents(first($this->components)))
            return [];
        if (!$day && !($day = $this->findCommonHistoryM1Start($symbols)))   // if no day was specified find the oldest available history
            return [];
        if (!$this->symbol->isTradingDay($day))                             // skip non-trading days
            return [];
        if (!$quotes = $this->loadComponentHistory($symbols, $day))
            return [];

        // calculate quotes
        echoPre('[Info]    '.$this->symbolName.'  calculating M1 history for '.gmDate('D, d-M-Y', $day));
        $GBPUSD = $quotes['GBPUSD'];
        $USDFX7 = $quotes['USDFX7'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPoint();
        $bars   = [];

        // GBPFX7 = USDFX7 * pow(GBPUSD, 8/7)
        foreach ($GBPUSD as $i => $bar) {
            $gbpusd = $GBPUSD[$i]['open'];
            $usdfx7 = $USDFX7[$i]['open'];
            $open   = $usdfx7 * pow($gbpusd, 8/7);
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $gbpusd = $GBPUSD[$i]['close'];
            $usdfx7 = $USDFX7[$i]['close'];
            $close  = $usdfx7 * pow($gbpusd, 8/7);
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
