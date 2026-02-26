# Google Analytics for WooCommerce - Agent Guidelines

A WordPress/WooCommerce plugin that integrates Google Analytics (GA4) tracking via gtag.js into WooCommerce stores. Tracks e-commerce events (add to cart, purchases, etc.), supports both classic and block-based storefronts, and integrates with the WP Consent API for GDPR compliance.

## Project Structure

```
woocommerce-google-analytics-integration.php  — Main plugin entry point, singleton bootstrap
includes/
  class-wc-google-analytics.php               — WC_Integration subclass, settings, script loading
  class-wc-abstract-google-analytics-js.php   — Base class for JS event generation
  class-wc-google-gtag-js.php                 — GA4 gtag.js event implementation
  class-wc-google-analytics-task.php          — Setup task for WooCommerce admin
assets/js/src/                                — JS source (entry: index.js)
  tracker/                                    — Client-side event tracking
  integrations/                               — Block-based checkout/cart integration
  utils/                                      — Shared utilities
assets/js/build/                              — Webpack output (committed for distribution)
tests/unit-tests/                             — PHPUnit tests
tests/e2e/                                    — Playwright E2E tests
```

## Commands

### Setup

```sh
nvm use               # Switch to Node 20
npm install           # Install JS dependencies
composer install      # Install PHP dev dependencies (PHPCS, PHPUnit)
```

### Build

```sh
npm run dev           # Development build (unminified)
npm run start         # Watch mode with hot reload
npm run build         # Production build + pot file + zip archive
```

### Lint

```sh
npm run lint:js       # ESLint (WordPress rules)
npm run lint:php      # PHPCS (WordPress Coding Standards)
```

### Test

```sh
npm run wp-env:up                 # Start local WordPress Docker environment
npm run test:php:setup            # Install WooCommerce + deps for PHPUnit (once after wp-env:up)
npm run test:php                  # Run PHP unit tests via wp-env
npm run test:e2e                  # Run Playwright E2E tests (headless, requires wp-env)
npm run test:e2e-dev              # Run E2E tests in debug mode with browser visible
vendor/bin/phpunit                # Run PHP unit tests locally (see README.md for setup)
```

### Local Environment (wp-env)

The project uses `@wordpress/env` for local development. Configuration is in `.wp-env.json`.

```sh
npm run wp-env:up     # Start Docker environment (WordPress + WooCommerce + plugin)
npm run wp-env:down   # Stop Docker environment
```

The local environment runs at `http://localhost:8888` (admin: `admin`/`password`).

wp-env automatically installs Basic Auth plugin for API testing and runs `tests/e2e/bin/test-env-setup.sh` on startup to configure the test environment.

## Conventions

### PHP

- MUST follow WordPress Coding Standards. Run `npm run lint:php` to verify.
- Use tabs for indentation (not spaces) in PHP.
- Short array syntax `[]` is preferred over `array()`.
- Yoda conditions are NOT required (disabled in phpcs config).
- Text domain is `woocommerce-google-analytics-integration` — MUST be used in all translatable strings.
- The `manage_woocommerce` capability is a recognized custom capability.
- Hook names may use `/` as a delimiter.

### JavaScript

- MUST follow `@wordpress/eslint-plugin/recommended` rules.
- Use `@wordpress/i18n` for translations, `@wordpress/hooks` for extensibility.
- Source lives in `assets/js/src/`, builds to `assets/js/build/`.

### Indentation and Formatting

- PHP, JS: tabs, width 4.
- JSON: spaces, width 2.
- YAML: spaces, width 2.
- All files: UTF-8, LF line endings, final newline.

### Pull Requests

- Branch from `trunk`. The main branch is `trunk`, not `main` or `master`.
- Release branches follow the pattern `release/x.y.z`.
- PR template is at `.github/PULL_REQUEST_TEMPLATE.md`. Include: description, checks, test instructions, and a changelog entry.
- Changelog entries use prefixes: `Fix`, `Add`, `Update`, `Break`, `Tweak`, `Dev`, `Doc`.

### Commits

- Write concise commit messages focused on the "why".
- No "Co-Authored-By" lines.
- No "Generated with Claude Code" or similar agent bylines.

## Architecture Notes

- The plugin registers as a WooCommerce Integration (`WC_Integration` subclass). Settings live under WooCommerce > Settings > Integrations > Google Analytics.
- `WC_Google_Analytics` (the integration class) handles settings, admin UI, and script enqueuing.
- `WC_Google_Gtag_JS` generates server-side JavaScript snippets for GA4 events. It extends `WC_Abstract_Google_Analytics_JS`.
- Client-side tracking in `assets/js/src/` handles cart interactions and checkout events that can't be tracked server-side.
- The plugin declares HPOS (High-Performance Order Storage), cart/checkout blocks, and product block editor compatibility.
- GA4 measurement ID (format: `G-XXXXXXXXXX`) is configured in plugin settings, not hardcoded.

## Common Pitfalls

- **Do NOT edit files in `assets/js/build/`** — these are generated by Webpack. Edit source in `assets/js/src/` and run `npm run dev` or `npm run start`.
- **Do NOT add production PHP dependencies** via Composer. The plugin ships without a `vendor/` directory. Composer is dev-only (PHPCS, PHPUnit).
- **Do NOT use `main` or `master`** — the primary branch is `trunk`.
- **Do NOT hardcode tracking IDs** — the measurement ID comes from plugin settings via `WC_Google_Analytics::get_integration()`.
- **The `WC_GOOGLE_ANALYTICS_INTEGRATION_MIN_WC_VER` constant** gates minimum WooCommerce version. Don't use WC APIs newer than this version without a compatibility check.
- **External scripts (gtag.js) are loaded from Google** — the `WordPress.WP.EnqueuedResourceParameters.MissingVersion` PHPCS warning is intentionally suppressed for this reason.
