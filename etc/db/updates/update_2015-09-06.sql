
use myfx;


set autocommit           = 0;
set collation_connection = 'latin1_german1_ci';


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Signals
insert into t_signal (created, name, alias, referenceid, currency) values
   (now(), 'Kilimanjaro'   , 'kilimanjaro'  , '2905', 'USD'),
   (now(), 'Steady Capture', 'steadycapture', '4023', 'USD')
   on duplicate key update version = version;

-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------


/*
*/
commit;
