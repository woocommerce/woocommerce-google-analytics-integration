<?php
/**
 * Plugin name: Google Analytics for WooCommerce Test Snippets
 * Description: A plugin to provide some PHP snippets used in E2E tests.
 *
 * Intended to function as a plugin while tests are running.
 * It hopefully goes without saying, this should not be used in a production environment.
 */

namespace Automattic\WooCommerce\GoogleListingsAndAds\Snippets;

/*
 * Customize/disable the gtag consent mode, to make testing easier by granting everything by default.
 * It's a hack to avoid specifying region for E2E environment, but it tests the customization of consent mode.
 */
add_filter(
	'woocommerce_ga_gtag_consent_modes',
	function ( $modes ) {
		$modes[0]['analytics_storage']  = 'granted';
		$modes[0]['ad_storage']         = 'granted';
		$modes[0]['ad_user_data']       = 'granted';
		$modes[0]['ad_personalization'] = 'granted';
		return $modes;
	}
);

/*
 * Mimic the behavior of GLA, or other plugin adding some inline events before `wp_enqueue_scripts`.
 */
add_action(
	'woocommerce_after_single_product',
	function () {
		wp_add_inline_script(
			'woocommerce-google-analytics-integration',
			'document.currentScript.__test__inlineSnippet = "works";',
		);
	}
);
