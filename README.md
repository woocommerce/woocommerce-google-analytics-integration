# Google Analytics for WooCommerce

[![PHP Unit Tests](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/php-unit-tests.yml/badge.svg)](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/php-unit-tests.yml)
[![JavaScript Linting](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/js-linting.yml/badge.svg)](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/js-linting.yml)
[![Build](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/build.yml/badge.svg)](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/build.yml)

WordPress plugin: Provides the integration between WooCommerce and Google Analytics.

Will be required for WooCommerce shops using the integration from WooCommerce 2.1 and up.

- [WordPress.org plugin page](https://wordpress.org/plugins/woocommerce-google-analytics-integration/)
- [Woo.com product page (free)](https://woo.com/products/woocommerce-google-analytics/)
- [User documentation](https://woo.com/document/google-analytics-integration/)

## NPM Scripts

Google Analytics for WooCommerce utilizes npm scripts for task management utilities.

`npm run build` - Runs the tasks necessary for a release. These may include building JavaScript, SASS, CSS minification, and language files.


## Unit tests
### Running PHP unit tests in your local dev environment
1. Install prerequisites: composer, git, xdebug, svn, wget or curl, mysqladmin
2. `cd` into the `woocommerce-google-analytics-integration/` plugin directory
3. Run `composer install`
4. Run `bin/install-unit-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [wc-version] [skip-database-creation]` e.g. `bin/install-unit-tests.sh wordpress_test root root localhost latest latest`
5. Run `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text` to run all unit test

_For more info see: [WordPress.org > Plugin Unit Tests](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/#running-tests-locally)._

## E2E Testing

E2E testing uses [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) which requires [Docker](https://www.docker.com/).

Make sure Docker is running in your machine, and run the following:

`npm run wp-env:up` - This will automatically download and run WordPress in a Docker container. You can access it at http://localhost:8889 (Username: admin, Password: password).

To install the PlayWright browser locally you can run:
`npx playwright install chromium`

Run E2E testing:

-   `npm run test:e2e` to run the test in headless mode.
-   `npm run test:e2e-dev` to run the tests in Chromium browser.

To remove the Docker container and images (this will **delete everything** in the WordPress Docker container):

`npm run wp-env destroy`

:warning: Currently, the E2E testing on GitHub Actions is only run automatically after opening a PR with `release/*` branches or pushing changes to `release/*` branches. To run it manually, please visit [here](../../actions/workflows/e2e-tests.yml) and follow [this instruction](https://docs.github.com/en/actions/managing-workflow-runs/manually-running-a-workflow?tool=webui) to do so.

## Coding standards checks

1. Run `composer install` (_if you haven't done so already_)
2. Run `npm run lint:php`

Alternatively, run `npm run lint:php:diff` to run coding standards checks agains the current git diff. An explanation of output can be [found here](https://github.com/squizlabs/PHP_CodeSniffer/wiki/Usage#printing-progress-information) e.g. what are the S's?

## Docs

- [Hooks defined or used in Google Analytics for WooCommerce](./docs/Hooks.md)

### Consent Mode

The extension sets up [the default state of consent mode](https://developers.google.com/tag-platform/security/guides/consent?hl=en&consentmode=advanced#default-consent), denying all parameters for the EEA region. You can append or overwrite that configuration using the following snippet:

```php
add_filter( 'woocommerce_ga_gtag_consent_modes', function ( $consent_modes ) {
    $consent_modes[] =
		array(
            'analytics_storage' => 'granted',
            'region'            => array( 'ES' ),
        );
    $consent_modes[] =
        array(
            'analytics_storage' => 'denied',
            'region'            => array( 'US-AK' ),
        );
   return $consent_modes;
} );
```

After the page loads, the consent for particular parameters can be updated by other plugins or custom code, implementing UI for customer-facing configuration using [Google's consent API](https://developers.google.com/tag-platform/security/guides/consent?hl=en&consentmode=advanced#update-consent) (`gtag('consent', 'update', {…})`).
