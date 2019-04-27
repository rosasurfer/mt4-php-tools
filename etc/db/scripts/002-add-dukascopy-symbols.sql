
pragma foreign_keys       = on;
pragma recursive_triggers = on;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- add Rosatrader symbols
-- RosaSymbols
insert into t_rosasymbol (type, name, description, digits) values
   ('forex', 'EURCHF', 'Euro vs Swiss Franc', 5);


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- add Dukascopy symbols
-- temporary
pragma temp_store = 2;
create temporary table tmp_symbol (
   id                integer  not null,
   name              text(11) not null collate nocase,
   digits            integer  not null,
   history_tick_from text[datetime],
   history_M1_from   text[datetime],
   primary key (id)
);
insert into tmp_symbol (name, digits, history_tick_from, history_M1_from) values
   ('AUDUSD', 5, '2003-08-04 00:00:00', '2003-08-03 03:00:00'),      -- FXT
   ('EURCHF', 5,  null                , '2003-08-03 03:00:00'),      -- FXT
   ('EURUSD', 5, '2003-05-05 00:00:00', '2003-05-04 03:00:00'),      -- FXT
   ('GBPUSD', 5, '2003-05-05 00:00:00', '2003-05-04 03:00:00'),      -- FXT
   ('NZDUSD', 5, '2003-08-04 00:00:00', '2003-08-03 03:00:00'),      -- FXT
   ('USDCAD', 5, '2003-08-04 00:00:00', '2003-08-03 03:00:00'),      -- FXT
   ('USDCHF', 5, '2003-05-05 00:00:00', '2003-05-04 03:00:00'),      -- FXT
   ('USDJPY', 3, '2003-05-05 00:00:00', '2003-05-04 03:00:00'),      -- FXT
   ('USDNOK', 5, '2003-08-04 03:00:00', '2003-08-05 03:00:00'),      -- FXT   TODO: M1 start is 04.08.2003
   ('USDSEK', 5, '2003-08-04 03:00:00', '2003-08-05 03:00:00'),      -- FXT   TODO: M1 start is 04.08.2003
   ('USDSGD', 5, '2004-11-16 20:00:00', '2004-11-17 02:00:00'),      -- FXT   TODO: M1 start is 16.11.2004
   ('USDZAR', 5, '1997-10-13 21:00:00', '1997-10-14 03:00:00'),      -- FXT   TODO: M1 start is 13.11.1997
   ('XAUUSD', 3, '2003-05-05 03:00:00', '1999-09-02 03:00:00');      -- FXT   TODO: M1 start is 01.09.1999

-- DukascopySymbols
insert into t_dukascopysymbol (name, digits, history_tick_from, history_tick_to, history_M1_from, history_M1_to, rosasymbol_id)
select tmp.name,
       tmp.digits,
       tmp.history_tick_from,
       null,
       tmp.history_M1_from,
       null,
       r.id
   from tmp_symbol tmp
   left join t_rosasymbol r on r.name = tmp.name;

drop table tmp_symbol;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
vacuum;
