/*
Created     16.01.2017
Modified    10.12.2018
Project     Xtrade
Model       Main model
Company     
Author      Peter Walther
Version     0.1
Database    MySQL 5
*/


set sql_mode             = 'traditional,high_not_precedence';
set collation_connection = 'utf8_unicode_ci';
set autocommit           = 0;


drop database if exists xtrade;
create database xtrade default collate 'latin1_general_ci';
use xtrade;


create table t_instrument (
   id int unsigned not null auto_increment,
   created timestamp not null default current_timestamp() comment 'GMT',
   modified timestamp null default null comment 'GMT',
   type enum('Forex','Metals','Synthetic') not null comment 'Forex | Metals | Synthetic',
   symbol varchar(11) not null,
   description varchar(63) not null comment 'long name',
   digits tinyint unsigned not null,
   historystart_ticks datetime comment 'FXT',
   historystart_m1 datetime comment 'FXT',
   historystart_d1 datetime comment 'FXT',
   unique index u_symbol (symbol),
   primary key (id),
   index i_type (type)
) engine = InnoDB;


create table t_test (
   id int unsigned not null auto_increment,
   created timestamp not null default current_timestamp() comment 'GMT',
   modified timestamp null default null comment 'GMT',
   strategy varchar(255) not null comment 'tested strategy',
   reportingid int unsigned not null comment 'the test''s reporting id',
   reportingsymbol varchar(11) not null comment 'the test''s reporting symbol',
   symbol varchar(11) not null comment 'tested symbol',
   timeframe int unsigned not null comment 'tested timeframe in minutes',
   starttime datetime not null comment 'FXT',
   endtime datetime not null comment 'FXT',
   barmodel enum('EveryTick','ControlPoints','BarOpen') not null comment 'EveryTick | ControlPoints | BarOpen',
   spread decimal(2,1) not null,
   bars int unsigned not null,
   ticks int unsigned not null,
   tradedirections enum('Long','Short','Both') not null comment 'Long | Short | Both',
   unique index u_reportingsymbol (reportingsymbol),
   primary key (id),
   unique key u_strategy_reportingid (strategy,reportingid),
   index i_strategy (strategy),
   index i_symbol (symbol),
   index i_barmodel (barmodel)
) engine = InnoDB;


create table t_strategyparameter (
   id int unsigned not null auto_increment,
   name varchar(32) not null,
   value varchar(255) not null,
   test_id int unsigned not null,
   primary key (id),
   unique key u_test_id_name (test_id,name),
   index i_test_id (test_id),
   constraint fk_strategyparameter_test foreign key (test_id) references t_test (id) on delete cascade on update cascade
) engine = InnoDB;


create table t_order (
   id int unsigned not null auto_increment,
   created timestamp not null default current_timestamp() comment 'GMT',
   modified timestamp null default null comment 'GMT',
   ticket int unsigned not null,
   type enum('Buy','Sell') not null comment 'Buy | Sell',
   lots decimal(10,2) not null,
   symbol varchar(11) not null,
   openprice decimal(10,5) not null,
   opentime datetime not null comment 'FXT',
   stoploss decimal(10,5),
   takeprofit decimal(10,5),
   closeprice decimal(10,5) not null,
   closetime datetime not null comment 'FXT',
   commission decimal(10,2) not null,
   swap decimal(10,2) not null,
   profit decimal(10,2) not null comment 'gross profit',
   magicnumber int unsigned,
   comment varchar(27),
   test_id int unsigned not null,
   primary key (id),
   unique key u_test_id_ticket (test_id,ticket),
   index i_type (type),
   index i_test_id (test_id),
   constraint fk_order_test foreign key (test_id) references t_test (id) on delete restrict on update cascade
) engine = InnoDB;


create table t_statistic (
   id int unsigned not null auto_increment,
   trades int unsigned not null,
   trades_day decimal(10,1) not null comment 'trades per day',
   duration_min int unsigned not null comment 'minimum trade duration in seconds',
   duration_avg int unsigned not null comment 'average trade duration in seconds',
   duration_max int unsigned not null comment 'maximum trade duration in seconds',
   pips_min decimal(10,1) not null comment 'minimum trade profit in pips',
   pips_avg decimal(10,1) not null comment 'average profit in pips',
   pips_max decimal(10,1) not null comment 'maximum trade profit in pips',
   pips decimal(10,1) not null comment 'total profit in pips',
   sharpe_ratio decimal(10,4) not null comment 'simplified non-normalized Sharpe ratio',
   sortino_ratio decimal(10,4) not null comment 'simplified non-normalized Sortino ratio',
   calmar_ratio decimal(10,4) not null comment 'simplified monthly Calmar ratio',
   max_recoverytime int unsigned not null comment 'maximum drawdown recovery time in seconds',
   gross_profit decimal(10,2) not null comment 'total gross profit in money',
   commission decimal(10,2) not null comment 'total commission',
   swap decimal(10,2) not null comment 'total swap',
   test_id int unsigned not null,
   unique index u_test_id (test_id),
   primary key (id),
   constraint fk_statistic_test foreign key (test_id) references t_test (id) on delete cascade on update cascade
) engine = InnoDB;


-- trigger definitions
delimiter //

create trigger tr_instrument_before_update before update on t_instrument for each row
begin
   -- update version timestamp if not yet done by the application layer
   if (new.modified = old.modified || new.modified is null) then
      set new.modified = current_timestamp();
   end if;
end;//

create trigger tr_test_before_update before update on t_test for each row
begin
   -- update version timestamp if not yet done by the application layer
   if (new.modified = old.modified || new.modified is null) then
      set new.modified = current_timestamp();
   end if;
end;//

create trigger tr_order_before_update before update on t_order for each row
begin
   -- update version timestamp if not yet done by the application layer
   if (new.modified = old.modified || new.modified is null) then
      set new.modified = current_timestamp();
   end if;
end;//

delimiter ;


-- seed the database
source db-xtrade-seed.sql;

commit;


