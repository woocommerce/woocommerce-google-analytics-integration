---
name: woo-beta-compat-test
description: Use when a new WooCommerce beta or release candidate is available and you need to test the plugin's compatibility. Triggers on phrases like "test against WC beta", "compatibility test", "new WooCommerce version".
---

# WooCommerce Beta Compatibility Test

## Overview

Run a full compatibility test of the released plugin against a new WooCommerce beta version. This covers QIT automated tests, GitHub Actions CI, and manual Playwright smoke tests.

## Usage

Invoke with the WooCommerce beta version as argument:
```
/woo-beta-compat-test 10.6.0-beta.1
```

## Process

Follow all four steps sequentially. Report results after each step before moving to the next.

### Step 1 — Download the latest stable plugin zip

Download the currently released version from wordpress.org:
```bash
curl -L -o /tmp/woocommerce-google-analytics-integration.zip \
  "https://downloads.wordpress.org/plugin/woocommerce-google-analytics-integration.latest-stable.zip"
```

Verify the download succeeded and the file is a valid zip.

### Step 2 — Run QIT commands

Run these three QIT commands with the downloaded zip. Use the exact beta version provided by the user first. Only if a version is not available, fall back to the `X.Y.Z-dev` format (e.g., `10.6.0-beta.1` → `10.6.0-dev`). Use `--help` on the failing command to discover available versions.

**Note:** Each command uses different flags — pay attention to the differences.

```bash
# run:activation uses --source (not --zip) and --woo for version
qit run:activation \
  --woo "<VERSION>" \
  --source /tmp/woocommerce-google-analytics-integration.zip \
  woocommerce-google-analytics-integration

# run:woo-api uses --zip and --woocommerce_version, supports --wait
qit run:woo-api \
  --woocommerce_version="<VERSION>" \
  --zip /tmp/woocommerce-google-analytics-integration.zip \
  --wait \
  woocommerce-google-analytics-integration

# run:woo-e2e uses --zip and --woocommerce_version, supports --wait
qit run:woo-e2e \
  --woocommerce_version "<VERSION>" \
  --zip /tmp/woocommerce-google-analytics-integration.zip \
  --wait \
  woocommerce-google-analytics-integration
```

The `--woocommerce_version` flag on `run:woo-api` and `run:woo-e2e` only accepts specific enumerated values (check `--help` output). If the beta version is not listed, use the `-dev` suffix version instead.

After each command completes, analyze the report output. Summarize pass/fail status and highlight any failures with details.

### Step 3 — Trigger GitHub Actions and wait for results

Trigger both CI workflows with the beta version and wait for completion:

```bash
gh workflow run e2e-tests.yml \
  -f wc-rc-version="<VERSION>"

gh workflow run php-unit-tests.yml \
  -f wc-rc-version="<VERSION>"
```

After triggering:
1. Use `gh run list --workflow=<workflow>` to find the run IDs
2. Use `gh run watch <run-id>` to wait for each run to complete
3. If a run fails, fetch the logs with `gh run view <run-id> --log-failed`
4. Analyze the failure logs and report what went wrong
5. If the failure is in plugin code, suggest or apply a fix

### Step 4 — Playwright smoke tests

Start the local WordPress environment with the WooCommerce beta installed:

```bash
npm run wp-env:up
npm run build
npm run -- wp-env run tests-cli -- wp plugin update woocommerce --version="<VERSION>"
npm run -- wp-env run tests-cli -- wp wc update
```

Then use the Playwright MCP browser to perform these smoke tests:

1. **Settings page** — Navigate to `wp-admin` (user: `admin`, pass: `password`), go to WooCommerce → Settings → Integration → Google Analytics. Verify the settings page loads without errors.
2. **Save settings** — Enter a test tracking ID (`G-TEST12345`), save. Verify settings persist after reload.
3. **Frontend GA snippet** — Navigate to the shop page. View page source or inspect the HTML to verify the gtag.js snippet is present with the correct tracking ID.
4. **Product page** — Open any product page. Verify it loads without errors and the GA snippet is present.
5. **Add to cart** — Add a product to the cart. Verify the cart updates and no JS errors appear in the console.

Report the result of each check. If any check fails, provide details and screenshots.

## After testing

Summarize all results in a table:

| Test | Status | Notes |
|------|--------|-------|
| QIT Activation | ✅/❌ | |
| QIT API | ✅/❌ | |
| QIT E2E | ✅/❌ | |
| GH Action: E2E Tests | ✅/❌ | |
| GH Action: PHP Unit Tests | ✅/❌ | |
| Smoke: Settings page | ✅/❌ | |
| Smoke: Save settings | ✅/❌ | |
| Smoke: Frontend GA snippet | ✅/❌ | |
| Smoke: Product page | ✅/❌ | |
| Smoke: Add to cart | ✅/❌ | |

If any tests failed, list the issues and recommended next steps.
