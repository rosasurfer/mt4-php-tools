#!/bin/sh
#
#

# check for Git  
which git &>/dev/null || { echo "ERROR: Git binary not found."; exit; }

# check for Cygwin environment (Windows)
CYGPATH=; which cygpath.exe &>/dev/null && CYGPATH=1


# resolve directories
CWD=$PWD
SCRIPT_DIR=$(dirname "$0")
TOP_LEVEL_DIR=$(git rev-parse --show-toplevel 2>/dev/null)
GIT_HOOK_DIR=$(git rev-parse --git-dir 2>/dev/null)'/hooks'

# Windows: normalize paths 
[ -n "$CYGPATH" ] && {
   CWD=$(cygpath -m "$CWD")
   SCRIPT_DIR=$(cygpath -m "$SCRIPT_DIR")
   TOP_LEVEL_DIR=$(cygpath -m "$TOP_LEVEL_DIR")
   GIT_HOOK_DIR=$(cygpath -m "$GIT_HOOK_DIR")
}
#echo "CWD          =$CWD"
#echo "SCRIPT_DIR   =$SCRIPT_DIR"
#echo "TOP_LEVEL_DIR=$TOP_LEVEL_DIR"
#echo "GIT_HOOK_DIR =$GIT_HOOK_DIR"


# make sure the script runs in the repo's root directory (as to not to mess-up nested repos)
if [ "$CWD" != "$TOP_LEVEL_DIR" ]; then
   echo "ERROR: This script must run in the repository's root directory."; exit 
fi


# define helper function
copy_hook() {
   # hook von script-dir nach hook-dir kopieren
   # hook executable machen
   return 0;
}


# copy all specified hooks
status=
for arg in "$@"; do
   copy_hook "$arg" || status=$?
done


# print success status
[ -z "$status" ] && status="OK" || status="error $status"
echo "Git hooks: $status"


# the ugly end: always return success as to not to trigger Composer's red alert bar
exit 0
