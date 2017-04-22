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
     * @var array - database mapping
     */
    protected $mapping = [
        'connection' => 'sqlite',
        'table'      => 't_test',
        'columns'    => [
            'id'              => ['column'=>'id'             , 'type'=>PHP_TYPE_INT   , 'primary'=>true],    // db:int
            'created'         => ['column'=>'created'        , 'type'=>PHP_TYPE_STRING,                ],    // db:text[datetime] GMT
            'modified'        => ['column'=>'modified'       , 'type'=>PHP_TYPE_STRING, 'version'=>true],    // db:text[datetime] GMT

            'strategy'        => ['column'=>'strategy'       , 'type'=>PHP_TYPE_STRING,                ],    // db:text
            'reportingId'     => ['column'=>'reportingid'    , 'type'=>PHP_TYPE_INT   ,                ],    // db:int
            'reportingSymbol' => ['column'=>'reportingsymbol', 'type'=>PHP_TYPE_STRING,                ],    // db:text
            'symbol'          => ['column'=>'symbol'         , 'type'=>PHP_TYPE_STRING,                ],    // db:text
            'timeframe'       => ['column'=>'timeframe'      , 'type'=>PHP_TYPE_INT   ,                ],    // db:int
            'startTime'       => ['column'=>'starttime'      , 'type'=>PHP_TYPE_STRING,                ],    // db:text[datetime] FXT
            'endTime'         => ['column'=>'endtime'        , 'type'=>PHP_TYPE_STRING,                ],    // db:text[datetime] FXT
            'tickModel'       => ['column'=>'tickmodel'      , 'type'=>PHP_TYPE_STRING,                ],    // db:text[enum] references enum_tickmodel(type)
            'spread'          => ['column'=>'spread'         , 'type'=>PHP_TYPE_FLOAT ,                ],    // db:float
            'bars'            => ['column'=>'bars'           , 'type'=>PHP_TYPE_INT   ,                ],    // db:int
            'ticks'           => ['column'=>'ticks'          , 'type'=>PHP_TYPE_INT   ,                ],    // db:int
            'tradeDirections' => ['column'=>'tradedirections', 'type'=>PHP_TYPE_STRING,                ],    // db:text[enum] references enum_tradedirection(type)
            'visualMode'      => ['column'=>'visualmode'     , 'type'=>PHP_TYPE_BOOL  ,                ],    // db:int[bool]
            'duration'        => ['column'=>'duration'       , 'type'=>PHP_TYPE_INT   ,                ],    // db:int
        ],
        'relations' => [
            'trades' => ['relation'=>'one-to-many', 'name'=>'trades', 'type'=>Order::class, 'column'=>'test_id'],
        ],
    ];


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
