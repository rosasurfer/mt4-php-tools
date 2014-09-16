/*
Created     17.09.2014
Modified    17.09.2014
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


-- Daten einlesen
source myfx_data.sql;

commit;


