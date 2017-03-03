<?php
namespace rosasurfer\myfx\metatrader\model;

use rosasurfer\db\orm\DAO;

use const rosasurfer\PHP_TYPE_BOOL;
use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\PHP_TYPE_STRING;

use const rosasurfer\db\BIND_TYPE_INT;
use const rosasurfer\db\BIND_TYPE_STRING;
use const rosasurfer\db\ID_CREATE;
use const rosasurfer\db\ID_DELETE;
use const rosasurfer\db\ID_PRIMARY;
use const rosasurfer\db\ID_VERSION;


/**
 * DAO for accessing Test instances.
 */
class TestDAO extends DAO {


   /**
    * @var array - database mapping
    */
   protected $mapping = [
      'connection' => 'sqlite',
      'table'      => 't_test',
      'columns'    => [
         'id'              => ['test_sid'       , PHP_TYPE_INT   , 0               , ID_PRIMARY],      // db:int
         'created'         => ['created_at'     , PHP_TYPE_STRING, 0               , ID_CREATE ],      // db:text[datetime GMT]
         'updated'         => ['version'        , PHP_TYPE_STRING, 0               , ID_VERSION],      // db:text[datetime GMT]
         'deleted'         => ['deleted_at'     , PHP_TYPE_STRING, 0               , ID_DELETE ],      // db:text[datetime GMT]

         'strategy'        => ['strategy'       , PHP_TYPE_STRING, 0               , 0         ],      // db:text(260)
         'reportingId'     => ['reportingid'    , PHP_TYPE_INT   , 0               , 0         ],      // db:int
         'reportingSymbol' => ['reportingsymbol', PHP_TYPE_STRING, 0               , 0         ],      // db:text(11)
         'symbol'          => ['symbol'         , PHP_TYPE_STRING, 0               , 0         ],      // db:text(11)
         'timeframe'       => ['timeframe'      , PHP_TYPE_INT   , 0               , 0         ],      // db:int
         'startTime'       => ['starttime'      , PHP_TYPE_STRING, 0               , 0         ],      // db:text[datetime FXT]
         'endTime'         => ['endtime'        , PHP_TYPE_STRING, 0               , 0         ],      // db:text[datetime FXT]
         'tickModel'       => ['tickmodel'      , PHP_TYPE_INT   , BIND_TYPE_STRING, 0         ],      // db:text[enum] references enum_TickModel(Type)
         'spread'          => ['spread'         , PHP_TYPE_FLOAT , 0               , 0         ],      // db:float(2,1)
         'bars'            => ['bars'           , PHP_TYPE_INT   , 0               , 0         ],      // db:int
         'ticks'           => ['ticks'          , PHP_TYPE_INT   , 0               , 0         ],      // db:int
         'tradeDirections' => ['tradedirections', PHP_TYPE_INT   , BIND_TYPE_STRING, 0         ],      // db:text[enum] references enum_TradeDirection(Type)
         'visualMode'      => ['visualmode'     , PHP_TYPE_BOOL  , BIND_TYPE_INT   , 0         ],      // db:int[bool]
         'duration'        => ['duration'       , PHP_TYPE_INT   , 0               , 0         ],      // db:int
   ]];
}
