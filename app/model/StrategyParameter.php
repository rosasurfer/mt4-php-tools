<?php
declare(strict_types=1);

namespace rosasurfer\rt\model;

use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\core\exception\InvalidValueException;


/**
 * Represents a single input parameter of a tested strategy.
 *
 * @method        string                                    getName()  Return the name of the input parameter.
 * @method        string                                    getValue() Return the value of the input parameter.
 * @method        \rosasurfer\rt\model\Test                 getTest()  Return the test this input parameter belongs to.
 * @method static \rosasurfer\rt\model\StrategyParameterDAO dao()      Return the {@link StrategyParameterDAO} for the calling class.
 */
class StrategyParameter extends RosatraderModel {


    /** @var string */
    protected $name;

    /** @var string */
    protected $value;

    /** @var Test [transient] */
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
    public static function create(Test $test, $name, $value) {
        Assert::string($name, '$name');
        if (!strlen($name)) throw new InvalidValueException('Illegal parameter $name "'.$name.'" (must be non-empty)');
        Assert::string($value, '$value');

        $param = new self();

        $param->test  = $test;
        $param->name  = $name;
        $param->value = $value;

        return $param;
    }
}
