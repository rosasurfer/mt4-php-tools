
pragma foreign_keys       = on;
pragma recursive_triggers = on;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols
update t_rosasymbol set groupid = 0 where name in ('- Forex Majors       -', 'AUDUSD', 'EURUSD', 'GBPUSD', 'NZDUSD', 'USDCAD', 'USDCHF', 'USDJPY');
update t_rosasymbol set groupid = 1 where name in ('- Forex Minors       -', 'EURCHF', 'USDNOK', 'USDSEK', 'USDSGD', 'USDZAR');
update t_rosasymbol set groupid = 2 where name in ('- Metals             -', 'XAUUSD', 'XAUI');
update t_rosasymbol set groupid = 3 where name in ('- ICE Future Indexes -', 'EURX', 'USDX');
update t_rosasymbol set groupid = 4 where name in ('- Forex Indexes      -', 'AUDFXI', 'CADFXI', 'CHFFXI', 'EURFXI', 'GBPFXI', 'JPYFXI', 'NOKFXI', 'NZDFXI', 'SEKFXI', 'SGDFXI', 'USDFXI', 'ZARFXI');
update t_rosasymbol set groupid = 5 where name in ('- LiteForex Indexes  -', 'AUDLFX', 'CADLFX', 'CHFLFX', 'EURLFX', 'GBPLFX', 'LFXJPY', 'NZDLFX', 'USDLFX');


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
vacuum;
