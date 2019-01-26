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
 * formula(1): AUDLFX = \sqrt[7]{\frac{AUDCAD * AUDCHF * AUDJPY * AUDUSD}{EURAUD * GBPAUD}}
 * formula(2): AUDLFX = USDLFX * AUDUSD
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

        // check/calculate via USDLFX
        // check/calculate via separate pairs



        $pairs = [];
        foreach ($this->components['pairs'] as $name) {
            /** @var RosaSymbol $pair */
            $pair = RosaSymbol::dao()->getByName($name);
            $pairs[$pair->getName()] = $pair;
        }

        // on $day == 0 start with the oldest available history of all components
        if (!$day) {
            /** @var RosaSymbol $pair */
            foreach ($pairs as $pair) {
                $historyStart = (int) $pair->getHistoryM1Start('U');    // 00:00 FXT of the first stored day
                if (!$historyStart) {
                    echoPre('[Error]   '.$this->symbol->getName().'  required M1 history for '.$pair->getName().' not available');
                    return [];                                          // no history stored
                }
                $day = max($day, $historyStart);
            }
            echoPre('[Info]    '.$this->symbol->getName().'  common M1 history starts at '.gmDate('D, d-M-Y', $day));
        }
        if (!$this->symbol->isTradingDay($day))                         // skip non-trading days
            return [];

        // load history for the specified day
        $quotes = [];
        foreach ($pairs as $name => $pair) {
            $quotes[$name] = $pair->getHistoryM1($day);
        }

        // calculate quotes
        echoPre('[Info]    '.$this->symbol->getName().'  calculating M1 quotes for '.gmDate('D, d-M-Y', $day));

        return [];
    }
}
