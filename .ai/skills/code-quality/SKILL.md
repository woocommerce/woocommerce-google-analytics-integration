---
name: code-quality
description: Use when linting, formatting or checking Google Analytics for WooCommerce code before a commit or push – PHPCS, ESLint, Prettier, the PHP deprecation check, the pre-commit hook or what CI runs. Triggers on "run the linters", "fix lint errors", "format this", "run qa", "the pre-commit hook failed", "why did CI lint fail".
---

# Code quality

## Commands

| Command | What it runs |
|---|---|
| `npm run lint` | `lint:php`, `lint:php:deprecations`, `lint:js` |
| `npm run lint:php` | PHPCS with WordPress Coding Standards (`phpcs.xml.dist`). Needs `composer install`. |
| `npm run lint:php:deprecations` | `bin/lint-php-deprecations.sh`: `php -l` on every PHP file with your local PHP, failing on compile-time deprecations. |
| `npm run lint:js` | ESLint (`eslint.config.mjs`, `@wordpress/eslint-plugin/recommended`), which also checks Prettier formatting. Fails on warnings (`--max-warnings 0`). |
| `npm run format` | Prettier (`wp-prettier`) on JS files. |
| `npm run qa` | `lint` + `test:js`. Run it before every push. |

There is no CSS lint and no PHPStan.

## One file

- PHP: `vendor/bin/phpcs <file>`; fix what can be fixed with `vendor/bin/phpcbf <file>`.
- JS: `npx eslint --max-warnings 0 <file>`; fix with `npx eslint --fix <file>`.

PHPCS output help: <https://github.com/squizlabs/PHP_CodeSniffer/wiki/Usage#printing-progress-information>.

## Pre-commit hook

`npm install` sets up Husky and lint-staged. On commit, `tasks/lint-staged-php.sh` runs PHPCS on staged PHP files and `tasks/lint-staged-js.sh` runs ESLint (`--max-warnings 0 --no-warn-ignored`) on staged JS files. A problem blocks the commit.

Switches go in `.env` in the repository root (gitignored); `.husky/common.sh` reads it:

```sh
MP_GIT_HOOKS_ENABLE=false        # skip the whole hook
MP_GIT_HOOKS_CODE_SNIFFER=false  # skip PHPCS
MP_GIT_HOOKS_ESLINT=false        # skip ESLint
```

The `MP_` prefix comes from MailPoet, so one `.env` works in every plugin repo. Do not use `--no-verify`. The hook only sees staged files, so it does not replace `npm run qa`.

## What CI runs

| Workflow | Check |
|---|---|
| `php-coding-standards.yml` | `vendor/bin/phpcs` on all files, at PHP 7.4 |
| `js-linting.yml` | `npm run lint:js` with GitHub annotations |
| `js-unit-tests.yml` | `npm run test:js` |
| `php-unit-tests.yml` | `bin/lint-php-deprecations.sh` and PHPUnit on a PHP and WooCommerce matrix |
| `e2e-tests.yml` | Production build, then Playwright on the test wp-env site |
| `build.yml`, `plugin-check.yml` | Plugin zip and the WordPress.org Plugin Check |

`npm run qa` covers the first three and the deprecation check with your local PHP. For PHPUnit and E2E, see the `running-tests` skill.
