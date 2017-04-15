/*
Created     16.01.2017
Modified    15.04.2017
Project     XTrade
Model       XTrade Tests
Author      Peter Walther
Database    SQLite3
*/
.open --new "xtrade.db"


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
   created_utc     text[datetime] not null default (datetime('now')),
   modified_utc    text[datetime],
   strategy        text(255)      not null collate nocase,
   reportingid     integer        not null,
   reportingsymbol text(11)       not null collate nocase,
   symbol          text(11)       not null collate nocase,           -- tested symbol
   timeframe       integer        not null,                          -- tested timeframe
   starttime_fxt   text[datetime] not null,
   endtime_fxt     text[datetime] not null,
   tickmodel       text[enum]     not null collate nocase,
   spread          float(2,1)     not null,                          -- in pips
   bars            integer        not null,                          -- number of tested bars
   ticks           integer        not null,                          -- number of tested ticks
   tradedirections text[enum]     not null collate nocase,
   visualmode      integer[bool]  not null,
   duration        integer        not null,                          -- test duration in seconds
   primary key (id),
   constraint u_reportingsymbol       unique (reportingsymbol),
   constraint u_strategy_reportingid  unique (strategy, reportingid),
   constraint fk_test_tickmodel       foreign key (tickmodel)       references enum_tickmodel(type)      on delete restrict on update cascade,
   constraint fk_test_tradedirections foreign key (tradedirections) references enum_tradedirection(type) on delete restrict on update cascade
);
create trigger tr_test_after_update after update on t_test
when (new.modified_utc = old.modified_utc || new.modified_utc is null)
begin
   update t_test set modified_utc = datetime('now') where id = new.id;
end;


-- StrategyParameters
create table t_strategyparameter (
   test_id integer   not null,
   name    text(32)  not null collate nocase,
   value   text(255) not null collate nocase,
   primary key (test_id,name),
   constraint fk_strategyparameter_test foreign key (test_id) references t_test(id) on delete cascade on update cascade
);


-- Orders
create table t_order (
   id            integer        not null,
   created_utc   text[datetime] not null default (datetime('now')),
   modified_utc  text[datetime],
   ticket        integer        not null,
   type          text[enum]     not null collate nocase,
   lots          float(10,2)    not null,
   symbol        text(11)       not null collate nocase,
   openprice     float(10,5)    not null,
   opentime_fxt  text[datetime] not null,
   stoploss      float(10,5),
   takeprofit    float(10,5),
   closeprice    float(10,5)    not null,
   closetime_fxt text[datetime] not null,
   commission    float(10,2)    not null,
   swap          float(10,2)    not null,
   profit        float(10,2)    not null,                            -- gross profit
   magicnumber   integer,
   comment       text(27)                collate nocase,
   test_id       integer        not null,
   primary key (id),
   constraint u_order_test_ticket unique (test_id, ticket),
   constraint fk_order_type foreign key (type)    references enum_ordertype(type) on delete restrict on update cascade,
   constraint fk_order_test foreign key (test_id) references t_test(id)           on delete restrict on update cascade
);
create trigger tr_order_after_update after update on t_order
when (new.modified_utc = old.modified_utc || new.modified_utc is null)
begin
   update t_order set modified_utc = datetime('now') where id = new.id;
end;
