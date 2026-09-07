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
import { checkout, simpleProductAddToCart } from '../../utils/customer';
import { getEventData, trackGtagEvent } from '../../utils/track-event';

/**
 * Reads the options object the snippet passed to gtag( 'config', … ).
 *
 * gtag() pushes its arguments object onto the dataLayer, so entries are
 * array-like; the config entry is [ 'config', '<measurement id>', { … } ].
 *
 * @param {import('@playwright/test').Page} page
 *
 * @return {Object} The config options, e.g. `{ track_404: true, page_location: '…' }`.
 */
async function getGtagConfig( page ) {
	return await page.evaluate(
		() =>
			( window.dataLayer || [] )
				.map( ( entry ) => Array.from( entry ) )
				.find( ( entry ) => entry[ 0 ] === 'config' )?.[ 2 ] ?? {}
	);
}

test.describe( 'URL redaction in the page location and referrer', () => {
	let simpleProductID;

	test.beforeAll( async () => {
		await setSettings();
		simpleProductID = await createSimpleProduct();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	test( 'Sensitive query parameters are stripped from the page location on any page', async ( {
		page,
	} ) => {
		const pageView = trackGtagEvent( page, 'page_view', 'shop' );
		await page.goto(
			'shop/?utm_source=e2e&key=abc123&order=wc_order_abc&login=bob&foo=bar'
		);
		const data = getEventData( await pageView, 'page_view' );
		const sent = new URL( data.dl );

		expect( sent.pathname ).toEqual( '/shop/' );
		expect( sent.searchParams.get( 'utm_source' ) ).toEqual( 'e2e' );
		expect( sent.searchParams.get( 'foo' ) ).toEqual( 'bar' );
		expect( sent.searchParams.has( 'key' ) ).toBe( false );
		expect( sent.searchParams.has( 'login' ) ).toBe( false );
		// `order` is not on the name list: it goes because its value is an order key.
		expect( sent.searchParams.has( 'order' ) ).toBe( false );
		expect( data.dl ).not.toContain( 'wc_order_' );

		// The browser URL keeps the parameters; only what gtag reports changes.
		expect( page.url() ).toContain( 'order=wc_order_abc' );
		const config = await getGtagConfig( page );
		expect( config.page_location ).toContain(
			'/shop/?utm_source=e2e&foo=bar'
		);
	} );

	test( 'Only allowlisted parameters survive on the order-received page', async ( {
		page,
	} ) => {
		await simpleProductAddToCart( page, simpleProductID );

		// Both the config hit and the server-side purchase event are sent from
		// the order-received page; neither may carry the order key.
		const pageView = trackGtagEvent(
			page,
			'page_view',
			'checkout/order-received'
		);
		const purchase = trackGtagEvent( page, 'purchase', 'checkout' );
		const orderID = await checkout( page );

		expect( page.url() ).toContain(
			`/checkout/order-received/${ orderID }/?key=wc_order_`
		);

		for ( const [ request, eventName ] of [
			[ await pageView, 'page_view' ],
			[ await purchase, 'purchase' ],
		] ) {
			const sent = new URL( getEventData( request, eventName ).dl );
			expect( sent.pathname ).toEqual(
				`/checkout/order-received/${ orderID }/`
			);
			expect( sent.searchParams.has( 'key' ) ).toBe( false );
			expect( sent.href ).not.toContain( 'wc_order_' );
		}

		const config = await getGtagConfig( page );
		expect( config.page_location ).toContain(
			`/checkout/order-received/${ orderID }/`
		);
		expect( config.page_location ).not.toContain( 'key=' );

		// Reloading the confirmation with extra parameters shows the allowlist
		// at work: campaign parameters stay, unknown ones and the key go.
		const reload = trackGtagEvent(
			page,
			'page_view',
			'checkout/order-received'
		);
		await page.goto(
			`${ page.url() }&utm_source=e2e&_gl=1abc&pay_for_order=true&foo=bar`
		);
		const reloaded = new URL(
			getEventData( await reload, 'page_view' ).dl
		);

		expect( reloaded.searchParams.get( 'utm_source' ) ).toEqual( 'e2e' );
		expect( reloaded.searchParams.get( '_gl' ) ).toEqual( '1abc' );
		expect( reloaded.searchParams.get( 'pay_for_order' ) ).toEqual(
			'true'
		);
		expect( reloaded.searchParams.has( 'foo' ) ).toBe( false );
		expect( reloaded.searchParams.has( 'key' ) ).toBe( false );
	} );

	test( 'Order key is stripped from the referrer reported on the next page', async ( {
		page,
	} ) => {
		await simpleProductAddToCart( page, simpleProductID );
		await checkout( page );
		expect( page.url() ).toContain( 'key=wc_order_' );

		const pageView = trackGtagEvent( page, 'page_view', 'shop' );
		// A script-initiated navigation carries the order-received URL as the
		// referrer; page.goto() would not set one.
		await page.evaluate( () => {
			window.location.assign( '/shop/' );
		} );
		await page.waitForURL( '**/shop/**' );
		const data = getEventData( await pageView, 'page_view' );

		expect( data.dr ).toContain( '/checkout/order-received/' );
		expect( data.dr ).not.toContain( 'key=' );
		expect( data.dr ).not.toContain( 'wc_order_' );

		const config = await getGtagConfig( page );
		expect( config.page_referrer ).toContain( '/checkout/order-received/' );
		expect( config.page_referrer ).not.toContain( 'key=' );
		// The shop URL itself had nothing to redact.
		expect( config ).not.toHaveProperty( 'page_location' );
	} );

	test( 'A page location without sensitive parameters is sent untouched', async ( {
		page,
	} ) => {
		const pageView = trackGtagEvent( page, 'page_view', 'shop' );
		await page.goto( 'shop/?color=red%20blue&utm_source=e2e' );
		const data = getEventData( await pageView, 'page_view' );

		// Query string byte-identical to the browser URL: `color` is on no
		// list, and the %20 was not re-encoded as `+`.
		expect( new URL( data.dl ).search ).toEqual(
			new URL( page.url() ).search
		);
		expect( data.dl ).toContain( '/shop/?color=red%20blue&utm_source=e2e' );

		const config = await getGtagConfig( page );
		expect( config ).not.toHaveProperty( 'page_location' );
		expect( config ).not.toHaveProperty( 'page_referrer' );
	} );

	test( 'A page location set by the merchant is left alone', async ( {
		page,
	} ) => {
		const pageView = trackGtagEvent( page, 'page_view' );
		await page.goto(
			'shop/?ga4w_e2e_page_location=http://example.test/custom/&key=wc_order_abc'
		);
		const data = getEventData( await pageView, 'page_view' );

		expect( data.dl ).toEqual( 'http://example.test/custom/' );

		const config = await getGtagConfig( page );
		expect( config.page_location ).toEqual( 'http://example.test/custom/' );
	} );
} );
