#!/bin/sh

mysqldump -uroot --add-drop-database --allow-keywords --triggers --routines -iclq -r signals.sql -B myfx


