#!/bin/sh

DATE=$(date '+%Y.%m.%d_%H.%M')

mysqldump="$(which mysqldump 2>&-)"

if [ -z "$mysqldump" ]; then
   echo mysqldump not found
   exit
fi

$mysqldump -uroot --add-drop-database --allow-keywords --triggers --routines -iclq -r signals_${DATE}.sql -B myfx
