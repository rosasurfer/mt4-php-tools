

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

   ('synthetic', 'AUDLFX', 'LiteForex scaled-down Australian Dollar FX6 index'  , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'CADLFX', 'LiteForex scaled-down Canadian Dollar FX6 index'    , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'CHFLFX', 'LiteForex scaled-down Swiss Franc FX6 index'        , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'EURLFX', 'LiteForex scaled-down Euro FX6 index'               , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'GBPLFX', 'LiteForex scaled-down Great Britain Pound FX6 index', 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'JPYLFX', 'LiteForex scaled-down Japanese Yen FX6 index'       , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'NZDLFX', 'LiteForex alias of New Zealand Dollar FX7 index'    , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'USDLFX', 'LiteForex scaled-down US Dollar FX6 index'          , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT

   ('synthetic', 'AUDFX6', 'Australian Dollar FX6 index'                        , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'CADFX6', 'Canadian Dollar FX6 index'                          , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'CHFFX6', 'Swiss Franc FX6 index'                              , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'EURFX6', 'Euro FX6 index'                                     , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'GBPFX6', 'Great Britain Pound FX6 index'                      , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'JPYFX6', 'Japanese Yen FX6 index'                             , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'USDFX6', 'US Dollar FX6 index'                                , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT

   ('synthetic', 'AUDFX7', 'Australian Dollar FX7 index'                        , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'CADFX7', 'Canadian Dollar FX7 index'                          , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'CHFFX7', 'Swiss Franc FX7 index'                              , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'EURFX7', 'Euro FX7 index'                                     , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'GBPFX7', 'Great Britain Pound FX7 index'                      , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'JPYFX7', 'Japanese Yen FX7 index'                             , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'NOKFX7', 'Norwegian Krone FX7 index'                          , 5),     -- history_M1_from was: 2003-08-05 00:00:00 GMT  =>  2003-08-05 03:00:00 FXT
   ('synthetic', 'NZDFX7', 'New Zealand Dollar FX7 index'                       , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'SEKFX7', 'Swedish Krona FX7 index'                            , 5),     -- history_M1_from was: 2003-08-05 00:00:00 GMT  =>  2003-08-05 03:00:00 FXT
   ('synthetic', 'SGDFX7', 'Singapore Dollar FX7 index'                         , 5),     -- history_M1_from was: 2004-11-16 00:00:00 GMT  =>  2004-11-16 02:00:00 FXT
   ('synthetic', 'USDFX7', 'US Dollar FX7 index'                                , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT
   ('synthetic', 'ZARFX7', 'South African Rand FX7 index'                       , 5),     -- history_M1_from was: 2003-08-03 00:00:00 GMT  =>  2003-08-03 03:00:00 FXT

   ('synthetic', 'EURX'  , 'ICE Euro Futures index'                             , 3),     -- history_M1_from was: 2003-08-04 00:00:00 GMT  =>  2003-08-04 03:00:00 FXT
   ('synthetic', 'USDX'  , 'ICE US Dollar Futures index'                        , 3);     -- history_M1_from was: 2003-08-04 00:00:00 GMT  =>  2003-08-04 03:00:00 FXT


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- DukascopySymbols
pragma temp_store = 2;
create temporary table tmp_symbol (
   id                integer  not null,
   name              text(11) not null collate nocase,
   digits            integer  not null,
   history_tick_from text[datetime],
   history_M1_from   text[datetime],
   primary key (id)
);

insert into tmp_symbol (name, digits, history_tick_from, history_M1_from) values
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

insert into t_dukascopysymbol (name, digits, history_tick_from, history_tick_to, history_M1_from, history_M1_to, rosasymbol_id)
select tmp.name,
       tmp.digits,
       tmp.history_tick_from,
       null,
       tmp.history_M1_from,
       null,
       r.id
   from tmp_symbol tmp
   left join t_rosasymbol r on r.name = tmp.name;

drop table tmp_symbol;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
