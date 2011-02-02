/*
Created     23.05.2009
Modified    02.02.2011
Project     MyFX
Model       
Company     
Author      Peter Walther
Version     
Database    MySQL 5
*/


set sql_mode             = 'traditional';
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
   balance decimal(10,2) not null default 0.00 comment 'aktueller Kontostand (read-only)',
   lastupdated datetime comment 'Zeitpunkt des letzten History-Updates (read-only)',
   mtiaccount_id varchar(50) comment 'MTi User-ID',
   primary key (id),
   unique key u_company_number (company,number)
) engine = InnoDB;


create table t_transaction (
   id int unsigned not null auto_increment,
   version timestamp not null default current_timestamp on update current_timestamp,
   created datetime comment '(read-only)',
   ticket varchar(50) not null,
   type enum('buy','sell','transfer','vendor') not null comment 'buy | sell | transfer | vendor',
   units int unsigned not null comment 'traded units (not lots)',
   symbol char(12),
   opentime datetime not null comment 'timezone: America/New_York+0700',
   openprice decimal(10,5) unsigned,
   closetime datetime not null comment 'timezone: America/New_York+0700',
   closeprice decimal(10,5) unsigned,
   commission decimal(10,2) not null default 0.00,
   swap decimal(10,2) not null default 0.00,
   netprofit decimal(10,2) not null default 0.00,
   grossprofit decimal(10,2) comment 'commission + swap + netprofit (read-only)',
   result enum('win','loss','breakeven','n/a') comment 'win | loss | breakeven | n/a (read-only)',
   pips decimal(5,1) comment 'normalized result (read-only)',
   duration int unsigned comment 'trade duration in minutes (read-only)',
   magicnumber int unsigned,
   comment varchar(255) not null default '',
   account_id int unsigned not null,
   primary key (id),
   unique key u_account_id_ticket (account_id,ticket),
   index i_closetime (closetime),
   index i_closetime_opentime_ticket (closetime,opentime,ticket),
   index i_account_id (account_id),
   constraint transaction_account_id foreign key (account_id) references t_account (id) on delete restrict on update cascade
) engine = InnoDB;


-- Trigger definitions
delimiter //

create trigger tr_transaction_before_insert before insert on t_transaction for each row
begin
   if (new.created     is not null          ) then call ERROR_TRANSACTION_CREATED_IS_READONLY();      end if;
   if (new.grossprofit is not null          ) then call ERROR_TRANSACTION_GROSSPROFIT_IS_READONLY();  end if;
   if (new.result      is not null          ) then call ERROR_TRANSACTION_RESULT_IS_READONLY();       end if;
   if (new.pips        is not null          ) then call ERROR_TRANSACTION_PIPS_IS_READONLY();         end if;
   if (new.duration    is not null          ) then call ERROR_TRANSACTION_DURATION_IS_READONLY();     end if;

   if (new.ticket <= 0                      ) then call ERROR_INVALID_TRANSACTION_TICKET();           end if;
   if (new.type = 0                         ) then call ERROR_INVALID_TRANSACTION_TYPE();             end if;
   if (new.opentime  = '0000-00-00 00:00:00') then call ERROR_INVALID_TRANSACTION_OPENTIME();         end if;
   if (new.closetime = '0000-00-00 00:00:00') then call ERROR_INVALID_TRANSACTION_CLOSETIME();        end if;
   if (new.opentime > new.closetime         ) then call ERROR_INVALID_TRANSACTION_OPEN_CLOSE_TIMES(); end if;

   set new.created     = now();
   set new.grossprofit = new.commission + new.swap + new.netprofit;

   if (new.type='buy' || new.type='sell') then
      if (length(ifNull(new.symbol, '')) < 3) then call ERROR_INVALID_TRANSACTION_SYMBOL();      end if;
      if (new.units <= 0                    ) then call ERROR_INVALID_TRANSACTION_UNITS();       end if;
      if (ifNull(new.openprice, 0) <= 0     ) then call ERROR_INVALID_TRANSACTION_OPENPRICE();   end if;
      if (ifNull(new.closeprice, 0) <= 0    ) then call ERROR_INVALID_TRANSACTION_CLOSEPRICE();  end if;
      if (new.magicnumber <= 0              ) then call ERROR_INVALID_TRANSACTION_MAGICNUMBER(); end if;

      set new.result   = if(new.grossprofit < 0, 'loss', if(new.grossprofit > 0, 'win', 'breakeven'));
      set new.duration = ceil((new.closetime - new.opentime)/60);
   else
      if (length(new.symbol) > 0       ) then call ERROR_INVALID_TRANSACTION_SYMBOL();           end if;
      if (new.units != 0               ) then call ERROR_INVALID_TRANSACTION_UNITS();            end if;
      if (new.opentime != new.closetime) then call ERROR_INVALID_TRANSACTION_OPEN_CLOSE_TIMES(); end if;
      if (new.openprice   is not null  ) then call ERROR_INVALID_TRANSACTION_OPENPRICE();        end if;
      if (new.closeprice  is not null  ) then call ERROR_INVALID_TRANSACTION_CLOSEPRICE();       end if;
      if (new.magicnumber is not null  ) then call ERROR_INVALID_TRANSACTION_MAGICNUMBER();      end if;

      set new.result   = 'n/a';
      set new.duration = 0;
   end if;
