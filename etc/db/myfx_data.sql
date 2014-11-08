
-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Signals
insert into t_signal (created, name, alias, referenceid, currency) values
   (now(), 'AlexProfit'  , 'alexprofit'  , '2474', 'USD'),
   (now(), 'Asta'        , 'asta'        , '2370', 'GBP'),
   (now(), 'Caesar2'     , 'caesar2'     , '1619', 'USD'),
   (now(), 'Caesar2.1'   , 'caesar21'    , '1803', 'USD'),
   (now(), 'DayFox'      , 'dayfox'      , '2465', 'EUR'),
   (now(), 'FX Viper'    , 'fxviper'     , '633' , 'USD'),
   (now(), 'GoldStar'    , 'goldstar'    , '2622', 'USD'),
   (now(), 'Yen Fortress', 'yenfortress' , '2877', 'USD'),
   (now(), 'SmartScalper', 'smartscalper', '1086', 'USD'),
   (now(), 'SmartTrader' , 'smarttrader' , '1081', 'USD');


   set @signal_alexprofit   = (select id from t_signal where alias = 'alexprofit'  );
   set @signal_asta         = (select id from t_signal where alias = 'asta'        );
   set @signal_caesar2      = (select id from t_signal where alias = 'caesar2'     );
   set @signal_caesar21     = (select id from t_signal where alias = 'caesar21'    );
   set @signal_dayfox       = (select id from t_signal where alias = 'dayfox'      );
   set @signal_fxviper      = (select id from t_signal where alias = 'fxviper'     );
   set @signal_goldstar     = (select id from t_signal where alias = 'goldstar'    );
   set @signal_yenfortress  = (select id from t_signal where alias = 'yenfortress' );
   set @signal_smarttrader  = (select id from t_signal where alias = 'smarttrader' );
   set @signal_smartscalper = (select id from t_signal where alias = 'smartscalper');


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
