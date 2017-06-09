<?php
namespace rosasurfer\xtrade\model\metatrader;

use rosasurfer\db\orm\DAO;

use const rosasurfer\db\orm\meta\FLOAT;
use const rosasurfer\db\orm\meta\INT;
use const rosasurfer\db\orm\meta\STRING;


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
                ['name'=>'id'             , 'type'=>INT   , 'primary'=>true],    // db:int
                ['name'=>'created'        , 'type'=>STRING,                ],    // db:text[datetime] GMT
                ['name'=>'modified'       , 'type'=>STRING, 'version'=>true],    // db:text[datetime] GMT

                ['name'=>'strategy'       , 'type'=>STRING,                ],    // db:text
                ['name'=>'reportingId'    , 'type'=>INT   ,                ],    // db:int
                ['name'=>'reportingSymbol', 'type'=>STRING,                ],    // db:text
                ['name'=>'symbol'         , 'type'=>STRING,                ],    // db:text
                ['name'=>'timeframe'      , 'type'=>INT   ,                ],    // db:int
                ['name'=>'startTime'      , 'type'=>STRING,                ],    // db:text[datetime] FXT
                ['name'=>'endTime'        , 'type'=>STRING,                ],    // db:text[datetime] FXT
                ['name'=>'tickModel'      , 'type'=>STRING,                ],    // db:text[enum] references enum_tickmodel(type)
                ['name'=>'spread'         , 'type'=>FLOAT ,                ],    // db:float
                ['name'=>'bars'           , 'type'=>INT   ,                ],    // db:int
                ['name'=>'ticks'          , 'type'=>INT   ,                ],    // db:int
                ['name'=>'tradeDirections', 'type'=>STRING,                ],    // db:text[enum] references enum_tradedirection(type)
            ],
            'relations' => [
                ['name'=>'strategyParameters', 'assoc'=>'one-to-many', 'type'=>StrategyParameter::class, 'ref-column'=>'test_id'],
                ['name'=>'trades'            , 'assoc'=>'one-to-many', 'type'=>Order::class            , 'ref-column'=>'test_id'],
                ['name'=>'stats'             , 'assoc'=>'one-to-one' , 'type'=>Statistic::class        , 'ref-column'=>'test_id'],
            ],
        ]));
    }
}
