<?php
use rosasurfer\db\orm\DAO;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;


/**
 * DAO zum Zugriff auf OpenPosition-Instanzen.
 */
class OpenPositionDAO extends DAO {


   // Datenbankmapping
   protected $mapping = [
      'connection' => 'myfx',
      'table'      => 't_openposition',
      'fields'     => [
         'id'          => ['id'         , self::T_INT   , self::T_NOT_NULL],      // int
         'version'     => ['version'    , self::T_STRING, self::T_NOT_NULL],      // datetime
         'created'     => ['created'    , self::T_STRING, self::T_NOT_NULL],      // datetime

         'ticket'      => ['ticket'     , self::T_INT   , self::T_NOT_NULL],      // int
         'type'        => ['type'       , self::T_STRING, self::T_NOT_NULL],      // string
         'lots'        => ['lots'       , self::T_FLOAT , self::T_NOT_NULL],      // decimal
         'symbol'      => ['symbol'     , self::T_STRING, self::T_NOT_NULL],      // string
         'openTime'    => ['opentime'   , self::T_STRING, self::T_NOT_NULL],      // datetime
         'openPrice'   => ['openprice'  , self::T_FLOAT , self::T_NOT_NULL],      // decimal
         'stopLoss'    => ['stoploss'   , self::T_FLOAT , self::T_NULL    ],      // decimal
         'takeProfit'  => ['takeprofit' , self::T_FLOAT , self::T_NULL    ],      // decimal
         'commission'  => ['commission' , self::T_FLOAT , self::T_NULL    ],      // decimal
         'swap'        => ['swap'       , self::T_FLOAT , self::T_NULL    ],      // decimal
         'magicNumber' => ['magicnumber', self::T_INT   , self::T_NULL    ],      // int
         'comment'     => ['comment'    , self::T_STRING, self::T_NULL    ],      // string
         'signal_id'   => ['signal_id'  , self::T_INT   , self::T_NOT_NULL],      // int
   ]];


   /**
    * Gibt die offenen Positionen des angegebenen Signals zurück.
    *
    * @param  Signal $signal      - Signal
    * @param  bool   $assocTicket - ob das Ergebnisarray assoziativ nach Tickets organisiert werden soll (default: nein)
    *
    * @return OpenPosition[] - Array von OpenPosition-Instanzen, aufsteigend sortiert nach {OpenTime,Ticket}
    */
   public function listBySignal(Signal $signal, $assocTicket=false) {
      if (!$signal->isPersistent()) throw new InvalidArgumentException('Cannot process non-persistent '.get_class($signal));

      return $this->listBySignalAlias($signal->getAlias(), $assocTicket);
   }


   /**
    * Gibt die offenen Positionen des angegebenen Signals zurück.
    *
    * @param  string $alias       - Signalalias
    * @param  bool   $assocTicket - ob das Ergebnisarray assoziativ nach Tickets organisiert werden soll (default: nein)
    *
    * @return OpenPosition[] - Array von OpenPosition-Instanzen, aufsteigend sortiert nach {OpenTime,Ticket}
    */
   public function listBySignalAlias($alias, $assocTicket=false) {
      if (!is_string($alias)) throw new IllegalTypeException('Illegal type of parameter $alias: '.getType($alias));

      $alias = addSlashes($alias);

      $sql = "select o.*
                 from t_signal       s
                 join t_openposition o on s.id = o.signal_id
                 where s.alias = '$alias'
                 order by o.opentime, o.ticket";
      $results = $this->findMany($sql);

      if ($assocTicket) {
         foreach ($results as $i => $position) {
            $results[(string) $position->getTicket()] = $position;
            unset($results[$i]);
         }
      }
      return $results;
   }


   /**
    * Gibt zu einem angegebenen Ticket die offene Position zurück.
    *
    * @param  Signal $signal - Signal
    * @param  int    $ticket - Ticket
    *
    * @return OpenPosition
    */
   public function getByTicket(Signal $signal, $ticket) {
      if (!$signal->isPersistent()) throw new InvalidArgumentException('Cannot process non-persistent '.get_class($signal));
      if (!is_int($ticket))         throw new IllegalTypeException('Illegal type of parameter $ticket: '.getType($ticket));

      $signal_id = $signal->getId();

      $sql = "select *
                 from t_openposition o
                 where o.signal_id = $signal_id
                   and o.ticket    = $ticket";
      return $this->findOne($sql);
   }
}
