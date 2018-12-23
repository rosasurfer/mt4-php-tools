
use rost;

set autocommit           = 0;
set collation_connection = 'latin1_german1_ci';


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- OpenPositions
alter table t_openposition
   change column commission commission decimal(10,2),
   change column swap       swap       decimal(10,2);

update t_openposition o
   join t_signal      s on s.id = o.signal_id
   set o.commission = null,
       o.swap       = null
   where s.provider = 'simpletrader';


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- ClosedPositions
alter table t_closedposition
   change column commission commission decimal(10,2),
   change column swap       swap       decimal(10,2),
   change column profit     netprofit  decimal(10,2) not null;

alter table t_closedposition
   add column profit decimal(10,2) after swap;

update t_closedposition c
   join t_signal        s on s.id = c.signal_id
   set c.commission = null,
       c.swap       = null,
       c.profit     = null
   where s.provider = 'simpletrader';


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
