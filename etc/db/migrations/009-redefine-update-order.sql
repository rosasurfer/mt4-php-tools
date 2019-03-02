
pragma foreign_keys       = off;
pragma recursive_triggers = off;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols: redefine group and update order
create table t_rosasymbol_tmp_20190302 (                                                -- Rosatrader instruments (history is stored in full days only)
   id                 integer        not null,
   created            text[datetime] not null default (datetime('now')),   -- GMT
   modified           text[datetime],                                      -- GMT
   type               text[enum]     not null collate nocase,              -- forex|metals|synthetic
   groupid            integer        not null,                             -- determines default view and sort order
   name               text(11)       not null collate nocase,              -- Rosatrader instrument identifier (the actual symbol)
   description        text(63)       not null collate nocase,              -- symbol description
   digits             integer        not null,                             -- decimal digits
   updateorder        integer        not null default 9999,                -- required symbols should be updated before dependent ones
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

insert into t_rosasymbol_tmp_20190302 (id, created, modified, type, groupid, name, description, digits, formula, historystart_tick, historyend_tick, historystart_m1, historyend_m1, historystart_d1, historyend_d1)
select id, created, modified, type, "group", name, description, digits, formula, historystart_tick, historyend_tick, historystart_m1, historyend_m1, historystart_d1, historyend_d1
   from t_rosasymbol;

update t_rosasymbol_tmp_20190302 set updateorder = 1 where updateorder = 9999 and type in ('forex', 'metals');
update t_rosasymbol_tmp_20190302 set updateorder = 2 where updateorder = 9999 and name in ('EURX', 'USDX', 'USDLFX');
update t_rosasymbol_tmp_20190302 set updateorder = 3 where updateorder = 9999 and name in ('AUDLFX', 'CADLFX', 'CHFLFX', 'EURLFX', 'GBPLFX', 'LFXJPY', 'NZDLFX');
update t_rosasymbol_tmp_20190302 set updateorder = 4 where updateorder = 9999;

drop index   if exists i_rosasymbol_type;
drop trigger if exists tr_rosasymbol_after_update;
drop table             t_rosasymbol;

alter table t_rosasymbol_tmp_20190302 rename to t_rosasymbol;

create index i_rosasymbol_type on t_rosasymbol(type);

create trigger tr_rosasymbol_after_update after update on t_rosasymbol
when (new.modified is null or (new.modified=old.modified and new.modified!=datetime('now')))
begin
   update t_rosasymbol set modified = datetime('now') where id = new.id;
end;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
pragma foreign_keys       = on;
pragma recursive_triggers = on;
vacuum;
