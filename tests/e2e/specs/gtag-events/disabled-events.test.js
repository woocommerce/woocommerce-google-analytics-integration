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
import {
	blockProductAddToCart,
	storeApiAddToCart,
	storeApiBatchDecreaseCartQuantity,
} from '../../utils/customer';
import { trackGtagEvent } from '../../utils/track-event';

/**
 * Collects the names of every gtag event sent to google-analytics.com/g/collect
 * after this is attached. Used to assert that a disabled event never fires:
 * unlike trackGtagEvent(), which waits for a request, asserting an absence needs
 * to observe the traffic and then check what was (not) seen.
 *
 * @param {import('@playwright/test').Page} page
 *
 * @return {string[]} A live array of event names, appended to as requests arrive.
 */
function collectGtagEventNames( page ) {
	const names = [];

	page.on( 'request', ( request ) => {
		const url = request.url();
		if ( ! url.includes( 'google-analytics.com/g/collect' ) ) {
			return;
		}

		// A single event is sent in the query string, multiple events in the body.
		const queryEvent = new URL( url ).searchParams.get( 'en' );
		if ( queryEvent ) {
			names.push( queryEvent );
		}

		for ( const line of ( request.postData() || '' ).split( /\r?\n/ ) ) {
			const bodyEvent = new URLSearchParams( line ).get( 'en' );
			if ( bodyEvent ) {
				names.push( bodyEvent );
			}
		}
	} );

	return names;
}

test.describe( 'Disabled event tracking', () => {
	let productID;

	test.beforeAll( async () => {
		productID = await createSimpleProduct();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	test( 'No ecommerce events fire when all event tracking is disabled', async ( {
		page,
	} ) => {
		await setSettings( {
			ga_ecommerce_tracking_enabled: 'no',
			ga_event_tracking_enabled: 'no',
			ga_enhanced_remove_from_cart_enabled: 'no',
			ga_enhanced_product_impression_enabled: 'no',
			ga_enhanced_product_click_enabled: 'no',
			ga_enhanced_product_detail_view_enabled: 'no',
			ga_enhanced_checkout_process_enabled: 'no',
		} );

		const eventNames = collectGtagEventNames( page );

		await page.goto( 'shop?orderby=date' );
		await blockProductAddToCart( page, productID );

		// The tracking scripts still load with everything disabled; only the list
		// of events the tracker will emit is empty. Asserting that proves the
		// disabled state propagated to the client rather than the scripts simply
		// failing to load (which would also produce no events).
		const enabledEvents = await page.evaluate(
			() => window.ga4w?.settings?.events
		);
		expect( enabledEvents ).toEqual( [] );

		// page_view is gtag's own config hit, not gated by the plugin toggles, so
		// it always fires. Awaiting one on a fresh navigation is a deterministic
		// barrier: any leaked ecommerce event from the interactions above would
		// have been collected before it.
		const pageView = trackGtagEvent( page, 'page_view' );
		await page.goto( 'shop' );
		await pageView;

		// Assert against every event the plugin can send rather than a
		// whitelist of the collected names, because gtag emits automatic
		// events of its own (page_view, session_start, user_engagement).
		const pluginEvents = [
			'purchase',
			'add_to_cart',
			'remove_from_cart',
			'view_item_list',
			'select_content',
			'view_item',
			'begin_checkout',
			'add_shipping_info',
			'add_payment_info',
		];
		expect(
			eventNames.filter( ( name ) => pluginEvents.includes( name ) )
		).toEqual( [] );
	} );

	test( 'Disabling add to cart leaves the other events working', async ( {
		page,
	} ) => {
		await setSettings( { ga_event_tracking_enabled: 'no' } );

		const eventNames = collectGtagEventNames( page );

		await page.goto( 'shop?orderby=date' );

		// Add two units (add_to_cart is disabled, so it must stay silent), then
		// remove one. The remove_from_cart event is still enabled and fires after
		// the add would have, so awaiting it is a deterministic barrier: once it
		// arrives, a non-suppressed add_to_cart would already have been collected.
		await storeApiAddToCart( page, productID, 2 );
		const removeEvent = trackGtagEvent( page, 'remove_from_cart' );
		await storeApiBatchDecreaseCartQuantity( page, productID, 1 );
		await removeEvent;

		const enabledEvents = await page.evaluate(
			() => window.ga4w?.settings?.events
		);
		expect( enabledEvents ).toContain( 'remove_from_cart' );
		expect( enabledEvents ).not.toContain( 'add_to_cart' );

		expect( eventNames ).toContain( 'remove_from_cart' );
		expect( eventNames ).not.toContain( 'add_to_cart' );
	} );
} );
