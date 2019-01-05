<?php
namespace rosasurfer\rost\synthetic;

use rosasurfer\core\Object;
use rosasurfer\exception\InvalidArgumentException;

use rosasurfer\rost\model\RosaSymbol;
use rosasurfer\rost\synthetic\calc\Calculator;
use rosasurfer\rost\synthetic\calc\CalculatorInterface as ICalculator;
use rosasurfer\rost\synthetic\calc\CalculatorInterface;


/**
 * Synthesizer
 *
 * A class for processing calculations on synthetic instruments. A synthetic instrument is made of components and a defined
 * relation between them (a math formula). Components and formula are stored with each synthetic instrument in text form.
 * The Synthesizer parses and evaluates these descriptions and calculates instrument quotes based on it. This makes it
 * possible to calculate quotes of on-the-fly generated synthetic instruments based on user input provided at runtime.
 *
 * Calculation can be considerably speed-up by providing an instrument-specific {@link CalculatorInterface} implementation.
 * If such an instrument-specific calculator is found it replaces the textual definitions and the Synthesizer passes
 * calculation on to an instance of that class.
 */
class Synthesizer extends Object implements ICalculator {


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

        $class = dirName(Calculator::class).'\\'.$symbol->getName();

        if (is_class($class)) $this->calculator = new $class($symbol);
        else                  $this->calculator = new Calculator($symbol);
        echoPre(__METHOD__.'()  $class="'.$class.'"  get_class($this->calculator): '.get_class($this->calculator));
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryTicksStart($format = 'Y-m-d H:i:s') {
        return $this->calculator->getHistoryTicksStart($format);
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryM1Start($format = 'Y-m-d H:i:s') {
        return $this->calculator->getHistoryM1Start($format);
    }


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($fxDay) {
        return $this->calculator->calculateQuotes($fxDay);
    }
}
