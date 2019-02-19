
pragma foreign_keys       = off;
pragma recursive_triggers = off;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Instruments: migrate to ProjectSymbols
create table t_projectsymbol (                                             -- project instruments
   id                integer        not null,
   created           text[datetime] not null default (datetime('now')),    -- GMT
   modified          text[datetime],                                       -- GMT
   type              text[enum]     not null collate nocase,               -- Forex|Metals|Synthetic
   name              text(11)       not null collate nocase,               -- the project's instrument identifier (the actual symbol)
   description       text(63)       not null collate nocase,               -- symbol description
   digits            integer        not null,                              -- decimal digits
   history_tick_from text[datetime],                                       -- FXT
   history_tick_to   text[datetime],                                       -- FXT
   history_M1_from   text[datetime],                                       -- FXT
   history_M1_to     text[datetime],                                       -- FXT
   history_D1_from   text[datetime],                                       -- FXT
   history_D1_to     text[datetime],                                       -- FXT
   primary key (id),
   constraint fk_projectsymbol_type foreign key (type) references enum_instrumenttype(type) on delete restrict on update cascade,
   constraint u_name unique (name)
);

create index i_projectsymbol_type on t_projectsymbol(type);

create trigger tr_projectsymbol_before_update before update on t_projectsymbol
when (new.modified is null or new.modified = old.modified)
begin
   update t_projectsymbol set modified = datetime('now') where id = new.id;
end;

insert into t_projectsymbol (id, created, modified, type, name, description, digits, history_tick_from, history_tick_to, history_M1_from, history_M1_to, history_D1_from, history_D1_to)
select id, created, modified, type, symbol, description, digits, hst_tick_from, hst_tick_to, hst_m1_from, hst_m1_to, hst_d1_from, hst_d1_to
   from t_instrument;

-- Instruments
drop table if exists t_instrument;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- DukascopySymbols
create table t_dukascopysymbol (                                           -- Dukascopy instruments
   id                integer        not null,
   created           text[datetime] not null default (datetime('now')),    -- GMT
   modified          text[datetime],                                       -- GMT
   name              text(11)       not null collate nocase,               -- Dukascopy's instrument identifier (the actual symbol)
   digits            integer        not null,                              -- decimal digits
   history_tick_from text[datetime],                                       -- FXT
   history_tick_to   text[datetime],                                       -- FXT
   history_M1_from   text[datetime],                                       -- FXT
   history_M1_to     text[datetime],                                       -- FXT
   projectsymbol_id  integer,
   primary key (id),
   constraint fk_dukascopysymbol_projectsymbol foreign key (projectsymbol_id) references t_projectsymbol (id) on delete restrict on update cascade
   constraint u_name          unique (name)
   constraint u_projectsymbol unique (projectsymbol_id)
);

create trigger tr_dukascopysymbol_before_update before update on t_dukascopysymbol
when (new.modified is null or new.modified = old.modified)
begin
   update t_dukascopysymbol set modified = datetime('now') where id = new.id;
end;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
pragma foreign_keys       = on;
pragma recursive_triggers = on;
vacuum;
