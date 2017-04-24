<?php
namespace rosasurfer\xtrade\model\metatrader;

use rosasurfer\db\orm\DAO;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;

use const rosasurfer\PHP_TYPE_BOOL;
use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\PHP_TYPE_STRING;


/**
 * DAO for accessing {@link Test} instances.
 */
class TestDAO extends DAO {


    /**
     * {@inheritdoc}
     */
    public function getMapping() {
        static $mapping; return $mapping ?: ($mapping=$this->parseMapping([
            'class'      => Test::class,
            'table'      => 't_test',
            'connection' => 'sqlite',
            'properties' => [
                ['name'=>'id'             , 'type'=>PHP_TYPE_INT   , 'primary'=>true],    // db:int
                ['name'=>'created'        , 'type'=>PHP_TYPE_STRING,                ],    // db:text[datetime] GMT
                ['name'=>'modified'       , 'type'=>PHP_TYPE_STRING, 'version'=>true],    // db:text[datetime] GMT

                ['name'=>'strategy'       , 'type'=>PHP_TYPE_STRING,                ],    // db:text
                ['name'=>'reportingId'    , 'type'=>PHP_TYPE_INT   ,                ],    // db:int
                ['name'=>'reportingSymbol', 'type'=>PHP_TYPE_STRING,                ],    // db:text
                ['name'=>'symbol'         , 'type'=>PHP_TYPE_STRING,                ],    // db:text
                ['name'=>'timeframe'      , 'type'=>PHP_TYPE_INT   ,                ],    // db:int
                ['name'=>'startTime'      , 'type'=>PHP_TYPE_STRING,                ],    // db:text[datetime] FXT
                ['name'=>'endTime'        , 'type'=>PHP_TYPE_STRING,                ],    // db:text[datetime] FXT
                ['name'=>'tickModel'      , 'type'=>PHP_TYPE_STRING,                ],    // db:text[enum] references enum_tickmodel(type)
                ['name'=>'spread'         , 'type'=>PHP_TYPE_FLOAT ,                ],    // db:float
                ['name'=>'bars'           , 'type'=>PHP_TYPE_INT   ,                ],    // db:int
                ['name'=>'ticks'          , 'type'=>PHP_TYPE_INT   ,                ],    // db:int
                ['name'=>'tradeDirections', 'type'=>PHP_TYPE_STRING,                ],    // db:text[enum] references enum_tradedirection(type)
                ['name'=>'visualMode'     , 'type'=>PHP_TYPE_BOOL  ,                ],    // db:int[bool]
                ['name'=>'duration'       , 'type'=>PHP_TYPE_INT   ,                ],    // db:int
            ],
            'relations' => [
                ['name'=>'strategyParameters', 'relation'=>'one-to-many', 'type'=>StrategyParameter::class, 'ref-column'=>'test_id'],
                ['name'=>'trades'            , 'relation'=>'one-to-many', 'type'=>Order::class            , 'ref-column'=>'test_id'],
                ['name'=>'stats'             , 'relation'=>'one-to-one' , 'type'=>Statistic::class        , 'ref-column'=>'test_id'],
            ],
        ]));
    }


    /**
     * Find and return the {@link Test} with the specified id.
     *
     * @param  int $id - test id (PK)
     *
     * @return Test
     */
    public function findById($id) {
        if (!is_int($id)) throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
        if ($id < 1)      throw new InvalidArgumentException('Invalid argument $id: '.$id);

        $sql = 'select *
                   from :Test
                   where id = '.$id;
        return $this->find($sql);
    }
}
