<?php
use rosasurfer\db\orm\DAO;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;


/**
 * DAO zum Zugriff auf Signal-Instanzen.
 */
class SignalDAO extends DAO {


   // Datenbankmapping
   protected $mapping = [
      'connection' => 'myfx',
      'table'      => 't_signal',
      'fields'     => [
         'id'         => ['id'         , self::T_INT   , self::T_NOT_NULL],     // int
         'version'    => ['version'    , self::T_STRING, self::T_NOT_NULL],     // datetime
         'created'    => ['created'    , self::T_STRING, self::T_NOT_NULL],     // datetime

         'provider'   => ['provider'   , self::T_STRING, self::T_NOT_NULL],     // enum
         'providerID' => ['provider_id', self::T_STRING, self::T_NOT_NULL],     // string
         'name'       => ['name'       , self::T_STRING, self::T_NOT_NULL],     // string
         'alias'      => ['alias'      , self::T_STRING, self::T_NOT_NULL],     // string
         'currency'   => ['currency'   , self::T_STRING, self::T_NOT_NULL],     // enum
   ]];


   /**
    * Gibt das Signal mit der angegebenen ID zurück.
    *
    * @param  int $id - Signal-ID (PK)
    *
    * @return Signal
    */
   public function getById($id) {
      if (!is_int($id)) throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
      if ($id < 1)      throw new InvalidArgumentException('Invalid argument $id: '.$id);

      $sql = "select *
                 from t_signal
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

      $provider = addSlashes($provider);
      $alias    = addSlashes($alias);

      $sql = "select *
                 from t_signal
                 where provider = '$provider'
                   and alias = '$alias'";
      return $this->findOne($sql);
   }


   /**
    * Gibt den Zeitpunkt der letzten bekannten Änderung der Net-Position eines Symbols zurück.
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
      $symbol    = addSlashes($symbol);

      $sql = "select max(time)
                 from (select max(o.opentime) as 'time'
                         from t_openposition o
                         where o.signal_id = $signal_id
                           and o.symbol    = '$symbol'
                       union all
                       select max(c.closetime)
                         from t_closedposition c
                         where c.signal_id = $signal_id
                           and c.symbol    = '$symbol'
                 ) as r";
      return $this->query($sql)->fetchField(null, null, $onNoRows=null);
   }


   /**
    * Return all active MyfxBook Signals.
    *
    * @return Signal[]
    */
   public function listActiveMyfxBook() {
      $sql = "select *
                 from t_signal
                 where provider = 'myfxbook'
                 order by alias";
      return $this->findMany($sql);
   }


   /**
    * Return all active SimpleTrader Signals.
    *
    * @return Signal[]
    */
   public function listActiveSimpleTrader() {
      $sql = "select *
                 from t_signal
                 where provider = 'simpletrader'
                   and alias != 'alexprofit'       -- deactivated: margin call
                   and alias != 'asta'             -- deactivated: loser
                   and alias != 'dayfox'           -- deactivated: loser, Alpari
                   and alias != 'novolr'           -- PHP error
                   and alias != 'overtrader'       -- deactivated: loser
                   and alias != 'yenfortress'      -- loser
                 order by alias";
      return $this->findMany($sql);
   }
}
