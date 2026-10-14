# Google Analytics for WooCommerce – Agent Guidelines

A WordPress/WooCommerce plugin that adds Google Analytics (GA4) tracking through gtag.js. It tracks e‑commerce events (add to cart, purchase and more) on classic and block‑based storefronts, and integrates with the WP Consent API for GDPR compliance.
Stack: PHP with no production Composer dependencies, JavaScript built with `@wordpress/scripts`, PHPUnit, Playwright and the Node.js test runner.

## Commands

| Task | Command |
|---|---|
| Start / stop dev site | `npm run env:start` / `npm run env:stop` |
| All lint checks | `npm run lint` (`lint:php`, `lint:php:deprecations`, `lint:js`) |
| Format | `npm run format` |
| Everything CI checks without a database | `npm run qa` |
| Tests | `npm run test:php`, `npm run test:js`, `npm run test:e2e` |

- Setup: `npm install`, `composer install` (dev tools only: PHPCS, PHPUnit). Use the Node version in `.nvmrc` (`nvm use`).
- Build: `npm run dev` (unminified), `npm run start` (watch), `npm run build` (production bundle, `.pot` file and plugin zip).
- The dev site runs at `http://localhost:8888`. `env:start`/`env:stop` are aliases for `wp-env:dev:up`/`wp-env:dev:down`.
- `test:php` and `test:e2e` run in a separate test site: `npm run wp-env:up`, `http://localhost:8889`, `admin`/`password`. See the `running-tests` skill.
- `qa` = `lint` + `test:js` and needs no Docker. CI also runs PHPUnit, E2E, Plugin Check and a build.
- `lint:js` fails on warnings too (`--max-warnings 0`). There is no `lint:css` or `lint:phpstan`.

## Git hooks

On commit, Husky runs lint-staged on staged files. To turn checks off, set these in `.env` in the repository root (gitignored): `MP_GIT_HOOKS_ENABLE=false` turns off all hooks; `MP_GIT_HOOKS_ESLINT=false` and `MP_GIT_HOOKS_CODE_SNIFFER=false` turn off one check each. Hooks only see staged files, so still run `npm run qa` before pushing. Do not use `--no-verify`.

The lint-staged scripts are in `tasks/`.

## Architecture

```
woocommerce-google-analytics-integration.php  – main plugin file, singleton bootstrap
includes/
  class-wc-google-analytics.php               – WC_Integration subclass: settings, admin UI, script loading
  class-wc-abstract-google-analytics-js.php   – base class for JS event generation
  class-wc-google-gtag-js.php                 – GA4 gtag.js events
  class-wc-google-analytics-task.php          – setup task for WooCommerce admin
assets/js/src/                                – JS source (entry: index.js)
  tracker/                                    – client-side event tracking
  integrations/                               – classic and block storefronts, WP Consent API
  utils/                                      – shared utilities
assets/js/build/                              – Webpack output (gitignored, added to the release zip)
tests/unit-tests/                             – PHPUnit tests
tests/e2e/                                    – Playwright E2E tests
tests/js/                                     – JS unit tests (Node.js test runner)
```

- The plugin registers as a WooCommerce Integration (`WC_Integration` subclass). Settings live under WooCommerce > Settings > Integrations > Google Analytics.
- `WC_Google_Gtag_JS` extends `WC_Abstract_Google_Analytics_JS` and builds the server‑side GA4 event snippets.
- Client‑side code in `assets/js/src/` tracks cart and checkout events that the server cannot see.
- The plugin declares HPOS and cart/checkout blocks compatibility.
- The GA4 measurement ID (`G-XXXXXXXXXX`) comes from the plugin settings via `WC_Google_Analytics_Integration::get_integration()`.

## Code rules