end;//


create trigger tr_transaction_after_insert after insert on t_transaction for each row
begin
   update t_account
      set balance     = balance + new.grossprofit,
          lastupdated = new.created
      where id = new.account_id;
end;//


create trigger tr_transaction_after_update after update on t_transaction for each row
begin
   -- EventTime ist 'after update' und nicht 'before', damit "INSERT ... ON DUPLICATE KEY UPDATE {do nothing}"-Statements den Trigger nicht unnötig auslösen.
   if (       old.created        !=ifNull(new.created    ,'')) then call ERROR_TRANSACTION_CREATED_IS_READ_ONLY();     end if;
   if (       old.ticket         !=ifNull(new.ticket     ,'')) then call ERROR_TRANSACTION_TICKET_IS_READ_ONLY();      end if;
   if (       old.units          !=ifNull(new.units      ,'')) then call ERROR_TRANSACTION_UNITS_IS_READ_ONLY();       end if;
   if (ifNull(old.symbol     ,'')!=ifNull(new.symbol     ,'')) then call ERROR_TRANSACTION_SYMBOL_IS_READ_ONLY();      end if;
   if (ifNull(old.opentime   ,'')!=ifNull(new.opentime   ,'')) then call ERROR_TRANSACTION_OPENTIME_IS_READ_ONLY();    end if;
   if (ifNull(old.openprice  ,'')!=ifNull(new.openprice  ,'')) then call ERROR_TRANSACTION_OPENPRICE_IS_READ_ONLY();   end if;
   if (ifNull(old.closetime  ,'')!=ifNull(new.closetime  ,'')) then call ERROR_TRANSACTION_CLOSETIME_IS_READ_ONLY();   end if;
   if (ifNull(old.closeprice ,'')!=ifNull(new.closeprice ,'')) then call ERROR_TRANSACTION_CLOSEPRICE_IS_READ_ONLY();  end if;
   if (       old.commission     !=ifNull(new.commission ,'')) then call ERROR_TRANSACTION_COMMISSION_IS_READ_ONLY();  end if;
   if (       old.swap           !=ifNull(new.swap       ,'')) then call ERROR_TRANSACTION_SWAP_IS_READ_ONLY();        end if;
   if (       old.netprofit      !=ifNull(new.netprofit  ,'')) then call ERROR_TRANSACTION_NETPROFIT_IS_READ_ONLY();   end if;
   if (       old.grossprofit    !=ifNull(new.grossprofit,'')) then call ERROR_TRANSACTION_GROSSPROFIT_IS_READ_ONLY(); end if;
   if (       old.result         !=ifNull(new.result     ,'')) then call ERROR_TRANSACTION_RESULT_IS_READ_ONLY();      end if;
   if (ifNull(old.pips       ,'')!=ifNull(new.pips       ,'')) then call ERROR_TRANSACTION_PIPS_IS_READ_ONLY();        end if;
   if (       old.duration       !=ifNull(new.duration   ,'')) then call ERROR_TRANSACTION_DURATION_IS_READ_ONLY();    end if;
   if (ifNull(old.magicnumber,'')!=ifNull(new.magicnumber,'')) then call ERROR_TRANSACTION_MAGICNUMBER_IS_READ_ONLY(); end if;
   if (       old.account_id     !=       new.account_id     ) then call ERROR_TRANSACTION_ACCOUNT_ID_IS_READ_ONLY();  end if;

   -- zulässige Änderungen sind:
   -- (1) type='transfer' => 'vendor' und zurück und (2) comment (wird nicht extra geprüft)
   if (old.type!=ifNull(new.type,'')) then
      if (new.type is null || old.type not in ('transfer','vendor') || new.type not in ('transfer','vendor')) then
         call ERROR_ILLEGAL_TRANSACTION_TYPE_CHANGE();
      end if;
   end if;
end;//


create trigger tr_transaction_before_delete before delete on t_transaction for each row
begin
   call ERROR_TRANSACTIONS_ARE_READ_ONLY();
end;//


create trigger tr_transaction_after_delete after delete on t_transaction for each row
begin
   update t_account
      set balance     = balance - old.grossprofit,
          lastupdated = now()
      where id = old.account_id;
end;//


delimiter ;


-- Daten einlesen
source data.sql;

commit;


