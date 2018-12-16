use mysql;

grant usage on * to 'rsx'@'localhost',
                    'rsx'@'rosasurfer.com';
revoke all privileges, grant option from 'rsx'@'localhost',
                                         'rsx'@'rosasurfer.com';
grant select, insert, update, delete, lock tables, create, execute, drop, create temporary tables on `rsx`.* to 'rsx'@'localhost'      identified by 'passwd',
                                                                                                                'rsx'@'rosasurfer.com' identified by 'passwd';
flush privileges;
