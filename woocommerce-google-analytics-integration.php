<?php

/*
Plugin Name: WooCommerce Google Analytics Integration
Plugin URI: http://wordpress.org/plugins/woocommerce-google-analytics-integration/
Description: Allows Google Analytics tracking code to be inserted into WooCommerce store pages.
Author: WooThemes
Author URI: http://www.woothemes.com
Version: 1.1.0
*/

// Add the integration to WooCommerce
function wc_google_analytics_add_integration( $integrations ) {
	global $woocommerce;

	if ( is_object( $woocommerce ) && version_compare( $woocommerce->version, '2.1-beta-1', '>=' ) ) {
		include_once( 'includes/class-wc-google-analytics-integration.php' );
		$integrations[] = 'WC_Google_Analytics';
	}

	return $integrations;
}

add_filter( 'woocommerce_integrations', 'wc_google_analytics_add_integration', 10 );
