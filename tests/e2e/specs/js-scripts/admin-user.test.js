/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
import { setSettings, clearSettings } from '../../utils/api';

test.use( { storageState: process.env.ADMINSTATE } );

test.describe( 'JavaScript loaded', () => {
	test.beforeAll( async () => {
		await setSettings();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	test( 'No tracking for logged in admin user', async ( { page } ) => {
		await page.goto( 'shop' );

		await expect(
			page.locator(
				'#woocommerce-google-analytics-integration-js-before'
			)
		).not.toBeAttached();

		await expect(
			page.locator( '#woocommerce-google-analytics-integration-js' )
		).not.toBeAttached();

		await expect(
			page.locator(
				'#woocommerce-google-analytics-integration-data-js-after'
			)
		).not.toBeAttached();
	} );
} );
