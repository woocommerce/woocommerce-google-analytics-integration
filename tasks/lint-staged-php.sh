#!/bin/sh

set -e

if [ "${MP_GIT_HOOKS_CODE_SNIFFER:-true}" = "true" ]; then
  vendor/bin/phpcs "$@"
else
  echo "MP_GIT_HOOKS_CODE_SNIFFER is not set to 'true', skipping PHPCS."
fi
