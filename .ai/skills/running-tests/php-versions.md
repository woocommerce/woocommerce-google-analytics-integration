# PHP versions

The dev and test PHP version is set in:

- `.wp-env.json` and `.wp-env.test.json` (`phpVersion`). The test file drives E2E in CI and `npm run test:php`.
- `.github/workflows/php-unit-tests.yml`: the matrix `php` default, the `include` rows, and the `workflow_dispatch` `php-version` default.

These stay at the minimum on purpose: `Requires PHP: 7.4` in the plugin header, `config.platform.php` (7.4.33) in `composer.json`, and PHPCS in `php-coding-standards.yml`.

To try another version without editing files: `WP_ENV_PHP_VERSION=8.5 npm run wp-env:up`.

## Moving to a new PHP version

1. Set `phpVersion` in both wp-env files.
2. Make it the matrix default and the `workflow_dispatch` default. Move the previous default into an `include` row.
3. Start the test site: `npm run wp-env:up`.
4. Run `npm run test:php`.
5. Run `npx wp-env run cli --config=.wp-env.test.json --env-cwd=wp-content/plugins/woocommerce-google-analytics-integration -- ./bin/lint-php-deprecations.sh`.
6. Run `npm run test:e2e`, then `npm run test:e2e:debug-log`.

Guards: PHPUnit fails on deprecations raised in tests. `lint:php:deprecations` catches compile-time ones. `test:e2e:debug-log` fails on plugin lines in `debug.log`.
