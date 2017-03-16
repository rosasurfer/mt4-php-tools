#!/bin/sh
#
# A hook script called by "git commit" without any arguments. The hook should
# exit with non-zero status after issuing an appropriate message if it wants
# to stop the commit.
#
# Link this script to ".git/hooks/pre-commit" if it's located elsewhere.
#


# redirect output to stderr
exec 1>&2


# check for and execute a user hook
if [ -f "$0.user" ]; then
   "$0.user" "$@" || exit 1
fi


# get the files to be committed
IFS=$'\n'
for file in $(git diff --staged --name-only); do
   # skip files to delete and error out on found BOM headers
   [ -f "$file" ] && file "$file" | grep 'UTF-8 Unicode (with BOM)' && exit 1
done


# explicitly specify the exit code
exit 0
