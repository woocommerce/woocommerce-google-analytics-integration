#!/usr/bin/env bash

if [ $# -lt 3 ]; then
  echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [wc-version]"
  exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
WC_VERSION=${6-latest}

cd $(dirname "$0")
source ./unit-tests-functions.sh
cd -

PLUGINS_TMP_DIR="$TMPDIR/tmp_plugins"
echo "$PLUGINS_TMP_DIR"

mkdir -p "$PLUGINS_TMP_DIR"
rm -rf "$PLUGINS_TMP_DIR"/*
mv "$PLUGINS_DIR"/*/ "$PLUGINS_TMP_DIR" || true
install_wp $WP_VERSION
mv -n "$PLUGINS_TMP_DIR"/* "$PLUGINS_DIR" || true
install_wc $WC_VERSION
install_test_suite $DB_NAME $DB_USER $DB_PASS $DB_HOST $WP_VERSION
