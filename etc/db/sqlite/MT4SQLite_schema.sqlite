/*
Project     MyFX
Model       MetaTrader
Author      Peter Walther
Database    SQLite3
*/
.open --new "MT4SQLite.db"


-- drop all database objects
pragma writable_schema = 1;
delete from sqlite_master;
pragma writable_schema = 0;
vacuum;
pragma foreign_keys = on;


-- enum OrderType
create table enum_OrderType (
    Type text primary key collate nocase
);
insert into  enum_OrderType (Type) values
   ('Buy' ),
   ('Sell');


-- enum TickModel
create table enum_TickModel (
    Type text primary key collate nocase
);
insert into  enum_TickModel (Type) values
   ('EveryTick'    ),
   ('ControlPoints'),
   ('BarOpen'      );


-- enum TradeDirection
create table enum_TradeDirection (
    Type text primary key collate nocase
);
insert into  enum_TradeDirection (Type) values
   ('Long' ),
   ('Short'),
   ('Both' );


-- Test
create table t_Test (
   Id              integer primary key,
   Created_utc     text[datetime] not null default (datetime('now')),
   Modified_utc    text[datetime],
   Strategy        text(255)      not null collate nocase,
   ReportingId     integer        not null,
   ReportingSymbol text(11)       not null collate nocase,
   Symbol          text(11)       not null collate nocase,           -- tested symbol
   Timeframe       integer        not null,                          -- tested timeframe
   StartTime_fxt   text[datetime] not null,
   EndTime_fxt     text[datetime] not null,
   TickModel       text[enum]     not null collate nocase,
   Spread          float(2,1)     not null,                          -- in pips
   Bars            integer        not null,                          -- number of tested bars
   Ticks           integer        not null,                          -- number of tested ticks
   TradeDirections text[enum]     not null collate nocase,
   VisualMode      integer[bool]  not null,
   Duration        integer        not null,                          -- test duration in milliseconds
   constraint u_reportingsymbol       unique (ReportingSymbol),
   constraint u_strategy_reportingid  unique (Strategy, ReportingId),
   constraint fk_test_tickmodel       foreign key (tickmodel)       references enum_TickModel(Type)      on delete restrict on update cascade,
   constraint fk_test_tradedirections foreign key (tradedirections) references enum_TradeDirection(Type) on delete restrict on update cascade
);

create trigger tr_test_after_update after update on t_test
when (new.modified_utc = old.modified_utc || new.modified_utc is null)
begin
   update t_test set modified_utc = datetime('now') where id = new.id;
end;


-- Order
create table t_Order (
   Id            integer primary key,
   Created_utc   text[datetime] not null default (datetime('now')),
   Modified_utc  text[datetime],
   Ticket        integer        not null,
   Type          text[enum]     not null collate nocase,
   Lots          float(10,2)    not null,
   Symbol        text(11)       not null collate nocase,
   OpenPrice     float(10,5)    not null,
   OpenTime_fxt  text[datetime] not null,
   StopLoss      float(10,5),
   TakeProfit    float(10,5),
   ClosePrice    float(10,5)    not null,
   CloseTime_fxt text[datetime] not null,
   Commission    float(10,2)    not null,
   Swap          float(10,2)    not null,
   Profit        float(10,2)    not null,                            -- gross profit
   MagicNumber   integer,
   Comment       text(27) collate nocase,
   Test_id       integer not null,
   constraint u_order_test_ticket unique (Test_id, Ticket),
   constraint fk_order_type foreign key (type)    references enum_OrderType(Type) on delete restrict on update cascade,
   constraint fk_order_test foreign key (test_id) references t_Test(Id)           on delete restrict on update cascade
);

create trigger tr_order_after_update after update on t_order
when (new.modified_utc = old.modified_utc || new.modified_utc is null)
begin
   update t_order set modified_utc = datetime('now') where id = new.id;
end;
