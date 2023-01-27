#!/usr/bin/env bash

if [ $# -lt 3 ]; then
  echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [wc-version] [skip-database-creation]"
  exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
WC_VERSION=${6-latest}
SKIP_DB_CREATE=${7-false}

cd $(dirname "$0")
source ./unit-tests-functions.sh
cd -

install_wp $WP_VERSION
install_wc $WC_VERSION
install_test_suite $DB_NAME $DB_USER $DB_PASS $DB_HOST $WP_VERSION
install_db $DB_NAME $DB_USER $DB_PASS $DB_HOST $SKIP_DB_CREATE
