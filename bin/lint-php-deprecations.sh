#!/usr/bin/env bash

# `php -l` exits 0 on compile-time deprecations, so scan its output for them.

cd "$(dirname "$0")/.." || exit 1

echo "Linting plugin PHP files with PHP $(php -r 'echo PHP_VERSION;')"

FAILED=0

while IFS= read -r FILE; do
  OUTPUT=$(php -d error_reporting=-1 -d display_errors=1 -d log_errors=0 -l "$FILE" 2>&1)
  STATUS=$?

  if [ $STATUS -ne 0 ] || echo "$OUTPUT" | grep -q 'Deprecated:'; then
    echo "$OUTPUT" | grep -v '^No syntax errors detected'
    FAILED=1
  fi
done < <(find includes -name '*.php'; echo woocommerce-google-analytics-integration.php)

exit $FAILED
