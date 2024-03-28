/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
import {
	createSimpleProduct,
	setSettings,
	clearSettings,
} from '../../utils/api';

test.describe( '`woocommerce-google-analytics-integration`', () => {
	test.beforeAll( async () => {
		await setSettings();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	/*
	 * This test requires a PHP snippet that adds inline script
	 * on `'wp_head'` to `'woocommerce-google-analytics-integration'`
	 * that sets `document.currentScript.__test__inlineSnippet = "works";`
	 *
	 * Some themes may change the execution sequence of WP actions against the traditional theme like Storefront.
	 * Make sure the theme you're testing runs `wp_enqueue_scripts` after the hook used in the snippet - `wp_head`.
	 */
	test( 'Is registered early enough to attach some data to it on `woocommerce_after_single_product`', async ( {
		page,
	} ) => {
		const simpleProductID = await createSimpleProduct();
		await page.goto( `?p=${ simpleProductID }&add_inline_to_wp_head=1` );

		await expect(
			page.locator( '#woocommerce-google-analytics-integration-js-after' )
		).toBeAttached();

		await expect(
			page.locator( '#woocommerce-google-analytics-integration-js-after' )
		).toHaveJSProperty( '__test__inlineSnippet', 'works' );
	} );
} );
