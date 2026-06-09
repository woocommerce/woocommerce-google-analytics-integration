#!/usr/bin/env bash

echo -e 'Activate twentytwentytwo theme \n'
wp-env run tests-cli wp theme activate twentytwentytwo

echo -e 'Install WooCommerce \n'
wp-env run tests-cli -- wp plugin install woocommerce --activate

echo -e 'Install WP Consent API \n'
wp-env run tests-cli -- wp plugin install wp-consent-api --activate

echo -e 'Update URL structure \n'
wp-env run tests-cli -- wp rewrite structure '/%postname%/' --hard

echo -e 'Add Customer user \n'
wp-env run tests-cli wp user create customer customer@e2etestsuite.test \
	--user_pass=password \
	--role=subscriber \
	--first_name='Jane' \
	--last_name='Smith' \
	--user_registered='2024-01-01 12:23:45'

echo -e 'Update Blog Name \n'
wp-env run tests-cli wp option update blogname 'WooCommerce E2E Test Suite'

echo -e 'Adding basic WooCommerce settings... \n'
wp-env run tests-cli wp wc payment_gateway update cod --enabled=1 --user=admin

echo -e 'Ensure a single Flat rate ($10) shipping method in the default zone \n'
# Reuse an existing Flat rate if one is already present. The default zone's
# methods cannot be deleted through the REST API, and instance ids are
# monotonic, so blindly creating on every run (afterStart also runs against a
# persisted database) would pile up methods and keep changing the rate id.
instance_id=$(wp-env run tests-cli wp wc shipping_zone_method list 0 --fields=instance_id,method_id --format=csv --user=admin | awk -F, '$2 == "flat_rate" { print $1; exit }')
if [ -z "$instance_id" ]; then
	instance_id=$(wp-env run tests-cli wp wc shipping_zone_method create 0 --method_id=flat_rate --user=admin --porcelain)
fi
wp-env run tests-cli wp option update "woocommerce_flat_rate_${instance_id}_settings" '{"title":"Flat rate","tax_status":"taxable","cost":"10"}' --format=json --user=admin

echo -e 'Set the store as live \n'
wp-env run tests-cli wp option update woocommerce_coming_soon 'no'
