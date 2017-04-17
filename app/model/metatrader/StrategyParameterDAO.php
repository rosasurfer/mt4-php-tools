<?php
namespace rosasurfer\xtrade\model\metatrader;

use rosasurfer\db\orm\DAO;

use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\db\orm\ID_PRIMARY;
use rosasurfer\exception\InvalidArgumentException;


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
            'id'      => ['id'     , PHP_TYPE_INT   , 0, ID_PRIMARY],      // db:int
            'name'    => ['name'   , PHP_TYPE_STRING, 0, 0         ],      // db:text
            'value'   => ['value'  , PHP_TYPE_STRING, 0, 0         ],      // db:text
            'test_id' => ['test_id', PHP_TYPE_INT   , 0, 0         ],      // db:int
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
