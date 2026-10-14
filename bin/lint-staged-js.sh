#!/bin/sh

set -e

if [ "${MP_GIT_HOOKS_ESLINT:-true}" = "true" ]; then
  npx eslint --max-warnings 0 "$@"
else
  echo "MP_GIT_HOOKS_ESLINT is not set to 'true', skipping ESLint."
fi
