=== WooCommerce Google Analytics Integration ===
Contributors: woocommerce, automattic, claudiosanches, bor0, royho, laurendavissmith001, c-shultz
Tags: woocommerce, google analytics
Requires at least: 3.9
Tested up to: 5.8
Stable tag: 1.5.5
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

= 1.5.5 - 2021-12-09 =
* Tweak - WC 6.0 compatibility.
* Tweak - WP 5.9 compatibility.

= 1.5.4 - 2021-11-10 =
* Fix - Remove the slow order counting query from admin init.
* Tweak - WC 5.9 compatibility.

= 1.5.3 - 2021-09-15 =
* Tweak - Avoid unnecessary completed orders queries.
* Tweak - WC 5.7 compatibility.
* Tweak - WP 5.8 compatibility.

= 1.5.2 - 2021-07-30 =
* Fix - Change utm_source and utm_medium in upsell notice link.
* Fix - add product links to readme.

= 1.5.1 - 2021-02-03 =
* Tweak - WC 5.0 compatibility.

= 1.5.0 - 2020-12-17 =
* Add - Option to use Global Site Tag and the gtag.js library (for Universal Analytics or Google Analytics 4).
* Add - Several new values added to the Tracker data.
* Add - Developer ID for gtag.js and analytics.js.
* Tweak - Bump minimum-supported WooCommerce version to 3.2.
* Tweak - Remove deprecated jQuery .click().
* Fix - Settings link in plugins table row points directly to plugin settings.
* Fix - Issue with multiple consecutive "Remove from Cart" events sent from the mini cart.

= 1.4.25 - 2020-11-25 =
* Tweak - WC 4.7 compatibility.
* Tweak - WordPress 5.6 compatibility.

= 1.4.24 - 2020-10-12 =
* Tweak - WC 4.5 compatibility.

= 1.4.23 - 2020-08-19 =
* Fix   - Prevent transaction from being tracked a second time when page is reloaded locally or from cache.
* Tweak - WordPress 5.5 compatibility.

= 1.4.22 - 2020-06-05 =
* Tweak - WC 4.2 compatibility.

= 1.4.21 - 2020-05-04 =
* Tweak - WC 4.1 compatibility.

= 1.4.20 - 2020-03-29 =
* Fix - Change wc_goole_analytics_send_pageview fiter name to wc_google_analytics_send_pageview.

== Upgrade Notice ==
= 1.4.0 =
Adds support for enhanced eCommerce (tracking full store process from view to order)
