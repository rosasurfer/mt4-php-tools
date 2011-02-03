/*
Created     23.05.2009
Modified    03.02.2011
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
   created datetime comment '(read-only)',
   company varchar(100) not null comment 'the criminal',
   number varchar(50) not null comment 'Kontonummer',
   demo tinyint(1) unsigned not null comment 'TRUE=demo, FALSE=live',
   type enum('MT4','FIX') not null comment 'MT4 | FIX',
   timezone enum('Africa/Abidjan','Africa/Accra','Africa/Addis_Ababa','Africa/Algiers','Africa/Asmera','Africa/Bamako','Africa/Bangui','Africa/Banjul','Africa/Bissau','Africa/Blantyre','Africa/Brazzaville','Africa/Bujumbura','Africa/Cairo','Africa/Casablanca','Africa/Ceuta','Africa/Conakry','Africa/Dakar','Africa/Dar_es_Salaam','Africa/Djibouti','Africa/Douala','Africa/El_Aaiun','Africa/Freetown','Africa/Gaborone','Africa/Harare','Africa/Johannesburg','Africa/Kampala','Africa/Khartoum','Africa/Kigali','Africa/Kinshasa','Africa/Lagos','Africa/Libreville','Africa/Lome','Africa/Luanda','Africa/Lubumbashi','Africa/Lusaka','Africa/Malabo','Africa/Maputo','Africa/Maseru','Africa/Mbabane','Africa/Mogadishu','Africa/Monrovia','Africa/Nairobi','Africa/Ndjamena','Africa/Niamey','Africa/Nouakchott','Africa/Ouagadougou','Africa/Porto-Novo','Africa/Sao_Tome','Africa/Timbuktu','Africa/Tripoli','Africa/Tunis','Africa/Windhoek','America/Adak','America/Anchorage','America/Anguilla','America/Antigua','America/Araguaina','America/Argentina/Buenos_Aires','America/Argentina/Catamarca','America/Argentina/ComodRivadavia','America/Argentina/Cordoba','America/Argentina/Jujuy','America/Argentina/La_Rioja','America/Argentina/Mendoza','America/Argentina/Rio_Gallegos','America/Argentina/San_Juan','America/Argentina/Tucuman','America/Argentina/Ushuaia','America/Aruba','America/Asuncion','America/Atikokan','America/Atka','America/Bahia','America/Barbados','America/Belem','America/Belize','America/Blanc-Sablon','America/Boa_Vista','America/Bogota','America/Boise','America/Buenos_Aires','America/Cambridge_Bay','America/Campo_Grande','America/Cancun','America/Caracas','America/Catamarca','America/Cayenne','America/Cayman','America/Chicago','America/Chihuahua','America/Coral_Harbour','America/Cordoba','America/Costa_Rica','America/Cuiaba','America/Curacao','America/Danmarkshavn','America/Dawson','America/Dawson_Creek','America/Denver','America/Detroit','America/Dominica','America/Edmonton','America/Eirunepe','America/El_Salvador','America/Ensenada','America/Fort_Wayne','America/Fortaleza','America/Glace_Bay','America/Godthab','America/Goose_Bay','America/Grand_Turk','America/Grenada','America/Guadeloupe','America/Guatemala','America/Guayaquil','America/Guyana','America/Halifax','America/Havana','America/Hermosillo','America/Indiana/Indianapolis','America/Indiana/Knox','America/Indiana/Marengo','America/Indiana/Petersburg','America/Indiana/Vevay','America/Indiana/Vincennes','America/Indianapolis','America/Inuvik','America/Iqaluit','America/Jamaica','America/Jujuy','America/Juneau','America/Kentucky/Louisville','America/Kentucky/Monticello','America/Knox_IN','America/La_Paz','America/Lima','America/Los_Angeles','America/Louisville','America/Maceio','America/Managua','America/Manaus','America/Martinique','America/Mazatlan','America/Mendoza','America/Menominee','America/Merida','America/Mexico_City','America/Miquelon','America/Moncton','America/Monterrey','America/Montevideo','America/Montreal','America/Montserrat','America/Nassau','America/New_York','America/Nipigon','America/Nome','America/Noronha','America/North_Dakota/Center','America/North_Dakota/New_Salem','America/Panama','America/Pangnirtung','America/Paramaribo','America/Phoenix','America/Port-au-Prince','America/Port_of_Spain','America/Porto_Acre','America/Porto_Velho','America/Puerto_Rico','America/Rainy_River','America/Rankin_Inlet','America/Recife','America/Regina','America/Rio_Branco','America/Rosario','America/Santiago','America/Santo_Domingo','America/Sao_Paulo','America/Scoresbysund','America/Shiprock','America/St_Johns','America/St_Kitts','America/St_Lucia','America/St_Thomas','America/St_Vincent','America/Swift_Current','America/Tegucigalpa','America/Thule','America/Thunder_Bay','America/Tijuana','America/Toronto','America/Tortola','America/Vancouver','America/Virgin','America/Whitehorse','America/Winnipeg','America/Yakutat','America/Yellowknife','Antarctica/Casey','Antarctica/Davis','Antarctica/DumontDUrville','Antarctica/Mawson','Antarctica/McMurdo','Antarctica/Palmer','Antarctica/Rothera','Antarctica/South_Pole','Antarctica/Syowa','Antarctica/Vostok','Arctic/Longyearbyen','Asia/Aden','Asia/Almaty','Asia/Amman','Asia/Anadyr','Asia/Aqtau','Asia/Aqtobe','Asia/Ashgabat','Asia/Ashkhabad','Asia/Baghdad','Asia/Bahrain','Asia/Baku','Asia/Bangkok','Asia/Beirut','Asia/Bishkek','Asia/Brunei','Asia/Calcutta','Asia/Choibalsan','Asia/Chongqing','Asia/Chungking','Asia/Colombo','Asia/Dacca','Asia/Damascus','Asia/Dhaka','Asia/Dili','Asia/Dubai','Asia/Dushanbe','Asia/Gaza','Asia/Harbin','Asia/Hong_Kong','Asia/Hovd','Asia/Irkutsk','Asia/Istanbul','Asia/Jakarta','Asia/Jayapura','Asia/Jerusalem','Asia/Kabul','Asia/Kamchatka','Asia/Karachi','Asia/Kashgar','Asia/Katmandu','Asia/Krasnoyarsk','Asia/Kuala_Lumpur','Asia/Kuching','Asia/Kuwait','Asia/Macao','Asia/Macau','Asia/Magadan','Asia/Makassar','Asia/Manila','Asia/Muscat','Asia/Nicosia','Asia/Novosibirsk','Asia/Omsk','Asia/Oral','Asia/Phnom_Penh','Asia/Pontianak','Asia/Pyongyang','Asia/Qatar','Asia/Qyzylorda','Asia/Rangoon','Asia/Riyadh','Asia/Saigon','Asia/Sakhalin','Asia/Samarkand','Asia/Seoul','Asia/Shanghai','Asia/Singapore','Asia/Taipei','Asia/Tashkent','Asia/Tbilisi','Asia/Tehran','Asia/Tel_Aviv','Asia/Thimbu','Asia/Thimphu','Asia/Tokyo','Asia/Ujung_Pandang','Asia/Ulaanbaatar','Asia/Ulan_Bator','Asia/Urumqi','Asia/Vientiane','Asia/Vladivostok','Asia/Yakutsk','Asia/Yekaterinburg','Asia/Yerevan','Atlantic/Azores','Atlantic/Bermuda','Atlantic/Canary','Atlantic/Cape_Verde','Atlantic/Faeroe','Atlantic/Jan_Mayen','Atlantic/Madeira','Atlantic/Reykjavik','Atlantic/South_Georgia','Atlantic/St_Helena','Atlantic/Stanley','Australia/ACT','Australia/Adelaide','Australia/Brisbane','Australia/Broken_Hill','Australia/Canberra','Australia/Currie','Australia/Darwin','Australia/Hobart','Australia/LHI','Australia/Lindeman','Australia/Lord_Howe','Australia/Melbourne','Australia/NSW','Australia/North','Australia/Perth','Australia/Queensland','Australia/South','Australia/Sydney','Australia/Tasmania','Australia/Victoria','Australia/West','Australia/Yancowinna','Europe/Amsterdam','Europe/Andorra','Europe/Athens','Europe/Belfast','Europe/Belgrade','Europe/Berlin','Europe/Bratislava','Europe/Brussels','Europe/Bucharest','Europe/Budapest','Europe/Chisinau','Europe/Copenhagen','Europe/Dublin','Europe/Gibraltar','Europe/Guernsey','Europe/Helsinki','Europe/Isle_of_Man','Europe/Istanbul','Europe/Jersey','Europe/Kaliningrad','Europe/Kiev','Europe/Lisbon','Europe/Ljubljana','Europe/London','Europe/Luxembourg','Europe/Madrid','Europe/Malta','Europe/Mariehamn','Europe/Minsk','Europe/Monaco','Europe/Moscow','Europe/Nicosia','Europe/Oslo','Europe/Paris','Europe/Prague','Europe/Riga','Europe/Rome','Europe/Samara','Europe/San_Marino','Europe/Sarajevo','Europe/Simferopol','Europe/Skopje','Europe/Sofia','Europe/Stockholm','Europe/Tallinn','Europe/Tirane','Europe/Tiraspol','Europe/Uzhgorod','Europe/Vaduz','Europe/Vatican','Europe/Vienna','Europe/Vilnius','Europe/Volgograd','Europe/Warsaw','Europe/Zagreb','Europe/Zaporozhye','Europe/Zurich','GMT','Indian/Antananarivo','Indian/Chagos','Indian/Christmas','Indian/Cocos','Indian/Comoro','Indian/Kerguelen','Indian/Mahe','Indian/Maldives','Indian/Mauritius','Indian/Mayotte','Indian/Reunion','Pacific/Apia','Pacific/Auckland','Pacific/Chatham','Pacific/Easter','Pacific/Efate','Pacific/Enderbury','Pacific/Fakaofo','Pacific/Fiji','Pacific/Funafuti','Pacific/Galapagos','Pacific/Gambier','Pacific/Guadalcanal','Pacific/Guam','Pacific/Honolulu','Pacific/Johnston','Pacific/Kiritimati','Pacific/Kosrae','Pacific/Kwajalein','Pacific/Majuro','Pacific/Marquesas','Pacific/Midway','Pacific/Nauru','Pacific/Niue','Pacific/Norfolk','Pacific/Noumea','Pacific/Pago_Pago','Pacific/Palau','Pacific/Pitcairn','Pacific/Ponape','Pacific/Port_Moresby','Pacific/Rarotonga','Pacific/Saipan','Pacific/Samoa','Pacific/Tahiti','Pacific/Tarawa','Pacific/Tongatapu','Pacific/Truk','Pacific/Wake','Pacific/Wallis','Pacific/Yap','UTC') not null comment 'Tradeserverzeitzone',
   currency enum('AUD','CAD','CHF','CZK','DKK','EUR','GBP','HKD','HUF','JPY','MXN','NOK','NZD','PLN','RUR','SEK','SGD','USD','ZAR') not null comment 'Kontowährung',
   balance decimal(10,2) comment 'aktueller Kontostand (read-only)',
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

create trigger tr_account_before_insert before insert on t_account for each row
begin
   if (new.created     is not null) then call ERROR_ACCOUNT_CREATED_IS_READONLY();     end if;
   if (new.balance     is not null) then call ERROR_ACCOUNT_BALANCE_IS_READONLY();     end if;
   if (new.lastupdated is not null) then call ERROR_ACCOUNT_LASTUPDATED_IS_READONLY(); end if;

   set new.company = trim(new.company);
   if (new.company = ''           ) then call ERROR_INVALID_ACCOUNT_COMPANY();         end if;

   set new.number = trim(new.number);
   if (new.number = ''            ) then call ERROR_INVALID_ACCOUNT_NUMBER();          end if;

   if (new.type     = 0           ) then call ERROR_INVALID_ACCOUNT_TYPE();            end if;
   if (new.timezone = 0           ) then call ERROR_INVALID_ACCOUNT_TIMEZONE();        end if;
   if (new.currency = 0           ) then call ERROR_INVALID_ACCOUNT_CURRENCY();        end if;

   if (new.mtiaccount_id is not null) then 
      set new.mtiaccount_id = trim(mtiaccount_id);
      if (new.mtiaccount_id = ''  ) then call ERROR_INVALID_MTIACCOUNT_ID();           end if;
   end if;

   set new.created = now();
   set new.balance = 0.00;
end;//


create trigger tr_account_before_update before update on t_account for each row
begin
   if (old.created!=ifNull(new.created,'')) then call ERROR_ACCOUNT_CREATED_IS_READ_ONLY(); end if;
end;//


create trigger tr_transaction_before_insert before insert on t_transaction for each row
begin
   if (new.created     is not null          ) then call ERROR_TRANSACTION_CREATED_IS_READONLY();      end if;
   if (new.grossprofit is not null          ) then call ERROR_TRANSACTION_GROSSPROFIT_IS_READONLY();  end if;
   if (new.result      is not null          ) then call ERROR_TRANSACTION_RESULT_IS_READONLY();       end if;
   if (new.pips        is not null          ) then call ERROR_TRANSACTION_PIPS_IS_READONLY();         end if;
   if (new.duration    is not null          ) then call ERROR_TRANSACTION_DURATION_IS_READONLY();     end if;

   set new.ticket = trim(new.ticket);
   if (new.ticket = ''                      ) then call ERROR_INVALID_TRANSACTION_TICKET();           end if;

   if (new.type = 0                         ) then call ERROR_INVALID_TRANSACTION_TYPE();             end if;
   if (new.opentime  = '0000-00-00 00:00:00') then call ERROR_INVALID_TRANSACTION_OPENTIME();         end if;
   if (new.closetime = '0000-00-00 00:00:00') then call ERROR_INVALID_TRANSACTION_CLOSETIME();        end if;
   if (new.opentime > new.closetime         ) then call ERROR_INVALID_TRANSACTION_OPEN_CLOSE_TIMES(); end if;

   set new.created     = now();
   set new.grossprofit = new.commission + new.swap + new.netprofit;
   set new.comment     = trim(new.comment);

   if (new.type='buy' || new.type='sell') then
      if (new.symbol is null            ) then call ERROR_INVALID_TRANSACTION_SYMBOL();           end if;
      set new.symbol = trim(new.symbol);
      if (length(new.symbol) < 3        ) then call ERROR_INVALID_TRANSACTION_SYMBOL();           end if;

      if (new.units <= 0                ) then call ERROR_INVALID_TRANSACTION_UNITS();            end if;
      if (ifNull(new.openprice, 0) <= 0 ) then call ERROR_INVALID_TRANSACTION_OPENPRICE();        end if;
      if (ifNull(new.closeprice, 0) <= 0) then call ERROR_INVALID_TRANSACTION_CLOSEPRICE();       end if;
      if (new.magicnumber <= 0          ) then call ERROR_INVALID_TRANSACTION_MAGICNUMBER();      end if;
   else
      if (new.symbol is not null        ) then call ERROR_INVALID_TRANSACTION_SYMBOL();           end if;
      if (new.units != 0                ) then call ERROR_INVALID_TRANSACTION_UNITS();            end if;
      if (new.opentime != new.closetime ) then call ERROR_INVALID_TRANSACTION_OPEN_CLOSE_TIMES(); end if;
      if (new.openprice   is not null   ) then call ERROR_INVALID_TRANSACTION_OPENPRICE();        end if;
      if (new.closeprice  is not null   ) then call ERROR_INVALID_TRANSACTION_CLOSEPRICE();       end if;
      if (new.magicnumber is not null   ) then call ERROR_INVALID_TRANSACTION_MAGICNUMBER();      end if;
   end if;
end;//


create trigger tr_transaction_after_insert after insert on t_transaction for each row
begin
   update t_account
      set balance     = balance + new.grossprofit,
          lastupdated = new.created
      where id = new.account_id;
end;//


create trigger tr_transaction_before_update before update on t_transaction for each row
begin
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
-- if (ifNull(new.result     ,'')!=ifNull(new.result     ,'')) then call ERROR_TRANSACTION_RESULT_IS_READ_ONLY();      end if;
-- if (ifNull(new.pips       ,'')!=ifNull(new.pips       ,'')) then call ERROR_TRANSACTION_PIPS_IS_READ_ONLY();        end if;
-- if (ifNull(new.duration   ,'')!=ifNull(new.duration   ,'')) then call ERROR_TRANSACTION_DURATION_IS_READ_ONLY();    end if;
   if (ifNull(old.magicnumber,'')!=ifNull(new.magicnumber,'')) then call ERROR_TRANSACTION_MAGICNUMBER_IS_READ_ONLY(); end if;
   if (       old.account_id     !=       new.account_id     ) then call ERROR_TRANSACTION_ACCOUNT_ID_IS_READ_ONLY();  end if;

   -- type='transfer' => 'vendor' und zurück
   if (old.type!=ifNull(new.type,'')) then
      if (new.type is null || old.type not in ('transfer','vendor') || new.type not in ('transfer','vendor')) then
         call ERROR_ILLEGAL_TRANSACTION_TYPE_CHANGE();
      end if;
   end if;

   -- comment (wird nicht extra überprüft)
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


