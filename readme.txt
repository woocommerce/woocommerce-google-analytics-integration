=== Google Analytics for WooCommerce ===
Contributors: woocommerce, automattic, claudiosanches, bor0, royho, laurendavissmith001, cshultz88, mmjones, tomalec
Tags: woocommerce, google analytics
Requires at least: 6.2
Tested up to: 6.7
Stable tag: 2.1.13
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

= 2.1.13 - 2025-02-18 =
* Tweak - WC 9.7 compatibility.

= 2.1.12 - 2025-02-11 =
* Dev - Update npm dependencies to resolve all npm vulnerabilities

= 2.1.11 - 2025-01-21 =
* Tweak - Add required PHP header.
* Tweak - Replace WooCommerce logo to Woo 2.0 Brand.
* Tweak - Resolve plugin check errors and warnings.

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-google-analytics-integration/trunk/changelog.txt).
