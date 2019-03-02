

-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols
insert into t_rosasymbol (type, groupid, name, description, digits) values
   ('forex'    , 1, 'AUDUSD', 'Australian Dollar vs US Dollar'               , 5),
   ('forex'    , 1, 'EURCHF', 'Euro vs Swiss Franc'                          , 5),
   ('forex'    , 1, 'EURUSD', 'Euro vs US Dollar'                            , 5),
   ('forex'    , 1, 'GBPUSD', 'Great Britain Pound vs US Dollar'             , 5),
   ('forex'    , 1, 'NZDUSD', 'New Zealand Dollar vs US Dollar'              , 5),
   ('forex'    , 1, 'USDCAD', 'US Dollar vs Canadian Dollar'                 , 5),
   ('forex'    , 1, 'USDCHF', 'US Dollar vs Swiss Franc'                     , 5),
   ('forex'    , 1, 'USDJPY', 'US Dollar vs Japanese Yen'                    , 3),
   ('forex'    , 1, 'USDNOK', 'US Dollar vs Norwegian Krona'                 , 5),
   ('forex'    , 1, 'USDSEK', 'US Dollar vs Swedish Kronor'                  , 5),
   ('forex'    , 1, 'USDSGD', 'US Dollar vs Singapore Dollar'                , 5),
   ('forex'    , 1, 'USDZAR', 'US Dollar vs South African Rand'              , 5),

   ('metals'   , 2, 'XAUUSD', 'Gold vs US Dollar'                            , 3),

   ('synthetic', 3, 'EURX'  , 'ICE Euro Futures index'                       , 3),
   ('synthetic', 3, 'USDX'  , 'ICE US Dollar Futures index'                  , 3),

   ('synthetic', 4, 'AUDFXI', 'Australian Dollar vs Major currencies index'  , 5),
   ('synthetic', 4, 'CADFXI', 'Canadian Dollar vs Major currencies index'    , 5),
   ('synthetic', 4, 'CHFFXI', 'Swiss Franc vs Major currencies index'        , 5),
   ('synthetic', 4, 'EURFXI', 'Euro vs Major currencies index'               , 5),
   ('synthetic', 4, 'GBPFXI', 'Great Britain Pound vs Major currencies index', 5),
   ('synthetic', 4, 'JPYFXI', 'Japanese Yen vs Major currencies index'       , 5),
   ('synthetic', 4, 'USDFXI', 'US Dollar vs Major currencies index'          , 5),

   ('synthetic', 5, 'NOKFXI', 'Norwegian Krone vs Major currencies index'    , 5),
   ('synthetic', 5, 'NZDFXI', 'New Zealand Dollar vs Major currencies index' , 5),
   ('synthetic', 5, 'SEKFXI', 'Swedish Krona vs Major currencies index'      , 5),
   ('synthetic', 5, 'SGDFXI', 'Singapore Dollar vs Major currencies index'   , 5),
   ('synthetic', 5, 'ZARFXI', 'South African Rand vs Major currencies index' , 5),

   ('synthetic', 6, 'AUDLFX', 'LiteForex Australian Dollar index'            , 5),
   ('synthetic', 6, 'CADLFX', 'LiteForex Canadian Dollar index'              , 5),
   ('synthetic', 6, 'CHFLFX', 'LiteForex Swiss Franc index'                  , 5),
   ('synthetic', 6, 'EURLFX', 'LiteForex Euro index'                         , 5),
   ('synthetic', 6, 'GBPLFX', 'LiteForex Great Britain Pound index'          , 5),
   ('synthetic', 6, 'LFXJPY', 'LiteForex Japanese Yen index'                 , 5),
   ('synthetic', 6, 'NZDLFX', 'LiteForex New Zealand Dollar index'           , 5),
   ('synthetic', 6, 'USDLFX', 'LiteForex US Dollar index'                    , 5);


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
