
pragma foreign_keys       = off;
pragma recursive_triggers = off;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols: add column groups
-- group integer null
create table t_rosasymbol_tmp_20190301_1 (
   id                 integer        not null,
   created            text[datetime] not null default (datetime('now')),
   modified           text[datetime],
   type               text[enum]     not null collate nocase,
   "group"            integer,
   name               text(11)       not null collate nocase,
   description        text(63)       not null collate nocase,
   digits             integer        not null,
   autoupdate         integer[bool]  not null default 1,
   formula            text,
   historystart_tick  text[datetime],
   historyend_tick    text[datetime],
   historystart_m1    text[datetime],
   historyend_m1      text[datetime],
   historystart_d1    text[datetime],
   historyend_d1      text[datetime],
   primary key (id),
   constraint fk_rosasymbol_type foreign key (type) references enum_instrumenttype(type) on delete restrict on update cascade,
   constraint u_name unique (name)
);

insert into t_rosasymbol_tmp_20190301_1 (id, created, modified, type, name, description, digits, autoupdate, formula, historystart_tick, historyend_tick, historystart_m1, historyend_m1, historystart_d1, historyend_d1)
select id, created, modified, type, name, description, digits, autoupdate, formula, historystart_ticks, historyend_ticks, historystart_m1, historyend_m1, historystart_d1, historyend_d1
   from t_rosasymbol;

update t_rosasymbol_tmp_20190301_1 set "group" = 1 where type = 'forex';
update t_rosasymbol_tmp_20190301_1 set "group" = 2 where type = 'metals';
update t_rosasymbol_tmp_20190301_1 set "group" = 3 where type = 'synthetic' and name in ('EURX', 'USDX');
update t_rosasymbol_tmp_20190301_1 set "group" = 4 where type = 'synthetic' and name in ('AUDFXI', 'CADFXI', 'CHFFXI', 'EURFXI', 'GBPFXI', 'JPYFXI', 'USDFXI');
update t_rosasymbol_tmp_20190301_1 set "group" = 5 where type = 'synthetic' and name in ('NOKFXI', 'NZDFXI', 'SEKFXI', 'SGDFXI', 'ZARFXI');
update t_rosasymbol_tmp_20190301_1 set "group" = 6 where type = 'synthetic' and name in ('AUDLFX', 'CADLFX', 'CHFLFX', 'EURLFX', 'GBPLFX', 'LFXJPY', 'NZDLFX', 'USDLFX');

-- group integer not null
create table t_rosasymbol_tmp_20190301_2 (                                 -- Rosatrader instruments (history is stored in full days only)
   id                 integer        not null,
   created            text[datetime] not null default (datetime('now')),   -- GMT
   modified           text[datetime],                                      -- GMT
   type               text[enum]     not null collate nocase,              -- forex|metals|synthetic
   "group"            integer        not null,                             -- determines default view and sort order
   name               text(11)       not null collate nocase,              -- Rosatrader instrument identifier (the actual symbol)
   description        text(63)       not null collate nocase,              -- symbol description
   digits             integer        not null,                             -- decimal digits
   autoupdate         integer[bool]  not null default 1,                   -- whether automatic history updates are enabled
   formula            text,                                                -- LaTeX formula to calculate quotes (only if synthetic instrument)
   historystart_tick  text[datetime],                                      -- time of first locally stored tick (FXT)
   historyend_tick    text[datetime],                                      -- time of last locally stored tick (FXT)
   historystart_m1    text[datetime],                                      -- minute of first locally stored M1 bar (FXT)
   historyend_m1      text[datetime],                                      -- minute of last locally stored M1 bar (FXT)
   historystart_d1    text[datetime],                                      -- day of first locally stored D1 bar (FXT)
   historyend_d1      text[datetime],                                      -- day of last locally stored D1 bar (FXT)
   primary key (id),
   constraint fk_rosasymbol_type foreign key (type) references enum_instrumenttype(type) on delete restrict on update cascade,
   constraint u_name unique (name)
);

insert into t_rosasymbol_tmp_20190301_2 (id, created, modified, type, "group", name, description, digits, autoupdate, formula, historystart_tick, historyend_tick, historystart_m1, historyend_m1, historystart_d1, historyend_d1)
select id, created, modified, type, "group", name, description, digits, autoupdate, formula, historystart_tick, historyend_tick, historystart_m1, historyend_m1, historystart_d1, historyend_d1
   from t_rosasymbol_tmp_20190301_1;

drop index   if exists i_rosasymbol_type;
drop trigger if exists tr_rosasymbol_after_update;
drop table             t_rosasymbol_tmp_20190301_1;
drop table             t_rosasymbol;

alter table t_rosasymbol_tmp_20190301_2 rename to t_rosasymbol;

create index i_rosasymbol_type on t_rosasymbol(type);

create trigger tr_rosasymbol_after_update after update on t_rosasymbol
when (new.modified is null or (new.modified=old.modified and new.modified!=datetime('now')))
begin
   update t_rosasymbol set modified = datetime('now') where id = new.id;
end;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- DukascopySymbols: rename column historystart_ticks
alter table t_dukascopysymbol rename to t_dukascopysymbol_old_20190301;
drop trigger if exists tr_dukascopysymbol_after_update;

create table t_dukascopysymbol (                                           -- Dukascopy instruments (available history may start or end intraday)
   id                 integer        not null,
   created            text[datetime] not null default (datetime('now')),   -- GMT
   modified           text[datetime],                                      -- GMT
   name               text(11)       not null collate nocase,              -- Dukascopy instrument identifier (the actual symbol)
   digits             integer        not null,                             -- decimal digits
   historystart_tick  text[datetime],                                      -- time of first available tick (FXT)
   historystart_m1    text[datetime],                                      -- minute of first available M1 history (FXT)
   historystart_h1    text[datetime],                                      -- minute of first available H1 history (FXT)
   historystart_d1    text[datetime],                                      -- day of first available D1 history (FXT)
   rosasymbol_id      integer,
   primary key (id),
   constraint fk_dukascopysymbol_rosasymbol foreign key (rosasymbol_id) references t_rosasymbol (id) on delete restrict on update cascade
   constraint u_name       unique (name)
   constraint u_rosasymbol unique (rosasymbol_id)
);

insert into t_dukascopysymbol (id, created, modified, name, digits, historystart_tick, historystart_m1, historystart_h1, historystart_d1, rosasymbol_id)
select id, created, modified, name, digits, historystart_ticks, historystart_m1, historystart_h1, historystart_d1, rosasymbol_id
   from t_dukascopysymbol_old_20190301;

create trigger tr_dukascopysymbol_after_update after update on t_dukascopysymbol
when (new.modified is null or (new.modified=old.modified and new.modified!=datetime('now')))
begin
   update t_dukascopysymbol set modified = datetime('now') where id = new.id;
end;

drop table if exists t_dukascopysymbol_old_20190301;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
pragma foreign_keys       = on;
pragma recursive_triggers = on;
vacuum;
