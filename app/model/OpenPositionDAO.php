<?php
use rosasurfer\db\orm\DAO;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;

use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\PHP_TYPE_STRING;


/**
 * DAO zum Zugriff auf OpenPosition-Instanzen.
 */
class OpenPositionDAO extends DAO {


   // Datenbankmapping
   protected $mapping = [
      'connection' => 'myfx',
      'table'      => 't_openposition',
      'columns'    => [
         'id'          => ['id'         , PHP_TYPE_INT   ],      // int
         'version'     => ['version'    , PHP_TYPE_STRING],      // datetime
         'created'     => ['created'    , PHP_TYPE_STRING],      // datetime

         'ticket'      => ['ticket'     , PHP_TYPE_INT   ],      // int
         'type'        => ['type'       , PHP_TYPE_STRING],      // string
         'lots'        => ['lots'       , PHP_TYPE_FLOAT ],      // decimal
         'symbol'      => ['symbol'     , PHP_TYPE_STRING],      // string
         'openTime'    => ['opentime'   , PHP_TYPE_STRING],      // datetime
         'openPrice'   => ['openprice'  , PHP_TYPE_FLOAT ],      // decimal
         'stopLoss'    => ['stoploss'   , PHP_TYPE_FLOAT ],      // decimal
         'takeProfit'  => ['takeprofit' , PHP_TYPE_FLOAT ],      // decimal
         'commission'  => ['commission' , PHP_TYPE_FLOAT ],      // decimal
         'swap'        => ['swap'       , PHP_TYPE_FLOAT ],      // decimal
         'magicNumber' => ['magicnumber', PHP_TYPE_INT   ],      // int
         'comment'     => ['comment'    , PHP_TYPE_STRING],      // string
         'signal_id'   => ['signal_id'  , PHP_TYPE_INT   ],      // int
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

      $alias = $this->escapeString($alias);

      $sql = "select o.*
                 from t_signal       s
                 join t_openposition o on s.id = o.signal_id
                 where s.alias = '$alias'
                 order by o.opentime, o.ticket";
      $results = $this->findAll($sql);

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
