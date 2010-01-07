/*
Created     23.05.2009
Modified    15.10.2009
Project     FxTrader
Model       
Company     
Author      Peter Walther
Version     0.1
Database    MySQL 5
*/


drop database if exists fxtrader;
create database fxtrader;
use fxtrader;


create table t_account (
   id int unsigned not null auto_increment,
   created datetime not null,
   name varchar(100) not null,
   currency char(3) not null comment 'Kontowährung',
   commission decimal(7,6) unsigned not null default 0 comment 'Rate je Einheit der Kontowährung',
   primary key (id)
) engine = InnoDB;


create table t_execution (
   id int unsigned not null auto_increment,
   created datetime not null,
   strategy varchar(100) not null default '' comment 'System, das das Signal ausgelöst hat',
   type enum('b','s') not null comment 'buy/sell',
   size int unsigned not null,
   symbol char(7) not null,
   price decimal(7,4) unsigned not null,
   commission decimal(10,2) unsigned not null default 0 comment 'denormalisiert (leitet sich aus der Menge, dem Konto und ggf. dem Instrument ab)',
   account_id int unsigned not null,
   primary key (id),
   index i_account_id (account_id),
   constraint execution_account_id foreign key (account_id) references t_account (id) on delete restrict on update cascade
) engine = InnoDB;


create table t_trade (
   id int unsigned not null auto_increment,
   created datetime not null,
   symbol char(7) not null,
   type enum('l','s') not null comment 'long/short',
   size int unsigned not null,
   opened datetime not null,
   openprice decimal(7,4) unsigned not null,
   closed datetime,
   closeprice decimal(7,4) unsigned,
   commission decimal(10,2) unsigned not null default 0 comment 'denormalisiert (leitet sich aus der Menge, dem Konto und ggf. dem Instrument ab)',
   comment varchar(100),
   account_id int unsigned not null,
   primary key (id),
   index i_account_id (account_id),
   constraint trade_account_id foreign key (account_id) references t_account (id) on delete restrict on update cascade
) engine = InnoDB;


create table t_ticks (
   id int unsigned not null auto_increment,
   margin decimal(10,2) unsigned not null,
   freemargin decimal(10,2) unsigned not null,
   datetime datetime not null,
   ask decimal(10,5) unsigned not null,
   bid decimal(10,5) unsigned not null,
   symbol varchar(20) not null,
   equity decimal(10,2) unsigned not null,
   primary key (id)
) engine = InnoDB;


-- Daten einlesen
source data.sql;

commit;


