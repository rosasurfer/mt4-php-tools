
-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Accounts
insert into t_account (company, number, demo, type, timezone, currency) values
   ('ATC Brokers',  '8199', true , 'MT4', 'America/New_York', 'USD'),
   ('ATC Brokers', '21529', false, 'MT4', 'America/New_York', 'USD');

   set @atc_brokers_8199  = (select id from t_account where company = 'ATC Brokers' and number =  '8199');
   set @atc_brokers_21529 = (select id from t_account where company = 'ATC Brokers' and number = '21529');


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
