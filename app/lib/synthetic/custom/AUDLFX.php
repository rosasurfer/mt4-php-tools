<?php
namespace rosasurfer\rt\synthetic\custom;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rt\FXT;
use rosasurfer\rt\model\RosaSymbol;
use rosasurfer\rt\synthetic\AbstractSynthesizer;
use rosasurfer\rt\synthetic\SynthesizerInterface as Synthesizer;


/**
 * AUDLFX synthesizer
 *
 * A {@link Synthesizer} for calculating the "LiteForex Australian Dollar index".
 *
 * <pre>
 * formula(1): AUDLFX = USDLFX * AUDUSD         (preferred)
 * formula(2): AUDLFX = \sqrt[7]{\frac{AUDCAD * AUDCHF * AUDJPY * AUDUSD}{EURAUD * GBPAUD}}
 * </pre>
 */
class AUDLFX extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'usdlfx' => ['AUDUSD', 'USDLFX'],                                           // preferred calculation method
        'pairs'  => ['AUDCAD', 'AUDCHF', 'AUDJPY', 'AUDUSD', 'EURAUD', 'GBPAUD'],   // alternative calculation method
    ];


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($day) {
        if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

        $symbols = [];
        foreach ($this->components['usdlfx'] as $name) {
            /** @var RosaSymbol $pair */
            $symbol = RosaSymbol::dao()->findByName($name);
            if (!$symbol) {                                             // symbol not found
                echoPre('[Error]   '.$this->symbol->getName().'  required M1 history for '.$name.' not available');
                return [];
            }
            $symbols[$symbol->getName()] = $symbol;
        }

        // on $day == 0 start with the oldest available history of all components
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
        echoPre('skipping...');
        return [];
    }
}
