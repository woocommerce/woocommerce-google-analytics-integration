/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
import { setSettings, clearSettings } from '../../utils/api';
import { getEventData, trackGtagEvent } from '../../utils/track-event';

test.describe( 'JavaScript file position', () => {
	test.beforeAll( async () => {
		await setSettings();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	test( 'Tracking is functional if main.js is loaded in the header', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'view_item_list' );

		await page.goto( 'shop?move_mainjs_to=head' );

		await expect(
			page.locator(
				'head #woocommerce-google-analytics-integration-head-js'
			)
		).toBeAttached();

		await expect(
			page.locator( '#woocommerce-google-analytics-integration-js' )
		).toHaveCount( 0 );

		await event.then( ( request ) => {
			const data = getEventData( request, 'view_item_list' );
			expect( data[ 'ep.item_list_name' ] ).toEqual( 'Viewing products' );
		} );
	} );

	test( 'Tracking is functional if main.js is loaded after the inline script data', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'view_item_list' );

		await page.goto( 'shop?move_mainjs_to=after_inline_data' );

		await expect(
			page.locator(
				'#woocommerce-google-analytics-integration-data-js-after + #woocommerce-google-analytics-integration-js'
			)
		).toBeAttached();

		await event.then( ( request ) => {
			const data = getEventData( request, 'view_item_list' );
			expect( data[ 'ep.item_list_name' ] ).toEqual( 'Viewing products' );
		} );
	} );
} );
