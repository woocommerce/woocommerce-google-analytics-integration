#!/usr/bin/env bash

echo -e 'Activate twentytwentytwo theme \n'
wp-env run tests-cli wp theme activate twentytwentytwo

echo -e 'Install WooCommerce \n'
wp-env run tests-cli -- wp plugin install woocommerce --activate

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
