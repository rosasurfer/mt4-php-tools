<?php
namespace rosasurfer\rost\synthetic;

use rosasurfer\core\Object;
use rosasurfer\rost\model\RosaSymbol;


/**
 * AbstractSynthesizer
 */
abstract class AbstractSynthesizer extends Object implements SynthesizerInterface {


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
}
