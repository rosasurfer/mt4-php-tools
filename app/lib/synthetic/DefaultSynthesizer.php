<?php
namespace rosasurfer\rt\lib\synthetic;

use rosasurfer\rt\lib\synthetic\SynthesizerInterface as Synthesizer;


/**
 * DefaultSynthesizer
 *
 * A {@link Synthesizer} to be used if no instrument-specific Synthesizer can be found.
 */
class DefaultSynthesizer extends AbstractSynthesizer {


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartTick($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($day) {
        return [];
    }
}
