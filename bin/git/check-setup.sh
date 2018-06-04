#!/bin/bash
#
# This script is called by Composer after execution of "composer install". It copies the hook scripts contained in this
# directory to the project's Git hook directory. These scripts are called by Git each time the project is updated from the
# repository.
#
# The scripts check the Composer lock file for modifications. If the lock file was modified by the update the scripts execute
# "composer install" to automatically update any changed Composer dependencies. If the lock file was not modified the scripts
# execute "composer dump-autoload" to automatically update any changed PHP class definitions.
#
# @see  https://getcomposer.org/doc/articles/scripts.md
#
set -e


# --- functions -------------------------------------------------------------------------------------------------------------

# print a message to stderr
function error() {
    echo "error: $@" 1>&2
}


# copy a hook file
function copy_hook() {
    SOURCE="$SCRIPT_DIR/$1"
    TARGET="$GIT_HOOK_DIR/$1"

    # copy hook from source to target and set executable permission
    cp -p "$SOURCE" "$TARGET" || return $?
    chmod u+x "$TARGET"       || return $?
    return 0
}

# --- end of functions ------------------------------------------------------------------------------------------------------


# check environment
command -v git >/dev/null || { error "ERROR: Git binary not found."; exit 1; }


# resolve directories
CWD=$(readlink -e "$PWD")
SCRIPT_DIR=$(dirname "$0")
TOP_LEVEL_DIR=$(git rev-parse --show-toplevel)
GIT_HOOK_DIR=$(git rev-parse --git-dir)'/hooks'


# normalize paths on Windows
if [ $(type -P cygpath.exe) ]; then
    CWD=$(cygpath -m "$CWD")
    SCRIPT_DIR=$(cygpath -m "$SCRIPT_DIR")
    TOP_LEVEL_DIR=$(cygpath -m "$TOP_LEVEL_DIR")
    GIT_HOOK_DIR=$(cygpath -m "$GIT_HOOK_DIR")
fi    


# make sure we run in the repo's root directory (as to not to mess-up nested repos)
if [ "$CWD" != "$TOP_LEVEL_DIR" ]; then
    error "ERROR: $(basename "$0") must run in the repository's root directory."; exit 1
fi


# call copy_hook() with all hooks specified in "composer.json"
STATUS=
for arg in "$@"; do
    copy_hook "$arg" || STATUS=$?
done


# print success status
[ -z "$STATUS" ] && STATUS="OK" || STATUS="error $STATUS"
echo "Git hooks: $STATUS"


exit