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
     * @var array - database mapping
     */
    protected $mapping = [
        'connection' => 'sqlite',
        'table'      => 't_strategyparameter',
        'columns'    => [
            'id'      => ['column'=>'id'     , 'type'=>PHP_TYPE_INT   , 'primary'=>true],      // db:int
            'name'    => ['column'=>'name'   , 'type'=>PHP_TYPE_STRING,                ],      // db:text
            'value'   => ['column'=>'value'  , 'type'=>PHP_TYPE_STRING,                ],      // db:text
            'test_id' => ['column'=>'test_id', 'type'=>PHP_TYPE_INT   ,                ],      // db:int
     ]];


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
