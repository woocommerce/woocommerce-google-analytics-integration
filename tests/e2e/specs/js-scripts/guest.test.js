/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
import { setSettings, clearSettings } from '../../utils/api';

test.describe( 'JavaScript loaded', () => {
	test.beforeAll( async () => {
		await setSettings();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	test( 'Tracking loaded for guest customer', async ( { page } ) => {
		await page.goto( 'shop' );

		await expect(
			page.locator( '#woocommerce-google-analytics-integration-js' )
		).toBeAttached();

		await expect(
			page.locator(
				'#woocommerce-google-analytics-integration-data-js-after'
			)
		).toBeAttached();
	} );
} );
