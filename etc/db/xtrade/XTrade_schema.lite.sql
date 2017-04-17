/*
Created     16.01.2017
Project     XTrade
Model       Test Management
Author      Peter Walther
Database    SQLite3
*/


.open --new "XTrade.db"


-- drop all database objects
pragma writable_schema = 1;
delete from sqlite_master;
pragma writable_schema = 0;
vacuum;
pragma foreign_keys = on;


-- OrderTypes
create table enum_ordertype (
    type text not null collate nocase,
    primary key (type)
);
insert into enum_ordertype (type) values
   ('Buy' ),
   ('Sell');


-- TickModels
create table enum_tickmodel (
    type text not null collate nocase,
    primary key (type)
);
insert into enum_tickmodel (type) values
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


-- Tests
create table t_test (
   id              integer        not null,
   created         text[datetime] not null default (datetime('now')),   -- GMT
   modified        text[datetime],                                      -- GMT
   strategy        text(255)      not null collate nocase,              -- tested strategy
   reportingid     integer        not null,                             -- the test's reporting id
   reportingsymbol text(11)       not null collate nocase,              -- the test's reporting symbol
   symbol          text(11)       not null collate nocase,              -- tested symbol
   timeframe       integer        not null,                             -- tested timeframe
   starttime       text[datetime] not null,                             -- FXT
   endtime         text[datetime] not null,                             -- FXT
   tickmodel       text[enum]     not null collate nocase,              -- EveryTick|ControlPoints|BarOpen
   spread          float(2,1)     not null,                             -- in pips
   bars            integer        not null,                             -- number of tested bars
   ticks           integer        not null,                             -- number of tested ticks
   tradedirections text[enum]     not null collate nocase,              -- Long|Short|Both
   visualmode      integer[bool]  not null,
   duration        integer        not null,                             -- test duration in seconds
   constraint pk_test_id              primary key (id),
   constraint fk_test_tickmodel       foreign key (tickmodel)       references enum_tickmodel(type)      on delete restrict on update cascade,
   constraint fk_test_tradedirections foreign key (tradedirections) references enum_tradedirection(type) on delete restrict on update cascade,
   constraint u_reportingsymbol       unique (reportingsymbol),
   constraint u_strategy_reportingid  unique (strategy, reportingid)
);
create trigger tr_test_after_update after update on t_test
when (new.modified = old.modified || new.modified is null)
begin
   update t_test set modified = datetime('now') where id = new.id;
end;


-- StrategyParameters
create table t_strategyparameter (
   id      integer   not null,
   name    text(32)  not null collate nocase,
   value   text(255) not null collate nocase,
   test_id integer   not null,
   constraint pk_strategyparameter_id   primary key (id),
   constraint fk_strategyparameter_test foreign key (test_id) references t_test(id) on delete cascade on update cascade,
   constraint u_test_name               unique (test_id, name)
);


-- Orders
create table t_order (
   id            integer        not null,
   created       text[datetime] not null default (datetime('now')),  -- GMT
   modified      text[datetime],                                     -- GMT
   ticket        integer        not null,
   type          text[enum]     not null collate nocase,             -- Buy|Sell
   lots          float(10,2)    not null,
   symbol        text(11)       not null collate nocase,
   openprice     float(10,5)    not null,
   opentime      text[datetime] not null,                            -- FXT
   stoploss      float(10,5),
   takeprofit    float(10,5),
   closeprice    float(10,5)    not null,
   closetime     text[datetime] not null,                            -- FXT
   commission    float(10,2)    not null,
   swap          float(10,2)    not null,
   profit        float(10,2)    not null,                            -- gross profit
   magicnumber   integer,
   comment       text(27)                collate nocase,
   test_id       integer        not null,
   constraint pk_order_id   primary key (id),
   constraint fk_order_type foreign key (type)    references enum_ordertype(type) on delete restrict on update cascade,
   constraint fk_order_test foreign key (test_id) references t_test(id)           on delete restrict on update cascade,
   constraint u_test_ticket unique (test_id, ticket)
);
create trigger tr_order_after_update after update on t_order
when (new.modified = old.modified || new.modified is null)
begin
   update t_order set modified = datetime('now') where id = new.id;
end;


-- Test statistics
create table t_statistic (
   id           integer     not null,
   trades       integer     not null,
   trades_day   float(10,1) not null,                                -- trades per day
   duration_min integer     not null,                                -- minimum trade duration in seconds
   duration_avg integer     not null,                                -- average trade duration in seconds
   duration_max integer     not null,                                -- maximum trade duration in seconds
   pips_min     float(10,1) not null,                                -- minimum trade profit in pips
   pips_avg     float(10,1) not null,                                -- average trade profit in pips
   pips_max     float(10,1) not null,                                -- maximum trade profit in pips
   pips         float(10,1) not null,                                -- full test profit in pips
   profit       float(10,2) not null,                                -- test gross profit in currency
   commission   float(10,2) not null,                                -- test commission
   swap         float(10,2) not null,                                -- test swap
   test_id      integer     not null,
   constraint pk_statistic_id   primary key (id),
   constraint fk_statistic_test foreign key (test_id) references t_test (id) on delete cascade on update cascade,
   constraint u_test            unique (test_id)
);
