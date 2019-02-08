<?php
namespace rosasurfer\rt\synthetic\index;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rt\synthetic\AbstractSynthesizer;
use rosasurfer\rt\synthetic\SynthesizerInterface as Synthesizer;


/**
 * NOKFXI synthesizer
 *
 * A {@link Synthesizer} for calculating the Norwegian Krone currency index. Due to the Krone's low value the index is
 * scaled-up by a factor of 10. This adjustment only effects the nominal scala, not the shape of the NOK index chart.
 *
 * <pre>
 * Formulas:
 * ---------
 * NOKFXI = 10 * USDLFX / USDNOK
 * NOKFXI = 10 * pow(USDCAD * USDCHF * USDJPY / (AUDUSD * EURUSD * GBPUSD), 1/7) / USDNOK
 * NOKFXI = 10 * pow(NOKJPY / (AUDNOK * CADNOK * CHFNOK * EURNOK * GBPNOK * USDNOK), 1/7)
 * </pre>
 */
class NOKFXI extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['USDLFX', 'USDNOK'],
        'majors'  => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDNOK'],
        'crosses' => ['AUDNOK', 'CADNOK', 'CHFNOK', 'EURNOK', 'GBPNOK', 'NOKJPY', 'USDNOK'],
    ];


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($day) {
        if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.gettype($day));

        if (!$symbols = $this->loadComponents(first($this->components)))
            return [];
        if (!$day && !($day = $this->findCommonHistoryM1Start($symbols)))   // if no day was specified find the oldest available history
            return [];
        if (!$this->symbol->isTradingDay($day))                             // skip non-trading days
            return [];
        if (!$quotes = $this->loadComponentHistory($symbols, $day))
            return [];

        // calculate quotes
        echoPre('[Info]    '.$this->symbolName.'  calculating M1 history for '.gmdate('D, d-M-Y', $day));
        $USDNOK = $quotes['USDNOK'];
        $USDLFX = $quotes['USDLFX'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPoint();
        $bars   = [];

        // NOKFXI = 10 * USDLFX / USDNOK
        foreach ($USDNOK as $i => $bar) {
            $usdnok = $USDNOK[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = 10 * $usdlfx / $usdnok;
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $usdnok = $USDNOK[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = 10 * $usdlfx / $usdnok;
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
