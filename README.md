# Google Analytics for WooCommerce

[![PHP Coding Standards](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/php-coding-standards.yml/badge.svg)](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/php-coding-standards.yml)
[![PHP Unit Tests](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/php-unit-tests.yml/badge.svg)](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/php-unit-tests.yml)
[![JavaScript Linting](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/js-linting.yml/badge.svg)](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/js-linting.yml)
[![Build](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/build.yml/badge.svg)](https://github.com/woocommerce/woocommerce-google-analytics-integration/actions/workflows/build.yml)

WordPress plugin: Provides the integration between WooCommerce and Google Analytics.

Will be required for WooCommerce shops using the integration from WooCommerce 2.1 and up.

- [WordPress.org plugin page](https://wordpress.org/plugins/woocommerce-google-analytics-integration/)
- [WooCommerce.com product page (free)](https://woocommerce.com/products/woocommerce-google-analytics/)
- [User documentation](https://woocommerce.com/document/google-analytics-integration/)

## NPM Scripts

Google Analytics for WooCommerce utilizes npm scripts for task management utilities.

`npm run build` - Runs the tasks necessary for a release. These may include building JavaScript, SASS, CSS minification, and language files.

## Unit tests

### Running PHP unit tests via wp-env (recommended)

This uses the same Docker environment as E2E tests — no local MySQL, svn, or manual setup required.

1. Make sure Docker is running
2. `npm run wp-env:up` to start the environment
3. `npm run test:php:setup` to install WooCommerce from source and dependencies (only needed once after starting the environment)
4. `npm run test:php` to run all PHPUnit tests

To test against a specific WooCommerce version:

`npm run test:php:setup -- --wc-version=9.6.0`

### Running PHP unit tests locally (without Docker)

1. Install prerequisites: composer, git, xdebug, svn, wget or curl, mysqladmin
2. `cd` into the `woocommerce-google-analytics-integration/` plugin directory
3. Run `composer install`
4. Run `bin/install-unit-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [wc-version] [skip-database-creation]` e.g. `bin/install-unit-tests.sh wordpress_test root root localhost latest latest`
5. Run `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text` to run all unit tests

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

`npm run wp-env:destroy`

:warning: Currently, the E2E testing on GitHub Actions is only run automatically after opening a PR with `release/*` branches or pushing changes to `release/*` branches. To run it manually, please visit [here](../../actions/workflows/e2e-tests.yml) and follow [this instruction](https://docs.github.com/en/actions/managing-workflow-runs/manually-running-a-workflow?tool=webui) to do so.

## Local development environment

The scripts above run against the test environment (`.wp-env.test.json`, port 8889). PHPUnit uses a dedicated `wordpress_test` database (configured in `phpunit.xml.dist`), while the browser-served site and the E2E suite use the separate `wordpress` database in the same MySQL container. PHPUnit and E2E therefore no longer overwrite each other and can be run in any order. A separate development environment is defined in `.wp-env.json` (port 8888) with WooCommerce and the plugin installed, intended for manual work:

-   `npm run wp-env:dev:up` to start it at http://localhost:8888
-   `npm run wp-env:dev:down` to stop it
-   `npm run wp-env:dev:destroy` to remove its containers, volumes, and images

Both environments are isolated and can run at the same time.

## Coding standards checks

1. Run `composer install` (_if you haven't done so already_)
2. Run `npm run lint:php`

An explanation of output can be [found here](https://github.com/squizlabs/PHP_CodeSniffer/wiki/Usage#printing-progress-information) e.g. what are the S's?

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

#### Cookie banners & WP Consent API

The extension does not provide any UI, like a cookie banner, to let your visitors grant consent for tracking. However, it's integrated with [WP Consent API](https://wordpress.org/plugins/wp-consent-api/), so you can pick another extension that provides a banner that meets your needs.

Each of those extensions may require additional setup or registration. Usually, the basic default setup works out of the box, but there may be some integration caveats. Here are a couple of the most frequent ones:

##### Visitors who have not answered the banner yet

When the WP Consent API reports that the site is opt-in, a visitor who has not answered the banner is reported to Google as `denied` for every consent type, rather than being left on the regional defaults. Sites the WP Consent API reports as opt-out, and sites where no extension declares a consent type at all, are unaffected: their visitors keep the defaults until they make a choice.

##### GA4W overwrites the consent mode defaults set by the other extension

If the additional extension you chose sets its own default state of consent modes, different than the one we set, and you would like to make sure we'll not overwrite that, you can use the `woocommerce_ga_gtag_consent_modes` snippet to change or disable our setup:

```php
add_filter( 'woocommerce_ga_gtag_consent_modes', function ( $consent_modes ) {
   return array();
} );
```

##### I want to stop firing the `page_view` event on the page load

This is actually unrelated to the consent mode; it's a matter of the default tag config. You can alter it using the `woocommerce_ga_gtag_config` snippet

```php
add_filter( 'woocommerce_ga_gtag_config', function ( $config ) {
    $config['send_page_view'] = false;
   return $config;
} );
```

### Sensitive query parameters in the page URL

gtag reports the page URL (`page_location`, the `dl` parameter) and the referrer (`page_referrer`, `dr`) with every hit. Before gtag reads them, the extension strips query parameters that must not end up in Google Analytics, such as the WooCommerce order key on the order-received page:

- On every page it removes `key`, `login`, `session`, `email`, `uid`, `_wpnonce`, `_wp_http_referer`, `woo-share`, `moderation-hash`, `unapproved`, `email_link_action_key`, `consumer_key`, `consumer_secret`, `redirect` and `redirect_to`, plus any parameter that carries a WooCommerce order key as its value or inside a URL nested in it.
- On the order-received and order-pay pages it keeps only the endpoint parameters, `pay_for_order`, the parameters that identify the page on plain permalinks (`page_id`, `p`, `pagename`, `lang`, `currency`), the standard `utm_` parameters, the click identifiers (`gclid`, `gbraid`, `wbraid`, `dclid`, `fbclid`, `msclkid`, `srsltid`) and the cross-domain linker `_gl`.

URLs with nothing to strip are reported exactly as gtag would report them. The lists are filterable; hook the filters before WooCommerce loads its integrations (from your plugin’s main file or `plugins_loaded`):

```php
add_filter( 'woocommerce_ga_redacted_url_params', function ( $params ) {
    $params[] = 'token';
    return $params;
} );

add_filter( 'woocommerce_ga_order_page_allowed_url_params', function ( $params ) {
    $params[] = 'ref';
    return $params;
} );
```

To switch the redaction off entirely:

```php
add_filter( 'woocommerce_ga_url_redaction_enabled', '__return_false' );
```

If your `woocommerce_ga_gtag_config` filter sets `page_location` or `page_referrer`, those values are used as they are.

The redaction is performed by `window.wcGoogleAnalyticsIntegration.redactGtagConfig( config )`, which is installed before the tag snippet runs. It is registered separately from the snippet, so if you replace the snippet through `woocommerce_gtag_snippet` you can keep the redaction by passing your config through it:

```js
gtag( 'config', 'G-XXXXXXXXXX', wcGoogleAnalyticsIntegration.redactGtagConfig( { /* … */ } ) );
```

A replacement snippet that does not call it reports the unredacted URL.

This only changes what the extension reports to Google. Browsers send the page URL in the `Referer` header of the requests the page makes, which for third-party resources means the origin alone under the usual `strict-origin-when-cross-origin` policy, and the full URL if the site relaxes that policy. Other tags on the page report their own `page_location`.
