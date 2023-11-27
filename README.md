# WooCommerce Google Analytics Integration

[![PHP Unit Tests](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/php-unit-tests.yml/badge.svg)](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/php-unit-tests.yml)
[![JavaScript Linting](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/js-linting.yml/badge.svg)](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/js-linting.yml)
[![Build](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/build.yml/badge.svg)](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/build.yml)

WordPress plugin: Provides the integration between WooCommerce and Google Analytics.

Will be required for WooCommerce shops using the integration from WooCommerce 2.1 and up.

- [WordPress.org plugin page](https://wordpress.org/plugins/woocommerce-google-analytics-integration/)
- [Woo.com product page (free)](https://woo.com/products/woocommerce-google-analytics/)
- [User documentation](https://woo.com/document/google-analytics-integration/)

## NPM Scripts

WooCommerce Google Analytics Integration utilizes npm scripts for task management utilities.

`npm run build` - Runs the tasks necessary for a release. These may include building JavaScript, SASS, CSS minification, and language files.


## Unit tests
### Running PHP unit tests in your local dev environment
1. Install prerequisites: composer, git, xdebug, svn, wget or curl, mysqladmin
2. `cd` into the `woocommerce-google-analytics-integration/` plugin directory
3. Run `composer install`
4. Run `bin/install-unit-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [wc-version] [skip-database-creation]` e.g. `bin/install-unit-tests.sh wordpress_test root root localhost latest latest`
5. Run `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text` to run all unit test

_For more info see: [WordPress.org > Plugin Unit Tests](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/#running-tests-locally)._

## Coding standards checks

1. Run `composer install` (_if you haven't done so already_)
2. Run `npm run lint:php`

Alternatively, run `npm run lint:php:diff` to run coding standards checks agains the current git diff. An explanation of output can be [found here](https://github.com/squizlabs/PHP_CodeSniffer/wiki/Usage#printing-progress-information) e.g. what are the S's?

## Docs

- [Hooks defined or used in WooCommerce Google Analytics Integration](./docs/Hooks.md)
