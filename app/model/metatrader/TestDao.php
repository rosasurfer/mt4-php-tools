<?php
namespace rosasurfer\myfx\metatrader\model;

use rosasurfer\db\orm\Dao;


/**
 * DAO for accessing Test instances.
 */
class TestDao extends Dao {


   /**
    * @var mixed[] - database mapping
    */
   protected $mapping = [
      'connection' => 'sqlite',
      'table'      => 't_test',
      'fields'     => [
         'id'              => ['id'             , self::T_INT   , self::T_NOT_NULL],      // int
         'created'         => ['created'        , self::T_STRING, self::T_NOT_NULL],      // text[datetime GMT]
         'version'         => ['version'        , self::T_STRING, self::T_NOT_NULL],      // text[datetime GMT]

         'strategy'        => ['strategy'       , self::T_STRING, self::T_NOT_NULL],      // text(260)
         'reportingId'     => ['reportingid'    , self::T_INT   , self::T_NOT_NULL],      // int
         'reportingSymbol' => ['reportingsymbol', self::T_STRING, self::T_NOT_NULL],      // text(11)
         'symbol'          => ['symbol'         , self::T_STRING, self::T_NOT_NULL],      // text(11)
         'timeframe'       => ['timeframe'      , self::T_INT   , self::T_NOT_NULL],      // int
         'startTime'       => ['starttime'      , self::T_STRING, self::T_NOT_NULL],      // text[datetime FXT]
         'endTime'         => ['endtime'        , self::T_STRING, self::T_NOT_NULL],      // text[datetime FXT]
         'tickModel'       => ['tickmodel'      , self::T_INT   , self::T_NOT_NULL],      // text[enum] references enum_TickModel(Type)
         'spread'          => ['spread'         , self::T_FLOAT , self::T_NOT_NULL],      // float(2,1)
         'bars'            => ['bars'           , self::T_INT   , self::T_NOT_NULL],      // int
         'ticks'           => ['ticks'          , self::T_INT   , self::T_NOT_NULL],      // int
         'tradeDirections' => ['tradedirections', self::T_INT   , self::T_NOT_NULL],      // text[enum] references enum_TradeDirection(Type)
         'visualMode'      => ['visualmode'     , self::T_BOOL  , self::T_NOT_NULL],      // int[bool]
         'duration'        => ['duration'       , self::T_INT   , self::T_NOT_NULL],      // int
   ]];
}
