#!/bin/sh

set -e

if [ "${MP_GIT_HOOKS_CODE_SNIFFER:-true}" = "true" ]; then
  vendor/bin/phpcs "$@"
else
  echo "MP_GIT_HOOKS_CODE_SNIFFER is not set to 'true', skipping PHPCS."
fi

# PHPStan checks the whole plugin, not only the staged files, so the baseline matches.
if [ "${MP_GIT_HOOKS_PHPSTAN:-true}" = "true" ]; then
  vendor/bin/phpstan analyse --memory-limit=2G --no-progress
else
  echo "MP_GIT_HOOKS_PHPSTAN is not set to 'true', skipping PHPStan."
fi
