<?php
declare(strict_types=1);

namespace rosasurfer\rt\model;

use rosasurfer\ministruts\core\exception\InvalidValueException;


/**
 * Represents a single input parameter of a tested strategy.
 *
 * @method        string               getName()  Return the name of the input parameter.
 * @method        string               getValue() Return the value of the input parameter.
 * @method        Test                 getTest()  Return the test this input parameter belongs to.
 * @method static StrategyParameterDAO dao()      Return the {@link StrategyParameterDAO} for the calling class.
 */
class StrategyParameter extends RosatraderModel {


    /** @var string */
    protected $name;

    /** @var string */
    protected $value;

    /** @var Test */
    protected $test;


    /**
     * Create a new parameter instance.
     *
     * @param  Test   $test
     * @param  string $name
     * @param  string $value
     *
     * @return self
     */
    public static function create(Test $test, string $name, string $value): self {
        if (!strlen($name)) throw new InvalidValueException('Illegal parameter $name "'.$name.'" (must be non-empty)');

        $param = new self();

        $param->test  = $test;
        $param->name  = $name;
        $param->value = $value;

        return $param;
    }
}
