#!/bin/sh

DATE=$(date '+%Y.%m.%d_%H.%M')

command -v mysqldump >/dev/null || { echo "ERROR: mysqldump not found"; exit; }

mysqldump -uroot --add-drop-database --allow-keywords --triggers --routines -iclq -r signals_${DATE}.sql -B myfx
