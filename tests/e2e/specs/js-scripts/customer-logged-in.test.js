/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
import { setSettings, clearSettings } from '../../utils/api';
import { getEventData, trackGtagEvent } from '../../utils/track-event';

test.use( { storageState: process.env.CUSTOMERSTATE } );

test.describe( 'JavaScript loaded', () => {
	test.beforeAll( async () => {
		await setSettings();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	test( 'Tracking loaded for logged in customer', async ( { page } ) => {
		await page.goto( 'shop' );

		await expect(
			page.locator(
				'#woocommerce-google-analytics-integration-js-before'
			)
		).toBeAttached();

		await expect(
			page.locator( '#woocommerce-google-analytics-integration-js' )
		).toBeAttached();

		await expect(
			page.locator(
				'#woocommerce-google-analytics-integration-data-js-after'
			)
		).toBeAttached();
	} );

	test( 'Page view event is sent for logged in customer', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'page_view' );

		await page.goto( 'shop' );

		await event.then( ( request ) => {
			const data = getEventData( request, 'page_view' );

			// Confirm we are tracking a logged in user.
			expect( data[ 'ep.logged_in' ] ).toEqual( 'true' );
		} );
	} );
} );
