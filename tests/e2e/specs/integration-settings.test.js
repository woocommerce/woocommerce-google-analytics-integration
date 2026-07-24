/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
import { clearSettings } from '../utils/api';

test.use( { storageState: process.env.ADMINSTATE } );

// The save test below writes real settings through the form; remove them so
// later specs start from their own setSettings() baseline.
test.afterAll( async () => {
	await clearSettings();
} );

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
// select against mislabeled options). The save round-trip is covered by the
// test below.
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

// Complements the render test above by exercising the full save round-trip.
test( 'Settings can be saved and persist across a reload', async ( {
	page,
} ) => {
	await page.goto( SETTINGS_URL );

	// A unique, clearly fake id: a value used elsewhere in the suite could
	// false-pass the persistence assertion if earlier state leaked through.
	await page
		.locator( '#woocommerce_google_analytics_ga_id' )
		.fill( 'G-E2ETEST01' );
	// The non-default option, so a save that silently falls back to the
	// field default would fail the assertion below.
	await page
		.locator( '#woocommerce_google_analytics_ga_product_identifier' )
		.selectOption( 'product_id' );
	await page
		.locator( '#woocommerce_google_analytics_ga_linker_cross_domains' )
		.fill( 'example.com' );

	const checkedBoxes = [
		'ga_support_display_advertising',
		'ga_linker_allow_incoming_enabled',
		'ga_ecommerce_tracking_enabled',
		'ga_event_tracking_enabled',
		'ga_enhanced_remove_from_cart_enabled',
		'ga_enhanced_product_impression_enabled',
		'ga_enhanced_product_click_enabled',
		'ga_enhanced_product_detail_view_enabled',
		'ga_enhanced_checkout_process_enabled',
	];
	for ( const checkbox of checkedBoxes ) {
		await page
			.locator( `#woocommerce_google_analytics_${ checkbox }` )
			.check();
	}
	// Unchecked persistence is the error-prone direction for checkboxes (a
	// key missing from the POST must be stored as "no"), so one default-on
	// toggle is saved unchecked.
	await page
		.locator( '#woocommerce_google_analytics_ga_404_tracking_enabled' )
		.uncheck();

	await page.getByRole( 'button', { name: 'Save changes' } ).click();
	await expect(
		page.getByText( 'Your settings have been saved.' )
	).toBeVisible();

	// A fresh GET forces the fields to re-render from the database; the save
	// request itself re-renders from the same in-memory instance and would
	// mask a persistence failure.
	await page.goto( SETTINGS_URL );

	await expect(
		page.locator( '#woocommerce_google_analytics_ga_id' )
	).toHaveValue( 'G-E2ETEST01' );
	await expect(
		page.locator( '#woocommerce_google_analytics_ga_product_identifier' )
	).toHaveValue( 'product_id' );
	await expect(
		page.locator( '#woocommerce_google_analytics_ga_linker_cross_domains' )
	).toHaveValue( 'example.com' );
	for ( const checkbox of checkedBoxes ) {
		await expect(
			page.locator( `#woocommerce_google_analytics_${ checkbox }` )
		).toBeChecked();
	}
	await expect(
		page.locator( '#woocommerce_google_analytics_ga_404_tracking_enabled' )
	).not.toBeChecked();
} );
