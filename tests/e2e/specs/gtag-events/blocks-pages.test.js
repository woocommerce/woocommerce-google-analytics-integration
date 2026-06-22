/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
import {
	createSimpleProduct,
	createVariableProduct,
	setSettings,
	clearSettings,
} from '../../utils/api';
import {
	blockProductAddToCart,
	checkout,
	relatedProductAddToCart,
	simpleProductAddToCart,
	variableProductAddToCart,
	storeApiAddToCart,
	storeApiBatchAddToCart,
	storeApiBatchDecreaseCartQuantity,
	storeApiBatchIncreaseCartQuantity,
	waitForStoreApiInterceptor,
} from '../../utils/customer';
import {
	createAllProductsBlockShopPage,
	createProductCollectionBlockShopPage,
	createRelatedProductsPage,
} from '../../utils/create-page';
import { getEventData, trackGtagEvent } from '../../utils/track-event';

const config = require( '../../config/default' );
const simpleProductPrice = parseFloat( config.products.simple.regular_price );

test.describe( 'GTag events on block pages', () => {
	let simpleProductID, variableProductID;

	test.beforeAll( async () => {
		await setSettings();
		variableProductID = await createVariableProduct();
		simpleProductID = await createSimpleProduct();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	// WooCommerce shop page is built with blocks.
	test( 'Add to cart event is sent from the shop page', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'add_to_cart' );

		// Go to shop page (newest first)
		await page.goto( 'shop?orderby=date' );
		await blockProductAddToCart( page, simpleProductID );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	test( 'Add to cart event is sent after a Store API add item request', async ( {
		page,
	} ) => {
		await page.goto( 'shop?orderby=date' );

		const event = trackGtagEvent( page, 'add_to_cart' );
		await storeApiAddToCart( page, simpleProductID );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			// The interceptor reports the item from the Store API add-item
			// response, which carries id/name/quantity/price. Categories are
			// not part of that cart item payload, so they are not asserted.
			expect( data.product1 ).toMatchObject( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	test( 'Add to cart event is sent after a Store API batch add item request', async ( {
		page,
	} ) => {
		await page.goto( 'shop?orderby=date' );

		const event = trackGtagEvent( page, 'add_to_cart' );
		await storeApiBatchAddToCart( page, simpleProductID );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			// The interceptor reports the item from the batched Store API
			// add-item response, which carries id/name/quantity/price.
			// Categories are not part of that cart item payload, so they are
			// not asserted.
			expect( data.product1 ).toMatchObject( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	test( 'Add to cart event is sent when a Store API batch update-item increases the quantity', async ( {
		page,
	} ) => {
		// Use a dedicated product so a polluted cart can't break the seeding
		// click below (which expects the product to go from 0 to "1 in cart").
		const updateProductID = await createSimpleProduct();
		await page.goto( 'shop?orderby=date' );

		// Seed through the block so the cart store the interceptor reads
		// (wc/store/cart) holds the current quantity — the same source the real
		// Interactivity API add-to-cart flow keeps in sync. Wait for the seed's
		// own add_to_cart to flush so the assertion below tracks the update, not
		// the seed (and so the de-dup window has passed before the update).
		const seedEvent = trackGtagEvent( page, 'add_to_cart' );
		await blockProductAddToCart( page, updateProductID );
		await seedEvent;

		const event = trackGtagEvent( page, 'add_to_cart' );
		// Raise the quantity by 2 via update-item; the event must report the
		// added delta (2), not the new total quantity.
		await storeApiBatchIncreaseCartQuantity( page, updateProductID, 2 );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toMatchObject( {
				id: updateProductID.toString(),
				nm: 'Simple product',
				qt: '2',
				// Price reflects the two added units (the scaled line total).
				pr: ( simpleProductPrice * 2 ).toString(),
			} );
		} );
	} );

	test( 'Remove from cart event is sent when a Store API batch update-item decreases the quantity', async ( {
		page,
	} ) => {
		const updateProductID = await createSimpleProduct();
		await page.goto( 'shop?orderby=date' );

		const seedEvent = trackGtagEvent( page, 'add_to_cart' );
		await storeApiAddToCart( page, updateProductID, 3 );
		await seedEvent;

		const event = trackGtagEvent( page, 'remove_from_cart' );
		await storeApiBatchDecreaseCartQuantity( page, updateProductID, 2 );

		await event.then( ( request ) => {
			const data = getEventData( request, 'remove_from_cart' );
			expect( data.product1 ).toMatchObject( {
				id: updateProductID.toString(),
				nm: 'Simple product',
				qt: '2',
				pr: ( simpleProductPrice * 2 ).toString(),
			} );
		} );
	} );

	test( 'Remove from cart event is sent for an update-item decrease when the live cart data is stale', async ( {
		page,
	} ) => {
		// Resilience check: when the Blocks cart store and the static cart are
		// both unavailable (stubbed empty below), the decrease delta must still
		// be resolved from the remembered cart items so remove_from_cart reports
		// the units removed. A quantity of 0 would be rejected by the Store API
		// (its minimum is 1), so the line is decreased rather than emptied.
		const updateProductID = await createSimpleProduct();
		await page.goto( 'shop?orderby=date' );

		const seedEvent = trackGtagEvent( page, 'add_to_cart' );
		await storeApiAddToCart( page, updateProductID, 3 );
		await seedEvent;
		await waitForStoreApiInterceptor( page );

		const event = trackGtagEvent( page, 'remove_from_cart' );
		await page.evaluate( async ( productID ) => {
			const cartResponse = await window.fetch(
				'/wp-json/wc/store/v1/cart'
			);
			const nonce =
				cartResponse.headers.get( 'Nonce' ) ||
				cartResponse.headers.get( 'X-WC-Store-API-Nonce' );
			const cart = await cartResponse.json();
			const item = cart.items.find(
				( cartItem ) => parseInt( cartItem.id, 10 ) === productID
			);

			if ( ! item ) {
				throw new Error( `Product ${ productID } is not in the cart` );
			}

			const originalSelect = window.wp?.data?.select;
			const originalCart = window.ga4w?.data?.cart;

			if ( window.wp?.data && originalSelect ) {
				window.wp.data.select = ( store ) => {
					if ( store === 'wc/store/cart' ) {
						return { getCartData: () => ( { items: [] } ) };
					}

					return originalSelect( store );
				};
			}

			if ( window.ga4w?.data ) {
				window.ga4w.data.cart = null;
			}

			try {
				const response = await window.fetch(
					'/wp-json/wc/store/v1/batch',
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							...( nonce ? { Nonce: nonce } : {} ),
						},
						body: JSON.stringify( {
							requests: [
								{
									method: 'POST',
									path: '/wc/store/v1/cart/update-item',
									headers: nonce ? { Nonce: nonce } : {},
									body: {
										key: item.key,
										quantity: 1,
									},
								},
							],
						} ),
					}
				);

				if ( ! response.ok ) {
					throw new Error( await response.text() );
				}
			} finally {
				if ( window.wp?.data && originalSelect ) {
					window.wp.data.select = originalSelect;
				}

				if ( window.ga4w?.data ) {
					window.ga4w.data.cart = originalCart;
				}
			}
		}, updateProductID );

		await event.then( ( request ) => {
			const data = getEventData( request, 'remove_from_cart' );
			expect( data.product1 ).toMatchObject( {
				id: updateProductID.toString(),
				nm: 'Simple product',
				qt: '2',
				pr: ( simpleProductPrice * 2 ).toString(),
			} );
		} );
	} );

	test( 'Add to cart event is sent when increasing quantity on the cart page', async ( {
		page,
	} ) => {
		await simpleProductAddToCart( page, simpleProductID );
		await page.goto( 'cart' );

		const event = trackGtagEvent( page, 'add_to_cart' );
		await page
			.locator( '.wc-block-components-quantity-selector__button--plus' )
			.first()
			.click();

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toMatchObject( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	test( 'Add to cart event fires exactly once when both cart-add-item hook and Store API add fire for the same product', async ( {
		page,
	} ) => {
		await page.goto( 'shop?orderby=date' );
		await waitForStoreApiInterceptor( page );

		// Count add_to_cart events. GA4 sends a single event in the query string
		// (`en=add_to_cart`) but batches multiple events into the POST body
		// (newline-separated), so we inspect both to count reliably.
		const addToCartEvents = [];
		page.on( 'request', ( request ) => {
			const url = request.url();
			if ( ! url.includes( 'google-analytics.com/g/collect' ) ) {
				return;
			}

			if ( new URL( url ).searchParams.get( 'en' ) === 'add_to_cart' ) {
				addToCartEvents.push( url );
				return;
			}

			( request.postData() || '' ).split( /\r?\n/ ).forEach( ( line ) => {
				if (
					new URLSearchParams( line ).get( 'en' ) === 'add_to_cart'
				) {
					addToCartEvents.push( line );
				}
			} );
		} );

		// Resolves once the first add_to_cart event has actually been sent to
		// GA4, which also tells us gtag has flushed (it can load after the page).
		const firstAddToCart = trackGtagEvent( page, 'add_to_cart' );

		// Drive both code paths for the same product: the cart-add-item hook
		// (All Products Block path) and a real Store API add (Interactivity API
		// path). The nonce is fetched up front so the hook and the add-item
		// request fall within the de-dup window.
		await page.evaluate( async ( productID ) => {
			const cartResponse = await window.fetch(
				'/wp-json/wc/store/v1/cart'
			);
			const nonce =
				cartResponse.headers.get( 'Nonce' ) ||
				cartResponse.headers.get( 'X-WC-Store-API-Nonce' );

			window.wp.hooks.doAction(
				'experimental__woocommerce_blocks-cart-add-item',
				{
					product: {
						id: productID,
						name: 'Simple product',
						categories: [ { name: 'Uncategorized' } ],
						quantity: 1,
						prices: { price: 0, currency_minor_unit: 2 },
					},
				}
			);

			const response = await window.fetch(
				'/wp-json/wc/store/v1/cart/add-item',
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						...( nonce ? { Nonce: nonce } : {} ),
					},
					body: JSON.stringify( { id: productID, quantity: 1 } ),
				}
			);

			if ( ! response.ok ) {
				throw new Error( await response.text() );
			}
		}, simpleProductID );

		// Wait for the first add_to_cart event to be sent, then allow time for a
		// (de-duplicated) second one from the fetch interceptor's setTimeout(0).
		await firstAddToCart;
		await page.waitForTimeout( 1000 );

		expect( addToCartEvents.length ).toBe( 1 );
	} );

	test( 'Store API add item request succeeds when tracking cart data is corrupted', async ( {
		page,
	} ) => {
		const safetyProductID = await createSimpleProduct();
		await page.goto( 'shop?orderby=date' );
		await waitForStoreApiInterceptor( page );

		const pageErrors = [];
		page.on( 'pageerror', ( error ) => pageErrors.push( error.message ) );

		const responseOk = await page.evaluate( async ( productID ) => {
			const cartResponse = await window.fetch(
				'/wp-json/wc/store/v1/cart'
			);
			const nonce =
				cartResponse.headers.get( 'Nonce' ) ||
				cartResponse.headers.get( 'X-WC-Store-API-Nonce' );
			const originalSelect = window.wp?.data?.select;

			if ( window.wp?.data ) {
				window.wp.data.select = () => {
					throw new Error( 'Corrupted cart store' );
				};
			}

			const originalGa4wDataDescriptor = Object.getOwnPropertyDescriptor(
				window.ga4w,
				'data'
			);
			Object.defineProperty( window.ga4w, 'data', {
				configurable: true,
				get() {
					throw new Error( 'Corrupted tracking data' );
				},
			} );

			let addResponseOk;
			let addedItemKey;
			try {
				const response = await window.fetch(
					'/wp-json/wc/store/v1/cart/add-item',
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							...( nonce ? { Nonce: nonce } : {} ),
						},
						body: JSON.stringify( {
							id: productID,
							quantity: 1,
						} ),
					}
				);

				addResponseOk = response.ok;
				if ( response.ok ) {
					const cart = await response.clone().json();
					addedItemKey = cart.items.find(
						( item ) => parseInt( item.id, 10 ) === productID
					)?.key;
				}
			} finally {
				if ( window.wp?.data && originalSelect ) {
					window.wp.data.select = originalSelect;
				}

				Object.defineProperty(
					window.ga4w,
					'data',
					originalGa4wDataDescriptor
				);
			}

			if ( addedItemKey ) {
				await window.fetch( '/wp-json/wc/store/v1/cart/remove-item', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						...( nonce ? { Nonce: nonce } : {} ),
					},
					body: JSON.stringify( { key: addedItemKey } ),
				} );
			}

			return addResponseOk;
		}, safetyProductID );

		await page.waitForTimeout( 100 );

		expect( responseOk ).toBe( true );
		expect( pageErrors ).toEqual( [] );
	} );

	test( 'Blocks add and checkout hooks tolerate malformed tracking payloads', async ( {
		page,
	} ) => {
		await page.goto( 'shop?orderby=date' );

		const pageErrors = [];
		page.on( 'pageerror', ( error ) => pageErrors.push( error.message ) );

		const hooksReturned = await page.evaluate( () => {
			const product = {};
			Object.defineProperties( product, {
				id: {
					get() {
						throw new Error( 'Corrupted product ID' );
					},
				},
				name: {
					get() {
						throw new Error( 'Corrupted product name' );
					},
				},
			} );

			const checkoutData = {};
			Object.defineProperty( checkoutData, 'storeCart', {
				get() {
					throw new Error( 'Corrupted checkout cart' );
				},
			} );

			window.wp.hooks.doAction(
				'experimental__woocommerce_blocks-cart-add-item',
				{ product }
			);
			window.wp.hooks.doAction(
				'experimental__woocommerce_blocks-checkout-render-checkout-form',
				checkoutData
			);
			window.wp.hooks.doAction(
				'experimental__woocommerce_blocks-checkout-set-selected-shipping-rate',
				checkoutData
			);
			window.wp.hooks.doAction(
				'experimental__woocommerce_blocks-checkout-set-active-payment-method',
				checkoutData
			);
			window.wp.hooks.doAction(
				'experimental__woocommerce_blocks-checkout-submit',
				checkoutData
			);

			return true;
		} );

		await page.waitForTimeout( 100 );

		expect( hooksReturned ).toBe( true );
		expect( pageErrors ).toEqual( [] );
	} );

	test( 'View item list event is sent from the shop page', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'view_item_list' );

		// Go to shop page (newest first)
		await page.goto( 'shop?orderby=date' );

		await event.then( ( request ) => {
			const data = getEventData( request, 'view_item_list' );
			expect( data.product1 ).toMatchObject( {
				nm: 'Simple product',
				ln: 'Shop',
				ca: 'Uncategorized',
				pr: simpleProductPrice.toString(),
				lp: '1',
			} );
			expect( data.product1.id ).toBeTruthy();
			expect( data[ 'ep.item_list_id' ] ).toEqual( 'shop' );
			expect( data[ 'ep.item_list_name' ] ).toEqual( 'Shop' );
		} );
	} );

	test( 'Remove from cart event is sent from the cart page', async ( {
		page,
	} ) => {
		await simpleProductAddToCart( page, simpleProductID );

		const event = trackGtagEvent( page, 'remove_from_cart' );
		await page.goto( 'cart' );

		await page
			.locator( '.wc-block-cart-item__remove-link' )
			.first()
			.click();

		await event.then( ( request ) => {
			const data = getEventData( request, 'remove_from_cart' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				qt: '1',
				pr: simpleProductPrice.toString(),
				va: '',
			} );
		} );
	} );

	test( 'Remove from cart event for a variable product is sent from the cart page', async ( {
		page,
	} ) => {
		await variableProductAddToCart( page, variableProductID );

		const event = trackGtagEvent( page, 'remove_from_cart' );
		await page.goto( 'cart' );

		await page
			.locator( '.wc-block-cart-item__remove-link' )
			.first()
			.click();

		await event.then( ( request ) => {
			const data = getEventData( request, 'remove_from_cart' );
			expect( data.product1 ).toMatchObject( {
				id: variableProductID.toString(),
				nm: 'Variable product',
				qt: '1',
				pr: '18.99',
				va: 'colour: Green, size: Medium',
			} );
		} );
	} );

	test( 'Remove from cart event is sent from the mini cart', async ( {
		page,
	} ) => {
		await simpleProductAddToCart( page, simpleProductID );

		const event = trackGtagEvent( page, 'remove_from_cart' );
		await page.goto( 'shop' );

		await page.locator( '.wc-block-mini-cart' ).click();
		await page
			.locator( '.wc-block-cart-item__remove-link' )
			.first()
			.click();

		await event.then( ( request ) => {
			const data = getEventData( request, 'remove_from_cart' );
			// Check common required fields
			expect( data.product1 ).toMatchObject( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
			// Accept either category (WooCommerce 10.4+ fallback) or variant (older WooCommerce hook)
			expect(
				data.product1.ca === 'Uncategorized' || data.product1.va === ''
			).toBe( true );
		} );
	} );

	test( 'Remove from cart DOM fallback parses price correctly', async ( {
		page,
	} ) => {
		await simpleProductAddToCart( page, simpleProductID );
		await page.goto( 'shop' );
		await page.locator( '.wc-block-mini-cart' ).click();

		// Wait for mini cart to be visible
		await page
			.locator( '.wc-block-cart-item__remove-link' )
			.first()
			.waitFor();

		// Force DOM fallback by clearing cart data sources
		// Currency settings from ga4w.settings.currency are still available
		await page.evaluate( () => {
			window.ga4w.data.cart = null;
			// Mock wp.data.select to return empty cart
			if ( window.wp?.data?.select ) {
				const originalSelect = window.wp.data.select;
				window.wp.data.select = ( store ) => {
					if ( store === 'wc/store/cart' ) {
						return { getCartData: () => ( { items: [] } ) };
					}
					return originalSelect( store );
				};
			}
		} );

		const event = trackGtagEvent( page, 'remove_from_cart' );
		await page
			.locator( '.wc-block-cart-item__remove-link' )
			.first()
			.click();

		await event.then( ( request ) => {
			const data = getEventData( request, 'remove_from_cart' );
			// DOM fallback should parse price correctly using ga4w.settings.currency
			expect( data.product1.nm ).toEqual( 'Simple product' );
			expect( data.product1.qt ).toEqual( '1' );
			expect( parseFloat( data.product1.pr ) ).toEqual(
				simpleProductPrice
			);
		} );
	} );

	test( 'Begin checkout event is sent from a checkout page', async ( {
		page,
	} ) => {
		await simpleProductAddToCart( page, simpleProductID );

		const event = trackGtagEvent( page, 'begin_checkout' );
		await page.goto( 'checkout' );

		await event.then( ( request ) => {
			const data = getEventData( request, 'begin_checkout' );
			// Check common required fields
			expect( data.product1 ).toMatchObject( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
			// Accept either category (WooCommerce 10.4+ fallback) or variant (older WooCommerce hook)
			expect(
				data.product1.ca === 'Uncategorized' || data.product1.va === ''
			).toBe( true );
			expect( data.cu ).toEqual( 'USD' );
			expect( data[ 'epn.value' ] ).toEqual(
				simpleProductPrice.toString()
			);
		} );
	} );

	test( 'Begin checkout event for a variable product includes variation data', async ( {
		page,
	} ) => {
		await variableProductAddToCart( page, variableProductID );

		const event = trackGtagEvent( page, 'begin_checkout' );
		await page.goto( 'checkout' );

		await event.then( ( request ) => {
			const data = getEventData( request, 'begin_checkout' );
			// The category (ca) is intentionally not asserted here: whether the
			// block checkout's Store API cart data exposes a variation's category
			// is WooCommerce-version dependent (see the simple-product begin
			// checkout test above). Category is covered reliably for the classic
			// checkout in the purchase test. The variation (va) is the data this
			// test verifies for the block checkout.
			expect( data.product1 ).toMatchObject( {
				id: variableProductID.toString(),
				nm: 'Variable product',
				qt: '1',
				pr: '18.99',
				va: 'colour: Green, size: Medium',
			} );
			expect( data.cu ).toEqual( 'USD' );
			expect( data[ 'epn.value' ] ).toEqual( '18.99' );
		} );
	} );

	test( 'Add shipping info event is sent from a checkout page', async ( {
		page,
	} ) => {
		await simpleProductAddToCart( page, simpleProductID );
		await page.goto( 'checkout' );

		// Wait for the live cart to expose its shipping rates so we can dispatch
		// the actual rate id rather than a hard-coded one. The rate's machine id
		// (e.g. flat_rate:N) is not deterministic — WooCommerce's instance_id is
		// monotonic, so it climbs whenever the test env's shipping method is
		// re-provisioned against a persisted database.
		await page.waitForFunction( () => {
			const cart = window.wp?.data
				?.select?.( 'wc/store/cart' )
				?.getCartData?.();
			return ( cart?.shippingRates ?? [] ).some(
				( pkg ) => ( pkg.shipping_rates ?? [] ).length
			);
		} );

		const event = trackGtagEvent( page, 'add_shipping_info' );
		await page.evaluate( () => {
			const cart = window.wp.data.select( 'wc/store/cart' ).getCartData();
			const rates = ( cart.shippingRates ?? [] ).flatMap(
				( pkg ) => pkg.shipping_rates ?? []
			);
			const rate = rates.find( ( r ) => r.selected ) ?? rates[ 0 ];
			window.wp.hooks.doAction(
				'experimental__woocommerce_blocks-checkout-set-selected-shipping-rate',
				{
					shippingRateId: rate.rate_id,
					storeCart: window.ga4w.data.cart,
				}
			);
		} );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_shipping_info' );
			expect( data.product1 ).toMatchObject( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
			expect( data[ 'ep.shipping_tier' ] ).toEqual( 'Flat rate' );
			expect( data.cu ).toEqual( 'USD' );
			expect( data[ 'epn.value' ] ).toEqual(
				simpleProductPrice.toString()
			);
		} );
	} );

	test( 'Add payment info event is sent from a checkout page', async ( {
		page,
	} ) => {
		await simpleProductAddToCart( page, simpleProductID );
		await page.goto( 'checkout' );

		const event = trackGtagEvent( page, 'add_payment_info' );
		await page.evaluate( () => {
			window.wp.hooks.doAction(
				'experimental__woocommerce_blocks-checkout-set-active-payment-method',
				{
					paymentMethodSlug: 'cod',
					storeCart: window.ga4w.data.cart,
				}
			);
		} );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_payment_info' );
			expect( data.product1 ).toMatchObject( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
			// The slug 'cod' is resolved to the gateway's human-readable title,
			// matching the label the classic checkout reports.
			expect( data[ 'ep.payment_type' ] ).toEqual( 'Cash on delivery' );
			expect( data.cu ).toEqual( 'USD' );
			expect( data[ 'epn.value' ] ).toEqual(
				simpleProductPrice.toString()
			);
		} );
	} );

	// The purchase event itself is built server-side from the order, so its data
	// does not depend on which checkout a shopper used. What this test guards is
	// the block-specific surface around it: placing an order through the
	// `woocommerce/checkout` block and the block order-confirmation page still
	// firing `woocommerce_thankyou`, which is where the purchase event is
	// enqueued. Those pieces can break independently of the classic checkout when
	// WooCommerce updates, so the block path is verified on its own here.
	test( 'Purchase event is sent after completing the block checkout', async ( {
		page,
	} ) => {
		// Add the simple product twice and one variable product so quantity and
		// variation handling are both exercised in the order.
		await simpleProductAddToCart( page, simpleProductID );
		await simpleProductAddToCart( page, simpleProductID );
		await variableProductAddToCart( page, variableProductID );

		const event = trackGtagEvent( page, 'purchase', 'checkout' );
		const orderID = await checkout( page );

		await event.then( ( request ) => {
			const data = getEventData( request, 'purchase' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '2',
				pr: simpleProductPrice.toString(),
			} );
			expect( data.product2 ).toEqual( {
				id: variableProductID.toString(),
				nm: 'Variable product',
				ca: 'Uncategorized',
				qt: '1',
				pr: '18.99',
				va: 'colour: Green, size: Medium',
			} );

			expect( data[ 'ep.transaction_id' ] ).toEqual( orderID );
			expect( data[ 'ep.affiliation' ] ).toEqual(
				'WooCommerce E2E Test Suite'
			);

			const shipping = 10;
			const total =
				simpleProductPrice + simpleProductPrice + 18.99 + shipping;
			expect( data.cu ).toEqual( 'USD' );
			expect( data[ 'epn.value' ] ).toEqual(
				total.toFixed( 2 ).toString()
			);
			expect( data[ 'epn.tax' ] ).toEqual( '0' );
			expect( data[ 'epn.shipping' ] ).toEqual( shipping.toString() );
		} );
	} );

	test( 'Add to cart event is sent from a product collection block shop page', async ( {
		page,
	} ) => {
		await createProductCollectionBlockShopPage();

		const event = trackGtagEvent( page, 'add_to_cart' );

		await page.goto( 'product-collection-block-shop' );
		await blockProductAddToCart( page, simpleProductID );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	test( 'View item list event is sent from the product collection block shop page', async ( {
		page,
	} ) => {
		await createProductCollectionBlockShopPage();

		const event = trackGtagEvent( page, 'view_item_list' );
		await page.goto( 'product-collection-block-shop' );

		await event.then( ( request ) => {
			const data = getEventData( request, 'view_item_list' );
			expect( data.product1 ).toMatchObject( {
				nm: 'Simple product',
				ln: 'Product List',
				ca: 'Uncategorized',
				pr: simpleProductPrice.toString(),
				lp: '1',
			} );
			expect( data.product1.id ).toBeTruthy();
			expect( data[ 'ep.item_list_id' ] ).toEqual( 'product_list' );
			expect( data[ 'ep.item_list_name' ] ).toEqual( 'Product List' );
		} );
	} );

	test( 'Add to cart event is sent from the all products block shop page', async ( {
		page,
	} ) => {
		await createAllProductsBlockShopPage();

		const event = trackGtagEvent( page, 'add_to_cart' );

		await page.goto( 'all-products-block-shop' );

		// Buttons do not have a product ID, since they are sorted by latest fetch the first product.
		const addToCartButton = await page
			.locator( '.add_to_cart_button' )
			.first();
		await addToCartButton.click();
		await expect( addToCartButton.getByText( '1 in cart' ) ).toBeVisible();

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toMatchObject( {
				nm: 'Simple product',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
			expect( data.product1.id ).toBeTruthy();
		} );
	} );

	test( 'View item list event is sent from the all products block shop page', async ( {
		page,
	} ) => {
		await createAllProductsBlockShopPage();

		const event = trackGtagEvent( page, 'view_item_list' );
		await page.goto( 'all-products-block-shop' );

		await event.then( ( request ) => {
			const data = getEventData( request, 'view_item_list' );
			expect( data.product1 ).toMatchObject( {
				nm: 'Simple product',
				ln: 'woocommerce/all-products',
				pr: simpleProductPrice.toString(),
				lp: '1',
			} );
			expect( data.product1.id ).toBeTruthy();
			expect( data[ 'ep.item_list_id' ] ).toEqual(
				'woocommerce_all_products'
			);
			expect( data[ 'ep.item_list_name' ] ).toEqual(
				'woocommerce/all-products'
			);
		} );
	} );

	test( 'Select content event is sent from the all products block shop page', async ( {
		page,
	} ) => {
		await createAllProductsBlockShopPage();

		const listEvent = trackGtagEvent( page, 'view_item_list' );
		await page.goto( 'all-products-block-shop' );
		await listEvent;

		const event = trackGtagEvent( page, 'select_content' );
		const productLink = page
			.getByRole( 'link', { name: 'Simple product' } )
			.first();

		await Promise.all( [ event, productLink.click() ] );

		await event.then( ( request ) => {
			const data = getEventData( request, 'select_content' );
			expect( data[ 'ep.content_type' ] ).toEqual( 'product' );
			expect( data[ 'ep.content_id' ] ).toBeTruthy();
		} );
	} );

	// Related products are blocks even though they are on a regular single product page.
	test( 'Add to cart event is sent from related product on single product page', async ( {
		page,
	} ) => {
		await createSimpleProduct(); // Create an additional product for related to show up.
		await page.goto( `?p=${ simpleProductID }` );

		// Check if it has the related products section.
		const hasRelatedProducts = await page
			.getByRole( 'heading', {
				name: 'Related products',
			} )
			.isVisible();

		test.skip(
			! hasRelatedProducts,
			'This WC setup does not have "Related products" section on the single product page.'
		);

		const event = trackGtagEvent( page, 'add_to_cart' );
		const relatedProductID = await relatedProductAddToCart( page );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toMatchObject( {
				id: relatedProductID.toString(),
				ca: 'Uncategorized',
				qt: '1',
			} );
			expect( data.product1.nm ).toBeTruthy();
			expect( data.product1.pr ).toBeTruthy();
		} );
	} );

	test( 'Add to cart event is sent from related products block', async ( {
		page,
	} ) => {
		await createSimpleProduct(); // Create an additional product for related to show up.

		const pageSlug = await createRelatedProductsPage( simpleProductID );

		const event = trackGtagEvent( page, 'add_to_cart' );

		// Go to block page
		await page.goto( pageSlug );

		const relatedProductID = await relatedProductAddToCart( page );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toEqual( {
				id: relatedProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );
} );
