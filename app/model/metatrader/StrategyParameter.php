<?php
namespace rosasurfer\trade\model\metatrader;

use rosasurfer\db\orm\PersistableObject;


/**
 * Represents a single strategy parameter of a {@link Test}.
 */
class StrategyParameter extends PersistableObject {


    /** @var int - primary key */
    protected $id;

    /** @var string */
    protected $name;

    /** @var string */
    protected $value;

    /** @var int - the test's id */
    protected $test_id;

    /** @var Test - the test instance the parameter belongs to */
    protected $test;
}
