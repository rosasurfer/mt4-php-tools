#!/bin/bash
#
# A script called by Composer after execution of "composer install". This script copies all distributed Git hooks to the
# project's Git hook directory. Extend it with your project-specific tasks to be executed after "composer install".
#
set -e


# --- functions -------------------------------------------------------------------------------------------------------------


# print a message to stderr
function error() {
    echo "error: $@" 1>&2
}


# copy a hook file
function copyHook() {
    SOURCE="$SCRIPT_DIR/$1"
    TARGET="$GIT_HOOK_DIR/$1"

    # copy file
    if ! cmp -s "$SOURCE" "$TARGET"; then
        cp -p "$SOURCE" "$TARGET" || return $?
    fi

    # set executable permission
    chmod u+x "$TARGET" || return $?
    return 0
}


# --- end of functions ------------------------------------------------------------------------------------------------------


# check required commands
command -v git >/dev/null || { error "ERROR: Git binary not found."; exit 1; }


# resolve directories
CWD=$(readlink -f "$PWD")
SCRIPT_DIR=$(dirname "$0")
REPO_ROOT_DIR=$(git rev-parse --show-toplevel)
GIT_HOOK_DIR=$(git rev-parse --git-dir)'/hooks'


# normalize paths on Windows
if [ $(type -P cygpath.exe) ]; then
    CWD=$(cygpath -m "$CWD")
    SCRIPT_DIR=$(cygpath -m "$SCRIPT_DIR")
    REPO_ROOT_DIR=$(cygpath -m "$REPO_ROOT_DIR")
    GIT_HOOK_DIR=$(cygpath -m "$GIT_HOOK_DIR")
fi


# make sure we run in the repo's root directory (as to not to mess-up nested repos)
#if [ "$CWD" != "$REPO_ROOT_DIR" ]; then
#    error "ERROR: $(basename "$0") must run in the repository's root directory."
#    exit 1
#fi


# call copyHook() with the arguments specified in "composer.json"
STATUS=
for arg in "$@"; do
    copyHook "$arg" || STATUS=$?
done


# print execution status
[ -z "$STATUS" ] && STATUS="OK" || STATUS="error $STATUS"
echo "Git hooks: $STATUS"
exit
