

-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols
insert into t_rosasymbol (type, name, description, digits) values
   ('forex'    , 'AUDUSD', 'Australian Dollar vs US Dollar'                     , 5),
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

   ('synthetic', 'AUDLFX', 'LiteForex scaled-down Australian Dollar FX6 index'  , 5),
   ('synthetic', 'CADLFX', 'LiteForex scaled-down Canadian Dollar FX6 index'    , 5),
   ('synthetic', 'CHFLFX', 'LiteForex scaled-down Swiss Franc FX6 index'        , 5),
   ('synthetic', 'EURLFX', 'LiteForex scaled-down Euro FX6 index'               , 5),
   ('synthetic', 'GBPLFX', 'LiteForex scaled-down Great Britain Pound FX6 index', 5),
   ('synthetic', 'JPYLFX', 'LiteForex scaled-down Japanese Yen FX6 index'       , 5),
   ('synthetic', 'NZDLFX', 'LiteForex alias of New Zealand Dollar FX7 index'    , 5),
   ('synthetic', 'USDLFX', 'LiteForex scaled-down US Dollar FX6 index'          , 5),

   ('synthetic', 'AUDFX6', 'Australian Dollar FX6 index'                        , 5),
   ('synthetic', 'CADFX6', 'Canadian Dollar FX6 index'                          , 5),
   ('synthetic', 'CHFFX6', 'Swiss Franc FX6 index'                              , 5),
   ('synthetic', 'EURFX6', 'Euro FX6 index'                                     , 5),
   ('synthetic', 'GBPFX6', 'Great Britain Pound FX6 index'                      , 5),
   ('synthetic', 'JPYFX6', 'Japanese Yen FX6 index'                             , 5),
   ('synthetic', 'USDFX6', 'US Dollar FX6 index'                                , 5),

   ('synthetic', 'AUDFX7', 'Australian Dollar FX7 index'                        , 5),
   ('synthetic', 'CADFX7', 'Canadian Dollar FX7 index'                          , 5),
   ('synthetic', 'CHFFX7', 'Swiss Franc FX7 index'                              , 5),
   ('synthetic', 'EURFX7', 'Euro FX7 index'                                     , 5),
   ('synthetic', 'GBPFX7', 'Great Britain Pound FX7 index'                      , 5),
   ('synthetic', 'JPYFX7', 'Japanese Yen FX7 index'                             , 5),
   ('synthetic', 'NOKFX7', 'Norwegian Krone FX7 index'                          , 5),
   ('synthetic', 'NZDFX7', 'New Zealand Dollar FX7 index'                       , 5),
   ('synthetic', 'SEKFX7', 'Swedish Krona FX7 index'                            , 5),
   ('synthetic', 'SGDFX7', 'Singapore Dollar FX7 index'                         , 5),
   ('synthetic', 'USDFX7', 'US Dollar FX7 index'                                , 5),
   ('synthetic', 'ZARFX7', 'South African Rand FX7 index'                       , 5),

   ('synthetic', 'EURX'  , 'ICE Euro Futures index'                             , 3),
   ('synthetic', 'USDX'  , 'ICE US Dollar Futures index'                        , 3);


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
