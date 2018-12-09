
-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Signals
insert into t_signal (created, provider, provider_id, name, alias, currency) values
   (now(), 'simpletrader', '2474', 'AlexProfit'       , 'alexprofit'   , 'USD'),
   (now(), 'simpletrader', '2370', 'Asta'             , 'asta'         , 'GBP'),
   (now(), 'simpletrader', '1619', 'Caesar2'          , 'caesar2'      , 'USD'),
   (now(), 'simpletrader', '1803', 'Caesar2.1'        , 'caesar21'     , 'USD'),
   (now(), 'simpletrader', '4351', 'Consistent Profit', 'consistent'   , 'USD'),
   (now(), 'simpletrader', '2465', 'DayFox'           , 'dayfox'       , 'EUR'),
   (now(), 'simpletrader', '633' , 'FX Viper'         , 'fxviper'      , 'USD'),
   (now(), 'simpletrader', '998' , 'GC-Edge'          , 'gcedge'       , 'USD'),
   (now(), 'simpletrader', '2622', 'GoldStar'         , 'goldstar'     , 'USD'),
   (now(), 'simpletrader', '2905', 'Kilimanjaro'      , 'kilimanjaro'  , 'USD'),
   (now(), 'simpletrader', '4322', 'NovoLRfund'       , 'novolr'       , 'USD'),
   (now(), 'simpletrader', '2973', 'OverTrader'       , 'overtrader'   , 'USD'),
   (now(), 'simpletrader', '5611', 'Ryan Analyst'     , 'ryan'         , 'USD');
   (now(), 'simpletrader', '1086', 'SmartScalper'     , 'smartscalper' , 'USD'),
   (now(), 'simpletrader', '1081', 'SmartTrader'      , 'smarttrader'  , 'USD'),
   (now(), 'simpletrader', '4023', 'Steady Capture'   , 'steadycapture', 'USD'),
   (now(), 'simpletrader', '3913', 'TwilightScalper'  , 'twilight'     , 'USD'),
   (now(), 'simpletrader', '2877', 'Yen Fortress'     , 'yenfortress'  , 'USD');

   set @signal_alexprofit    = (select id from t_signal where provider = 'simpletrader' and lias = 'alexprofit'   );
   set @signal_asta          = (select id from t_signal where provider = 'simpletrader' and lias = 'asta'         );
   set @signal_caesar2       = (select id from t_signal where provider = 'simpletrader' and lias = 'caesar2'      );
   set @signal_caesar21      = (select id from t_signal where provider = 'simpletrader' and lias = 'caesar21'     );
   set @signal_consistent    = (select id from t_signal where provider = 'simpletrader' and lias = 'consistent'   );
   set @signal_dayfox        = (select id from t_signal where provider = 'simpletrader' and lias = 'dayfox'       );
   set @signal_fxviper       = (select id from t_signal where provider = 'simpletrader' and lias = 'fxviper'      );
   set @signal_gcedge        = (select id from t_signal where provider = 'simpletrader' and lias = 'gcedge'       );
   set @signal_goldstar      = (select id from t_signal where provider = 'simpletrader' and lias = 'goldstar'     );
   set @signal_kilimanjaro   = (select id from t_signal where provider = 'simpletrader' and lias = 'kilimanjaro'  );
   set @signal_novolr        = (select id from t_signal where provider = 'simpletrader' and lias = 'novolr'       );
   set @signal_overtrader    = (select id from t_signal where provider = 'simpletrader' and lias = 'overtrader'   );
   set @signal_ryan          = (select id from t_signal where provider = 'simpletrader' and lias = 'ryan'         );
   set @signal_smartscalper  = (select id from t_signal where provider = 'simpletrader' and lias = 'smartscalper' );
   set @signal_smarttrader   = (select id from t_signal where provider = 'simpletrader' and lias = 'smarttrader'  );
   set @signal_steadycapture = (select id from t_signal where provider = 'simpletrader' and lias = 'steadycapture');
   set @signal_twilight      = (select id from t_signal where provider = 'simpletrader' and lias = 'twilight'     );
   set @signal_yenfortress   = (select id from t_signal where provider = 'simpletrader' and lias = 'yenfortress'  );


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
