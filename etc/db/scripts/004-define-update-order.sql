
pragma foreign_keys       = on;
pragma recursive_triggers = on;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols
update t_rosasymbol set updateorder = 10 where name in ('AUDUSD','EURCHF','EURUSD','GBPUSD','NZDUSD','USDCAD','USDCHF','USDJPY');
update t_rosasymbol set updateorder = 11 where name in ('USDNOK','USDSEK','USDSGD','USDZAR');
update t_rosasymbol set updateorder = 12 where name in ('XAUUSD');
update t_rosasymbol set updateorder = 20 where name in ('EURX','USDX');
update t_rosasymbol set updateorder = 30 where name in ('USDLFX');
update t_rosasymbol set updateorder = 31 where name in ('AUDFXI','CADFXI','CHFFXI','EURFXI','GBPFXI','JPYFXI','NZDFXI','USDFXI');
update t_rosasymbol set updateorder = 32 where name in ('NOKFXI','SEKFXI','SGDFXI','ZARFXI');
update t_rosasymbol set updateorder = 33 where name in ('AUDLFX','CADLFX','CHFLFX','EURLFX','GBPLFX','LFXJPY','NZDLFX');


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
vacuum;
