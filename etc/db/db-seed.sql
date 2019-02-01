

-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols
insert into t_rosasymbol (type, name, description, digits) values
   ('forex'    , 'AUDUSD', 'Australian Dollar vs US Dollar'                     , 5),
   ('forex'    , 'EURCHF', 'Euro vs Swiss Franc'                                , 5),
   ('forex'    , 'EURUSD', 'Euro vs US Dollar'                                  , 5),
   ('forex'    , 'GBPUSD', 'Great Britain Pound vs US Dollar'                   , 5),
   ('forex'    , 'NZDUSD', 'New Zealand Dollar vs US Dollar'                    , 5),
   ('forex'    , 'USDCAD', 'US Dollar vs Canadian Dollar'                       , 5),
   ('forex'    , 'USDCHF', 'US Dollar vs Swiss Franc'                           , 5),
   ('forex'    , 'USDJPY', 'US Dollar vs Japanese Yen'                          , 3),
   ('forex'    , 'USDNOK', 'US Dollar vs Norwegian Krona'                       , 5),
   ('forex'    , 'USDSEK', 'US Dollar vs Swedish Kronor'                        , 5),
   ('forex'    , 'USDSGD', 'US Dollar vs Singapore Dollar'                      , 5),
   ('forex'    , 'USDZAR', 'US Dollar vs South African Rand'                    , 5),

   ('metals'   , 'XAUUSD', 'Gold vs US Dollar'                                  , 3),

   ('synthetic', 'AUDLFX', 'LiteForex Australian Dollar index'                  , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'CADLFX', 'LiteForex Canadian Dollar index'                    , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'CHFLFX', 'LiteForex Swiss Franc index'                        , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'EURLFX', 'LiteForex Euro index'                               , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'GBPLFX', 'LiteForex Great Britain Pound index'                , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'LFXJPY', 'LiteForex Japanese Yen index'                       , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'NZDLFX', 'LiteForex New Zealand Dollar index'                 , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'USDLFX', 'LiteForex US Dollar index'                          , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT

   ('synthetic', 'AUDFXI', 'Australian Dollar vs Major currencies index'        , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'CADFXI', 'Canadian Dollar vs Major currencies index'          , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'CHFFXI', 'Swiss Franc vs Major currencies index'              , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'EURFXI', 'Euro vs Major currencies index'                     , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'GBPFXI', 'Great Britain Pound vs Major currencies index'      , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'JPYFXI', 'Japanese Yen vs Major currencies index'             , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'USDFXI', 'US Dollar vs Major currencies index'                , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT

   ('synthetic', 'NOKFXI', 'Norwegian Krone vs Major currencies index'          , 5),     -- history_M1_from was: 2003-08-05 00:00:00 GMT  =>  2003-08-05 03:00:00 FXT
   ('synthetic', 'NZDFXI', 'New Zealand Dollar vs Major currencies index'       , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'SEKFXI', 'Swedish Krona vs Major currencies index'            , 5),     -- history_M1_from was: 2003-08-05 00:00:00 GMT  =>  2003-08-05 03:00:00 FXT
   ('synthetic', 'SGDFXI', 'Singapore Dollar vs Major currencies index'         , 5),     -- history_M1_from was: 2004-11-16 00:00:00 GMT  =>  2004-11-16 02:00:00 FXT
   ('synthetic', 'ZARFXI', 'South African Rand vs Major currencies index'       , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT

   ('synthetic', 'EURX'  , 'ICE Euro Futures index'                             , 3),     -- history_M1_from was: 2003-08-04 00:00:00 GMT  =>  2003-08-04 03:00:00 FXT
   ('synthetic', 'USDX'  , 'ICE US Dollar Futures index'                        , 3);     -- history_M1_from was: 2003-08-04 00:00:00 GMT  =>  2003-08-04 03:00:00 FXT


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- DukascopySymbols
pragma temp_store = 2;
create temporary table tmp_symbol (
   id                 integer  not null,
   name               text(11) not null collate nocase,
   digits             integer  not null,
   historystart_ticks text[datetime],
   historystart_m1    text[datetime],
   primary key (id)
);

insert into tmp_symbol (name, digits, historystart_ticks, historystart_m1) values
   ('AUDUSD', 5, '2003-08-04 00:00:00', '2003-08-03 03:00:00'),                           -- FXT
   ('EURCHF', 5,  null                , '2003-08-03 03:00:00'),                           -- FXT
   ('EURUSD', 5, '2003-05-05 00:00:00', '2003-05-04 03:00:00'),                           -- FXT
   ('GBPUSD', 5, '2003-05-05 00:00:00', '2003-05-04 03:00:00'),                           -- FXT
   ('NZDUSD', 5, '2003-08-04 00:00:00', '2003-08-03 03:00:00'),                           -- FXT
   ('USDCAD', 5, '2003-08-04 00:00:00', '2003-08-03 03:00:00'),                           -- FXT
   ('USDCHF', 5, '2003-05-05 00:00:00', '2003-05-04 03:00:00'),                           -- FXT
   ('USDJPY', 3, '2003-05-05 00:00:00', '2003-05-04 03:00:00'),                           -- FXT
   ('USDNOK', 5, '2003-08-04 03:00:00', '2003-08-05 03:00:00'),                           -- FXT   TODO: M1 start is 04.08.2003
   ('USDSEK', 5, '2003-08-04 03:00:00', '2003-08-05 03:00:00'),                           -- FXT   TODO: M1 start is 04.08.2003
   ('USDSGD', 5, '2004-11-16 20:00:00', '2004-11-17 02:00:00'),                           -- FXT   TODO: M1 start is 16.11.2004
   ('USDZAR', 5, '1997-10-13 21:00:00', '1997-10-14 03:00:00'),                           -- FXT   TODO: M1 start is 13.11.1997
   ('XAUUSD', 3, '2003-05-05 03:00:00', '1999-09-02 03:00:00');                           -- FXT   TODO: M1 start is 01.09.1999

insert into t_dukascopysymbol (name, digits, historystart_ticks, historyend_ticks, historystart_m1, historyend_m1, rosasymbol_id)
select tmp.name,
       tmp.digits,
       tmp.historystart_ticks,
       null,
       tmp.historystart_m1,
       null,
       r.id
   from tmp_symbol tmp
   left join t_rosasymbol r on r.name = tmp.name;

drop table tmp_symbol;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
