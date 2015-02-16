
use mysql;

grant usage on * to 'myfx'@'localhost',
                    'myfx'@'mt4.rosasurfer.com';

revoke all privileges, grant option from 'myfx'@'localhost',
                                         'myfx'@'mt4.rosasurfer.com';

grant select, insert, update, delete, lock tables, create, execute, drop, create temporary tables on `myfx`.* to 'myfx'@'localhost'            identified by 'passwd',
                                                                                                                 'myfx'@'mt4.rosasurfer.com' identified by 'passwd';

flush privileges;
