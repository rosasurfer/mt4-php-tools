<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib\synthetic;


/**
 * GenericSynthesizer
 *
 * A {@link ISynthesizer} to be used if no instrument-specific Synthesizer can be found.
 */
class GenericSynthesizer extends AbstractSynthesizer {


    /**
     * {@inheritdoc}
     *
     * @param  string $format [optional]
     *
     * @return string
     */
    public function getHistoryStartTick($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     *
     * @param  string $format [optional]
     *
     * @return string
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     *
     * @param  int  $period
     * @param  int  $time
     * @param  bool $optimized [optional]
     *
     * @return array
     */
    public function calculateHistory($period, $time, $optimized = false) {
        return [];
    }
}
