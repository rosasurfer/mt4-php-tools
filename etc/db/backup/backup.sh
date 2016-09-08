#!/bin/sh

DATE=$(date '+%Y.%m.%d_%H.%M')

if ! command -v 'mysqldump' >/dev/null 2>&1; then
   echo mysqldump not found
   exit
fi

mysqldump -uroot --add-drop-database --allow-keywords --triggers --routines -iclq -r signals_${DATE}.sql -B myfx
