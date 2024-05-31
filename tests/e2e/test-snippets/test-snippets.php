<?php
/**
 * Plugin name: Google Analytics for WooCommerce Test Snippets
 * Description: A plugin to provide some PHP snippets used in E2E tests.
 *
 * Intended to function as a plugin while tests are running.
 * It hopefully goes without saying, this should not be used in a production environment.
 */

namespace Automattic\WooCommerce\GoogleListingsAndAds\Snippets;

use WC_Google_Analytics_Integration;
use WC_Google_Gtag_JS;

/*
 * Customize/disable the gtag consent mode, to make testing easier by granting everything by default.
 * It's a hack to avoid specifying region for E2E environment, but it tests the customization of consent mode.
 */
add_filter(
	'woocommerce_ga_gtag_consent_modes',
	function ( $modes ) {
		$status = 'granted';
		// Optional: Set the default consent state for tests via the `consent_default` URL parameter.
		if ( isset( $_GET['consent_default'] ) ) {
			$status = sanitize_text_field( wp_unslash( $_GET['consent_default'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$modes[0]['analytics_storage']  = $status;
		$modes[0]['ad_storage']         = $status;
		$modes[0]['ad_user_data']       = $status;
		$modes[0]['ad_personalization'] = $status;

		return $modes;
	}
);

/**
 * Snippet to allow the main.js file to be moved either to the page head or to
 * late in the footer after the extension inline data has been added to the page.
 *
 * This allows basic E2E tests to confirm tracking works regardless of when the
 * script is loaded. This is important because some third-party plugins will
 * change the load order in unexpected ways which has previously caused problems.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		 // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['move_mainjs_to'] ) ) {
			// main.js is a dependency of the inline data script so we need to make sure it doesn't load
			add_filter(
				'script_loader_src',
				function ( $src, $handle ) {
					if ( $handle === WC_Google_Gtag_JS::get_instance()->script_handle ) {
						$src = '';
					}
					return $src;
				},
				10,
				2
			);

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			switch ( $_GET['move_mainjs_to'] ) {
				case 'head':
					wp_enqueue_script(
						WC_Google_Gtag_JS::get_instance()->script_handle . '-head',
						WC_Google_Analytics_Integration::get_instance()->get_js_asset_url( 'main.js' ),
						array(
							...WC_Google_Analytics_Integration::get_instance()->get_js_asset_dependencies( 'main' ),
							'google-tag-manager',
						),
						WC_Google_Analytics_Integration::get_instance()->get_js_asset_version( 'main' ),
						false
					);
					break;
				case 'after_inline_data':
					add_action(
						'wp_footer',
						function () {
							printf(
								'<script src="%1$s" id="woocommerce-google-analytics-integration-js"></script>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
								WC_Google_Analytics_Integration::get_instance()->get_js_asset_url( 'main.js' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							);
						},
						9999
					);
					break;
			}
		}
	}
);

/*
 * Mimic the behavior of Google Listings & Ads or other plugins,
 * adding some inline events before `wp_enqueue_scripts.`
 */
add_action(
	'wp',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['add_inline_to_wp_hook'] ) ) {
			wp_add_inline_script(
				'woocommerce-google-analytics-integration',
				'document.currentScript.__test__inlineSnippet = "works";',
			);
		}
	}
);

/**
 * Snippet to bypass the WooCommerce dependency in Google Listings & Ads because
 * in wp-env WooCommerce is installed in the directory woocommerce-trunk-nightly
 */
add_action(
	'wp_plugin_dependencies_slug',
	function ( $slug ) {
		if ( 'woocommerce' === $slug ) {
			$slug = '';
		}

		return $slug;
	}
);
