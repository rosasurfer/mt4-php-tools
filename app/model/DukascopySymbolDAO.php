<?php
namespace rosasurfer\rt\model;

use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\db\NoSuchRecordException;
use rosasurfer\ministruts\db\orm\DAO;

use const rosasurfer\ministruts\db\orm\meta\INT;
use const rosasurfer\ministruts\db\orm\meta\STRING;


/**
 * DAO for accessing {@link DukascopySymbol} instances.
 */
class DukascopySymbolDAO extends DAO {


    /**
     *
     */
    public function getMapping() {
        static $mapping; return $mapping ?: ($mapping=$this->parseMapping([
            'connection' => 'rosatrader',
            'table'      => 't_dukascopysymbol',
            'class'      => DukascopySymbol::class,
            'properties' => [
                ['name'=>'id',                                              'type'=>INT,    'primary'=>true],   // db:int
                ['name'=>'created',                                         'type'=>STRING,                ],   // db:text[datetime] GMT
                ['name'=>'modified',                                        'type'=>STRING, 'version'=>true],   // db:text[datetime] GMT

                ['name'=>'name',                                            'type'=>STRING,                ],   // db:text
                ['name'=>'digits',                                          'type'=>INT,                   ],   // db:int
                ['name'=>'historyStartTick', 'column'=>'historystart_tick', 'type'=>STRING,                ],   // db:text[datetime] FXT
                ['name'=>'historyStartM1',   'column'=>'historystart_m1',   'type'=>STRING,                ],   // db:text[datetime] FXT
                ['name'=>'historyStartH1',   'column'=>'historystart_h1',   'type'=>STRING,                ],   // db:text[datetime] FXT
                ['name'=>'historyStartD1',   'column'=>'historystart_d1',   'type'=>STRING,                ],   // db:text[datetime] FXT
            ],
            'relations' => [
                ['name'=>'rosaSymbol', 'assoc'=>'one-to-one', 'type'=>RosaSymbol::class, 'column'=>'rosasymbol_id'],    // db:int
            ],
        ]));
    }


    /**
     * Find the {@link DukascopySymbol} with the specified name.
     *
     * @param  string $name
     *
     * @return DukascopySymbol|null
     */
    public function findByName($name) {
        Assert::string($name);

        $name = $this->escapeLiteral($name);
        $sql = 'select *
                   from :DukascopySymbol
                   where name = '.$name;
        return $this->find($sql);
    }


    /**
     * Get the {@link DukascopySymbol} with the specified name.
     *
     * @param  string $name
     *
     * @return DukascopySymbol
     *
     * @throws NoSuchRecordException if no such instance was found
     */
    public function getByName($name) {
        Assert::string($name);

        $name = $this->escapeLiteral($name);

        $sql = 'select *
                   from :DukascopySymbol
                   where name = '.$name;
        return $this->get($sql);
    }
}
