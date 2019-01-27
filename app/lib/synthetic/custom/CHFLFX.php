<?php
namespace rosasurfer\rt\synthetic\custom;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rt\FXT;
use rosasurfer\rt\model\RosaSymbol;
use rosasurfer\rt\synthetic\AbstractSynthesizer;
use rosasurfer\rt\synthetic\SynthesizerInterface as Synthesizer;


/**
 * CHFLFX synthesizer
 *
 * A {@link Synthesizer} for calculating the "LiteForex Swiss Franc index" (a scaled-down FX6 index).
 *
 * <pre>
 * Formulas:
 * ---------
 * CHFLFX = UDLFX / USDCHF
 * CHFLFX = \sqrt[7]{\frac{CHFJPY}{AUDCHF * CADCHF * EURCHF * GBPCHF * USDCHF}}
 * </pre>
 */
class CHFLFX extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast' => ['USDCHF', 'USDLFX'],
        'slow' => ['AUDCHF', 'CADCHF', 'CHFJPY', 'EURCHF', 'GBPCHF', 'USDCHF'],
    ];


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($day) {
        if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

        $symbols = [];
        foreach (first($this->components) as $name) {
            /** @var RosaSymbol $symbol */
            $symbol = RosaSymbol::dao()->findByName($name);
            if (!$symbol) {                                             // symbol not found
                echoPre('[Error]   '.$this->symbol->getName().'  required M1 history for '.$name.' not available');
                return [];
            }
            $symbols[$symbol->getName()] = $symbol;
        }

        // without a day look-up the oldest available history of all components
        if (!$day) {
            /** @var RosaSymbol $symbol */
            foreach ($symbols as $symbol) {
                $historyStart = (int) $symbol->getHistoryM1Start('U');  // 00:00 FXT of the first stored day
                if (!$historyStart) {
                    echoPre('[Error]   '.$this->symbol->getName().'  required M1 history for '.$symbol->getName().' not available');
                    return [];                                          // no history stored
                }
                $day = max($day, $historyStart);
            }
            echoPre('[Info]    '.$this->symbol->getName().'  available M1 history for all sources starts at '.gmDate('D, d-M-Y', $day));
        }
        if (!$this->symbol->isTradingDay($day))                         // skip non-trading days
            return [];

        // load history for the specified day
        $quotes = [];
        foreach ($symbols as $name => $symbol) {
            if (!$quotes[$name] = $symbol->getHistoryM1($day)) {
                echoPre('[Error]   '.$this->symbol->getName().'  required '.$name.' history for '.gmDate('D, d-M-Y', $day).' not available');
                return [];
            }
        }

        // calculate quotes
        echoPre('[Info]    '.$this->symbol->getName().'  calculating M1 history for '.gmDate('D, d-M-Y', $day));
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
            $bars[$i]['high' ] = $iOpen > $iClose ? $open : $close;     // no min()/max(): This is a massive loop and
            $bars[$i]['low'  ] = $iOpen < $iClose ? $open : $close;     // every function call slows it down.
            $bars[$i]['close'] = $close;
            $bars[$i]['ticks'] = $iOpen==$iClose ? 1 : (abs($iOpen-$iClose) << 1);
        }
        return $bars;
    }
}
