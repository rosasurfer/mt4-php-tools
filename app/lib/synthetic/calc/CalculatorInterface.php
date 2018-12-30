<?php
namespace rosasurfer\rost\synthetic\calc;


/**
 * CalculatorInterface
 *
 * Interface to be implemented by instrument-specific calculators.
 */
interface CalculatorInterface {


    /**
     * Return the consolidated start time of a synthetic instrument's tick history. This is the latest history start time of
     * all the subcomponents the instrument is made of.
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartTicks($format = 'Y-m-d H:i:s');


    /**
     * Return the consolidated start time of a synthetic instrument's M1 history. This is the latest history start time of
     * all the subcomponents the instrument is made of.
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s');


    /**
     * Calculate and return a synthetic instrument's quotes for the specified day.
     *
     * @param  int $day - FXT timestamp of the day to calculate quotes for. If the value is 0 (zero) the quotes for the
     *                    oldest available day of the instrument are calculated.
     *
     * @return array[] - If no history is available for the specified day an empty array is returned. Otherwise a timeseries
     *                   array is returned with each element describing a bar as following:
     * <pre>
     * Array [
     *     'time'   => {numeric-value},         // (int)    FXT timestamp of bar open time
     *     'open'   => {numeric-value},         // (double) open value
     *     'high'   => {numeric-value},         // (double) high value
     *     'low'    => {numeric-value},         // (double) low value
     *     'close'  => {numeric-value},         // (double) close value
     *     'volume' => {numeric-value},         // (int)    volume if available
     * ]
     * </pre>
     */
    public function calculateQuotes($day);
}
