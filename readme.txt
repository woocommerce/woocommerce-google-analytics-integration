=== WooCommerce Google Analytics Integration ===
Contributors: woocommerce, claudiosanches, bor0, royho, laurendavissmith001, c-shultz
Tags: woocommerce, google analytics
Requires at least: 3.9
Tested up to: 5.3
Stable tag: 1.4.17
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Provides integration between Google Analytics and WooCommerce.

== Description ==

This plugin provides the integration between Google Analytics and the WooCommerce plugin. You can link a referral to a purchase and add transaction information to your Google Analytics data. It also supports the new Universal Analytics, eCommerce, and enhanced eCommerce event tracking.

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

Also please make sure your Google Analytics ID under WooCommerce -> Settings -> Integrations.

= My code is there. Why is it still not tracking sales?  =

Duplicate Google Analytics code causes a conflict in tracking. Remove any other Google Analytics plugin or code from your site to avoid duplication and conflicts in tracking.

= Can I install it already? =

Starting the WooCommerce 2.1 release, the Google Analytics integration for WooCommerce is no longer part of the WooCommerce plugin.

Until you've updated to WooCommerce 2.1, this plugin puts itself in some sort of hibernate mode.

You can leave this plugin activated and it will seamlessly take over the integration that once was in the WooCommerce plugin, once you update to the next version.

= My settings are not saving! =

Do you have SUHOSIN installed/active on your server? If so, the default index length is 64 and some settings on this plugin requires longer lengths. Try setting your SUHOSIN configuration's "max_array_index_length" to "100" and test again.

= My national data privacy laws require that I offer an opt-out for users, how can I do this? =

Include the following html code snippet within the page where you want to have the opt-out, e.g. the your Imprint our Data Privacy page:

https://gist.github.com/claudiosanches/b12d15b245be21c92ebc

Exact wording depends on the national data privacy laws and should be adjusted.

== Screenshots ==

1. Google Analytics Integration Settings.

== Changelog ==

= 1.4.17 - 2020-01-13 =
* Tweak - Update constant VERSION in plugin file

= 1.4.16 - 2020-01-13 =
* Tweak - WC 3.9 compatibility.

= 1.4.15 - 2019-11-04 =
* Tweak - WC 3.8 compatibility.

= 1.4.14 - 2019-09-04 =
* Fix - Google Analytics JS URL missing quotes.

= 1.4.13 - 2019-09-03 =
* Tweak - Make Google Analytics JS script URL filterable.

= 1.4.12 - 2019-08-13 =
* Tweak - WC 3.7 compatibility.

= 1.4.11 - 2019-08-02 =
* Add - Filter to bypass "send pageview" for users whom want to use separate standard GA. `wc_goole_analytics_send_pageview`.
* Fix - Revert last release due to it causing ecommerce tracking to be disabled when standard tracking is disabled.

= 1.4.10 - 2019-07-10 =
* Fix - Ensure universal analytics pageview doesn’t occur if standard tracking is disabled.

= 1.4.9 - 2019-04-16 =
* Tweak - WC 3.6 compatibility.

= 1.4.8 - 2019-03-04 =
* Fix - Event for deleting from cart not sent after a cart update.

= 1.4.7 - 11/19/2018 =
* Tweak - WP 5.0 compatibility.

= 1.4.6 - 06/11/2018 =
* Fix - Check for active WooCommerce plugin.

= 1.4.5 - 16/10/2018 =
* Tweak - Mention Google Analytics Pro in certain cases.
* Tweak - WC 3.5 compatibility.

== Upgrade Notice ==
= 1.4.0 =
Adds support for enhanced eCommerce (tracking full store process from view to order)
