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
set collation_connection = 'latin1_german1_ci';
set autocommit           = 0;

drop database if exists myfx_history;
create database myfx_history character set latin1;
use myfx_history;


-- Daten einlesen
source myfx_history_data.sql;

commit;


