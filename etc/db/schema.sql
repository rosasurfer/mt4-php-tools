/*
Created     23.05.2009
Modified    01.02.2011
Project     MyFX
Model
Company
Author      Peter Walther
Version
Database    MySQL 5
*/


set sql_mode             = 'TRADITIONAL';
set collation_connection = 'latin1_german1_ci';

drop database if exists myfx;
create database myfx character set latin1;
use myfx;


create table t_account (
   id int unsigned not null auto_increment,
   version timestamp not null default current_timestamp on update current_timestamp,
   created datetime not null,
   company varchar(100) not null comment 'the criminal',
   number int unsigned not null comment 'Kontonummer',
   demo tinyint(1) unsigned not null comment 'TRUE=demo, FALSE=live',
   type enum('MT4','FIX') not null comment 'MT4 | FIX',
   timezone varchar(100) not null comment 'Tradeserverzeitzone',
   currency char(3) not null comment 'Kontowährung',
   balance decimal(10,2) not null default 0.00 comment 'aktueller Kontostand',
   mti_account_id varchar(50) comment 'MTi Account-ID',
   primary key (id),
   unique key u_company_number (company,number),
   unique key u_mtiaccount_id (mti_account_id)
) engine = InnoDB;


create table t_transaction (
   id int unsigned not null auto_increment,
   version timestamp not null default current_timestamp on update current_timestamp,
   created datetime comment 'read-only',
   ticket varchar(50) not null,
   type enum('buy','sell','transfer','vendormatching') not null comment 'buy | sell | transfer | vendormatching',
   units int unsigned not null comment 'traded units (not lots)',
   symbol char(12),
   opentime datetime not null comment 'timezone: America/New_York+0700',
   openprice decimal(10,5) unsigned,
   closetime datetime not null comment 'timezone: America/New_York+0700',
   closeprice decimal(10,5) unsigned,
   commission decimal(10,2) not null default 0.00,
   swap decimal(10,2) not null default 0.00,
   netprofit decimal(10,2) not null default 0.00,
   grossprofit decimal(10,2) comment 'read-only: commission + swap + netprofit',
   result enum('win','loss','breakeven','n/a') comment 'read-only: win | loss | breakeven | n/a',
   pips decimal(5,1) comment 'read-only: normalized result',
   duration int unsigned comment 'read-only: trade duration in minutes',
   magicnumber int unsigned,
   comment varchar(255) not null default '',
   account_id int unsigned not null,
   primary key (id),
   unique key u_account_id_ticket (account_id,ticket),
   index i_account_id (account_id),
   constraint transaction_account_id foreign key (account_id) references t_account (id) on delete restrict on update cascade
) engine = InnoDB;


-- Trigger definitions
delimiter //

create trigger tr_transaction_before_insert before insert on t_transaction for each row
begin
   if (new.created     is not null ) then call ERROR_TRANSACTION_CREATED_IS_READONLY();      end if;
   if (new.grossprofit is not null ) then call ERROR_TRANSACTION_GROSSPROFIT_IS_READONLY();  end if;
   if (new.result      is not null ) then call ERROR_TRANSACTION_RESULT_IS_READONLY();       end if;
   if (new.pips        is not null ) then call ERROR_TRANSACTION_PIPS_IS_READONLY();         end if;
   if (new.duration    is not null ) then call ERROR_TRANSACTION_DURATION_IS_READONLY();     end if;

   if (new.ticket <= 0             ) then call ERROR_INVALID_TRANSACTION_TICKET();           end if;
   if (new.type    = 0             ) then call ERROR_INVALID_TRANSACTION_TYPE();             end if;
   if (new.opentime > new.closetime) then call ERROR_INVALID_TRANSACTION_OPEN_CLOSE_TIMES(); end if;

   set new.created     = now();
   set new.grossprofit = new.commission + new.swap + new.netprofit;

   if (new.type='buy' || new.type='sell') then
      if (new.units <= 0                               ) then call ERROR_INVALID_TRANSACTION_UNITS();       end if;
      if (new.openprice  is null || new.openprice  <= 0) then call ERROR_INVALID_TRANSACTION_OPENPRICE();   end if;
      if (new.closeprice is null || new.closeprice <= 0) then call ERROR_INVALID_TRANSACTION_CLOSEPRICE();  end if;
      if (new.magicnumber <= 0                         ) then call ERROR_INVALID_TRANSACTION_MAGICNUMBER(); end if;

      set new.result   = if(new.grossprofit < 0, 'loss', if(new.grossprofit > 0, 'win', 'breakeven'));
      set new.duration = ceil((new.closetime - new.opentime)/60);
   else
      if (new.units != 0               ) then call ERROR_INVALID_TRANSACTION_UNITS();            end if;
      if (new.opentime != new.closetime) then call ERROR_INVALID_TRANSACTION_OPEN_CLOSE_TIMES(); end if;
      if (new.openprice   is not null  ) then call ERROR_INVALID_TRANSACTION_OPENPRICE();        end if;
      if (new.closeprice  is not null  ) then call ERROR_INVALID_TRANSACTION_CLOSEPRICE();       end if;
      if (new.magicnumber is not null  ) then call ERROR_INVALID_TRANSACTION_MAGICNUMBER();      end if;

      set new.result   = 'n/a';
      set new.duration = 0;
   end if;
end;//


delimiter ;


-- Daten einlesen
source data.sql;

commit;


