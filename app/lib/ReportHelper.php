<?
use rosasurfer\core\Object;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;


/**
 * ReportHelper
 */
class ReportHelper extends Object {


   /**
    * Gibt einen Verlaufsreport der Netto-Position eines Signals ab dem angegebenen Zeitpunkt zurück. Waren zu diesem
    * Zeitpunkt Positionen offen, wird der Reportzeitraum über den angegebenen Startzeitpunkt hinaus bis zum Open-Zeitpunkt
    * der offenen Positionen vergrößert.
    *
    * @param  Signal $signal    - Signal
    * @param  string $symbol    - Symbol
    * @param  string $starttime - Startzeitpunkt des Reports
    *
    * @return array - Datenreport
    *
    *
    *  Example:
    *  +---------------------+---------+------+------+--------+-------+---------+-------+--------+------+--------+
    *  | time                | ticket  | type | lots | symbol | trade | price   | total | hedged | long | short  |
    *  +---------------------+---------+------+------+--------+-------+---------+-------+--------+------+--------+
    *  | 2014-10-09 17:18:29 | 2083416 | buy  | 0.20 | NZDUSD | open  | 0.79246 |  0.20 |   0.00 | 0.20 |   0.00 |
    *  | 2014-10-09 17:18:29 | 2084310 | buy  | 0.20 | NZDUSD | open  | 0.79246 |  0.40 |   0.00 | 0.40 |   0.00 |
    *  | 2014-10-09 17:18:29 | 2084317 | buy  | 0.20 | NZDUSD | open  | 0.79246 |  0.60 |   0.00 | 0.60 |   0.00 |
    *  | 2014-10-09 17:18:29 | 2084323 | buy  | 0.80 | NZDUSD | open  | 0.79246 |  1.40 |   0.00 | 1.40 |   0.00 |
    *  | 2014-10-09 17:18:29 | 2084420 | buy  | 3.13 | NZDUSD | open  | 0.79246 |  4.53 |   0.00 | 4.53 |   0.00 |
    *  +---------------------+---------+------+------+--------+-------+---------+-------+--------+------+--------+
    */
   public static function getNetPositionHistory(Signal $signal, $symbol, $starttime) {
      if (!$signal->isPersistent())                throw new InvalidArgumentException('Cannot process non-persistent '.get_class($signal));
      if (!is_string($symbol))                     throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
      if (!strLen($symbol))                        throw new InvalidArgumentException('Invalid argument $symbol: '.$symbol);
      if (!is_string($starttime))                  throw new IllegalTypeException('Illegal type of parameter $starttime: '.getType($starttime));
      if (!is_datetime($starttime, 'Y-m-d H:i:s')) throw new InvalidArgumentException('Invalid argument $starttime: '.$starttime);

      $db = Signal::db();

      $signal_id = $signal->getId();
      $symbol    = $db->escapeLiteral($symbol);

      // SQL-Variablen definieren
      $db->execute("set @change = 0.0")
         ->execute("set @total  = 0.0")
         ->execute("set @long   = 0.0")
         ->execute("set @short  = 0.0");

      // Report erstellen
      $sql = "select r.time,
                     r.ticket,
                     r.type,
                     r.lots,
                     r.symbol,
                     r.trade,
                     r.price,
                     r.total,
                     r.hedged,
                     r.long,
                     r.short
                 from (select time,
                              ticket,
                              type,
                              lots,
                              symbol,
                              trade,
                              price,
                              @change:=if(type='buy', 1, -1) * if(trade='open', 1, -1) * lots as 'change',
                              @long  :=round(@long +if(type='buy',  @change, 0), 2)           as 'long',
                              @short :=round(@short+if(type='sell', @change, 0), 2)           as 'short',
                              round(least(@long, -@short), 2)                                 as 'hedged',
                              @total :=round(@total+@change, 2)                               as 'total'
                          from ((select o.ticket,                             -- alle Open-Deals der zur Zeit offenen Positionen
                                        o.opentime  as 'time',
                                        o.type,
                                        o.lots,
                                        o.symbol,
                                        'open'      as 'trade',
                                        o.openprice as 'price'
                                   from t_openposition o
                                   where o.signal_id = $signal_id
                                     and o.symbol    = $symbol)

                                union all
                                (select c.ticket,                             -- alle Open-Deals der ab dem angegebenen Zeitpunkt geschlossenen Positionen
                                        c.opentime,
                                        c.type,
                                        c.lots,
                                        c.symbol,
                                        'open',
                                        c.openprice
                                   from t_closedposition c
                                   where c.signal_id = $signal_id
                                     and c.symbol    = $symbol
                                     and c.closetime >= '$starttime')

                                union all
                                (select c.ticket,                             -- alle Close-Deals ab dem angegebenen Zeitpunkt
                                        c.closetime,
                                        c.type,
                                        c.lots,
                                        c.symbol,
                                        'close',
                                        c.closeprice
                                   from t_closedposition c
                                   where c.signal_id = $signal_id
                                     and c.symbol    = $symbol
                                     and c.closetime >= '$starttime')
                                ) as ri
                          order by time, ticket
                 ) as r";
      $result = $db->query($sql);

      while ($data[] = $result->fetchNext(ARRAY_ASSOC));       // TODO: replace with $result->fetchAll(ARRAY_ASSOC)
      array_pop($data);

      foreach ($data as &$row) {
         $row['ticket'] = (int)   $row['ticket'];
         $row['lots'  ] = (float) $row['lots'  ];
         $row['price' ] = (float) $row['price' ];
         $row['total' ] = (float) $row['total' ];
         $row['hedged'] = (float) $row['hedged'];
         $row['long'  ] = (float) $row['long'  ];
         $row['short' ] = (float) $row['short' ];
      }
      return $data;
   }
}
