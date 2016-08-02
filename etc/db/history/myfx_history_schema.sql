/*
Created     26.07.2015
Modified    26.07.2015
Project     MyFX Quote History
Model
Company
Author      Peter Walther
Version     0.1
Database    MySQL 5
*/


set sql_mode             = 'traditional';
set autocommit           = 0;
set collation_connection = 'latin1_general_ci';

drop database if exists myfx_history;
create database myfx_history character set latin1 collate latin1_general_ci;
use myfx_history;


create table t_instrument (
   id int unsigned not null auto_increment,
   version timestamp not null default current_timestamp on update current_timestamp,
   created datetime not null,
   symbol varchar(11) not null,
   unique index u_symbol (symbol),
   primary key (id)
) engine = InnoDB;


-- Trigger definitions
delimiter //

delimiter ;


-- Daten einlesen
source myfx_history_seed.sql;

commit;


