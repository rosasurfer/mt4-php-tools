<?php
namespace rosasurfer\myfx\metatrader\model;

use rosasurfer\db\orm\DAO;

use const rosasurfer\PHP_TYPE_BOOL;
use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\PHP_TYPE_STRING;


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
         'id'              => ['id'             , PHP_TYPE_INT   ],      // int
         'created'         => ['created'        , PHP_TYPE_STRING],      // text[datetime GMT]
         'version'         => ['version'        , PHP_TYPE_STRING],      // text[datetime GMT]

         'strategy'        => ['strategy'       , PHP_TYPE_STRING],      // text(260)
         'reportingId'     => ['reportingid'    , PHP_TYPE_INT   ],      // int
         'reportingSymbol' => ['reportingsymbol', PHP_TYPE_STRING],      // text(11)
         'symbol'          => ['symbol'         , PHP_TYPE_STRING],      // text(11)
         'timeframe'       => ['timeframe'      , PHP_TYPE_INT   ],      // int
         'startTime'       => ['starttime'      , PHP_TYPE_STRING],      // text[datetime FXT]
         'endTime'         => ['endtime'        , PHP_TYPE_STRING],      // text[datetime FXT]
         'tickModel'       => ['tickmodel'      , PHP_TYPE_INT   ],      // text[enum] references enum_TickModel(Type)
         'spread'          => ['spread'         , PHP_TYPE_FLOAT ],      // float(2,1)
         'bars'            => ['bars'           , PHP_TYPE_INT   ],      // int
         'ticks'           => ['ticks'          , PHP_TYPE_INT   ],      // int
         'tradeDirections' => ['tradedirections', PHP_TYPE_INT   ],      // text[enum] references enum_TradeDirection(Type)
         'visualMode'      => ['visualmode'     , PHP_TYPE_BOOL  ],      // int[bool]
         'duration'        => ['duration'       , PHP_TYPE_INT   ],      // int
   ]];
}
