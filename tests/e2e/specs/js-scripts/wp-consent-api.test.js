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
import { storeApiAddToCart } from '../../utils/customer';
import { getEventData, trackGtagEvent } from '../../utils/track-event';

test.describe( 'WP Consent API Integration', () => {
	test.beforeAll( async () => {
		await setSettings();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	test( 'window.wp_consent_type is set to `optin`', async ( { page } ) => {
		await page.goto( 'shop' );

		const consentType = await page.evaluate( () => window.wp_consent_type );
		await expect( consentType ).toEqual( 'optin' );
	} );

	test( 'Consent update granting `analytics_storage` is sent when WP Consent API `statistics` category is `allowed`', async ( {
		page,
	} ) => {
		await page.goto( 'shop?consent_default=denied' );
		await page.evaluate( () =>
			window.wp_set_consent( 'statistics', 'allow' )
		);

		const dataLayer = await page.evaluate( () => window.dataLayer );
		const consentState = dataLayer.filter( ( i ) => i[ 0 ] === 'consent' );

		await expect( consentState.length ).toEqual( 2 );

		await expect( consentState[ 0 ] ).toEqual( {
			0: 'consent',
			1: 'default',
			2: expect.objectContaining( { analytics_storage: 'denied' } ),
		} );

		await expect( consentState[ 1 ] ).toEqual( {
			0: 'consent',
			1: 'update',
			2: { analytics_storage: 'granted' },
		} );
	} );

	test( 'Consent update granting `ad_storage`, `ad_user_data`, `ad_personalization` is sent when WP Consent API `marketing` category is `allowed`', async ( {
		page,
	} ) => {
		await page.goto( 'shop?consent_default=denied' );
		await page.evaluate( () =>
			window.wp_set_consent( 'marketing', 'allow' )
		);

		const dataLayer = await page.evaluate( () => window.dataLayer );
		const consentState = dataLayer.filter( ( i ) => i[ 0 ] === 'consent' );

		await expect( consentState.length ).toEqual( 2 );

		await expect( consentState[ 0 ] ).toEqual( {
			0: 'consent',
			1: 'default',
			2: expect.objectContaining( {
				ad_storage: 'denied',
				ad_user_data: 'denied',
				ad_personalization: 'denied',
			} ),
		} );

		await expect( consentState[ 1 ] ).toEqual( {
			0: 'consent',
			1: 'update',
			2: {
				ad_storage: 'granted',
				ad_user_data: 'granted',
				ad_personalization: 'granted',
			},
		} );
	} );

	test( 'Consent update denying `analytics_storage` is sent when WP Consent API `statistics` category is `denied`', async ( {
		page,
	} ) => {
		await page.goto( 'shop' );
		await page.evaluate( () =>
			window.wp_set_consent( 'statistics', 'deny' )
		);

		const dataLayer = await page.evaluate( () => window.dataLayer );
		const consentState = dataLayer.filter( ( i ) => i[ 0 ] === 'consent' );

		await expect( consentState.length ).toEqual( 2 );

		await expect( consentState[ 0 ] ).toEqual( {
			0: 'consent',
			1: 'default',
			2: expect.objectContaining( { analytics_storage: 'granted' } ),
		} );

		await expect( consentState[ 1 ] ).toEqual( {
			0: 'consent',
			1: 'update',
			2: { analytics_storage: 'denied' },
		} );
	} );

	test( 'Consent update denying `ad_storage`, `ad_user_data`, `ad_personalization` is sent when WP Consent API `marketing` category is `denied`', async ( {
		page,
	} ) => {
		await page.goto( 'shop' );
		await page.evaluate( () =>
			window.wp_set_consent( 'marketing', 'deny' )
		);

		const dataLayer = await page.evaluate( () => window.dataLayer );
		const consentState = dataLayer.filter( ( i ) => i[ 0 ] === 'consent' );

		await expect( consentState.length ).toEqual( 2 );

		await expect( consentState[ 0 ] ).toEqual( {
			0: 'consent',
			1: 'default',
			2: expect.objectContaining( {
				ad_storage: 'granted',
				ad_user_data: 'granted',
				ad_personalization: 'granted',
			} ),
		} );

		await expect( consentState[ 1 ] ).toEqual( {
			0: 'consent',
			1: 'update',
			2: {
				ad_storage: 'denied',
				ad_user_data: 'denied',
				ad_personalization: 'denied',
			},
		} );
	} );

	test( 'Consent state is sent as update when page is loaded', async ( {
		page,
	} ) => {
		await page.goto( 'shop?consent_default=denied' );
		await page.evaluate( () =>
			window.wp_set_consent( 'marketing', 'allow' )
		);
		// Go to a new page to confirm that the consent state is maintained across page loads
		await page.goto( '/?consent_default=denied' );

		const dataLayer = await page.evaluate( () => window.dataLayer );
		const consentState = dataLayer.filter( ( i ) => i[ 0 ] === 'consent' );

		await expect( consentState.length ).toEqual( 2 );

		await expect( consentState[ 0 ] ).toEqual( {
			0: 'consent',
			1: 'default',
			2: expect.objectContaining( {
				ad_storage: 'denied',
				ad_user_data: 'denied',
				ad_personalization: 'denied',
				analytics_storage: 'denied',
			} ),
		} );

		await expect( consentState[ 1 ] ).toEqual( {
			0: 'consent',
			1: 'update',
			2: {
				ad_storage: 'granted',
				ad_user_data: 'granted',
				ad_personalization: 'granted',
			},
		} );
	} );

	test( 'Consent state is sent as update when page is loaded if the default is set to `granted`', async ( {
		page,
	} ) => {
		await page.goto( 'shop' );
		await page.evaluate( () =>
			window.wp_set_consent( 'statistics', 'deny' )
		);
		await page.goto( 'shop' );

		const dataLayer = await page.evaluate( () => window.dataLayer );
		const consentState = dataLayer.filter( ( i ) => i[ 0 ] === 'consent' );

		await expect( consentState.length ).toEqual( 2 );

		await expect( consentState[ 0 ] ).toEqual( {
			0: 'consent',
			1: 'default',
			2: expect.objectContaining( {
				ad_storage: 'granted',
				ad_user_data: 'granted',
				ad_personalization: 'granted',
				analytics_storage: 'granted',
			} ),
		} );

		await expect( consentState[ 1 ] ).toEqual( {
			0: 'consent',
			1: 'update',
			2: {
				analytics_storage: 'denied',
			},
		} );
	} );

	// The `gcs` parameter on each collect hit is the consent state Google's
	// tooling (e.g. Tag Assistant) decodes: "G1" followed by the ad_storage
	// and analytics_storage digits. Asserting it proves the events themselves
	// carry the updated consent, not only that the update was dispatched.
	// Every stage sets both categories explicitly because the plugin's
	// defaults are region-scoped to the EEA: outside that region no default
	// applies and the digits read "-".
	test( 'Event hits carry the consent state in the `gcs` parameter', async ( {
		page,
	} ) => {
		const firstProductID = await createSimpleProduct();
		const secondProductID = await createSimpleProduct();
		const thirdProductID = await createSimpleProduct();
		const fourthProductID = await createSimpleProduct();

		await page.goto( 'shop' );

		await page.evaluate( () => {
			window.wp_set_consent( 'statistics', 'allow' );
			window.wp_set_consent( 'marketing', 'deny' );
		} );
		const deniedAdsAddEvent = trackGtagEvent( page, 'add_to_cart' );
		await storeApiAddToCart( page, firstProductID );
		expect(
			getEventData( await deniedAdsAddEvent, 'add_to_cart' ).gcs
		).toEqual( 'G101' );

		// Granting marketing flips the ad_storage digit.
		await page.evaluate( () =>
			window.wp_set_consent( 'marketing', 'allow' )
		);
		const grantedAdsAddEvent = trackGtagEvent( page, 'add_to_cart' );
		await storeApiAddToCart( page, secondProductID );
		expect(
			getEventData( await grantedAdsAddEvent, 'add_to_cart' ).gcs
		).toEqual( 'G111' );

		// Denying it again flips the digit back on subsequent hits.
		await page.evaluate( () =>
			window.wp_set_consent( 'marketing', 'deny' )
		);
		const revokedAdsAddEvent = trackGtagEvent( page, 'add_to_cart' );
		await storeApiAddToCart( page, thirdProductID );
		expect(
			getEventData( await revokedAdsAddEvent, 'add_to_cart' ).gcs
		).toEqual( 'G101' );

		// Denying statistics flips the analytics_storage digit; the hit is
		// still sent as a consent-restricted ping.
		await page.evaluate( () =>
			window.wp_set_consent( 'statistics', 'deny' )
		);
		const revokedStatsAddEvent = trackGtagEvent( page, 'add_to_cart' );
		await storeApiAddToCart( page, fourthProductID );
		expect(
			getEventData( await revokedStatsAddEvent, 'add_to_cart' ).gcs
		).toEqual( 'G100' );
	} );
} );
