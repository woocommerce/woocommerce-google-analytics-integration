---
name: writing-tests
description: Use before writing, planning or reviewing tests for Google Analytics for WooCommerce – deciding whether a change needs a test at all, which suite it belongs in (JS unit, PHPUnit, E2E), how to write it, and how to prove it catches a real regression. Triggers on "write a test", "add tests for this", "which suite should this test go in", "review these tests". For running the tests see running-tests.
---

# Writing tests

Tests cost time three times: to write, on every CI run, and every time someone debugs a red build. Write the tests that catch a real regression, at the cheapest level that can catch it, and no others. A test earns its place when it catches a break no other test catches.

**Lint the pattern, test the decision.** Much of the code follows a pattern (a settings field, a hook callback, a gtag event handler). A lint or static-analysis rule checks the pattern in every file, including files not written yet. A test is for the part of a file that goes beyond its pattern. Full coverage is not the goal.

For running tests see the `running-tests` skill. Commands come from `package.json`; don’t guess them.

## 1. Does this change need a test?

Ask: **if someone breaks this next month, what goes wrong, and would anyone notice before a merchant does?**

- **Yes, test it:** logic that makes a decision (which events fire, the prices, quantities and IDs in an event, consent defaults, what is redacted from URLs sent to Google, who is not tracked), a bug that has happened before, and every security fix.
- **Usually no new test:**
  - Copy, styling, layout and markup changes. Check them in the browser.
  - Config, version bumps, dependency updates, renames and pure refactors. The existing tests must pass before and after.
  - Code that only passes values through to WordPress, WooCommerce or gtag.js with no decision of its own. Don’t test the framework.
  - A small, low-risk fix whose only possible test would be an E2E test. Check it by hand instead.
- **Already covered?** Search `tests/` first. Add an assertion to an existing test or a case to a data provider before writing a new test.

**Name the regression before you keep a test.** Write one sentence: a realistic code change that would make this test fail and that no existing test already catches. “If `get_catalog_price()` stopped reading the composite price, Composite Products would be reported at their base price, often 0” is a sentence. “Verifies the class works” is not. No sentence, no test.

**What to cover:** the happy path, plus usually one to three high-impact cases. A case is high impact when it is a failure that loses data, money, access or security (wrong revenue in a purchase event, data sent before consent, a personal value left in a URL); the bug being fixed; or the main error result a caller acts on. Skip boundary, empty-input and odd-format variants unless one of them is the bug.

**A file that only follows its pattern gets no test of its own.** Such a test restates the code and never finds a bug. A file earns a test when it decides something the pattern doesn’t:

- it branches on a type, scope, role or status (product type, order status, user role, classic or block storefront);
- it builds a write from a read, and a failed read must stop the write;
- it updates only some fields (which ones reach the write?);
- it does more than one write (order, and what happens when one fails);
- it makes a security decision, such as a value the server decides instead of taking it from the request;
- it remaps or swallows an error, or reshapes a payload (event data built from a cart, an order or a Store API response).

Plain utility functions have no pattern: test them directly.

When you decide not to write a test, say so in the PR in one line, with the reason. “No test: copy change, checked in the browser” is a complete answer.

## 2. Which suite?

Pick the **lowest** level that can catch the failure. Go one level up only when the lower one can’t, and write down why.

| You need to test… | Use | Why |
|---|---|---|
| JS logic in `assets/js/src/` (event payloads, Store API parsing, labels), or JS embedded in a PHP string | JS unit, `tests/js/` | Node.js test runner, no Docker, part of `npm run qa` |
| PHP logic: event data, settings, script registration, notices, abilities | PHPUnit, `tests/unit-tests/` | Real WordPress and WooCommerce on the wp-env test site; the only PHP suite |
| A gtag request that only a real browser on a classic or block page sends, or consent with the real WP Consent API | E2E, `tests/e2e/specs/` | Only place a page actually sends events |

- **JS unit is the default for JS.** Load the module with `tests/js/helpers/load-source.js` and stub the globals it reads (`globalThis.window`, `document`). If the code is hard to test because it reads the DOM or globals in the middle of its logic, a small refactor that moves the decision into a plain function is often the better fix.
- **PHPUnit for PHP.** There is no PHP suite without WordPress: despite the folder name, every test in `tests/unit-tests/` loads WordPress, WooCommerce and the `wordpress_test` database. Call the class directly with plain inputs and real WooCommerce objects. Don’t mock our own classes to feed the decision its data: a test with mocked data can’t notice when the real source starts returning something else.
- **E2E almost never.** Slow, flaky and slow to debug. Add one only for a critical flow (purchase or add-to-cart events on classic and block pages, consent) when the task asks for it, never for a bug a JS unit test or PHPUnit can catch. If a spec already walks the flow, add an assertion to it instead of a new test.

