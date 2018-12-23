use mysql;

grant usage on * to 'rost'@'localhost',
                    'rost'@'rosasurfer.com';
revoke all privileges, grant option from 'rost'@'localhost',
                                         'rost'@'rosasurfer.com';
grant select, insert, update, delete, lock tables, create, execute, drop, create temporary tables on `rost`.* to 'rost'@'localhost'      identified by 'passwd',
                                                                                                                 'rost'@'rosasurfer.com' identified by 'passwd';
flush privileges;
