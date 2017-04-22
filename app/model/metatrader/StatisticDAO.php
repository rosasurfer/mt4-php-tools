<?php
namespace rosasurfer\xtrade\model\metatrader;

use rosasurfer\db\orm\DAO;
use rosasurfer\exception\InvalidArgumentException;

use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;


/**
 * DAO for accessing {@link Statistic} instances.
 */
class StatisticDAO extends DAO {


    /**
     * @var array - database mapping
     */
    protected $mapping = [
        'connection' => 'sqlite',
        'table'      => 't_statistic',
        'columns'    => [
            'id'           => ['column'=>'id'          , 'type'=>PHP_TYPE_INT  , 'primary'=>true],      // db:int
            'trades'       => ['column'=>'trades'      , 'type'=>PHP_TYPE_INT  ,                ],      // db:int
            'tradesPerDay' => ['column'=>'trades_day'  , 'type'=>PHP_TYPE_FLOAT,                ],      // db:float
            'minDuration'  => ['column'=>'duration_min', 'type'=>PHP_TYPE_INT  ,                ],      // db:int
            'avgDuration'  => ['column'=>'duration_avg', 'type'=>PHP_TYPE_INT  ,                ],      // db:int
            'maxDuration'  => ['column'=>'duration_max', 'type'=>PHP_TYPE_INT  ,                ],      // db:int
            'minPips'      => ['column'=>'pips_min'    , 'type'=>PHP_TYPE_FLOAT,                ],      // db:float
            'avgPips'      => ['column'=>'pips_avg'    , 'type'=>PHP_TYPE_FLOAT,                ],      // db:float
            'maxPips'      => ['column'=>'pips_max'    , 'type'=>PHP_TYPE_FLOAT,                ],      // db:float
            'pips'         => ['column'=>'pips'        , 'type'=>PHP_TYPE_FLOAT,                ],      // db:float
            'profit'       => ['column'=>'profit'      , 'type'=>PHP_TYPE_FLOAT,                ],      // db:float
            'commission'   => ['column'=>'commission'  , 'type'=>PHP_TYPE_FLOAT,                ],      // db:float
            'swap'         => ['column'=>'swap'        , 'type'=>PHP_TYPE_FLOAT,                ],      // db:float
            'test_id'      => ['column'=>'test_id'     , 'type'=>PHP_TYPE_INT  ,                ],      // db:int
     ]];


    /**
     * Find and return the {@link Statistic} instance of the specified {@link Test}.
     *
     * @param  Test $test
     *
     * @return Statistic
     */
    public function findByTest(Test $test) {
        if (!$test->isPersistent()) throw new InvalidArgumentException('Cannot process non-persistent '.get_class($test));

        $test_id = $test->getId();

        $sql = 'select *
                   from :Statistic
                   where test_id = '.$test_id;
        return $this->find($sql);
    }
}
