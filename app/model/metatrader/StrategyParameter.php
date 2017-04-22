<?php
namespace rosasurfer\xtrade\model\metatrader;

use rosasurfer\db\orm\PersistableObject;

use rosasurfer\exception\IllegalArgumentException;
use rosasurfer\exception\IllegalTypeException;


/**
 * Represents a single input parameter of a tested strategy.
 */
class StrategyParameter extends PersistableObject {


    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

    /** @var string */
    protected $value;

    /** @var int */
    protected $test_id;

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
    public static function create(Test $test, $name, $value) {
        if (!is_string($name))  throw new IllegalTypeException('Illegal type of parameter $name: '.getType($name));
        if (!strLen($name))     throw new IllegalArgumentException('Illegal parameter $name "'.$name.'" (must be non-empty)');
        if (!is_string($value)) throw new IllegalTypeException('Illegal type of parameter $value: '.getType($value));

        $param = new self();

        $param->test  = $test;
        $param->name  = $name;
        $param->value = $value;

        return $param;
    }


    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }


    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }


    /**
     * @return string
     */
    public function getValue() {
        return $this->value;
    }


    /**
     * @return int
     */
    public function getTest_id() {
        if ($this->test_id === null) {
            if (is_object($this->test))
                $this->test_id = $this->test->getId();
        }
        return $this->test_id;
    }


    /**
     * @return Test
     */
    public function getTest() {
        if ($this->test === null) {
            if ($this->test_id)
                $this->test = Test::dao()->findById($this->test_id);
        }
        return $this->test;
    }


    /**
     * Insert pre-processing hook (application-side ORM trigger).
     *
     * Assign the {@link Test} id as this is not yet automated by the ORM.
     *
     * {@inheritdoc}
     */
    protected function beforeInsert() {
        if (!$this->test_id)
            $this->test_id = $this->test->getId();
        return true;
    }
}
