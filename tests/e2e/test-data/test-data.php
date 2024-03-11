<?php
/**
 * Plugin name: Google Analytics for WooCommerce Test Data
 * Description: Utility intended to set test data on the site through a REST API request.
 *
 * Intended to function as a plugin while tests are running.
 * It hopefully goes without saying, this should not be used in a production environment.
 */

namespace Automattic\WooCommerce\GoogleAnalytics\TestData;

add_action( 'rest_api_init', __NAMESPACE__ . '\register_routes' );

/**
 * Register routes for setting test data.
 */
function register_routes() {
	register_rest_route(
		'wc/v3',
		'ga4w-test/settings',
		[
			[
				'methods'             => 'POST',
				'callback'            => __NAMESPACE__ . '\set_settings',
				'permission_callback' => __NAMESPACE__ . '\permissions',
			],
			[
				'methods'             => 'DELETE',
				'callback'            => __NAMESPACE__ . '\clear_settings',
				'permission_callback' => __NAMESPACE__ . '\permissions',
			],
		],
	);
}

/**
 * Set the settings to enable tracking.
 */
function set_settings() {
	update_option(
		'woocommerce_google_analytics_settings',
		[
			'ga_product_identifier'                   => 'product_id',
			'ga_id'                                   => 'G-ABCD123',
			'ga_support_display_advertising'          => 'no',
			'ga_404_tracking_enabled'                 => 'yes',
			'ga_linker_allow_incoming_enabled'        => 'no',
			'ga_ecommerce_tracking_enabled'           => 'yes',
			'ga_event_tracking_enabled'               => 'yes',
			'ga_enhanced_ecommerce_tracking_enabled'  => 'yes',
			'ga_enhanced_remove_from_cart_enabled'    => 'yes',
			'ga_enhanced_product_impression_enabled'  => 'yes',
			'ga_enhanced_product_click_enabled'       => 'yes',
			'ga_enhanced_product_detail_view_enabled' => 'yes',
			'ga_enhanced_checkout_process_enabled'    => 'yes',
			'ga_linker_cross_domains'                 => '',
		]
	);
}

/**
 * Clear the previously set settings.
 */
function clear_settings() {
	delete_option( 'woocommerce_google_analytics_settings' );
}

/**
 * Check permissions for API requests.
 */
function permissions() {
	return current_user_can( 'manage_woocommerce' );
}
