<?php
namespace rosasurfer\xtrade\model\metatrader;

use rosasurfer\db\orm\DAO;
use rosasurfer\exception\InvalidArgumentException;

use const rosasurfer\PHP_TYPE_INT;


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
                ['name'=>'id'     , 'type'=>PHP_TYPE_INT   , 'primary'=>true],      // db:int
                ['name'=>'name'   , 'type'=>PHP_TYPE_STRING,                ],      // db:text
                ['name'=>'value'  , 'type'=>PHP_TYPE_STRING,                ],      // db:text
                ['name'=>'test_id', 'type'=>PHP_TYPE_INT   ,                ],      // db:int
            ],
            'relations' => [
                ['name'=>'test', 'relation'=>'many-to-one', 'type'=>Test::class, 'column'=>'test_id'],
            ],
        ]));
    }


    /**
     * Return the strategy parameters of the specified {@link Test}.
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
