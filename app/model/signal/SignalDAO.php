<?php
namespace rosasurfer\rsx\model\signal;

use rosasurfer\db\orm\DAO;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;

use const rosasurfer\db\orm\meta\INT;
use const rosasurfer\db\orm\meta\STRING;


/**
 * DAO zum Zugriff auf Signal-Instanzen.
 */
class SignalDAO extends DAO {


    /**
     * {@inheritdoc}
     */
    public function getMapping() {
        static $mapping; return $mapping ?: ($mapping=$this->parseMapping([
            'connection' => 'mysql',
            'table'      => 't_signal',
            'class'      => Signal::class,
            'properties' => [
                ['name'=>'id',              'type'=>INT,    'primary'=>true        ],     // db:int
                ['name'=>'created',         'type'=>STRING,                        ],     // db:datetime
                ['name'=>'version',         'type'=>STRING, 'version'=>true        ],     // db:timestamp

                ['name'=>'provider',        'type'=>STRING,                        ],     // db:enum
                ['name'=>'providerId',      'type'=>STRING, 'column'=>'provider_id'],     // db:string
                ['name'=>'name',            'type'=>STRING,                        ],     // db:string
                ['name'=>'alias',           'type'=>STRING,                        ],     // db:string
                ['name'=>'accountCurrency', 'type'=>STRING, 'column'=>'currency'   ],     // db:enum
            ],
            'relations' => [
                ['name'=>'openPositions'  , 'assoc'=>'one-to-many', 'type'=>OpenPosition::class  , 'ref-column'=>'signal_id'],
                ['name'=>'closedPositions', 'assoc'=>'one-to-many', 'type'=>ClosedPosition::class, 'ref-column'=>'signal_id'],
            ],
        ]));
    }


    /**
     * Return the Signal of the specified provider and alias.
     *
     * @param  string $provider - provider
     * @param  string $alias    - signal alias
     *
     * @return Signal|null
     */
    public function getByProviderAndAlias($provider, $alias) {
        if (!is_string($provider)) throw new IllegalTypeException('Illegal type of parameter $provider: '.getType($provider));
        if (!strLen($provider))    throw new InvalidArgumentException('Invalid argument $provider: '.$provider);
        if (!is_string($alias))    throw new IllegalTypeException('Illegal type of parameter $alias: '.getType($alias));
        if (!strLen($alias))       throw new InvalidArgumentException('Invalid argument $alias: '.$alias);

        $provider = $this->escapeLiteral($provider);
        $alias    = $this->escapeLiteral($alias);

        $sql = "select *
                   from :Signal
                   where provider = $provider
                     and alias    = $alias";
        return $this->find($sql);
    }


    /**
     * Gibt den Zeitpunkt der letzten bekannten Aenderung der Net-Position eines Symbols zurueck.
     *
     * @param  Signal $signal - Signal
     * @param  string $symbol - Symbol
     *
     * @return string - Zeitpunkt
     */
    public function getLastKnownPositionChangeTime(Signal $signal, $symbol) {
        if (!$signal->isPersistent()) throw new InvalidArgumentException('Cannot process non-persistent '.get_class($signal));
        if (!is_string($symbol))      throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
        if (!strLen($symbol))         throw new InvalidArgumentException('Invalid argument $symbol: '.$symbol);

        $signal_id = $signal->getId();
        $symbol    = $this->escapeLiteral($symbol);

        $sql = "select max(time)
                   from (select max(o.opentime) as 'time'
                            from :OpenPosition o
                            where o.signal_id = $signal_id
                              and o.symbol    = $symbol
                            union all
                            select max(c.closetime)
                               from :ClosedPosition c
                               where c.signal_id = $signal_id
                                 and c.symbol    = $symbol
                   ) as r";
        return $this->query($sql)->fetchColumn(null, null, null, $onNoRows=null);
    }


    /**
     * Return all active MyfxBook Signals.
     *
     * @return Signal[]
     */
    public function findAllActiveMyfxBook() {
        $sql = "select *
                   from :Signal
                   where provider = 'myfxbook'
                   order by alias";
        return $this->findAll($sql);
    }


    /**
     * Return all active SimpleTrader Signals.
     *
     * @return Signal[]
     */
    public function findAllActiveSimpleTrader() {
        $sql = "select *
                   from :Signal
                   where provider = 'simpletrader'
                     and alias != 'alexprofit'       -- deactivated: margin call
                     and alias != 'asta'             -- deactivated: loser
                     and alias != 'dayfox'           -- deactivated: loser, Alpari
                     and alias != 'novolr'           -- PHP error
                     and alias != 'overtrader'       -- deactivated: loser
                     and alias != 'yenfortress'      -- loser
                   order by alias";
        return $this->findAll($sql);
    }
}
