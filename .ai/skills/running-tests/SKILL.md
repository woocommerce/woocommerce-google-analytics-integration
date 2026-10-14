---
name: running-tests
description: Use when running Google Analytics for WooCommerce tests – PHPUnit, JS unit tests or Playwright E2E, a full suite, one file or one test – or when setting up the wp-env test site or moving it to another PHP version. Triggers on "run the tests", "run this test", "why does this test fail locally", "start wp-env", "bump the PHP version".
---

# Running tests

## JS unit tests

`npm run test:js` runs `tests/js/**/*.test.js` with the Node.js test runner. No Docker needed. It is part of `npm run qa`.

One file: `node --test tests/js/<file>.test.js`. One test: add `--test-name-pattern="<name>"`.

## The wp-env sites

The repo has two isolated `@wordpress/env` configs. Both set `testsEnvironment: false`, so each runs one environment (the old automatic second tests environment is deprecated in `@wordpress/env` 11).

| Config | Port | Use | Commands |
|---|---|---|---|
| `.wp-env.json` | 8888 | Manual dev work (WooCommerce + plugin) | `npm run env:start` / `env:stop`, `wp-env:dev:destroy` |
| `.wp-env.test.json` | 8889 | PHPUnit, E2E and CI | `npm run wp-env:up` / `wp-env:down` / `wp-env:destroy` |

- The test site’s admin login is `admin`/`password`. Both sites can run at the same time.
- The test config installs Basic Auth for API tests and runs `tests/e2e/bin/test-env-setup.sh` after start and clean.
- The test npm scripts and CI pass `--config=.wp-env.test.json`. `wp-env run cli` must target it too. Scripts read the config from `WP_ENV_CONFIG_FILE`, which the npm scripts and lifecycle scripts set.
- `wp-env:destroy` deletes everything in the test containers, including volumes and images.

## PHPUnit (wp-env, recommended)

1. Start Docker, then `npm run wp-env:up`.
2. `npm run test:php:setup` – creates the `wordpress_test` database, installs WooCommerce if it is missing, and downloads the WooCommerce test helpers from GitHub. Run it once after each start. For a specific WooCommerce version: `npm run test:php:setup -- --wc-version=9.6.0`.
3. `npm run test:php` – runs every test in `tests/unit-tests/`.

One file or test: `npm run test:php -- --filter <TestClass or method>`.

PHPUnit uses its own `wordpress_test` database (`phpunit.xml.dist`). The site at 8889 and E2E use the `wordpress` database in the same MySQL container, so the two suites do not interfere and run in any order.

PHPUnit fails on any deprecation, notice or warning raised in a test.

## PHPUnit without Docker

1. Install composer, git, xdebug, svn, wget or curl, and mysqladmin.
2. `composer install` in the plugin directory.
3. `bin/install-unit-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [wc-version] [skip-database-creation]`, e.g. `bin/install-unit-tests.sh wordpress_test root root localhost latest latest`.
4. `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text`.

CI runs PHPUnit this way, on a matrix of PHP and WooCommerce versions (`.github/workflows/php-unit-tests.yml`).

## E2E (Playwright)

1. `npm run build` (or `npm run dev`), then `npm run wp-env:up`.
2. Once: `npx playwright install chromium`.
3. `npm run test:e2e` – headless. `pretest:e2e` empties `debug.log` first.
4. `npm run test:e2e:debug-log` – fails if the plugin wrote lines to `debug.log` during the run.

`npm run test:e2e-dev` runs the tests in a visible browser with the Playwright debugger. One spec: `npm run test:e2e -- tests/e2e/specs/<file>.test.js`. Failure screenshots land in `tests/e2e/test-results/`.

On GitHub Actions, E2E runs on every PR (except docs-only changes) and on pushes to `trunk` and `release/*`. To test a WordPress or WooCommerce RC, run the `e2e-tests.yml` or `php-unit-tests.yml` workflow by hand with its RC inputs (see the `beta-compat-test` skill).

## PHP versions

See [php-versions.md](php-versions.md) for where the PHP version is set and how to move to a new one.
