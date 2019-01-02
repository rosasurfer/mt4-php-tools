<?php
namespace rosasurfer\rost\synthetic\calc;

use rosasurfer\rost\model\RosaSymbol;


/**
 * CalculatorInterface
 *
 * Interface to be implemented by instrument-specific calculators.
 */
interface CalculatorInterface {


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
     * subcomponents the instrument is made of.
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartTicks($format = 'Y-m-d H:i:s');


    /**
     * Return the consolidated start time of an instrument's M1 history. This is the latest history start time of all the
     * subcomponents the instrument is made of.
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s');


    /**
     * Calculate and return an instrument's quotes for the specified day.
     *
     * @param  int $day - FXT timestamp of the day to calculate quotes for. If the value is 0 (zero) quotes for the oldest
     *                    available day of the instrument are calculated.
     *
     * @return array[] - If history for the specified day is not available an empty array is returned. Otherwise a timeseries
     *                   array is returned with each element describing a single bar as following:
     * <pre>
     * Array [
     *     'time'   => {numeric},           // (int)    FXT timestamp of bar open time
     *     'open'   => {numeric},           // (double) open value
     *     'high'   => {numeric},           // (double) high value
     *     'low'    => {numeric},           // (double) low value
     *     'close'  => {numeric},           // (double) close value
     *     'volume' => {numeric},           // (int)    volume if available
     * ]
     * </pre>
     */
    public function calculateQuotes($day);
}
