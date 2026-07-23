/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

test.use( { storageState: process.env.ADMINSTATE } );

const SETTINGS_URL =
	'/wp-admin/admin.php?page=wc-settings&tab=integration&section=google_analytics';

test( 'Able to setup in WooCommerce > Settings > Integration', async ( {
	page,
} ) => {
	await page.goto( SETTINGS_URL );

	await expect(
		page.getByRole( 'heading', { name: 'Google Analytics' } )
	).toBeVisible();
} );

// The settings form is defined by the plugin's init_form_fields(). Asserting the
// controls render guards against a control being dropped (and the identifier
// select against mislabeled options). The save mechanism itself is WooCommerce
// core and is not exercised here.
test( 'Settings form renders the plugin controls', async ( { page } ) => {
	await page.goto( SETTINGS_URL );

	const identifier = page.locator(
		'#woocommerce_google_analytics_ga_product_identifier'
	);
	await expect( identifier ).toBeVisible();
	await expect( identifier.locator( 'option' ) ).toHaveText( [
		'Product ID',
		'Product SKU with prefixed (#) ID as fallback',
	] );

	await expect(
		page.locator( '#woocommerce_google_analytics_ga_id' )
	).toBeVisible();

	// Every remaining control defined by init_form_fields() is present.
	const controls = [
		'ga_support_display_advertising',
		'ga_404_tracking_enabled',
		'ga_linker_allow_incoming_enabled',
		'ga_ecommerce_tracking_enabled',
		'ga_event_tracking_enabled',
		'ga_enhanced_remove_from_cart_enabled',
		'ga_enhanced_product_impression_enabled',
		'ga_enhanced_product_click_enabled',
		'ga_enhanced_product_detail_view_enabled',
		'ga_enhanced_checkout_process_enabled',
		'ga_linker_cross_domains',
	];
	for ( const control of controls ) {
		await expect(
			page.locator( `#woocommerce_google_analytics_${ control }` )
		).toBeVisible();
	}
} );
