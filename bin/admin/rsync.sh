#!/bin/bash

# determine rsync source file
SELF=$(readlink -e "$0")
DB_FILE=$(dirname "$SELF")"/../../data/rost.db"
SOURCE=$(readlink -e "$DB_FILE")
[ ! -f "$SOURCE" ] && { echo "source database file not found: $DB_FILE"; exit 1; }


# determine rsync target directory
if [ $# -gt 0 ]; then
    [ -z "$1" ]                     && { echo "error: invalid argument for rsync target"; exit 1; }
    TARGET="$1"
else
    [ -z ${ROST_RSYNC_TARGET+x} ] && { echo "error: missing argument or env ROST_RSYNC_TARGET for rsync target"; exit 1; }
    [ -z ${ROST_RSYNC_TARGET}   ] && { echo "error: invalid environment setting ROST_RSYNC_TARGET"; exit 1; }
    TARGET="$ROST_RSYNC_TARGET"
fi
[ "${TARGET: -1}" != "/" ] && TARGET="$TARGET/"


# check required binaries
command -v rsync >/dev/null || { echo "error: rsync binary not found"; exit 1; }
command -v ssh   >/dev/null || { echo "error: ssh binary not found";   exit 1; }


# run rsync (TODO: externalize ownership settings)
rsync -ahzPv --no-r --chown=apache:apache -e ssh "$SOURCE" "rost:$TARGET"
