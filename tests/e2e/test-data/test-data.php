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
	// TODO: set some test settings.
}

/**
 * Clear the previously set settings.
 */
function clear_settings() {
	// TODO: clear settings.
}

/**
 * Check permissions for API requests.
 */
function permissions() {
	return current_user_can( 'manage_woocommerce' );
}
