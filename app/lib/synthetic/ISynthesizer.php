<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib\synthetic;

use rosasurfer\rt\lib\HistorySource;
use rosasurfer\rt\model\RosaSymbol;


/**
 * ISynthesizer
 *
 * An interface for classes processing calculations on synthetic instruments.
 *
 * A synthetic instrument is made of components and a defined relation between them (a math formula). Components and formula
 * are stored with the instrument in text form. A Synthesizer reads and evaluates a formula and calculates quotes based on it.
 * This way quotes of runtime generated synthetic instruments can be calculated.
 *
 * Performance can be considerably increased by providing an instrument-specific Synthesizer implementation. If no instrument-
 * specific implementation is found calculations are processed by a {@link GenericSynthesizer}.
 */
interface ISynthesizer extends HistorySource {


    /**
     * Constructor
     *
     * Create a new instance for the specified instrument.
     *
     * @param  RosaSymbol $symbol
     */
    public function __construct(RosaSymbol $symbol);


    /**
     * Return the consolidated start time of an instrument's tick history. This is the latest history start time of all the
     * subcomponents the instrument needs for calculation.
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartTick($format = 'Y-m-d H:i:s');


    /**
     * Return the consolidated start time of an instrument's M1 history. This is the latest history start time of all the
     * subcomponents the instrument needs for calculation.
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s');
}