- PHP: WordPress Coding Standards (`npm run lint:php`). Tabs. Short array syntax `[]`. Yoda conditions are not required (turned off in `phpcs.xml.dist`).
- Text domain: `woocommerce-google-analytics-integration` in every translatable string.
- `manage_woocommerce` is a known custom capability. Hook names may use `/` as a delimiter.
- JS: `@wordpress/eslint-plugin/recommended`. Use `@wordpress/i18n` for translations and `@wordpress/hooks` for extensibility.
- Edit JS in `assets/js/src/`, then run `npm run dev` or `npm run start`.
- Formatting: PHP and JS use tabs (width 4); JSON and YAML use 2 spaces. All files: UTF‑8, LF, final newline.
- `WC_GOOGLE_ANALYTICS_INTEGRATION_MIN_WC_VER` is the minimum WooCommerce version. Do not use newer WooCommerce APIs without a compatibility check.
- Tag new hooks, functions and methods `@since x.x.x`. Woorelease replaces it with the version in the paths listed in `package.json` `config.version_replace_paths`.

## Backward compatibility

A change to an externally exposed surface must state its BC impact in the PR description. If you cannot establish the impact, stop and flag it.

- Hooks listed in `docs/Hooks.md` (`woocommerce_ga_*`, `woocommerce_gtag_*`). A release workflow regenerates that file.
- `window.wcGoogleAnalyticsIntegration` (for example `redactGtagConfig()`), which custom snippets call.
- Settings stored in `woocommerce_google_analytics_settings`, and the legacy `woocommerce_ga_*` options that still feed the defaults.
- Public PHP classes and constants (`WC_Google_Analytics`, `WC_GOOGLE_ANALYTICS_INTEGRATION_*`).
- The minimum PHP version stays at 7.4: `Requires PHP` in the plugin header, `config.platform.php` (7.4.33) in `composer.json`, and PHPCS in `php-coding-standards.yml`.

## Gotchas

- Do not add production PHP dependencies with Composer. The plugin ships without `vendor/`.
- gtag.js loads from Google, so the PHPCS warning `WordPress.WP.EnqueuedResourceParameters.MissingVersion` is suppressed on purpose.
- There are two wp-env configs: `.wp-env.json` is the dev site (port 8888) and `.wp-env.test.json` is the test site (port 8889) that PHPUnit, E2E and CI use. `wp-env run cli` commands for tests need `--config=.wp-env.test.json`.
- The main branch is `trunk`, not `main` or `master`.

## Git and pull requests

Branch from `trunk`; never commit to it. Start branches with the `starting-branch` skill. Open PRs with the `creating-pull-requests` skill; never run `gh pr create` directly. PRs follow `.github/pull_request_template.md`; write `_N/A_` in sections that don’t apply.

Fill `### Changelog entry`, the last template section, with lines like `Fix - …` (`Add`, `Update`, `Fix`, `Tweak`, `Dev`, `Doc`, `Break`). Woorelease parses it, so keep the heading exact. With no entry, add the `changelog: none` label.

- Commit messages are short and explain why.
- No `Co-Authored-By` lines and no “Generated with Claude Code” or other agent bylines.
- The branch prefix sets the changelog label: `add/`, `update/`, `fix/`, `tweak/`, `dev/`, `doc/`, `breaking/`.
- Release branches are `release/x.y.z`.

## Skills

Skills live in `.ai/skills/` (`.claude/skills` links there). Claude Code loads them as skills. Other agents read `.ai/skills/<name>/SKILL.md` when the task matches. After reading one, also apply `~/.ai/skills/<name>-personal/SKILL.md` if it exists; it wins on conflict.

- `starting-branch` – name and create a branch from `trunk`.
- `creating-pull-requests` – run the checks, fill the template and changelog entry, push and open the PR.
- `code-quality` – lint, format, `qa`, hook switches and what CI runs.
- `running-tests` – PHPUnit, JS and E2E tests, the wp-env setup and PHP version bumps.
- `reviewing-code` – review a PR or local changes against these rules.
- `writing-changelog` – the `### Changelog entry` section and the `changelog: none` label.
- `beta-compat-test` – test the released plugin against a WooCommerce beta or RC.

## Boundaries

- **Always:** run `npm run qa` and the tests for the code you touched before pushing; sanitize input and escape output; follow the PR template; use the plugin text domain.
- **Ask first:** new Composer or npm dependencies; CI workflow changes; changes to a surface listed under Backward compatibility; raising the minimum PHP or WooCommerce version.
- **Never:** commit to `trunk`; commit `.env`, secrets or API keys; edit generated or vendored files (`assets/js/build/`, `vendor/`, `node_modules/`, `docs/Hooks.md`); skip hooks with `--no-verify`; hard‑code a tracking ID.
