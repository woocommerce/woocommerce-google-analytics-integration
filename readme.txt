=== Google Analytics for WooCommerce ===
Contributors: woocommerce, automattic, claudiosanches, bor0, royho, laurendavissmith001, cshultz88, mmjones, tomalec, neosinner
Tags: woocommerce, google analytics
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 2.3.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Provides integration between Google Analytics and WooCommerce.

== Description ==

This plugin provides the integration between Google Analytics and the WooCommerce plugin. You can link a referral to a purchase and add transaction information to your Google Analytics data. It supports Global Site Tag (GA4) and eCommerce event tracking.

Please visit the [documentation page for additional information](https://woocommerce.com/document/google-analytics-integration/).

Contributions are welcome via the [GitHub repository](https://github.com/woocommerce/woocommerce-google-analytics-integration).

== Installation ==

1. Download the plugin file to your computer and unzip it
2. Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation’s wp-content/plugins/ directory.
3. Activate the plugin from the Plugins menu within the WordPress admin.
4. Don't forget to enable e-commerce tracking in your Google Analytics account: [https://support.google.com/analytics/answer/1009612?hl=en](https://support.google.com/analytics/answer/1009612?hl=en)

Or use the automatic installation wizard through your admin panel, just search for this plugin's name.

== Frequently Asked Questions ==

= Where can I find the setting for this plugin? =

This plugin will add the settings to the Integration tab, found in the WooCommerce → Settings menu.

= I don't see the code on my site. Where is it? =

We purposefully don't track admin visits to the site. Log out of the site (or open a Google Chrome Incognito window) and check if the code is there for non-admins.

Also please make sure to enter your Google Analytics ID under WooCommerce → Settings → Integrations.

= My code is there. Why is it still not tracking sales?  =

Duplicate Google Analytics code causes a conflict in tracking. Remove any other Google Analytics plugins or code from your site to avoid duplication and conflicts in tracking.

== Screenshots ==

1. Google Analytics Integration Settings.

== Changelog ==

= 2.3.0 - 2026-06-25 =
* Update - Require WooCommerce 10.8+.

= 2.2.1 - 2026-06-22 =
* Dev - Fix E2E tests against current WooCommerce shipping and checkout behavior.
* Dev - Update vulnerable npm development dependencies.
* Fix - Prevent a fatal TypeError on PHP 8 when a cart item, order line, or product loop references a product that no longer resolves to a WC_Product.

= 2.2.0 - 2026-06-09 =
* Add - Add a read-only Google Analytics tracking settings ability.
* Add - Expose JavaScript tracking formatters and utilities for custom integrations.
* Add - Track checkout shipping and payment info events.
* Dev - Add CodeRabbit configuration and ignore .claude/settings.local.json.
* Dev - Add focused unit coverage for asset metadata and tracking settings snapshots.
* Dev - Add product formatter unit coverage.
* Dev - Enable min-release-age supply-chain protection requiring npm >=11.10.0.
* Dev - Expand purchase event E2E coverage for transaction and order totals.
* Dev - Remove deprecated Products (Beta) block from E2E test coverage; Product Collection coverage supersedes it.
* Dev - Store Google Analytics tracking settings on the tracking instance.
* Dev - Update npm dependencies and transitive overrides to resolve non-breaking security advisories.
* Dev - Update npm development dependencies for security fixes.
* Fix - Ensure all settings defaults are populated in `$this->settings` on fresh activation, preventing undefined array key notices.
* Fix - Persist add_to_cart event across cart redirect.
* Fix - Price minor-unit rounding.
* Fix - Rename misspelled public method `enquque_tracker()` to `enqueue_tracker()`; keep deprecated alias for backward compatibility.
* Fix - Track Store API add-to-cart events without duplicate analytics events.
* Fix - Track Store API cart quantity decreases as remove_from_cart events.
* Fix - Track classic add_to_cart events without a button when single-product data is available.
* Fix - Track view_item_list, add_to_cart, and select_content events correctly when the cart is empty and products are rendered via a Product Collection block.
* Fix - `item_list_id` and `item_list_name` in `view_item_list` GA4 events now reflect the actual page context (shop, category, tag, search) instead of hardcoded placeholder values.
* Tweak - Improve tracking payload limits, cart event binding, and linker domain validation.
* Tweak - Remove the compatibility declaration for the product editor beta, which is being retired in WooCommerce 11.0.
* Tweak - WC 10.8 compatibility.
* Tweak - WP 7.0 compatibility.
* Update - Declare compatibility with WordPress 7.0 while requiring WordPress 6.9 or newer.
* Update - Format product categories in parent-first hierarchy order.
* Update - Require WooCommerce 10.7+.
* Update - Use get_order_number() for transaction_id.

= 2.1.23 - 2026-04-15 =
* Dev - Enable min-release-age supply-chain protection.
* Dev - Override serialize-javascript to ^7.0.5 and ajv to ^8.18.0 to resolve npm security vulnerabilities.
* Update - Require WooCommerce 10.6+.

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-google-analytics-integration/trunk/changelog.txt).
