=== WooCommerce Google Analytics Integration ===
Contributors: woocommerce, automattic, claudiosanches, bor0, royho, laurendavissmith001, c-shultz
Tags: woocommerce, google analytics
Requires at least: 3.9
Tested up to: 6.4
Stable tag: 1.8.14
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

= 1.8.14 - 2024-02-07 =
* Tweak - Declare feature compatibility for the new product editor (also called product block editor).
* Tweak - WC 8.6 compatibility.

= 1.8.13 - 2024-01-09 =
* Dev - Upgrade all NPM dev dependencies.
* Dev - Upgrade to Node.js 18.
* Tweak - WC 8.5 compatibility.

= 1.8.12 - 2023-12-28 =
* Fix - Avoid JavaScript exceptions when sending checkout event tracking due to incompatible data structure.
* Fix - Correct misplaced each product index value as its quantity when sending checkout event tracking.

= 1.8.11 - 2023-12-18 =
* Dev - Prevent DB warnings in unit tests.
* Tweak - WC 8.4 compatibility.

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-google-analytics-integration/trunk/changelog.txt).
