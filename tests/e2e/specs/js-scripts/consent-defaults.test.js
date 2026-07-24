/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
import { setSettings, clearSettings } from '../../utils/api';

/**
 * Reads the consent default entries that reached the browser's dataLayer.
 *
 * gtag() pushes its arguments object onto the dataLayer, so entries are
 * array-like; entries pushed as plain objects (gtm events) convert to empty
 * arrays and fall out of the filter.
 *
 * @param {import('@playwright/test').Page} page
 *
 * @return {Object[]} The consent mode objects, in push order.
 */
async function getConsentDefaults( page ) {
	return await page.evaluate( () =>
		( window.dataLayer || [] )
			.map( ( entry ) => Array.from( entry ) )
			.filter(
				( entry ) =>
					entry[ 0 ] === 'consent' && entry[ 1 ] === 'default'
			)
			.map( ( entry ) => entry[ 2 ] )
	);
}

test.describe( 'Consent mode defaults', () => {
	test.beforeAll( async () => {
		await setSettings();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	// The test environment's snippet rewrites the default statuses (granting
	// everything unless `consent_default` says otherwise), so the region list
	// is the part that proves the plugin's EEA default reached the browser.
	test( 'Default consent for the EEA regions reaches the dataLayer', async ( {
		page,
	} ) => {
		await page.goto( 'shop?consent_default=denied' );

		const defaults = await getConsentDefaults( page );
		expect( defaults.length ).toBeGreaterThanOrEqual( 1 );

		const eeaDefault = defaults[ 0 ];
		expect( eeaDefault.analytics_storage ).toEqual( 'denied' );
		expect( eeaDefault.ad_storage ).toEqual( 'denied' );
		expect( eeaDefault.ad_user_data ).toEqual( 'denied' );
		expect( eeaDefault.ad_personalization ).toEqual( 'denied' );
		expect( eeaDefault.wait_for_update ).toEqual( 500 );
		expect( eeaDefault.region ).toHaveLength( 32 );
		expect( eeaDefault.region ).toEqual(
			expect.arrayContaining( [ 'DE', 'FR', 'GB', 'CH', 'NO', 'IS' ] )
		);
	} );

	test( 'A consent mode appended through the filter reaches the dataLayer', async ( {
		page,
	} ) => {
		await page.goto( 'shop?ga4w_e2e_extra_consent_region=ES' );

		const defaults = await getConsentDefaults( page );
		expect( defaults.length ).toBeGreaterThanOrEqual( 2 );

		const appended = defaults[ defaults.length - 1 ];
		expect( appended.region ).toEqual( [ 'ES' ] );
		expect( appended.analytics_storage ).toEqual( 'granted' );
		expect( appended.ad_storage ).toEqual( 'granted' );
	} );
} );
