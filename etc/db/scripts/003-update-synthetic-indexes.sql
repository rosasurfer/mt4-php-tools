
pragma foreign_keys       = on;
pragma recursive_triggers = on;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols
delete from t_rosasymbol
   where name in ('AUDFX7', 'CADFX7', 'CHFFX7', 'EURFX7', 'GBPFX7', 'JPYFX7', 'JPYLFX', 'USDFX7');

update t_rosasymbol set description = 'LiteForex Australian Dollar index'  where name = 'AUDLFX';
update t_rosasymbol set description = 'LiteForex Canadian Dollar index'    where name = 'CADLFX';
update t_rosasymbol set description = 'LiteForex Swiss Franc index'        where name = 'CHFLFX';
update t_rosasymbol set description = 'LiteForex Euro index'               where name = 'EURLFX';
update t_rosasymbol set description = 'LiteForex Great Britain Pound index'where name = 'GBPLFX';
update t_rosasymbol set description = 'LiteForex Japanese Yen index'       where name = 'LFXJPY';
update t_rosasymbol set description = 'LiteForex New Zealand Dollar index' where name = 'NZDLFX';
update t_rosasymbol set description = 'LiteForex US Dollar index'          where name = 'USDLFX';

update t_rosasymbol set description = 'Australian Dollar vs Majors index'  , name = 'AUDFXI' where name = 'AUDFX6';
update t_rosasymbol set description = 'Canadian Dollar vs Majors index'    , name = 'CADFXI' where name = 'CADFX6';
update t_rosasymbol set description = 'Swiss Franc vs Majors index'        , name = 'CHFFXI' where name = 'CHFFX6';
update t_rosasymbol set description = 'Euro vs Majors index'               , name = 'EURFXI' where name = 'EURFX6';
update t_rosasymbol set description = 'Great Britain Pound vs Majors index', name = 'GBPFXI' where name = 'GBPFX6';
update t_rosasymbol set description = 'Japanese Yen vs Majors index'       , name = 'JPYFXI' where name = 'JPYFX6';
update t_rosasymbol set description = 'Norwegian Krone vs Majors index'    , name = 'NOKFXI' where name = 'NOKFX7';
update t_rosasymbol set description = 'New Zealand Dollar vs Majors index' , name = 'NZDFXI' where name = 'NZDFX7';
update t_rosasymbol set description = 'Swedish Krona vs Majors index'      , name = 'SEKFXI' where name = 'SEKFX7';
update t_rosasymbol set description = 'Singapore Dollar vs Majors index'   , name = 'SGDFXI' where name = 'SGDFX7';
update t_rosasymbol set description = 'US Dollar vs Majors index'          , name = 'USDFXI' where name = 'USDFX6';
update t_rosasymbol set description = 'South African Rand vs Majors index' , name = 'ZARFXI' where name = 'ZARFX7';


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
vacuum;
