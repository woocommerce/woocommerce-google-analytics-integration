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

const config = require( '../../config/default' );
const simpleProductPrice = parseFloat( config.products.simple.regular_price );

// The product identifier is a merchant setting: events can report a product by
// its WooCommerce id (the default, already exercised across the other specs) or
// by its SKU. The SKU value is resolved server-side into the event data and
// falls back to a "#id" form when a product has no SKU, so it can break when
// store settings or WooCommerce versions change. These tests pin that end-to-end
// behavior with the setting switched to SKU.
test.describe( 'Product identifier setting (SKU)', () => {
	let skuProductID, noSkuProductID;
	const sku = `E2E-IDENT-${ Date.now() }`;

	test.beforeAll( async () => {
		await setSettings( { ga_product_identifier: 'product_sku' } );
		skuProductID = await createSimpleProduct( { sku } );
		// The default product config has no SKU, so this exercises the fallback.
		noSkuProductID = await createSimpleProduct();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	test( 'View item reports the product SKU', async ( { page } ) => {
		const event = trackGtagEvent( page, 'view_item' );

		await page.goto( `?p=${ skuProductID }` );

		await event.then( ( request ) => {
			const data = getEventData( request, 'view_item' );
			expect( data.product1 ).toMatchObject( {
				id: sku,
				nm: 'Simple product',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	test( 'View item falls back to the prefixed id when the product has no SKU', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'view_item' );

		await page.goto( `?p=${ noSkuProductID }` );

		await event.then( ( request ) => {
			const data = getEventData( request, 'view_item' );
			expect( data.product1 ).toMatchObject( {
				id: `#${ noSkuProductID }`,
				nm: 'Simple product',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	// The block add-to-cart flow carries the identifier in the Store API cart
	// item's extensions, which is a separate resolution path from the
	// server-rendered single product page above. This guards the SKU on the
	// block storefront specifically.
	test( 'Store API add to cart reports the product SKU', async ( {
		page,
	} ) => {
		await page.goto( 'shop?orderby=date' );

		const event = trackGtagEvent( page, 'add_to_cart' );
		await storeApiAddToCart( page, skuProductID );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toMatchObject( {
				id: sku,
				nm: 'Simple product',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );
} );
