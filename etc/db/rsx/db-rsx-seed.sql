

-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Instruments
insert into t_instrument (type, symbol, description, digits) values
   ('forex'    , 'AUDUSD', 'Australian Dollar vs US Dollar'  , 5),
   ('forex'    , 'EURUSD', 'Euro vs US Dollar'               , 5),
   ('forex'    , 'GBPUSD', 'Great Britain Pound vs US Dollar', 5),
   ('forex'    , 'NZDUSD', 'New Zealand Dollar vs US Dollar' , 5),
   ('forex'    , 'USDCAD', 'US Dollar vs Canadian Dollar'    , 5),
   ('forex'    , 'USDCHF', 'US Dollar vs Swiss Franc'        , 5),
   ('forex'    , 'USDJPY', 'US Dollar vs Japanese Yen'       , 3),
   ('forex'    , 'USDNOK', 'US Dollar vs Norwegian Krona'    , 5),
   ('forex'    , 'USDSEK', 'US Dollar vs Swedish Kronor'     , 5),
   ('forex'    , 'USDSGD', 'US Dollar vs Singapore Dollar'   , 5),
   ('forex'    , 'USDZAR', 'US Dollar vs South African Rand' , 5),

   ('metals'   , 'XAUUSD', 'Gold vs US Dollar'               , 3),

   ('synthetic', 'AUDLFX', 'AUD Index (LiteForex FX6 index)' , 5),
   ('synthetic', 'CADLFX', 'CAD Index (LiteForex FX6 index)' , 5),
   ('synthetic', 'CHFLFX', 'CHF Index (LiteForex FX6 index)' , 5),
   ('synthetic', 'EURLFX', 'EUR Index (LiteForex FX6 index)' , 5),
   ('synthetic', 'GBPLFX', 'GBP Index (LiteForex FX6 index)' , 5),
   ('synthetic', 'JPYLFX', 'JPY Index (LiteForex FX6 index)' , 5),
   ('synthetic', 'NZDLFX', 'NZD Index (LiteForex FX7 index)' , 5),
   ('synthetic', 'USDLFX', 'USD Index (LiteForex FX6 index)' , 5),

   ('synthetic', 'AUDFX6', 'AUD Index (FX6 index)'           , 5),
   ('synthetic', 'CADFX6', 'CAD Index (FX6 index)'           , 5),
   ('synthetic', 'CHFFX6', 'CHF Index (FX6 index)'           , 5),
   ('synthetic', 'EURFX6', 'EUR Index (FX6 index)'           , 5),
   ('synthetic', 'GBPFX6', 'GBP Index (FX6 index)'           , 5),
   ('synthetic', 'JPYFX6', 'JPY Index (FX6 index)'           , 5),
   ('synthetic', 'USDFX6', 'USD Index (FX6 index)'           , 5),

   ('synthetic', 'AUDFX7', 'AUD Index (FX7 index)'           , 5),
   ('synthetic', 'CADFX7', 'CAD Index (FX7 index)'           , 5),
   ('synthetic', 'CHFFX7', 'CHF Index (FX7 index)'           , 5),
   ('synthetic', 'EURFX7', 'EUR Index (FX7 index)'           , 5),
   ('synthetic', 'GBPFX7', 'GBP Index (FX7 index)'           , 5),
   ('synthetic', 'JPYFX7', 'JPY Index (FX7 index)'           , 5),
   ('synthetic', 'NOKFX7', 'NOK Index (FX7 index)'           , 5),
   ('synthetic', 'NZDFX7', 'NZD Index (FX7 index)'           , 5),
   ('synthetic', 'SEKFX7', 'SEK Index (FX7 index)'           , 5),
   ('synthetic', 'SGDFX7', 'SGD Index (FX7 index)'           , 5),
   ('synthetic', 'USDFX7', 'USD Index (FX7 index)'           , 5),
   ('synthetic', 'ZARFX7', 'ZAR Index (FX7 index)'           , 5),

   ('synthetic', 'EURX'  , 'EUR Index (ICE)'                 , 3),
   ('synthetic', 'USDX'  , 'USD Index (ICE)'                 , 3);


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
