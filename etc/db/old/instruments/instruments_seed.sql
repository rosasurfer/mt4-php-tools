
-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Instruments
insert into t_instrument (created, symbol) values
   (now(), 'EURUSD'),
   (now(), 'EURCHF'),
   (now(), 'EURGBP');

   set @eurusd = (select id from t_instrument where symbol = 'EURUSD');
   set @eurchf = (select id from t_instrument where symbol = 'EURCHF');
   set @eurgbp = (select id from t_instrument where symbol = 'EURGBP');

-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
