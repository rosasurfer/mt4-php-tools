<?php
namespace rosasurfer\rt\lib\synthetic;

use rosasurfer\rt\lib\synthetic\ISynthesizer;


/**
 * GenericSynthesizer
 *
 * A {@link ISynthesizer} to be used if no instrument-specific Synthesizer can be found.
 */
class GenericSynthesizer extends AbstractSynthesizer {


    /**
     *
     */
    public function getHistoryStartTick($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     *
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     *
     */
    public function calculateHistory($period, $time, $optimized = false) {
        return [];
    }
}
