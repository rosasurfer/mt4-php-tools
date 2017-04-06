<?php
namespace rosasurfer\trade\model\metatrader;

use rosasurfer\db\orm\DAO;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;

use const rosasurfer\ARRAY_ASSOC;

use const rosasurfer\PHP_TYPE_BOOL;
use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\PHP_TYPE_STRING;

use const rosasurfer\db\orm\BIND_TYPE_INT;
use const rosasurfer\db\orm\ID_CREATE;
use const rosasurfer\db\orm\ID_PRIMARY;
use const rosasurfer\db\orm\ID_VERSION;


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
            'id'              => ['id'             , PHP_TYPE_INT   , 0               , ID_PRIMARY],      // db:int
            'created'         => ['created_utc'    , PHP_TYPE_STRING, 0               , ID_CREATE ],      // db:text[datetime]
            'modified'        => ['modified_utc'   , PHP_TYPE_STRING, 0               , ID_VERSION],      // db:text[datetime]

            'strategy'        => ['strategy'       , PHP_TYPE_STRING, 0               , 0         ],      // db:text
            'reportingId'     => ['reportingid'    , PHP_TYPE_INT   , 0               , 0         ],      // db:int
            'reportingSymbol' => ['reportingsymbol', PHP_TYPE_STRING, 0               , 0         ],      // db:text
            'symbol'          => ['symbol'         , PHP_TYPE_STRING, 0               , 0         ],      // db:text
            'timeframe'       => ['timeframe'      , PHP_TYPE_INT   , 0               , 0         ],      // db:int
            'startTime'       => ['starttime_fxt'  , PHP_TYPE_STRING, 0               , 0         ],      // db:text[datetime]
            'endTime'         => ['endtime_fxt'    , PHP_TYPE_STRING, 0               , 0         ],      // db:text[datetime]
            'tickModel'       => ['tickmodel'      , PHP_TYPE_STRING, 0               , 0         ],      // db:text[enum] references enum_tickmodel(type)
            'spread'          => ['spread'         , PHP_TYPE_FLOAT , 0               , 0         ],      // db:float
            'bars'            => ['bars'           , PHP_TYPE_INT   , 0               , 0         ],      // db:int
            'ticks'           => ['ticks'          , PHP_TYPE_INT   , 0               , 0         ],      // db:int
            'tradeDirections' => ['tradedirections', PHP_TYPE_STRING, 0               , 0         ],      // db:text[enum] references enum_tradedirection(type)
            'visualMode'      => ['visualmode'     , PHP_TYPE_BOOL  , BIND_TYPE_INT   , 0         ],      // db:int[bool]
            'duration'        => ['duration'       , PHP_TYPE_INT   , 0               , 0         ],      // db:int
    ]];


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

        $sql = "select *
                   from :Test
                   where id = $id";
        return $this->findOne($sql);
    }


    /**
     * Find and return all strategy parameters of the specified {@link Test}.
     *
     * @param  Test $test
     *
     * @return string[]
     */
    public function findStrategyParameters(Test $test) {
        if (!$test->isPersistent()) throw new InvalidArgumentException('Cannot process non-persistent '.get_class($test));
        $id = $test->getId();
        $sql = 'select *
                   from t_strategyparameter
                   where test_id = '.$id.'
                   order by rowid';
        $result = $this->db()->query($sql);

        $params = [];
        while ($row = $result->fetchRow(ARRAY_ASSOC)) {
            $row = array_change_key_case($row, CASE_LOWER);
            $name  = $row['name'];
            $value = $row['value'];
            $params[] = $name.'='.$value;
        }
        return $params;
    }
}
