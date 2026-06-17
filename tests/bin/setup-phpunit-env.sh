#!/usr/bin/env bash
#
# Setup wp-env for running PHPUnit tests.
# Ensures WooCommerce is installed and downloads the WooCommerce test framework
# files (not included in the wordpress.org zip) that PHPUnit bootstrap requires.
#
# Usage: npm run test:php:setup [-- --wc-version=X.Y.Z]
#
# This script only needs to be run once after `npm run wp-env:up`.

set -e

# Target the wp-env environment named by WP_ENV_CONFIG_FILE (set by the
# test:php:setup npm script) so the PHPUnit setup runs in the test environment
# rather than the default one.
CONFIG_ARG="${WP_ENV_CONFIG_FILE:+--config=$WP_ENV_CONFIG_FILE}"

WC_VERSION=""

# Parse arguments
for arg in "$@"; do
	case $arg in
		--wc-version=*)
			WC_VERSION="${arg#*=}"
			;;
	esac
done

# Install WooCommerce if not already present, or if a specific version was requested
if [ -n "$WC_VERSION" ]; then
	echo "==> Installing WooCommerce ${WC_VERSION}..."
	wp-env run cli $CONFIG_ARG -- wp plugin install woocommerce --version="${WC_VERSION}" --activate --force
else
	# Check if WooCommerce is already installed
	INSTALLED=$(wp-env run cli $CONFIG_ARG wp plugin list --field=name 2>/dev/null | grep '^woocommerce$' || true)
	if [ -z "$INSTALLED" ]; then
		echo "==> Installing WooCommerce (latest)..."
		wp-env run cli $CONFIG_ARG -- wp plugin install woocommerce --activate
	fi

	WC_VERSION=$(wp-env run cli $CONFIG_ARG wp plugin get woocommerce --field=version 2>/dev/null | tail -1)
fi

if [ -z "$WC_VERSION" ]; then
	echo "Error: Could not determine WooCommerce version." >&2
	exit 1
fi

echo "==> Using WooCommerce ${WC_VERSION}"
echo "==> Downloading test framework files from GitHub..."

# The wordpress.org zip doesn't include tests/. The PHPUnit bootstrap needs
# specific test helpers from the WooCommerce source. Download them individually
# from the GitHub monorepo rather than cloning or downloading the full archive.
RAW_BASE="https://raw.githubusercontent.com/woocommerce/woocommerce/${WC_VERSION}/plugins/woocommerce"

# Files required by tests/class-unittestsbootstrap.php includes() method
TEST_FILES=(
	"tests/legacy/includes/wp-http-testcase.php"
	"tests/legacy/framework/helpers/class-wc-helper-coupon.php"
	"tests/legacy/framework/helpers/class-wc-helper-product.php"
	"tests/legacy/framework/helpers/class-wc-helper-order.php"
	"tests/legacy/framework/helpers/class-wc-helper-shipping.php"
	"tests/legacy/framework/helpers/class-wc-helper-customer.php"
)

# Also need the legacy bootstrap marker so the bootstrap detects the legacy path
TEST_FILES+=("tests/legacy/bootstrap.php")

wp-env run cli $CONFIG_ARG bash -c "
	set -e
	WC_DIR=/var/www/html/wp-content/plugins/woocommerce
	RAW_BASE='${RAW_BASE}'

	for file in ${TEST_FILES[*]}; do
		dir=\$(dirname \"\${WC_DIR}/\${file}\")
		mkdir -p \"\${dir}\"
		echo \"    Downloading \${file}\"
		curl -sf \"\${RAW_BASE}/\${file}\" -o \"\${WC_DIR}/\${file}\"
	done

	echo '    Test framework files installed.'
"

echo "==> Installing plugin composer dependencies..."
wp-env run cli $CONFIG_ARG --env-cwd=wp-content/plugins/woocommerce-google-analytics-integration composer install --no-interaction 2>&1 | tail -3

echo ""
echo "==> PHPUnit environment ready. Run tests with: npm run test:php"