### Pattern checks belong in a linter, not a test

If a test would only check that a file follows its pattern (output is escaped, input is sanitized, the nonce is checked, the text domain is right), that check belongs in PHPCS, PHPStan or ESLint. A rule catches a dropped line in every file, with `file:line`, before review.

- PHPCS (`phpcs.xml.dist`) runs the WordPress standard, including `WordPress.Security.EscapeOutput`, `WordPress.Security.ValidatedSanitizedInput`, `WordPress.Security.NonceVerification`, `WordPress.WP.I18n` (with the plugin text domain) and `WordPress.WP.Capabilities`.
- PHPStan (`phpstan.neon.dist`) checks the plugin code at level 9, not the tests.
- ESLint (`eslint.config.mjs`) runs `@wordpress/eslint-plugin/recommended`.
- If the rule exists, rely on it and don’t repeat it in a test. If it doesn’t, say in the PR which rule would replace the test, instead of writing the pattern test.

## 3. Where new tests go and what they extend

### JS unit (`tests/js/`)

- File pattern `<name>.test.js`. `node:test` (`test`, `beforeEach`) and `node:assert`.
- Load sources with `require( './helpers/load-source' )( '<path in assets/js/src>' )`. Reset stubbed globals and module state in `beforeEach` (for example `api.forgetCartItems()` in `tests/js/store-api.test.js`).
- Time: `t.mock.timers`, as `tests/js/block-grid-add-to-cart.test.js` does.
- Test logic, not the DOM.

### PHPUnit (`tests/unit-tests/`)

- One class per file, file name = class name (`TrackingId.php`), namespace `GoogleAnalyticsIntegration\Tests`. Methods are `test_<what_happens>`, with a docblock (PHPCS checks tests too).
- Base class `WP_UnitTestCase`. Tests about event data extend `GoogleAnalyticsIntegration\Tests\EventsDataTest` (`tests/EventsDataTest.php`): it creates one product, customer and order per class, and records the `woocommerce_gtag_event_data` filter (`get_event_data()`, `get_event_data_filter_call_count()`).
- Use `set_up()` / `tear_down()` (snake_case). Tests that load tracking use the `ResetsGtagInstance` trait (`tests/ResetsGtagInstance.php`) so the singleton does not leak into the next test.
- PHPUnit 9.6 (`composer.json`). Data providers use the `@dataProvider` annotation.

### E2E (`tests/e2e/specs/`)

- Playwright, file pattern `<name>.test.js`, grouped by area (`gtag-events/`, `js-scripts/`).
- Set up through the helpers in `tests/e2e/utils/`: `api.js` (products, coupons, tax rates, settings over REST), `create-page.js`, `customer.js` (add to cart, checkout) and `track-event.js` (`trackGtagEvent()`, `getEventData()` read the gtag request the page sends).
- PHP the tests need on the site goes in `tests/e2e/test-snippets/test-snippets.php`.
- Prefer `getByRole` and other user-facing locators to CSS classes from WooCommerce markup. Wait for the request or the element (`trackGtagEvent()`, `expect(…)`), not a fixed `waitForTimeout()`.

### Test data

PHPUnit: the WooCommerce test helpers the bootstrap loads (`WC_Helper_Product`, `WC_Helper_Order`, `WC_Helper_Customer`, `WC_Helper_Coupon`, `WC_Helper_Shipping`) and the WordPress factory. E2E: `tests/e2e/utils/api.js`, `tests/e2e/config/default.json` and `tests/e2e/utils/fixtures/`. If you build the same data a second time, add it to a helper.

## 4. How to write it

