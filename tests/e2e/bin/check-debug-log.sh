#!/usr/bin/env bash

# Fail when the E2E run left PHP errors, warnings, notices or deprecations that
# come from this plugin in wp-content/debug.log. Lines about WordPress core or
# other plugins are only counted.
CONFIG_ARG="${WP_ENV_CONFIG_FILE:+--config=$WP_ENV_CONFIG_FILE}"

log=$(wp-env run cli $CONFIG_ARG -- bash -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true') || {
  echo "Could not read the debug log."
  exit 1
}
log=$(tr -d '\r' <<< "$log")

# Plugin-owned files, or core notices that name the plugin's text domain or classes.
pattern='woocommerce-google-analytics-integration|class-wc-(abstract-)?google-(analytics|gtag)|WC_(Abstract_)?Google_(Analytics|Gtag)'

plugin_lines=$(grep -E "$pattern" <<< "$log")
other_count=$(grep -c . <<< "$log")

if [ -n "$plugin_lines" ]; then
  echo "PHP debug log has lines from the plugin:"
  echo "$plugin_lines"
  exit 1
fi

echo "No plugin PHP errors in the debug log ($other_count other lines ignored)."
