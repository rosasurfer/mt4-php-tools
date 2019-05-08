

-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols
insert into t_rosasymbol (type, groupid, updateorder, name, description, digits) values
   ('forex'    , 0, 10, 'AUDUSD', 'Australian Dollar vs US Dollar'               , 5),
   ('forex'    , 0, 10, 'EURUSD', 'Euro vs US Dollar'                            , 5),
   ('forex'    , 0, 10, 'GBPUSD', 'Great Britain Pound vs US Dollar'             , 5),
   ('forex'    , 0, 10, 'NZDUSD', 'New Zealand Dollar vs US Dollar'              , 5),
   ('forex'    , 0, 10, 'USDCAD', 'US Dollar vs Canadian Dollar'                 , 5),
   ('forex'    , 0, 10, 'USDCHF', 'US Dollar vs Swiss Franc'                     , 5),
   ('forex'    , 0, 10, 'USDJPY', 'US Dollar vs Japanese Yen'                    , 3),

   ('forex'    , 1, 10, 'EURCHF', 'Euro vs Swiss Franc'                          , 5),
   ('forex'    , 1, 11, 'USDSGD', 'US Dollar vs Singapore Dollar'                , 5),
   ('forex'    , 1, 11, 'USDNOK', 'US Dollar vs Norwegian Krona'                 , 5),
   ('forex'    , 1, 11, 'USDSEK', 'US Dollar vs Swedish Kronor'                  , 5),
   ('forex'    , 1, 11, 'USDZAR', 'US Dollar vs South African Rand'              , 5),

   ('metals'   , 2, 12, 'XAUUSD', 'Gold vs US Dollar'                            , 3),

   ('synthetic', 3, 20, 'EURX'  , 'ICE Euro Futures index'                       , 3),
   ('synthetic', 3, 20, 'USDX'  , 'ICE US Dollar Futures index'                  , 3),

   ('synthetic', 4, 31, 'AUDFXI', 'Australian Dollar vs Majors index'            , 5),
   ('synthetic', 4, 31, 'CADFXI', 'Canadian Dollar vs Majors index'              , 5),
   ('synthetic', 4, 31, 'CHFFXI', 'Swiss Franc vs Majors index'                  , 5),
   ('synthetic', 4, 31, 'EURFXI', 'Euro vs Majors index'                         , 5),
   ('synthetic', 4, 31, 'GBPFXI', 'Great Britain Pound vs Majors index'          , 5),
   ('synthetic', 4, 31, 'JPYFXI', 'Japanese Yen vs Majors index'                 , 5),
   ('synthetic', 4, 31, 'NZDFXI', 'New Zealand Dollar vs Majors index'           , 5),
   ('synthetic', 4, 31, 'USDFXI', 'US Dollar vs Majors index'                    , 5),
   ('synthetic', 4, 32, 'NOKFXI', 'Norwegian Krona vs Majors index'              , 5),
   ('synthetic', 4, 32, 'SEKFXI', 'Swedish Kronor vs Majors index'               , 5),
   ('synthetic', 4, 32, 'SGDFXI', 'Singapore Dollar vs Majors index'             , 5),
   ('synthetic', 4, 32, 'ZARFXI', 'South African Rand vs Majors index'           , 5),

   ('synthetic', 5, 33, 'AUDLFX', 'LiteForex Australian Dollar index'            , 5),
   ('synthetic', 5, 33, 'CADLFX', 'LiteForex Canadian Dollar index'              , 5),
   ('synthetic', 5, 33, 'CHFLFX', 'LiteForex Swiss Franc index'                  , 5),
   ('synthetic', 5, 33, 'EURLFX', 'LiteForex Euro index'                         , 5),
   ('synthetic', 5, 33, 'GBPLFX', 'LiteForex Great Britain Pound index'          , 5),
   ('synthetic', 5, 33, 'LFXJPY', 'LiteForex Japanese Yen index'                 , 5),
   ('synthetic', 5, 33, 'NZDLFX', 'LiteForex New Zealand Dollar index'           , 5),
   ('synthetic', 5, 30, 'USDLFX', 'LiteForex US Dollar index'                    , 5);


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- DukascopySymbols
insert into t_dukascopysymbol (name, digits) values
   ('AUDUSD', 5),
   ('EURCHF', 5),
   ('EURUSD', 5),
   ('GBPUSD', 5),
   ('NZDUSD', 5),
   ('USDCAD', 5),
   ('USDCHF', 5),
   ('USDJPY', 3),
   ('USDNOK', 5),
   ('USDSEK', 5),
   ('USDSGD', 5),
   ('USDZAR', 5),
   ('XAUUSD', 3);

update t_dukascopysymbol
   set rosasymbol_id = (select id
                           from t_rosasymbol
                           where name = t_dukascopysymbol.name);


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
