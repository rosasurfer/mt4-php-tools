<?php
namespace rosasurfer\rt\synthetic;

use rosasurfer\core\Object;
use rosasurfer\rt\model\RosaSymbol;


/**
 * AbstractSynthesizer
 */
abstract class AbstractSynthesizer extends Object implements SynthesizerInterface {


    /** @var RosaSymbol */
    protected $symbol;

    /** @var string[][] - one or more sets of components */
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
    public function getHistoryTicksStart($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryM1Start($format = 'Y-m-d H:i:s') {
        return '0';
    }
}
