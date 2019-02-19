
pragma foreign_keys       = off;
pragma recursive_triggers = off;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- DukascopySymbols: add timeframe columns
alter table t_dukascopysymbol rename to t_dukascopysymbol_old_20190220;
drop trigger if exists tr_dukascopysymbol_after_update;

create table t_dukascopysymbol (                                           -- Dukascopy instruments (available history may start intraday)
   id                 integer        not null,
   created            text[datetime] not null default (datetime('now')),   -- GMT
   modified           text[datetime],                                      -- GMT
   name               text(11)       not null collate nocase,              -- Dukascopy instrument identifier (the actual symbol)
   digits             integer        not null,                             -- decimal digits
   historystart_ticks text[datetime],                                      -- start of available history (FXT)
   historystart_m1    text[datetime],                                      -- start of available history (FXT)
   historystart_h1    text[datetime],                                      -- start of available history (FXT)
   historystart_d1    text[datetime],                                      -- start of available history (FXT)
   rosasymbol_id      integer,
   primary key (id),
   constraint fk_dukascopysymbol_rosasymbol foreign key (rosasymbol_id) references t_rosasymbol (id) on delete restrict on update cascade
   constraint u_name       unique (name)
   constraint u_rosasymbol unique (rosasymbol_id)
);

insert into t_dukascopysymbol (id, created, modified, name, digits, historystart_ticks, historystart_m1, rosasymbol_id)
select id, created, modified, name, digits, historystart_ticks, historystart_m1, rosasymbol_id
   from t_dukascopysymbol_old_20190220;

create trigger tr_dukascopysymbol_after_update after update on t_dukascopysymbol
when (new.modified is null or (new.modified=old.modified and new.modified!=datetime('now')))
begin
   update t_dukascopysymbol set modified = datetime('now') where id = new.id;
end;

drop table if exists t_dukascopysymbol_old_20190220;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
pragma foreign_keys       = on;
pragma recursive_triggers = on;
vacuum;
