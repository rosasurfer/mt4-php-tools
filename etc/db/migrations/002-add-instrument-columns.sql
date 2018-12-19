
pragma foreign_keys       = off;
pragma recursive_triggers = off;
begin;


-- add instrument columns
-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Instruments
alter table  t_instrument rename to t_instrument_old_20181219;
drop index   if exists i_instrument_type;
drop trigger if exists tr_instrument_before_update;

create table t_instrument (
   id                 integer        not null,
   created            text[datetime] not null default (datetime('now')),   -- GMT
   modified           text[datetime],                                      -- GMT
   type               text[enum]     not null collate nocase,              -- Forex|Metals|Synthetic
   symbol             text(11)       not null collate nocase,
   description        text(63)       not null collate nocase,              -- symbol description
   digits             integer        not null,                             -- decimal digits
   hst_tick_from      text[datetime],                                      -- FXT
   hst_tick_to        text[datetime],                                      -- FXT
   hst_m1_from        text[datetime],                                      -- FXT
   hst_m1_to          text[datetime],                                      -- FXT
   hst_d1_from        text[datetime],                                      -- FXT
   hst_d1_to          text[datetime],                                      -- FXT
   primary key (id),
   constraint fk_instrument_type foreign key (type) references enum_instrumenttype(type) on delete restrict on update cascade,
   constraint u_symbol           unique (symbol)
);
insert into t_instrument (id, created, modified, type, symbol, description, digits, hst_tick_from, hst_m1_from, hst_d1_from)
select id, created, modified, type, symbol, description, digits, historystart_ticks, historystart_m1, historystart_d1
   from t_instrument_old_20181219;

create index i_instrument_type on t_instrument(type);

create trigger tr_instrument_before_update before update on t_instrument
when (new.modified is null or new.modified = old.modified)
begin
   update t_instrument set modified = datetime('now') where id = new.id;
end;

drop table if exists t_instrument_old_20181219;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
pragma foreign_keys       = on;
pragma recursive_triggers = on;
