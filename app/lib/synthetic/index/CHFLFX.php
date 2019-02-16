<?php
namespace rosasurfer\rt\lib\synthetic\index;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rt\lib\synthetic\AbstractSynthesizer;
use rosasurfer\rt\lib\synthetic\SynthesizerInterface as Synthesizer;


/**
 * CHFLFX synthesizer
 *
 * A {@link Synthesizer} for calculating the LiteForex Swiss Franc index.
 *
 * <pre>
 * Formulas:
 * ---------
 * CHFLFX = UDLFX / USDCHF
 * CHFLFX = pow(CHFJPY / (AUDCHF * CADCHF * EURCHF * GBPCHF * USDCHF), 1/7)
 * </pre>
 */
class CHFLFX extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['USDCHF', 'USDLFX'],
        'crosses' => ['AUDCHF', 'CADCHF', 'CHFJPY', 'EURCHF', 'GBPCHF', 'USDCHF'],
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
        $USDCHF = $quotes['USDCHF'];
        $USDLFX = $quotes['USDLFX'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPoint();
        $bars   = [];

        // CHFLFX = UDLFX / USDCHF
        foreach ($USDCHF as $i => $bar) {
            $usdchf = $USDCHF[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = round($usdlfx / $usdchf, $digits);
            $iOpen  = (int) round($open/$point);

            $usdchf = $USDCHF[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = round($usdlfx / $usdchf, $digits);
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
