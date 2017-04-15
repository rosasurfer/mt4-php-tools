<?php
namespace rosasurfer\xtrade\model\metatrader;

use rosasurfer\db\orm\DAO;

use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;

use const rosasurfer\db\orm\ID_PRIMARY;
use rosasurfer\exception\InvalidArgumentException;


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
            'id'           => ['id'          , PHP_TYPE_INT  , 0, ID_PRIMARY],      // db:int
            'trades'       => ['trades'      , PHP_TYPE_INT  , 0, 0         ],      // db:int
            'tradesPerDay' => ['trades_day'  , PHP_TYPE_FLOAT, 0, 0         ],      // db:float
            'minDuration'  => ['duration_min', PHP_TYPE_INT  , 0, 0         ],      // db:int
            'avgDuration'  => ['duration_avg', PHP_TYPE_INT  , 0, 0         ],      // db:int
            'maxDuration'  => ['duration_max', PHP_TYPE_INT  , 0, 0         ],      // db:int
            'minPips'      => ['pips_min'    , PHP_TYPE_FLOAT, 0, 0         ],      // db:float
            'avgPips'      => ['pips_avg'    , PHP_TYPE_FLOAT, 0, 0         ],      // db:float
            'maxPips'      => ['pips_max'    , PHP_TYPE_FLOAT, 0, 0         ],      // db:float
            'pips'         => ['pips'        , PHP_TYPE_FLOAT, 0, 0         ],      // db:float
            'profit'       => ['profit'      , PHP_TYPE_FLOAT, 0, 0         ],      // db:float
            'commission'   => ['commission'  , PHP_TYPE_FLOAT, 0, 0         ],      // db:float
            'swap'         => ['swap'        , PHP_TYPE_FLOAT, 0, 0         ],      // db:float
            'test_id'      => ['test_id'     , PHP_TYPE_INT  , 0, 0         ],      // db:int
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
        return $this->findOne($sql);
    }
}
