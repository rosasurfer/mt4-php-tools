<?php
use rosasurfer\db\orm\DAO;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;

use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\PHP_TYPE_STRING;

use const rosasurfer\db\orm\ID_CREATE;
use const rosasurfer\db\orm\ID_PRIMARY;
use const rosasurfer\db\orm\ID_VERSION;


/**
 * DAO zum Zugriff auf Signal-Instanzen.
 */
class SignalDAO extends DAO {


   // Datenbankmapping
   protected $mapping = [
      'connection' => 'myfx',
      'table'      => 't_signal',
      'columns'    => [
         'id'         => ['id'         , PHP_TYPE_INT   , 0, ID_PRIMARY],     // db:int
         'created'    => ['created'    , PHP_TYPE_STRING, 0, ID_CREATE ],     // db:datetime
         'version'    => ['version'    , PHP_TYPE_STRING, 0, ID_VERSION],     // db:timestamp

         'provider'   => ['provider'   , PHP_TYPE_STRING, 0, 0         ],     // db:enum
         'providerId' => ['provider_id', PHP_TYPE_STRING, 0, 0         ],     // db:string
         'name'       => ['name'       , PHP_TYPE_STRING, 0, 0         ],     // db:string
         'alias'      => ['alias'      , PHP_TYPE_STRING, 0, 0         ],     // db:string
         'currency'   => ['currency'   , PHP_TYPE_STRING, 0, 0         ],     // db:enum
   ]];


   /**
    * Gibt das Signal mit der angegebenen ID zurueck.
    *
    * @param  int $id - Signal-ID (PK)
    *
    * @return Signal
    */
   public function getById($id) {
      if (!is_int($id)) throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
      if ($id < 1)      throw new InvalidArgumentException('Invalid argument $id: '.$id);

      $sql = "select *
                 from :Signal
                 where id = $id";
      return $this->findOne($sql);
   }


   /**
    * Return the Signal of the specified provider and alias.
    *
    * @param  string $provider - provider
    * @param  string $alias    - signal alias
    *
    * @return Signal
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
      return $this->findOne($sql);
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
      return $this->query($sql)->fetchField(null, null, null, $onNoRows=null);
   }


   /**
    * Return all active MyfxBook Signals.
    *
    * @return Signal[]
    */
   public function listActiveMyfxBook() {
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
   public function listActiveSimpleTrader() {
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
