=== WooCommerce Google Analytics Integration ===
Contributors: woothemes
Tags: woocommerce, google analytics
Requires at least: 3.8
Tested up to: 3.8
Stable tag: 1.2.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Provides integration between Google Analytics and WooCommerce.

== Description ==

This plugin provides the integration between Google Analytics and the WooCommerce plugin. You can link a referral to a purchase and add transaction information to your Google Analytics data. It also supports the new Universal Analytics, eCommerce and event tracking.

Starting WooCommerce 2.1, this integration will no longer be part of WooCommerce and will only be available by using this plugin.

Contributions are welcome via the [GitHub repository](https://github.com/woothemes/woocommerce-google-analytics-integration).

== Installation ==

1. Download the plugin file to your computer and unzip it
2. Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation’s wp-content/plugins/ directory.
3. Activate the plugin from the Plugins menu within the WordPress admin.
4. Don't forget to enable e-commerce tracking in your Google Analytics account: https://support.google.com/analytics/answer/1009612?hl=en

Or use the automatic installation wizard through your admin panel, just search for this plugins name.

== Frequently Asked Questions ==

= Where can I find the setting for this plugin? =

This plugin will add the settings to the Integration tab, to be found in the WooCommerce > Settings menu.

= I don't see the code on my site. Where is it? =

We purposefully don't track admin visits to the site. Log out of the site (or open a Google Chrome Incognito window) and check if the site is there for non-admins.

Also please make sure your Google Analytics ID under WooCommerce->Settings->Integrations.

= Can I install it already? =

Starting the WooCommerce 2.1 release, the Google Analytics integration for WooCommerce is no longer part of the WooCommerce plugin.

Until you've updated to WooCommerce 2.1, this plugin puts itself in some sort of hibernate mode.

You can leave this plugin activated and it will seamlessly take over the integration that once was in the WooCommerce plugin, once you update to the next version.

== Changelog ==

= 1.2.0 - 28/07/2014 =
 * Feature - Adding display advertising parameter to Universal Analytics
 * Fix     - Using get_total_shipping() instead of get_shipping
 * Fix     - Using wc_enqueue_js() instead of $woocommerce->add_inline_js(
 * Tweak   - Updating plugin FAQ
 * Tweak   - Adding parenthesis for clarity

= 1.1 - 29/05/2014 =
 * Added option to enable Display Advertising
 * Added compatibility support for WooCommerce 2.1 beta releases

= 1.0 - 22/11/2013 =
 * Initial release
