#!/usr/bin/env bash

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

# Plugin variables.
PLUGINS_DIR="${WP_CORE_DIR}/wp-content/plugins"

download() {
  if [ $(which curl) ]; then
    curl -s "$1" >"$2"
  elif [ $(which wget) ]; then
    wget -nv -O "$2" "$1"
  fi
}

get_latest_wp_version() {
  # http serves a single offer, whereas https serves multiple. we only want one
  download http://api.wordpress.org/core/version-check/1.7/ "${TMPDIR}/wp-latest.json"
  local LATEST_VERSION=$(grep -o '"version":"[^"]*' "${TMPDIR}/wp-latest.json" | sed 's/"version":"//')
  if [[ -z "$LATEST_VERSION" ]]; then
    echo "Latest WordPress version could not be found"
    exit 1
  fi
  echo $LATEST_VERSION
}

cleanup() {
  if [ -d "$WP_CORE_DIR" ]; then
    rm -rf "$WP_CORE_DIR"
  fi

  if [ -d "$WP_TESTS_DIR" ]; then
    rm -rf "$WP_TESTS_DIR"
  fi
}

install_wp() {
  local WP_VERSION=$1

  if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
    echo "Installing WordPress ($WP_VERSION)."
    cleanup
    mkdir -p "$WP_CORE_DIR"
    mkdir -p "$TMPDIR"/wordpress-trunk
    rm -rf "$TMPDIR"/wordpress-trunk/*
    svn export --quiet https://core.svn.wordpress.org/trunk "$TMPDIR"/wordpress-trunk/wordpress
    mv "$TMPDIR"/wordpress-trunk/wordpress/* "$WP_CORE_DIR"
  else
    if [ $WP_VERSION == 'latest' ]; then
      local ARCHIVE_NAME='latest'
      local LATEST_VERSION=$(get_latest_wp_version)
    elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+ ]]; then
      # https serves multiple offers, whereas http serves single.
      download https://api.wordpress.org/core/version-check/1.7/ "$TMPDIR"/wp-latest.json
      if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
        # version x.x.0 means the first release of the major version, so strip off the .0 and download version x.x
        local LATEST_VERSION=${WP_VERSION%??}
      else
        # otherwise, scan the releases and get the most up to date minor version of the major release
        local VERSION_ESCAPED=$(echo $WP_VERSION | sed 's/\./\\./g')
        local LATEST_VERSION=$(grep -o '"version":"'$VERSION_ESCAPED'[^"]*' "$TMPDIR"/wp-latest.json | sed 's/"version":"//' | head -1)
      fi
      if [[ -z "$LATEST_VERSION" ]]; then
        local ARCHIVE_NAME="wordpress-$WP_VERSION"
      else
        local ARCHIVE_NAME="wordpress-$LATEST_VERSION"
      fi
    else
      local ARCHIVE_NAME="wordpress-$WP_VERSION"
    fi

    local TARGET_VERSION=${LATEST_VERSION:-$WP_VERSION}
    local WP_VERSION_FILE="${WP_CORE_DIR}/version-"$(echo $TARGET_VERSION | sed -e "s/\//-/")

    if [ -f "$WP_VERSION_FILE" ]; then
      echo "WordPress ($TARGET_VERSION) already installed."
      return 0
    fi

    echo "Installing WordPress ($TARGET_VERSION)."
    cleanup
    mkdir -p "$WP_CORE_DIR"
    download https://wordpress.org/${ARCHIVE_NAME}.tar.gz "$TMPDIR"/wordpress.tar.gz
    tar --strip-components=1 -zxmf "$TMPDIR"/wordpress.tar.gz -C "$WP_CORE_DIR"
    touch "$WP_VERSION_FILE"
  fi

  download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php "$WP_CORE_DIR"/wp-content/db.php
}

install_test_suite() {
  local DB_NAME=$1
  local DB_USER=$2
  local DB_PASS=$3
  local DB_HOST=$4
  local WP_VERSION=$5

  # resolve WP_TESTS_TAG from WP_VERSION
  if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-(beta|RC)[0-9]+$ ]]; then
    local WP_BRANCH=${WP_VERSION%\-*}
    local WP_TESTS_TAG="branches/$WP_BRANCH"

  elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
    local WP_TESTS_TAG="branches/$WP_VERSION"
  elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
    if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
      # version x.x.0 means the first release of the major version, so strip off the .0 and download version x.x
      local WP_TESTS_TAG="tags/${WP_VERSION%??}"
    else
      local WP_TESTS_TAG="tags/$WP_VERSION"
    fi
  elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
    local WP_TESTS_TAG="trunk"
  else
    local LATEST_VERSION=$(get_latest_wp_version)
    local WP_TESTS_TAG="tags/$LATEST_VERSION"
  fi

  echo "Install test suite ${WP_TESTS_TAG}"

  # portable in-place argument for both GNU sed and Mac OSX sed
  if [[ $(uname -s) == 'Darwin' ]]; then
    local ioption='-i.bak'
  else
    local ioption='-i'
  fi

  if [ -d "$WP_TESTS_DIR" ]; then
    rm -rf "$WP_TESTS_DIR"/{includes,data}
  fi

  # set up testing suite
  mkdir -p "$WP_TESTS_DIR"
  svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ "$WP_TESTS_DIR"/includes
  svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ "$WP_TESTS_DIR"/data

  # set up wp-tests-config.php file
  local CONFIG_FILE="${WP_TESTS_DIR}/wp-tests-config.php"
  download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php "$CONFIG_FILE"
  # remove all forward slashes in the end
  local WP_CORE_DIR_FOR_CONFIG=$(echo $WP_CORE_DIR | sed "s:/\+$::")
  sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR_FOR_CONFIG/':" "$CONFIG_FILE"
  sed $ioption "s:__DIR__ . '/src/':'$WP_CORE_DIR_FOR_CONFIG/':" "$CONFIG_FILE"
  sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$CONFIG_FILE"
  sed $ioption "s/yourusernamehere/$DB_USER/" "$CONFIG_FILE"
  sed $ioption "s/yourpasswordhere/$DB_PASS/" "$CONFIG_FILE"
  sed $ioption "s|localhost|${DB_HOST}|" "$CONFIG_FILE"
}

recreate_db() {
  local DB_NAME=$1
  local DB_USER=$2
  local DB_PASS=$3
  local DELETE_EXISTING_DB=$4

  shopt -s nocasematch
  if [[ $DELETE_EXISTING_DB =~ ^(y|yes)$ ]]; then
    mysqladmin drop $DB_NAME -f --user="$DB_USER" --password="$DB_PASS"$EXTRA
    create_db $DB_NAME $DB_USER $DB_PASS
    echo "Recreated the database ($DB_NAME)."
  else
    echo "Leaving the existing database ($DB_NAME) in place."
  fi
  shopt -u nocasematch
}

create_db() {
  local DB_NAME=$1
  local DB_USER=$2
  local DB_PASS=$3

  mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_db() {
  local DB_NAME=$1
  local DB_USER=$2
  local DB_PASS=$3
  local DB_HOST=$4
  local SKIP_DB_CREATE=$5

  if [ ${SKIP_DB_CREATE} = "true" ]; then
    return 0
  fi

  # parse DB_HOST for port or socket references
  local PARTS=(${DB_HOST//\:/ })
  local DB_HOSTNAME=${PARTS[0]}
  local DB_SOCK_OR_PORT=${PARTS[1]}
  local EXTRA=""

  if ! [ -z $DB_HOSTNAME ]; then
    if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
      EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
    elif ! [ -z $DB_SOCK_OR_PORT ]; then
      EXTRA=" --socket=$DB_SOCK_OR_PORT"
    elif ! [ -z $DB_HOSTNAME ]; then
      EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
    fi
  fi

  # create database
  if [ $(mysql --user="$DB_USER" --password="$DB_PASS"$EXTRA --execute='show databases;' | grep ^$DB_NAME$) ]; then
    echo "Reinstalling will delete the existing test database ($DB_NAME)"
    read -p 'Are you sure you want to proceed? [y/N]: ' DELETE_EXISTING_DB
    recreate_db $DB_NAME $DB_USER $DB_PASS $DELETE_EXISTING_DB
  else
    create_db $DB_NAME $DB_USER $DB_PASS
  fi
}

install_wc() {
  local WC_VERSION=$1
  local WC_DIR="${PLUGINS_DIR}/woocommerce"

  # Get latest WC version
  if [[ $WC_VERSION == 'latest' ]]; then
    download https://api.wordpress.org/plugins/info/1.0/woocommerce.json "${TMPDIR}/wc-latest.json"
    local WC_LATEST_VERSION=$(grep -o '"version":"[^"]*' "${TMPDIR}/wc-latest.json" | sed 's/"version":"//')
    if [[ -z "$WC_LATEST_VERSION" ]]; then
      echo "Latest WooCommerce version could not be found"
      exit 1
    fi
    local WC_VERSION=$WC_LATEST_VERSION
  fi

  local WC_VERSION_FILE="${WC_DIR}/version-"$(echo $WC_VERSION | sed -e "s/\//-/")
  if [ ! -f "$WC_VERSION_FILE" ]; then
    rm -rf "$WC_DIR"
    echo "Installing WooCommerce ($WC_VERSION)."

    local WC_TMPDIR="${TMPDIR}/woocommerce-${WC_VERSION}"
    rm -rf "${WC_TMPDIR}"
    git clone --quiet --depth=1 --branch="${WC_VERSION}" https://github.com/woocommerce/woocommerce.git "${WC_TMPDIR}"
    ln -s "${WC_TMPDIR}"/plugins/woocommerce "$WC_DIR"
    touch "$WC_VERSION_FILE"

    # Install composer for WooCommerce
    cd "${WC_DIR}"
    composer install --ignore-platform-reqs --no-interaction --no-dev

    # Generate feature config for WooCommerce
    local GENERATE_FEATURE_CONFIG=bin/generate-feature-config.php
    if [ -f $GENERATE_FEATURE_CONFIG ]; then
      php $GENERATE_FEATURE_CONFIG
    fi

    cd -
  else
    echo "WooCommerce ($WC_VERSION) already installed."
  fi
}
