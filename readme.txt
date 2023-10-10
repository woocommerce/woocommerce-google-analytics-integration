=== WooCommerce Google Analytics Integration ===
Contributors: woocommerce, automattic, claudiosanches, bor0, royho, laurendavissmith001, c-shultz
Tags: woocommerce, google analytics
Requires at least: 3.9
Tested up to: 6.3
Stable tag: 1.8.7
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

= 1.8.7 - 2023-10-10 =
* Fix - JS syntax error on pages with cart and mini-cart rendered, which was causing purchases and cart removals not to be tracked.

= 1.8.6 - 2023-10-03 =
* Add - Privacy policy guide section.
* Dev - Enable since tag replacement.
* Fix - Track select_content instead of add_to_cart for variations.
* Tweak - Add documentation link with UTM parameters.
* Tweak - Tracking for Products ( Add To Cart and Impression) when using Products (Beta) Block.
* Tweak - WC 8.2 compatibility.

= 1.8.5 - 2023-09-14 =
* Dev - Add Workflow for generation Hooks documentation.
* Dev - Fetch WooCommerce and WordPress versions for our tests.
* Fix - Add To Cart and Impression events when using Blocks.
* Fix - Compat - Add PHP 8.2 support.
* Tweak - WC 8.1.0 compatibility.

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-google-analytics-integration/trunk/changelog.txt).
