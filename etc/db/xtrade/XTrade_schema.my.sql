/*
Created     16.01.2017
Modified    15.04.2017
Project     XTrade
Model       XTrade Tests
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


create table t_test (
   id int unsigned not null auto_increment,
   created_utc timestamp not null default current_timestamp() comment 'GMT',
   modified_utc timestamp null default null comment 'GMT',
   strategy varchar(255) not null comment 'tested strategy',
   reportingid int unsigned not null comment 'the test''s reporting id',
   reportingsymbol varchar(11) not null comment 'the test''s reporting symbol',
   symbol varchar(11) not null comment 'tested symbol',
   timeframe int unsigned not null comment 'tested timeframe',
   starttime_fxt datetime not null comment 'FXT',
   endtime_fxt datetime not null comment 'FXT',
   tickmodel enum('EveryTick','ControlPoints','BarOpen') not null comment 'EveryTick | ControlPoints | BarOpen',
   spread decimal(2,1) unsigned not null,
   bars int unsigned not null,
   ticks int unsigned not null,
   tradedirections enum('Long','Short','Both') not null comment 'Long | Short | Both',
   visualmode tinyint(1) unsigned not null,
   duration int unsigned not null comment 'test duration in seconds',
   unique index u_reportingsymbol (reportingsymbol),
   primary key (id),
   unique key u_strategy_reportingid (strategy,reportingid)
) engine = InnoDB;


create table t_strategyparameter (
   test_id int unsigned not null,
   name varchar(32) not null,
   value varchar(255) not null,
   primary key (test_id,name),
   index i_test_id (test_id),
   constraint fk_strategyparameter_test foreign key (test_id) references t_test (id) on delete cascade on update cascade
) engine = InnoDB;


create table t_order (
   id int unsigned not null auto_increment,
   created_utc timestamp not null default current_timestamp() comment 'GMT',
   modified_utc timestamp null default null comment 'GMT',
   ticket int unsigned not null,
   type enum('Buy','Sell') not null comment 'Buy | Sell',
   lots decimal(10,2) unsigned not null,
   symbol varchar(11) not null,
   openprice decimal(10,5) unsigned not null,
   opentime_fxt datetime not null comment 'FXT',
   stoploss decimal(10,5) unsigned,
   takeprofit decimal(10,5) unsigned,
   closeprice decimal(10,5) unsigned not null,
   closetime_fxt datetime not null comment 'FXT',
   commission decimal(10,2) not null,
   swap decimal(10,2) not null,
   profit decimal(10,2) not null comment 'gross profit',
   magicnumber int unsigned,
   comment varchar(27),
   test_id int unsigned not null,
   primary key (id),
   unique key u_signal_id_ticket (test_id,ticket),
   index i_test_id (test_id),
   constraint fk_order_test foreign key (test_id) references t_test (id) on delete restrict on update cascade
) engine = InnoDB;


create table t_result (
   id int unsigned not null,
   trades int unsigned not null,
   pips decimal(10,1) not null,
   avg_pips decimal(10,1) not null comment 'average trade profit in pip',
   avg_duration int unsigned not null comment 'average trade duration in seconds',
   profit decimal(10,2) not null comment 'test gross profit',
   commission decimal(10,2) not null comment 'test commission',
   swap decimal(10,2) not null comment 'test swap',
   primary key (id),
   constraint fk_result_test foreign key (id) references t_test (id) on delete cascade on update cascade
) engine = InnoDB;


-- trigger definitions
delimiter //

create trigger tr_test_before_update before update on t_test for each row
begin
    -- update version timestamp if not yet done by the application layer
    if (new.modified_utc = old.modified_utc || new.modified_utc is null) then
        set new.modified_utc = current_timestamp();
    end if;
end;//


create trigger tr_order_before_update before update on t_order for each row
begin
    -- update version timestamp if not yet done by the application layer
    if (new.modified_utc = old.modified_utc || new.modified_utc is null) then
        set new.modified_utc = current_timestamp();
    end if;
end;//


delimiter ;


