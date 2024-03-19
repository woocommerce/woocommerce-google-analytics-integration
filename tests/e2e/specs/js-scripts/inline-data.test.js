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

/*
 * This test requires a PHP snippet that adds inline script
 * on `'woocommerce_after_single_product'` to `'woocommerce-google-analytics-integration'`
 * that sets `document.currentScript.__test__inlineSnippet = "works";`
 */
test.describe( '`woocommerce-google-analytics-integration`', () => {
	test.beforeAll( async () => {
		await setSettings();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	test( 'Is registered early enough to attach some data to it on `woocommerce_after_single_product`', async ( {
		page,
	} ) => {
		const simpleProductID = await createSimpleProduct();
		await page.goto( `?p=${ simpleProductID }` );

		await expect(
			page.locator( '#woocommerce-google-analytics-integration-js-after' )
		).toBeAttached();

		await expect(
			page.locator( '#woocommerce-google-analytics-integration-js-after' )
		).toHaveJSProperty( '__test__inlineSnippet', 'works' );
	} );
} );
