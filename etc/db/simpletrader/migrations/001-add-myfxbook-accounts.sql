
use myfx;

set autocommit           = 0;
set collation_connection = 'latin1_german1_ci';


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Signals
alter table t_signal
   add    column             provider    enum('myfxbook','simpletrader') after created,
   change column referenceid provider_id varchar(100) not null           after provider,
   change column alias       alias       varchar(100) not null,
   drop   index u_name,
   drop   index u_alias,
   add    unique key u_provider_provider_id (provider,provider_id),
   add    unique key u_provider_name (provider,name),
   add    unique key u_provider_alias (provider,alias);

update t_signal
   set provider = 'simpletrader';

alter table t_signal
   change column provider provider enum('myfxbook','simpletrader') not null;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
