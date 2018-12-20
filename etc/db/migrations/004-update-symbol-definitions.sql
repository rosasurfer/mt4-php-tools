
pragma foreign_keys       = off;
pragma recursive_triggers = off;
begin;


-- alter ProjectSymbols to RosaSymbols
-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols
create table t_rosasymbol (                                                -- Rosatrader instruments
   id                integer        not null,
   created           text[datetime] not null default (datetime('now')),    -- GMT
   modified          text[datetime],                                       -- GMT
   type              text[enum]     not null collate nocase,               -- forex|metals|synthetic
   name              text(11)       not null collate nocase,               -- Rosatrader instrument identifier (the actual symbol)
   description       text(63)       not null collate nocase,               -- symbol description
   digits            integer        not null,                              -- decimal digits
   history_tick_from text[datetime],                                       -- FXT
   history_tick_to   text[datetime],                                       -- FXT
   history_M1_from   text[datetime],                                       -- FXT
   history_M1_to     text[datetime],                                       -- FXT
   history_D1_from   text[datetime],                                       -- FXT
   history_D1_to     text[datetime],                                       -- FXT
   primary key (id),
   constraint fk_rosasymbol_type foreign key (type) references enum_instrumenttype(type) on delete restrict on update cascade,
   constraint u_name unique (name)
);
create index i_rosasymbol_type on t_rosasymbol(type);

create trigger tr_rosasymbol_before_update before update on t_rosasymbol
when (new.modified is null or new.modified = old.modified)
begin
   update t_rosasymbol set modified = datetime('now') where id = new.id;
end;

insert into t_rosasymbol (id, created, modified, type, name, description, digits, history_tick_from, history_tick_to, history_M1_from, history_M1_to, history_D1_from, history_D1_to)
select id, created, modified, lower(type), name, description, digits, history_tick_from, history_tick_to, history_M1_from, history_M1_to, history_D1_from, history_D1_to
   from t_projectsymbol;

-- ProjectSymbols
drop table if exists t_projectsymbol;


-- update DukascopySymbol
-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- DukascopySymbol
alter table t_dukascopysymbol rename to t_dukascopysymbol_old_20181220;
drop trigger if exists tr_dukascopysymbol_before_update;

create table t_dukascopysymbol (                                           -- Dukascopy instruments
   id                integer        not null,
   created           text[datetime] not null default (datetime('now')),    -- GMT
   modified          text[datetime],                                       -- GMT
   name              text(11)       not null collate nocase,               -- Dukascopy instrument identifier (the actual symbol)
   digits            integer        not null,                              -- decimal digits
   history_tick_from text[datetime],                                       -- FXT
   history_tick_to   text[datetime],                                       -- FXT
   history_M1_from   text[datetime],                                       -- FXT
   history_M1_to     text[datetime],                                       -- FXT
   rosasymbol_id     integer,
   primary key (id),
   constraint fk_dukascopysymbol_rosasymbol foreign key (rosasymbol_id) references t_rosasymbol (id) on delete restrict on update cascade
   constraint u_name       unique (name)
   constraint u_rosasymbol unique (rosasymbol_id)
);
insert into t_dukascopysymbol (id, created, modified, name, digits, history_tick_from, history_tick_to, history_M1_from, history_M1_to, rosasymbol_id)
select id, created, modified, name, digits, history_tick_from, history_tick_to, history_M1_from, history_M1_to, projectsymbol_id
   from t_dukascopysymbol_old_20181220;

create trigger tr_dukascopysymbol_before_update before update on t_dukascopysymbol
when (new.modified is null or new.modified = old.modified)
begin
   update t_dukascopysymbol set modified = datetime('now') where id = new.id;
end;

drop table if exists t_dukascopysymbol_old_20181220;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
pragma foreign_keys       = on;
pragma recursive_triggers = on;
vacuum;
