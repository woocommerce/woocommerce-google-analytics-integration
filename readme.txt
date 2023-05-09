=== WooCommerce Google Analytics Integration ===
Contributors: woocommerce, automattic, claudiosanches, bor0, royho, laurendavissmith001, c-shultz
Tags: woocommerce, google analytics
Requires at least: 3.9
Tested up to: 6.2
Stable tag: 1.8.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Provides integration between Google Analytics and WooCommerce.

== Description ==

This plugin provides the integration between Google Analytics and the WooCommerce plugin. You can link a referral to a purchase and add transaction information to your Google Analytics data. It also supports Global Site Tag, Universal Analytics, eCommerce, and enhanced eCommerce event tracking.

Starting from WooCommerce 2.1, this integration is not packaged with WooCommerce and is only available by using this plugin.

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

= My settings are not saving! =

Do you have SUHOSIN installed/active on your server? If so, the default index length is 64 and some settings on this plugin requires longer lengths. Try setting your SUHOSIN configuration's "max_array_index_length" to "100" and test again.

= My national data privacy laws require that I offer an opt-out for users, how can I do this? =

Include the following html code snippet within the page where you want to have the opt-out, e.g. the your Imprint our Data Privacy page:

https://gist.github.com/claudiosanches/b12d15b245be21c92ebc

Exact wording depends on the national data privacy laws and should be adjusted.

== Screenshots ==

1. Google Analytics Integration Settings.

== Changelog ==

= 1.8.1 - 2023-05-09 =
* Fix - Fatal error when running with Elementor.
* Tweak - WC 7.7 compatibility.

= 1.8.0 - 2023-05-02 =
* Add - Create WordPress Hook Actions for Google Analytics.
* Add - Implement tracking with Actions Hooks.
* Dev - Implement JS Build (ES6) and JS Lint.
* Dev - Implement Javascript Building.

= 1.7.1 - 2023-04-12 =
* Fix - Bug with tracking enhanced ecommerce.

= 1.7.0 - 2023-03-28 =
* Dev - Load scripts via `wp_register_scripts` and `wp_eneuque_js`.
* Fix - Avoid duplication of Google Tag Manager script.
* Tweak - WC 7.6 compatibility.
* Tweak - WP 6.2 compatibility.

= 1.6.2 - 2023-03-07 =
* Tweak - WC 7.5 compatibility.
* Tweak - WP 6.2 compatibility.

= 1.6.1 - 2023-02-15 =
* Tweak - WC 7.4 compatibility.

= 1.6.0 - 2023-01-31 =
* Add - Common function for event code.
* Fix - Add PHP unit tests.
* Fix - Feature/consistency across gtag implementation.
* Fix - Fix inconsistencies across item data in events.
* Fix - Fix usage of tracker_var() in load_analytics().

= 1.5.19 - 2023-01-11 =
* Fix - undefined WC constant.
* Tweak - WC 7.3 compatibility.

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-google-analytics-integration/trunk/changelog.txt).
