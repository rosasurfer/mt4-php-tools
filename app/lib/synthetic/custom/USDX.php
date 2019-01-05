<?php
namespace rosasurfer\rost\synthetic\custom;

use rosasurfer\rost\synthetic\AbstractSynthesizer;
use rosasurfer\rost\synthetic\SynthesizerInterface as Synthesizer;


/**
 * USDX synthesizer
 *
 * A {@link Synthesizer} for calculating the "ICE US Dollar Futures index".
 */
class USDX extends AbstractSynthesizer {


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
