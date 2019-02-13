<?php
namespace rosasurfer\rt\synthetic;

use rosasurfer\rt\model\RosaSymbol;


/**
 * SynthesizerInterface
 *
 * An interface for classes processing calculations on synthetic instruments.
 *
 * A synthetic instrument is made of components and a defined relation between them (a math formula). Components and formula
 * are stored with the instrument in text form. A Synthesizer reads and evaluates a formula and calculates quotes based on it.
 * This way quotes of runtime generated synthetic instruments can be calculated.
 *
 * Performance can be considerably increased by providing an instrument-specific Synthesizer implementation. If an instrument
 * specific implementation is found calculations are passed on to an instance of that class. Otherwise calculations are
 * processed by a {@link DefaultSynthesizer}.
 */
interface SynthesizerInterface {


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
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryTicksStart($format = 'Y-m-d H:i:s');


    /**
     * Return the consolidated start time of an instrument's M1 history. This is the latest history start time of all the
     * subcomponents the instrument needs for calculation.
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryM1Start($format = 'Y-m-d H:i:s');


    /**
     * Calculate and return instrument quotes for the specified day.
     *
     * @param  int $day - FXT timestamp of the day to calculate quotes for. If the value is 0 (zero) quotes for the oldest
     *                    available day of the instrument are calculated.
     *
     * @return array[] - If history for the specified day is not available an empty array is returned. Otherwise a timeseries
     *                   array is returned with each element describing a single bar as follows:
     * <pre>
     * Array [
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (double),         // open value
     *     'high'  => (double),         // high value
     *     'low'   => (double),         // low value
     *     'close' => (double),         // close value
     *     'ticks' => (int),            // ticks or volume (if available)
     * ]
     * </pre>
     */
    public function calculateQuotes($day);
}
