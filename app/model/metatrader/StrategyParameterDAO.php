<?php
namespace rosasurfer\xtrade\model\metatrader;

use rosasurfer\db\orm\DAO;
use rosasurfer\exception\InvalidArgumentException;

use const rosasurfer\db\orm\meta\INT;
use const rosasurfer\db\orm\meta\STRING;


/**
 * DAO for accessing {@link StrategyParameter} instances.
 */
class StrategyParameterDAO extends DAO {


    /**
     * {@inheritdoc}
     */
    public function getMapping() {
        static $mapping; return $mapping ?: ($mapping=$this->parseMapping([
            'class'      => StrategyParameter::class,
            'table'      => 't_strategyparameter',
            'connection' => 'sqlite',
            'properties' => [
                ['name'=>'id'   , 'type'=>INT   , 'primary'=>true],                                 // db:int
                ['name'=>'name' , 'type'=>STRING,                ],                                 // db:text
                ['name'=>'value', 'type'=>STRING,                ],                                 // db:text
            ],
            'relations' => [
                ['name'=>'test', 'assoc'=>'many-to-one', 'type'=>Test::class, 'column'=>'test_id'], // db:int
            ],
        ]));
    }


    /**
     * Return the {@link StrategyParameter}s of the specified {@link Test}.
     *
     * @param  Test $test
     *
     * @return StrategyParameter[]
     */
    public function findAllByTest(Test $test) {
        if (!$test->isPersistent()) throw new InvalidArgumentException('Cannot process non-persistent '.get_class($test));

        $id = $test->getId();

        $sql = 'select *
                   from :StrategyParameter
                   where test_id = '.$id.'
                   order by id';
        return $this->findAll($sql);
    }
}
