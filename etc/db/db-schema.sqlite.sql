/*
Created     16.01.2017
Modified    20.12.2018
Project     Rosatrader
Model       Main model
Author      Peter Walther
Version     0.2
Database    SQLite3
*/


-- drop all database objects
.bail on
pragma writable_schema = 1;
delete from sqlite_master;
pragma writable_schema = 0;
vacuum;
pragma foreign_keys       = on;
pragma recursive_triggers = on;
begin;


-- InstrumentTypes
create table enum_instrumenttype (
   type text not null collate nocase,
   primary key (type)
);
insert into enum_instrumenttype (type) values
   ('Forex'    ),
   ('Metals'   ),
   ('Synthetic');


-- OrderTypes
create table enum_ordertype (
   type text not null collate nocase,
   primary key (type)
);
insert into enum_ordertype (type) values
   ('Buy' ),
   ('Sell');


-- BarModels
create table enum_barmodel (
   type text not null collate nocase,
   primary key (type)
);
insert into enum_barmodel (type) values
   ('EveryTick'    ),
   ('ControlPoints'),
   ('BarOpen'      );


-- TradeDirections
create table enum_tradedirection (
   type text not null collate nocase,
   primary key (type)
);
insert into enum_tradedirection (type) values
   ('Long' ),
   ('Short'),
   ('Both' );


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


-- DukascopySymbols
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

create trigger tr_dukascopysymbol_before_update before update on t_dukascopysymbol
when (new.modified is null or new.modified = old.modified)
begin
   update t_dukascopysymbol set modified = datetime('now') where id = new.id;
end;


-- Tests
create table t_test (
   id              integer        not null,
   created         text[datetime] not null default (datetime('now')),      -- GMT
   modified        text[datetime],                                         -- GMT
   strategy        text(255)      not null collate nocase,                 -- tested strategy
   reportingid     integer        not null,                                -- the test's reporting id
   reportingsymbol text(11)       not null collate nocase,                 -- the test's reporting symbol
   symbol          text(11)       not null collate nocase,                 -- tested symbol
   timeframe       integer        not null,                                -- tested timeframe
   starttime       text[datetime] not null,                                -- FXT
   endtime         text[datetime] not null,                                -- FXT
   barmodel        text[enum]     not null collate nocase,                 -- EveryTick|ControlPoints|BarOpen
   spread          float          not null,                                -- in pip
   bars            integer        not null,                                -- number of tested bars
   ticks           integer        not null,                                -- number of tested ticks
   tradedirections text[enum]     not null collate nocase,                 -- Long|Short|Both
   primary key (id),
   constraint fk_test_barmodel        foreign key (barmodel)        references enum_barmodel(type)       on delete restrict on update cascade,
   constraint fk_test_tradedirections foreign key (tradedirections) references enum_tradedirection(type) on delete restrict on update cascade,
   constraint u_reportingsymbol       unique (reportingsymbol),
   constraint u_strategy_reportingid  unique (strategy, reportingid)
);
create index i_test_strategy        on t_test(strategy);
create index i_test_symbol          on t_test(symbol);
create index i_test_barmodel        on t_test(barmodel);
create index i_test_tradedirections on t_test(tradedirections);

create trigger tr_test_before_update before update on t_test
when (new.modified is null or new.modified = old.modified)
begin
   update t_test set modified = datetime('now') where id = new.id;
end;


-- StrategyParameters
create table t_strategyparameter (
   id      integer   not null,
   name    text(32)  not null collate nocase,
   value   text(255) not null collate nocase,
   test_id integer   not null,
   primary key (id),
   constraint fk_strategyparameter_test foreign key (test_id) references t_test(id) on delete cascade on update cascade,
   constraint u_test_name               unique (test_id, name)
);


-- Test statistics
create table t_statistic (
   id               integer not null,
   trades           integer not null,
   trades_day       float   not null,                                       -- trades per day
   duration_min     integer not null,                                       -- minimum trade duration in seconds
   duration_avg     integer not null,                                       -- average trade duration in seconds
   duration_max     integer not null,                                       -- maximum trade duration in seconds
   pips_min         float   not null,                                       -- minimum trade profit in pip
   pips_avg         float   not null,                                       -- average profit in pip
   pips_max         float   not null,                                       -- maximum trade profit in pip
   pips             float   not null,                                       -- total profit in pip
   sharpe_ratio     float   not null,                                       -- simplified non-normalized Sharpe ratio
   sortino_ratio    float   not null,                                       -- simplified non-normalized Sortino ratio
   calmar_ratio     float   not null,                                       -- simplified monthly Calmar ratio
   max_recoverytime integer not null,                                       -- maximum drawdown recovery time in seconds
   gross_profit     float   not null,                                       -- test gross profit in money
   commission       float   not null,                                       -- total commission
   swap             float   not null,                                       -- total swap
   test_id          integer not null,
   primary key (id),
   constraint fk_statistic_test foreign key (test_id) references t_test (id) on delete cascade on update cascade,
   constraint u_test            unique (test_id)
);


-- Orders
create table t_order (
   id          integer        not null,
   created     text[datetime] not null default (datetime('now')),          -- GMT
   modified    text[datetime],                                             -- GMT
   ticket      integer        not null,
   type        text[enum]     not null collate nocase,                     -- Buy|Sell
   lots        float          not null,
   symbol      text(11)       not null collate nocase,
   openprice   float          not null,
   opentime    text[datetime] not null,                                    -- FXT
   stoploss    float,
   takeprofit  float,
   closeprice  float          not null,
   closetime   text[datetime] not null,                                    -- FXT
   commission  float          not null,
   swap        float          not null,
   profit      float          not null,                                    -- gross profit
   magicnumber integer,
   comment     text(27)                collate nocase,
   test_id     integer        not null,
   primary key (id),
   constraint fk_order_type foreign key (type)    references enum_ordertype(type) on delete restrict on update cascade,
   constraint fk_order_test foreign key (test_id) references t_test(id)           on delete restrict on update cascade,
   constraint u_test_ticket unique (test_id, ticket)
);
create index i_order_type on t_order(type);

create trigger tr_order_before_update before update on t_order
when (new.modified is null or new.modified = old.modified)
begin
   update t_order set modified = datetime('now') where id = new.id;
end;


-- check schema
.lint fkey-indexes


-- seed the database
.read "db-seed.sql"

commit;
