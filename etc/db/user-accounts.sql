
use mysql;

grant usage on * to 'myfx'@'localhost',
                    'myfx'@'mt4.rosasurfer.com',
                    'myfx'@'cob02.tropmi.de';

revoke all privileges, grant option from 'myfx'@'localhost',
                                         'myfx'@'mt4.rosasurfer.com',
                                         'myfx'@'cob02.tropmi.de';

grant select, insert, update, delete, lock tables, create, execute, drop, create temporary tables on `myfx`.* to 'myfx'@'localhost'       identified by 'passwd',
                                                                                                                 'myfx'@'mt4.rosasurfer.com' identified by 'passwd',
                                                                                                                 'myfx'@'cob02.tropmi.de' identified by 'passwd';

flush privileges;
