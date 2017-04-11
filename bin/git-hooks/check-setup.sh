#!/bin/sh
#

# check environment
command -v git >/dev/null || { echo "ERROR: Git binary not found."; exit; }


# resolve directories
CWD=$(readlink -e "$PWD")
SCRIPT_DIR=$(dirname "$0")
TOP_LEVEL_DIR=$(git rev-parse --show-toplevel 2>/dev/null)
GIT_HOOK_DIR=$(git rev-parse --git-dir 2>/dev/null)'/hooks'


# normalize paths on Windows
CYGPATH=; command -v cygpath.exe >/dev/null && CYGPATH=1
[ -n "$CYGPATH" ] && {
    CWD=$(cygpath -m "$CWD")
    SCRIPT_DIR=$(cygpath -m "$SCRIPT_DIR")
    TOP_LEVEL_DIR=$(cygpath -m "$TOP_LEVEL_DIR")
    GIT_HOOK_DIR=$(cygpath -m "$GIT_HOOK_DIR")
}


# make sure we run in the repo's root directory (as to not to mess-up nested repos)
if [ "$CWD" != "$TOP_LEVEL_DIR" ]; then
    echo "ERROR: $(basename "$0") must run in the repository's root directory."; exit
fi


# function to copy a hook file
copy_hook() {
    SOURCE="$SCRIPT_DIR/$1"
    TARGET="$GIT_HOOK_DIR/$1"

    # TODO: check already symlinked/hardlinked files
    # resolve link: readlink
    # get inode: ls -li

    # copy hook from source to target and set executable permission
    cp -p "$SOURCE" "$TARGET" || return $?
    chmod u+x "$TARGET"       || return $?
    return 0;
}


# call function with all specified hooks
status=
for arg in "$@"; do
    copy_hook "$arg" || status=$?
done


# print success status
[ -z "$status" ] && status="OK" || status="error $status"
echo "Git hooks: $status"


# the ugly end: never return an error as to not to trigger Composer's red alert bar
exit
