<?php
namespace rosasurfer\rost\synthetic\custom;

use rosasurfer\rost\synthetic\AbstractSynthesizer;
use rosasurfer\rost\synthetic\SynthesizerInterface as Synthesizer;


/**
 * EURX synthesizer
 *
 * A {@link Synthesizer} for calculating the "ICE Euro Futures index".
 */
class EURX extends AbstractSynthesizer {


    /**
     * {@inheritdoc}
     */
    public function getHistoryTicksStart($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryM1Start($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($day) {
        return [];
    }
}
