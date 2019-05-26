
pragma foreign_keys       = on;
pragma recursive_triggers = on;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols
insert into t_rosasymbol (type, groupid, updateorder, name, description, digits) values
   ('synthetic', 2, 32, 'XAUI', 'Gold vs Majors index', 3);


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
vacuum;
