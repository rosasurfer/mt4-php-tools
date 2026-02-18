<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib\synthetic;


/**
 * GenericSynthesizer
 *
 * A {@link ISynthesizer} to be used if no instrument-specific ISynthesizer implementation can be found.
 *
 * @phpstan-import-type RT_PRICE_BAR from \rosasurfer\rt\phpstan\CustomTypes
 */
class GenericSynthesizer extends Synthesizer {


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartTick(string $format = 'Y-m-d H:i:s'): string {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartM1(string $format = 'Y-m-d H:i:s'): string {
        return '0';
    }


    /**
     * {@inheritdoc}
     *
     * @phpstan-return RT_PRICE_BAR[]
     *
     * @see \rosasurfer\rt\phpstan\RT_PRICE_BAR
     */
    public function calculateHistory(int $period, int $time): array {
        return [];
    }
}
