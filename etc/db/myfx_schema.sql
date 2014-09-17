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


create table t_account (
   id int unsigned not null auto_increment,
   version timestamp not null default current_timestamp on update current_timestamp,
   created datetime,
   company varchar(100) not null,
   name varchar(100) not null,
   number varchar(50) not null comment 'Kontonummer',
   currency enum('AUD','CAD','CHF','EUR','GBP','JPY','NZD','USD') not null comment 'Kontowährung',
   primary key (id),
   unique key u_company_number (company,number)
) engine = InnoDB;


create table t_openposition (
   id int unsigned not null auto_increment,
   version timestamp not null default current_timestamp on update current_timestamp,
   created datetime,
   ticket varchar(50) not null,
   type enum('buy','sell') not null comment 'buy | sell',
   units int unsigned not null comment 'traded units (not lots)',
   symbol char(11),
   opentime datetime not null comment 'timezone: FXT',
   openprice decimal(10,5) unsigned not null,
   commission decimal(10,2) not null default 0.00,
   swap decimal(10,2) not null default 0.00,
   magicnumber int unsigned,
   comment varchar(255) not null default '',
   account_id int unsigned not null,
   primary key (id),
   unique key u_account_id_ticket (account_id,ticket),
   index i_opentime_ticket (opentime,ticket),
   index i_account_id (account_id),
   constraint openposition_account_id foreign key (account_id) references t_account (id) on delete restrict on update cascade
) engine = InnoDB;


create table t_closedposition (
   id int unsigned not null auto_increment,
   version timestamp not null default current_timestamp on update current_timestamp,
   created datetime,
   ticket varchar(50) not null,
   type enum('buy','sell') not null comment 'buy | sell',
   units int unsigned not null comment 'traded units (not lots)',
   symbol char(11),
   opentime datetime not null comment 'timezone: FXT',
   openprice decimal(10,5) unsigned not null,
   closetime datetime not null comment 'timezone: FXT',
   closeprice decimal(10,5) unsigned not null,
   commission decimal(10,2) not null default 0.00,
   swap decimal(10,2) not null default 0.00,
   profit decimal(10,2) not null default 0.00,
   magicnumber int unsigned,
   comment varchar(255) not null default '',
   account_id int unsigned not null,
   primary key (id),
   unique key u_account_id_ticket (account_id,ticket),
   index i_opentime (opentime),
   index i_closetime (closetime),
   index i_opentime_closetime (opentime,closetime),
   index i_closetime_opentime_ticket (closetime,opentime,ticket),
   index i_account_id (account_id),
   constraint closedposition_account_id foreign key (account_id) references t_account (id) on delete restrict on update cascade
) engine = InnoDB;


-- Trigger definitions
delimiter //

delimiter ;


-- Daten einlesen
source myfx_data.sql;

commit;


