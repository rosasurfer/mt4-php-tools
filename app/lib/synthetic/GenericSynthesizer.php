<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib\synthetic;


/**
 * GenericSynthesizer
 *
 * A {@link ISynthesizer} to be used if no instrument-specific ISynthesizer implementation can be found.
 *
 * @phpstan-import-type  PRICE_BAR from \rosasurfer\rt\Rosatrader
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
     * @param  int $period
     * @param  int $time
     *
     * @return array[] - PRICE_BAR array with history data
     * @phpstan-return PRICE_BAR[]
     *
     * @see  \rosasurfer\rt\PRICE_BAR
     */
    public function calculateHistory(int $period, int $time): array {
        return [];
    }
}
