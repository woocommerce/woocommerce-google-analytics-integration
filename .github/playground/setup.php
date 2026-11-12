<?php
/**
 * Prepares the WordPress Playground preview of a pull request build.
 *
 * The Blueprints of the "Test the build" comment run this file once, after
 * WooCommerce and the build are active. See README.md in this folder.
 *
 * @package WC_Google_Analytics_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Skip the setup wizard and open the store to visitors, on the order storage a new store gets.
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_onboarding_profile', [ 'skipped' => true ] );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_custom_orders_table_enabled', 'yes' );
update_option( 'permalink_structure', '/%postname%/' );

// Tracking needs an ID. This one is too short to belong to a Google Analytics property.
$ga4w_preview_settings = get_option( 'woocommerce_google_analytics_settings' );
$ga4w_preview_settings = is_array( $ga4w_preview_settings ) ? $ga4w_preview_settings : [];

$ga4w_preview_settings['ga_id'] = 'G-PREVIEW';
update_option( 'woocommerce_google_analytics_settings', $ga4w_preview_settings );

// A visitor can check out: cash on delivery and one flat rate.
update_option( 'woocommerce_cod_settings', [ 'enabled' => 'yes' ] );

$ga4w_preview_rate = WC_Shipping_Zones::get_zone( 0 )->add_shipping_method( 'flat_rate' );
update_option(
	"woocommerce_flat_rate_{$ga4w_preview_rate}_settings",
	[
		'title'      => 'Flat rate',
		'tax_status' => 'taxable',
		'cost'       => '10',
	]
);

// Sample products.
$ga4w_preview_category = wp_insert_term( 'Clothing', 'product_cat' );
$ga4w_preview_products = [
	'Beanie'  => '18',
	'Hoodie'  => '45',
	'T-Shirt' => '20',
];

foreach ( $ga4w_preview_products as $ga4w_preview_name => $ga4w_preview_price ) {
	$ga4w_preview_product = new WC_Product_Simple();
	$ga4w_preview_product->set_name( $ga4w_preview_name );
	$ga4w_preview_product->set_sku( 'preview-' . sanitize_title( $ga4w_preview_name ) );
	$ga4w_preview_product->set_regular_price( $ga4w_preview_price );
	if ( is_array( $ga4w_preview_category ) ) {
		$ga4w_preview_product->set_category_ids( [ (int) $ga4w_preview_category['term_id'] ] );
	}
	$ga4w_preview_product->save();
}

// The panel that lists the events.
wp_mkdir_p( WPMU_PLUGIN_DIR );
copy( __DIR__ . '/event-log.php', WPMU_PLUGIN_DIR . '/ga4w-preview-event-log.php' );

flush_rewrite_rules();
