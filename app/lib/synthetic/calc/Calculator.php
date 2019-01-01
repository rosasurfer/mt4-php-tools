<?php
namespace rosasurfer\rost\synthetic\calc;

use rosasurfer\core\Object;
use rosasurfer\rost\model\RosaSymbol;


/**
 * Calculator
 */
class Calculator extends Object implements CalculatorInterface {


    /** @var RosaSymbol */
    protected $symbol;

    /** @var string[] */
    protected $components = [];


    /**
     * {@inheritdoc}
     */
    public function __construct(RosaSymbol $symbol) {
        $this->symbol = $symbol;
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartTicks($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($day) {
        return [];
    }
}
