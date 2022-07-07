#!/usr/bin/env bash

GIT_LOG=$(git log -100 --graph --pretty=format:'%H %D')

# Find the nearest commit which may be the parent of HEAD.
# 1. Branch off from the default or another branch without any new commit.
# 2. Branch off from the default or another branch and has new commits.
LINE=1
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
COMMITS=$(echo "$GIT_LOG" | grep -E '^\* (  \w{40}|\w{40} .+, )')
if echo "$COMMITS" | grep -q "origin/${CURRENT_BRANCH}"; then
	LINE=2
fi
COMMIT=$(echo "$COMMITS" | sed -n "${LINE}p")
SHA=$(echo "$COMMIT" | awk '{print $2}')

echo "$COMMIT" | sed 's/* */Diff target: /'
vendor/bin/diffFilter --phpcsStrict <(git diff $SHA) <(vendor/bin/phpcs ./* -q --report=json) 0
