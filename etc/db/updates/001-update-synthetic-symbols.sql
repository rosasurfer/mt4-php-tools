
.bail on
.open "rsx.db"
pragma foreign_keys = on;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- update synthetic symbol descriptions
-- Instruments
update t_instrument set description = 'ICE Euro Futures index'                              where symbol = 'EURX';
update t_instrument set description = 'ICE US Dollar Futures index'                         where symbol = 'USDX';
update t_instrument set description = 'Australian Dollar FX6 index'                         where symbol = 'AUDFX6';
update t_instrument set description = 'Canadian Dollar FX6 index'                           where symbol = 'CADFX6';
update t_instrument set description = 'Swiss Franc FX6 index'                               where symbol = 'CHFFX6';
update t_instrument set description = 'Euro FX6 index'                                      where symbol = 'EURFX6';
update t_instrument set description = 'Great Britain Pound FX6 index'                       where symbol = 'GBPFX6';
update t_instrument set description = 'Japanese Yen FX6 index'                              where symbol = 'JPYFX6';
update t_instrument set description = 'US Dollar FX6 index'                                 where symbol = 'USDFX6';
update t_instrument set description = 'Australian Dollar FX7 index'                         where symbol = 'AUDFX7';
update t_instrument set description = 'Canadian Dollar FX7 index'                           where symbol = 'CADFX7';
update t_instrument set description = 'Swiss Franc FX7 index'                               where symbol = 'CHFFX7';
update t_instrument set description = 'Euro FX7 index'                                      where symbol = 'EURFX7';
update t_instrument set description = 'Great Britain Pound FX7 index'                       where symbol = 'GBPFX7';
update t_instrument set description = 'Japanese Yen FX7 index'                              where symbol = 'JPYFX7';
update t_instrument set description = 'Norwegian Krone FX7 index'                           where symbol = 'NOKFX7';
update t_instrument set description = 'New Zealand Dollar FX7 index'                        where symbol = 'NZDFX7';
update t_instrument set description = 'Swedish Krona FX7 index'                             where symbol = 'SEKFX7';
update t_instrument set description = 'Singapore Dollar FX7 index'                          where symbol = 'SGDFX7';
update t_instrument set description = 'US Dollar FX7 index'                                 where symbol = 'USDFX7';
update t_instrument set description = 'South African Rand FX7 index'                        where symbol = 'ZARFX7';
update t_instrument set description = 'LiteForex scaled-down Australian Dollar FX6 index'   where symbol = 'AUDLFX';
update t_instrument set description = 'LiteForex scaled-down Canadian Dollar FX6 index'     where symbol = 'CADLFX';
update t_instrument set description = 'LiteForex scaled-down Swiss Franc FX6 index'         where symbol = 'CHFLFX';
update t_instrument set description = 'LiteForex scaled-down Euro FX6 index'                where symbol = 'EURLFX';
update t_instrument set description = 'LiteForex scaled-down Great Britain Pound FX6 index' where symbol = 'GBPLFX';
update t_instrument set description = 'LiteForex scaled-down Japanese Yen FX6 index'        where symbol = 'JPYLFX';
update t_instrument set description = 'LiteForex alias of New Zealand Dollar FX7 index'     where symbol = 'NZDLFX';
update t_instrument set description = 'LiteForex scaled-down US Dollar FX6 index'           where symbol = 'USDLFX';


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
