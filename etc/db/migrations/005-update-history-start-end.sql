
pragma foreign_keys       = off;
pragma recursive_triggers = off;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols: add columns "autoupdate" and "formula", rename columns "history_*"
alter table t_rosasymbol rename to t_rosasymbol_old_20181228;
drop index   if exists i_rosasymbol_type;
drop trigger if exists tr_rosasymbol_before_update;

create table t_rosasymbol (                                                -- Rosatrader instruments (history is stored in full days only)
   id                 integer        not null,
   created            text[datetime] not null default (datetime('now')),   -- GMT
   modified           text[datetime],                                      -- GMT
   type               text[enum]     not null collate nocase,              -- forex|metals|synthetic
   name               text(11)       not null collate nocase,              -- Rosatrader instrument identifier (the actual symbol)
   description        text(63)       not null collate nocase,              -- symbol description
   digits             integer        not null,                             -- decimal digits
   autoupdate         integer[bool]  not null default 1,                   -- whether automatic history updates are enabled
   formula            text,                                                -- LaTex formula to calculate quotes (only if synthetic instrument)
   historystart_ticks text[datetime],                                      -- first day with stored history (FXT)
   historyend_ticks   text[datetime],                                      -- last day with stored history (FXT)
   historystart_m1    text[datetime],                                      -- first day with stored history (FXT)
   historyend_m1      text[datetime],                                      -- last day with stored history (FXT)
   historystart_d1    text[datetime],                                      -- first day with stored history (FXT)
   historyend_d1      text[datetime],                                      -- last day with stored history (FXT)
   primary key (id),
   constraint fk_rosasymbol_type foreign key (type) references enum_instrumenttype(type) on delete restrict on update cascade,
   constraint u_name unique (name)
);

insert into t_rosasymbol (id, created, modified, type, name, description, digits, historystart_ticks, historyend_ticks, historystart_m1, historyend_m1, historystart_d1, historyend_d1)
select id, created, modified, type, name, description, digits, history_tick_from, history_tick_to, history_M1_from, history_M1_to, history_D1_from, history_D1_to
   from t_rosasymbol_old_20181228;

create index i_rosasymbol_type on t_rosasymbol(type);

create trigger tr_rosasymbol_before_update before update on t_rosasymbol
when (new.modified is null or new.modified = old.modified)
begin
   update t_rosasymbol set modified = datetime('now') where id = new.id;
end;

drop table if exists t_rosasymbol_old_20181228;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- DukascopySymbols: rename columns "history_*"
alter table t_dukascopysymbol rename to t_dukascopysymbol_old_20181228;
drop trigger if exists tr_dukascopysymbol_before_update;

create table t_dukascopysymbol (                                           -- Dukascopy instruments (available history may start or end intraday)
   id                 integer        not null,
   created            text[datetime] not null default (datetime('now')),   -- GMT
   modified           text[datetime],                                      -- GMT
   name               text(11)       not null collate nocase,              -- Dukascopy instrument identifier (the actual symbol)
   digits             integer        not null,                             -- decimal digits
   historystart_ticks text[datetime],                                      -- start of available history (FXT)
   historyend_ticks   text[datetime],                                      -- end of available history (FXT)
   historystart_m1    text[datetime],                                      -- start of available history (FXT)
   historyend_m1      text[datetime],                                      -- end of available history (FXT)
   rosasymbol_id      integer,
   primary key (id),
   constraint fk_dukascopysymbol_rosasymbol foreign key (rosasymbol_id) references t_rosasymbol (id) on delete restrict on update cascade
   constraint u_name       unique (name)
   constraint u_rosasymbol unique (rosasymbol_id)
);

insert into t_dukascopysymbol (id, created, modified, name, digits, historystart_ticks, historyend_ticks, historystart_m1, historyend_m1, rosasymbol_id)
select id, created, modified, name, digits, history_tick_from, history_tick_to, history_M1_from, history_M1_to, rosasymbol_id
   from t_dukascopysymbol_old_20181228;

create trigger tr_dukascopysymbol_before_update before update on t_dukascopysymbol
when (new.modified is null or new.modified = old.modified)
begin
   update t_dukascopysymbol set modified = datetime('now') where id = new.id;
end;

drop table if exists t_dukascopysymbol_old_20181228;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
pragma foreign_keys       = on;
pragma recursive_triggers = on;
vacuum;
