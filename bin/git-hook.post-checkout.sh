#!/usr/bin/bash
#
# Git hook to run "composer install" if the file "composer.lock" was changed.
#
# This hook is not executed by Eclipse.
#


# check for and execute an existing user hook
if [ -f "$0.user" ]; then
   "$0.user" "$@"
fi


# check existence of Composer
result=$(type -P composer.phar 2> /dev/null)
if [ "$result" == "" ]; then
    result=$(type -P composer 2> /dev/null)
    [ "$result" == "" ] && echo " * error: could not find Composer" && exit 1
fi


# get changed file names
changed_files=$(git diff-tree -r --name-only --no-commit-id $1 $2)


check_run() {
    [ -f "$1" ]                                     && \
    echo "$changed_files" | grep --quiet -Fx "$1"   && \
    echo " * changes detected in $1"                && \
    echo " * running $2"                            && \
    eval "$2 --ansi"
}


# run command if file has changed
check_run 'composer.lock' 'composer install'
