
pragma foreign_keys       = on;
pragma recursive_triggers = on;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols
insert into t_rosasymbol (type, name, description, digits) values
   ('synthetic', 'LFXJPY', 'LiteForex scaled-down and inversed Japanese Yen FX6 index', 3);


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
