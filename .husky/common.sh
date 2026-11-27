#!/bin/sh

# Optional local overrides, e.g. MP_GIT_HOOKS_ESLINT=false. The variable names
# match MailPoet's, so the same .env lines work in every plugin repository.
ENV_FILE="$(dirname "$0")/../.env"
if [ -f "$ENV_FILE" ]; then
  . "$ENV_FILE"
fi

export MP_GIT_HOOKS_ENABLE="${MP_GIT_HOOKS_ENABLE:-true}"
export MP_GIT_HOOKS_ESLINT="${MP_GIT_HOOKS_ESLINT:-true}"
export MP_GIT_HOOKS_CODE_SNIFFER="${MP_GIT_HOOKS_CODE_SNIFFER:-true}"
export MP_GIT_HOOKS_PHPSTAN="${MP_GIT_HOOKS_PHPSTAN:-true}"
