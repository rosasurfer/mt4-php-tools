
use myfx;


set autocommit           = 0;
set collation_connection = 'latin1_german1_ci';


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Signals
insert into t_signal (created, name, alias, referenceid, currency) values
   (now(), 'Kilimanjaro'    , 'kilimanjaro'  , '2905', 'USD'),
   (now(), 'Steady Capture' , 'steadycapture', '4023', 'USD'),
   (now(), 'TwilightScalper', 'twilight'     , '3913', 'USD')
   on duplicate key update name        = values(name),
                           alias       = values(alias),
                           referenceid = values(referenceid),
                           currency    = values(currency);

-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------


/*
*/
commit;
