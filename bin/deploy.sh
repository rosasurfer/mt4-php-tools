#!/bin/bash
#
# Application deploy script for Git based repositories. Deploys a branch, a tag or a specific commit.
# Sends email notifications with the deployed changes (commit mesages) if configured.
#
# Usage: deploy.sh [<branch-name> | <tag-name> | <commit-sha>]
#
# Without arguments the latest version of the current branch is deployed.
#
#
# TODO: update existing submodules
#
set -e


# notification configuration (environment variables of the same name will have precedence over values hardcoded here)
NOTIFY_FOR_PROJECT="${NOTIFY_FOR_PROJECT:-<project-name>}"      `# fill in your project identifier              `
NOTIFY_ON_HOST="${NOTIFY_ON_HOST:-<hostname>}"                  `# fill in the hostname to notify if deployed on`
NOTIFY_RECEIVER="${NOTIFY_RECEIVER:-<email@domain.tld>}"        `# fill in the notification receiver            `


# --- functions -------------------------------------------------------------------------------------------------------------

# print a message to stderr
function error() {
    echo "error: $@" 1>&2
}

# --- end of functions ------------------------------------------------------------------------------------------------------


# check git availability
command -v git >/dev/null || { error "ERROR: Git command not found."; exit 1; }


# change to the project's toplevel directory
cd "$(dirname "$(readlink -e "$0")")"
PROJECT_DIR=$(git rev-parse --show-toplevel)
cd "$PROJECT_DIR"


# get current repo status and fetch changes
FROM_BRANCH=$(git rev-parse --abbrev-ref HEAD)
FROM_COMMIT=$(git rev-parse --short HEAD)
git fetch origin


# check arguments
if [ $# -eq 0 ]; then
    # no arguments given, get current branch name
    if [ "$FROM_BRANCH" = "HEAD" ]; then
        error "HEAD is currently detached at $FROM_COMMIT, you must specify a ref-name to deploy."
        error "Usage: $(basename "$0") [<branch-name> | <tag-name> | <commit-sha>]"
        exit 2
    fi
    BRANCH="$FROM_BRANCH"
elif [ $# -eq 1 ]; then
    # argument given, resolve its type
    if git show-ref -q --verify "refs/heads/$1"; then
        BRANCH="$1"
    elif git show-ref -q --verify "refs/remotes/origin/$1"; then
        BRANCH="$1"
    elif git show-ref -q --verify "refs/tags/$1"; then
        TAG="$1"
    elif git rev-parse -q --verify "$1^{commit}" >/dev/null; then
        COMMIT="$1"
    else
        error "Unknown ref-name $1"
        error "Usage: $(basename "$0") [<branch-name> | <tag-name> | <commit-sha>]"
        exit 2
    fi
else
    error "Usage: $(basename "$0") [<branch-name> | <tag-name> | <commit-sha>]"
    exit 2
fi


# update project
if [ -n "$BRANCH" ]; then
    [ "$BRANCH" != "$FROM_BRANCH" ] && git checkout "$BRANCH"
    git merge --ff-only "origin/$BRANCH"
elif [ -n "$TAG"    ]; then
    git checkout "$TAG"
elif [ -n "$COMMIT" ]; then
    git checkout "$COMMIT"
fi


# check updates
OLD=$FROM_COMMIT
NEW=$(git rev-parse --short HEAD)

if [ "$OLD" = "$NEW" ]; then
    echo No changes.
else
    # send deployment notifications if configured

    # trim angle brackets
    NOTIFY_FOR_PROJECT=${NOTIFY_FOR_PROJECT#<}; NOTIFY_FOR_PROJECT=${NOTIFY_FOR_PROJECT%>}
    NOTIFY_ON_HOST=${NOTIFY_ON_HOST#<};         NOTIFY_ON_HOST=${NOTIFY_ON_HOST%>}
    NOTIFY_RECEIVER=${NOTIFY_RECEIVER#<};       NOTIFY_RECEIVER=${NOTIFY_RECEIVER%>}

    # reset default values
    [ "$NOTIFY_FOR_PROJECT" = "project-name"     ] && NOTIFY_FOR_PROJECT=
    [ "$NOTIFY_ON_HOST"     = "hostname"         ] && NOTIFY_ON_HOST=
    [ "$NOTIFY_RECEIVER"    = "email@domain.tld" ] && NOTIFY_RECEIVER=

    # check missing values
    NOTIFY=0
    if [[ -n "$NOTIFY_ON_HOST" && -n "$NOTIFY_RECEIVER" ]]; then
        if [ "$NOTIFY_ON_HOST" = "$(hostname)" ]; then
            [ -z "$NOTIFY_FOR_PROJECT" ] && NOTIFY_FOR_PROJECT=$(basename $(git config --get remote.origin.url) .git)
            NOTIFY=1
        fi
    fi

    # notify
    if [ $NOTIFY -eq 1 ]; then
        if command -v sendmail >/dev/null; then
            (
            echo 'From: "Deployments '$NOTIFY_FOR_PROJECT'" <'$NOTIFY_RECEIVER'>'
            if   [ -n "$BRANCH" ]; then echo "Subject: Updated $NOTIFY_FOR_PROJECT, branch $BRANCH to latest ($NEW)"
            elif [ -n "$TAG"    ]; then echo "Subject: Reset $NOTIFY_FOR_PROJECT to tag $TAG ($NEW)"
            elif [ -n "$COMMIT" ]; then echo "Subject: Reset $NOTIFY_FOR_PROJECT to commit $COMMIT"
            fi
            git log --pretty='%h %ae %s' $OLD..$NEW
            ) | sendmail -f "$NOTIFY_RECEIVER" "$NOTIFY_RECEIVER"
        fi
    fi
fi


# update access permissions and ownership for writing of files
DIRS="etc/log  etc/tmp"

for dir in $DIRS; do
    dir="$PROJECT_DIR/$dir/"
    [ -d "$dir" ] || mkdir -p "$dir"
    chmod 777 "$dir"
done

USER="<username>"
id -u "$USER" >/dev/null 2>&1 && chown -R --from=root.root "$USER.$USER" "$PROJECT_DIR"
