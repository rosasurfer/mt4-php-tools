<?php
namespace rosasurfer\rost\synthetic;

use rosasurfer\core\Object;
use rosasurfer\exception\InvalidArgumentException;

use rosasurfer\rost\model\RosaSymbol;
use rosasurfer\rost\synthetic\calc\CalculatorInterface as ICalculator;


/**
 * Synthesizer
 *
 * A class for processing calculations on a synthetic instrument. A synthetic instrument is made of components and a defined
 * relation between them (a math formula). Components and formula are stored with each synthetic instrument in text form.
 * The Synthesizer parses and evaluates these descriptions and calculates instrument prices based on it. This allows it to
 * calculate values of synthetic instruments on-the-fly based on arbitrary user input provided at runtime.
 *
 * Calculation can be considerably speed-up by providing an instrument-specific {@link CalculatorInterface} implementation.
 * If an instrument-specific calculator is found it replaces the textual definitions and the Synthesizer passes calculation
 * on to an instance of that class.
 */
class Synthesizer extends Object {


    /** @var RosaSymbol */
    protected $symbol;

    /** @var ICalculator */
    protected $calculator;


    /**
     * Constructor
     *
     * Create a new instance for the specified synthetic instrument.
     *
     * @param  RosaSymbol $symbol
     */
    public function __construct(RosaSymbol $symbol) {
        if (!$symbol->isSynthetic()) throw new InvalidArgumentException('Not a synthetic instrument: "'.$symbol->getName().'"');
        $this->symbol = $symbol;
    }


    /**
     * Return the consolidated M1 history start time of a synthetic instrument. This is the latest history start time of all
     * the subcomponents the instrument is made of.
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getAvailableM1HistoryStart($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * Return the consolidated tick history start time of a synthetic instrument. This is the latest history start time of
     * all the subcomponents the instrument is made of.
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getAvailableTickHistoryStart($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * Calculate and return an instrument's price values for the specified day.
     *
     * @param  int $day - FXT timestamp of the day to calculate values for
     *
     * @return array[] - timeseries array with each element describing a bar as following:
     *
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
    public function calculateValues($day) {
        return [];
    }
}
