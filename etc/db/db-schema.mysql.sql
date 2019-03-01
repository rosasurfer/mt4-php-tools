/*
Created     16.01.2017
Modified    01.03.2019
Project     Rosatrader
Model       Rosatrader
Company
Author      Peter Walther
Version     0.2
Database    MySQL 5
*/


set sql_mode             = 'traditional,high_not_precedence';
set collation_connection = 'utf8_unicode_ci';
set autocommit           = 0;


drop database if exists rosatrader;
create database rosatrader default collate 'latin1_general_ci';
use rosatrader;


create table t_rosasymbol (
   id int unsigned not null auto_increment,
   created timestamp not null default current_timestamp(),
   modified timestamp null default null,
   type enum('forex','metals','synthetic') not null,
   group_ int unsigned not null,
   name varchar(11) not null,
   description varchar(63) not null,
   digits tinyint unsigned not null,
   autoupdate bool not null default 1,
   formula text,
   historystart_tick datetime,
   historyend_tick datetime,
   historystart_m1 datetime,
   historyend_m1 datetime,
   historystart_d1 datetime,
   historyend_d1 datetime,
   unique index u_name (name),
   primary key (id),
   index i_type (type)
) engine = InnoDB;


create table t_dukascopysymbol (
   id int unsigned not null auto_increment,
   created timestamp not null default current_timestamp(),
   modified timestamp null default null,
   name varchar(11) not null,
   digits tinyint unsigned not null,
   historystart_tick datetime,
   historystart_m1 datetime,
   historystart_h1 datetime,
   historystart_d1 datetime,
   rosasymbol_id int unsigned,
   unique index u_name (name),
   unique index u_rosasymbol (rosasymbol_id),
   primary key (id),
   constraint fk_dukascopysymbol_rosasymbol foreign key (rosasymbol_id) references t_rosasymbol (id) on delete restrict on update cascade
) engine = InnoDB;


create table t_test (
   id int unsigned not null auto_increment,
   created timestamp not null default current_timestamp(),
   modified timestamp null default null,
   strategy varchar(255) not null,
   reportingid int unsigned not null,
   reportingsymbol varchar(11) not null,
   symbol varchar(11) not null,
   timeframe int unsigned not null,
   starttime datetime not null,
   endtime datetime not null,
   barmodel enum('EveryTick','ControlPoints','BarOpen') not null,
   spread decimal(10,1) not null,
   bars int unsigned not null,
   ticks int unsigned not null,
   tradedirections enum('Long','Short','Both') not null,
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


create table t_statistic (
   id int unsigned not null auto_increment,
   trades int unsigned not null,
   trades_day decimal(10,1) not null,
   duration_min int unsigned not null,
   duration_avg int unsigned not null,
   duration_max int unsigned not null,
   pips_min decimal(10,1) not null,
   pips_avg decimal(10,1) not null,
   pips_max decimal(10,1) not null,
   pips decimal(10,1) not null,
   sharpe_ratio decimal(10,4) not null,
   sortino_ratio decimal(10,4) not null,
   calmar_ratio decimal(10,4) not null,
   max_recoverytime int unsigned not null,
   gross_profit decimal(10,2) not null,
   commission decimal(10,2) not null,
   swap decimal(10,2) not null,
   test_id int unsigned not null,
   unique index u_test_id (test_id),
   primary key (id),
   constraint fk_statistic_test foreign key (test_id) references t_test (id) on delete cascade on update cascade
) engine = InnoDB;


create table t_order (
   id int unsigned not null auto_increment,
   created timestamp not null default current_timestamp(),
   modified timestamp null default null,
   ticket int unsigned not null,
   type enum('Buy','Sell') not null,
   lots decimal(10,2) not null,
   symbol varchar(11) not null,
   openprice decimal(10,5) not null,
   opentime datetime not null,
   stoploss decimal(10,5),
   takeprofit decimal(10,5),
   closeprice decimal(10,5) not null,
   closetime datetime not null,
   commission decimal(10,2) not null,
   swap decimal(10,2) not null,
   profit decimal(10,2) not null,
   magicnumber int unsigned,
   comment varchar(27),
   test_id int unsigned not null,
   primary key (id),
   unique key u_test_id_ticket (test_id,ticket),
   index i_type (type),
   index i_test_id (test_id),
   constraint fk_order_test foreign key (test_id) references t_test (id) on delete restrict on update cascade
) engine = InnoDB;


-- trigger definitions
delimiter //

create trigger tr_rosasymbol_before_update before update on t_rosasymbol for each row
begin
   -- update version timestamp if not yet done by the application layer
   if (new.modified = old.modified or new.modified is null) then
      set new.modified = current_timestamp();
   end if;
end;//

create trigger tr_dukascopysymbol_before_update before update on t_dukascopysymbol for each row
begin
   -- update version timestamp if not yet done by the application layer
   if (new.modified = old.modified or new.modified is null) then
      set new.modified = current_timestamp();
   end if;
end;//

create trigger tr_test_before_update before update on t_test for each row
begin
   -- update version timestamp if not yet done by the application layer
   if (new.modified = old.modified or new.modified is null) then
      set new.modified = current_timestamp();
   end if;
end;//

create trigger tr_order_before_update before update on t_order for each row
begin
   -- update version timestamp if not yet done by the application layer
   if (new.modified = old.modified or new.modified is null) then
      set new.modified = current_timestamp();
   end if;
end;//

delimiter ;


-- seed the database (skipped as seeding is DB specific now)
-- source db-seed.sql;

commit;


