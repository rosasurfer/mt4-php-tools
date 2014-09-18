/*
Created     17.09.2014
Modified    18.09.2014
Project     myfx
Model       main model
Company     pewasoft
Author      Peter Walther
Version     0.1
Database    MySQL 5
*/


set sql_mode             = 'traditional';
set collation_connection = 'latin1_german1_ci';
set autocommit           = 0;

drop database if exists myfx;
create database myfx character set latin1;
use myfx;


create table t_signal (
   id int unsigned not null auto_increment,
   version timestamp not null default current_timestamp on update current_timestamp,
   created datetime not null,
   refid int not null,
   alias varchar(20) not null,
   name varchar(100) not null,
   currency enum('AUD','CAD','CHF','EUR','GBP','JPY','NZD','USD') not null,
   unique index u_refid (refid),
   unique index u_alias (alias),
   unique index u_name (name),
   primary key (id)
) engine = InnoDB;


create table t_openposition (
   id int unsigned not null auto_increment,
   version timestamp not null default current_timestamp on update current_timestamp,
   created datetime not null,
   ticket varchar(50) not null,
   type enum('buy','sell') not null comment 'buy | sell',
   lots decimal(10,2) unsigned not null,
   symbol char(11) not null,
   opentime datetime not null comment 'FXT',
   openprice decimal(10,5) unsigned not null,
   stoploss decimal(10,5),
   takeprofit decimal(10,5),
   commission decimal(10,2) not null default 0.00,
   swap decimal(10,2) not null default 0.00,
   magicnumber int unsigned,
   comment varchar(255) default '',
   signal_id int unsigned not null,
   primary key (id),
   unique key u_signal_id_ticket (signal_id,ticket),
   index i_opentime_ticket (opentime,ticket),
   index i_signal_id (signal_id),
   constraint openposition_signal_id foreign key (signal_id) references t_signal (id) on delete restrict on update cascade
) engine = InnoDB;


create table t_closedposition (
   id int unsigned not null auto_increment,
   version timestamp not null default current_timestamp on update current_timestamp,
   created datetime not null,
   ticket varchar(50) not null,
   type enum('buy','sell') not null comment 'buy | sell',
   lots decimal(10,2) unsigned not null,
   symbol char(11) not null,
   opentime datetime not null comment 'FXT',
   openprice decimal(10,5) unsigned not null,
   closetime datetime not null comment 'FXT',
   closeprice decimal(10,5) unsigned not null,
   stoploss decimal(10,5),
   takeprofit decimal(10,5),
   commission decimal(10,2) not null default 0.00,
   swap decimal(10,2) not null default 0.00,
   profit decimal(10,2) not null default 0.00,
   magicnumber int unsigned,
   comment varchar(255) not null default '',
   signal_id int unsigned not null,
   primary key (id),
   unique key u_signal_id_ticket (signal_id,ticket),
   index i_opentime (opentime),
   index i_closetime (closetime),
   index i_opentime_closetime (opentime,closetime),
   index i_closetime_opentime_ticket (closetime,opentime,ticket),
   index i_signal_id (signal_id),
   constraint closedposition_signal_id foreign key (signal_id) references t_signal (id) on delete restrict on update cascade
) engine = InnoDB;


-- Trigger definitions
delimiter //

delimiter ;


-- Daten einlesen
source myfx_data.sql;

commit;


