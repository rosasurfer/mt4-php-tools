/*
Created     23.05.2009
Modified    30.01.2011
Project     Forex web
Model       
Company     
Author      Peter Walther
Version     
Database    MySQL 5
*/


drop database if exists fxtrader;
create database fxtrader character set latin1;
use fxtrader;


create table t_account (
   id int unsigned not null auto_increment,
   created datetime not null,
   company varchar(100) not null comment 'account company',
   type enum('demo','live') not null comment 'demo | live',
   number int unsigned not null comment 'account number',
   timezone varchar(50) not null comment 'format: timezone_name[+/-offset]',
   currency char(3) not null comment 'base currency',
   mtiaccount_id varchar(50) comment 'MTi account id',
   primary key (id),
   unique key u_company_number (company,number),
   unique key u_mtiaccount_id (mtiaccount_id)
) engine = InnoDB;


create table t_transaction (
   id int unsigned not null auto_increment,
   created datetime not null,
   ticket int unsigned not null,
   type enum('buy','sell','vendormatching','transfer') not null comment 'buy | sell | vendor matching | transfer',
   units int unsigned not null comment 'traded units (not lots)',
   symbol char(12),
   opentime datetime not null,
   openprice decimal(10,5) unsigned not null,
   openslippage decimal(4,1) not null,
   closetime datetime not null,
   closeprice decimal(10,5) unsigned not null,
   closeslippage decimal(4,1) not null,
   commission decimal(10,2) not null,
   swap decimal(10,2) not null,
   netprofit decimal(10,2) not null,
   grossprofit decimal(10,2) not null,
   result enum('win','loss','breakeven','n/a') not null comment 'win | loss | breakeven | n/a',
   pips decimal(5,1) not null comment 'normalized result',
   duration int unsigned not null comment 'trade duration in minutes',
   magicnumber int unsigned,
   comment varchar(255) not null,
   account_id int unsigned not null,
   primary key (id),
   unique key u_account_id_ticket (account_id,ticket),
   index i_account_id (account_id),
   constraint transaction_account_id foreign key (account_id) references t_account (id) on delete restrict on update cascade
) engine = InnoDB;


-- Daten einlesen
source data.sql;

commit;