- **Small, linear tests with descriptive names.** No `if` / `for` / `while` in a PHP test: loops hide which case failed. In JS, a `for … of` over a short table of cases is the data-provider form; give every assertion a message that names the case, as `tests/js/store-api.test.js` does. The name says the scenario and the expected outcome, so CI output alone tells a reader what broke.
- **Test behaviour through the public interface.** No reflection into private or protected methods.
- **Assert values, not shapes.** `assertSame( 'G-ABC123', … )` or `assert.strictEqual( …, 'G-ABC123' )`, not `assertNotEmpty()`, `assertIsArray()` or `assert.ok()`.
- **`assertSame()` over `assertEquals()`** in new assertions, unless you compare objects by value. In JS, `assert.strictEqual()` and `assert.deepStrictEqual()`. Don’t rewrite existing assertions only to change this.
- **Write the expected value as a literal.** Don’t recompute it with the helpers or defaults the code under test uses, or the test agrees with the bug.
- **Mocks only at the edges** (HTTP, time, `window.gtag`, the browser globals, other plugins such as Product Bundles or Composite Products). WordPress and WooCommerce are real in PHPUnit: use them. A test that only checks that your mocks were called proves the mocks work, not the code. Assert what leaves the code instead: the event data passed to `woocommerce_gtag_event_data`, the script or inline data registered in `wp_scripts()`, the options saved, the gtag request sent.
- **Stubs must not hide calls.** A method you list in `onlyMethods()` or `addMethods()`, and every method of a `createMock()` object, returns an empty value (`null`, `false`, `[]`, `''`, by return type) unless the test sets it up, so an unexpected call passes silently and can send the code down another branch. When a call must not happen, say so with `->expects( $this->never() )`, as `tests/unit-tests/DataFormatting.php` does.
- **Don’t copy setup between test files.** A setup you need a second time belongs in a shared base class or helper (`tests/EventsDataTest.php`, `tests/js/helpers/`, `tests/e2e/utils/`). Set up the happy path once in `set_up()` / `beforeEach`, then each test changes only what it is about.
- **Check behaviour that goes through another plugin once against the real plugin.** Product Bundles and Composite Products are tested with stubs, and a stub can drift from the real plugin. When a change alters what is tracked through another plugin, run it at least once with the real plugin installed (a browser check) and say which version in the PR. The WP Consent API is installed for real on the E2E site.
- **No hollow tests:** no `assertTrue( true )`, no `addToAssertionCount( 1 )`, no `assert.ok( true )`, no “the class exists”, no “does not throw” with nothing else checked, no test that pins a constant to its own literal.
- **No near-duplicates.** Three tests that differ in one input are one test with a data provider. Keep providers short (about 3 to 6 cases, one per distinct outcome), with a string key per case, as in `tests/unit-tests/PluginNotices.php`. New providers can be `public static`: PHPUnit 9.6 accepts both and PHPUnit 10 requires it.
- **Pin broad exceptions.** A bare `expectException( \Exception::class )` or `assert.throws( fn )` passes when the wrong path throws. Expect the specific class, or match a short, stable part of the message when nothing else tells the paths apart.
- **Don’t assert inside a callback the code under test may catch.** Filters, event handlers and stub callbacks often run inside a production `try` / `catch` (the block integrations in `assets/js/src/integrations/blocks.js` catch errors), which swallows the failed assertion. Record what the callback received, then assert after the call returns.
- **Fast and isolated.** No sleeps, no real time, no dependence on test order or on state left by another test (the tracking singleton, module state, options).
- **Prefer self-explanatory tests over comments.** Rename or split the test first; keep a comment only if it still adds something.
- **Only the tests your change adds or changes.** Don’t rewrite existing tests that are out of scope.

## 5. Prove it

- **Red before green.** Run each new test before the fix and watch it fail for the reason it exists (the assertion about the bug, not a syntax error or a missing fixture). Then fix, and watch it pass. Never commit a test you haven’t seen pass; if you can’t run it, say so.
- **Break what it protects.** Delete the guard, flip the condition or return early, confirm the test fails, then restore the file and confirm it passes. If it still passes with the guard removed, tighten the assertion or drop the test. This is required for security fixes.
- **Run the cheap tests often:** `node --test tests/js/<file>.test.js`, or `npm run test:php -- --filter <TestClass or method>` for the PHPUnit tests you touched. Run the full PHPUnit and E2E suites at the end. Don’t start two PHPUnit runs at the same time on one test site: they share the `wordpress_test` database.

## 6. Reviewing tests you didn’t write

Apply sections 1, 2 and 4 to the tests a PR adds or changes, and report findings instead of editing: tests with no nameable regression, edge-case bulk, near-duplicates, hollow or constant-pinning tests, computed expected values, broad exceptions, assertions inside caught callbacks, mocks beyond the edges, pattern checks that belong in a linter, and a suite higher than needed. Don’t flag tests the PR didn’t touch.

## 7. Report

When you hand the work back (to the person who asked, or in the agent’s final report), show the test decision so nobody has to guess it:

```
Tests
Kept:
- TrackingId::test_bar (PHPUnit): catches <realistic change that would fail it>; fails when <the line you broke>
Merged or dropped:
- test_x, test_y → merged into the test_z data provider (near-duplicates)
Not tested:
- <what, and the one-line reason, e.g. "copy change, checked in the browser">
```

The PR gets only what the diff doesn’t show, in “Code review notes”: the “Not tested” lines, and a suite choice above the default with its reason (“E2E, because the event is only sent by the block checkout”). Kept tests are in the diff, so don’t list them there.

Before reporting, compare the number of new tests with the lines of production code changed. Many tests for a small change usually means one change tested many times: look again.

## Related skills

- `running-tests` – run PHPUnit, JS and E2E tests, one file or one test, and set up the wp-env test site.
- `code-quality` – lint, format and `qa` before pushing.
- `reviewing-code` – review a PR or local changes, including its tests.
