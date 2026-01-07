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
	relatedProductAddToCart,
	simpleProductAddToCart,
} from '../../utils/customer';
import {
	createAllProductsBlockShopPage,
	createProductCollectionBlockShopPage,
	createProductsBlockShopPage,
	createRelatedProductsPage,
} from '../../utils/create-page';
import { getEventData, trackGtagEvent } from '../../utils/track-event';

const config = require( '../../config/default' );
const simpleProductPrice = parseFloat( config.products.simple.regular_price );

test.describe( 'GTag events on block pages', () => {
	let simpleProductID;

	test.beforeAll( async () => {
		await setSettings();
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

	test( 'View item list event is sent from the shop page', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'view_item_list' );

		// Go to shop page (newest first)
		await page.goto( 'shop?orderby=date' );

		await event.then( ( request ) => {
			const data = getEventData( request, 'view_item_list' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ln: 'Product List',
				ca: 'Uncategorized',
				pr: simpleProductPrice.toString(),
				lp: '1',
			} );
			expect( data[ 'ep.item_list_id' ] ).toEqual( 'engagement' );
			expect( data[ 'ep.item_list_name' ] ).toEqual( 'Viewing products' );
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
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ln: 'Product List',
				ca: 'Uncategorized',
				pr: simpleProductPrice.toString(),
				lp: '1',
			} );
			expect( data[ 'ep.item_list_id' ] ).toEqual( 'engagement' );
			expect( data[ 'ep.item_list_name' ] ).toEqual( 'Viewing products' );
		} );
	} );

	test( 'Add to cart event is sent from a products block shop page', async ( {
		page,
	} ) => {
		await createProductsBlockShopPage();

		const event = trackGtagEvent( page, 'add_to_cart' );

		await page.goto( 'products-block-shop' );
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

	test( 'Add to cart has correct quantity when product is already in cart', async ( {
		page,
	} ) => {
		const addToCart = `[data-product_id="${ simpleProductID }"]`;

		await createProductsBlockShopPage();
		await page.goto( `products-block-shop` );

		const addToCartButton = await page.locator( addToCart ).first();

		await addToCartButton.click();
		await expect( addToCartButton.getByText( '1 in cart' ) ).toBeVisible();
		await addToCartButton.click();
		await expect( addToCartButton.getByText( '2 in cart' ) ).toBeVisible();

		await page.reload();

		const event = trackGtagEvent( page, 'add_to_cart' );

		const addToCartButton2 = await page.locator( addToCart ).first();
		await addToCartButton2.click();
		await expect( addToCartButton.getByText( '3 in cart' ) ).toBeVisible();

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

	test( 'View item list event is sent from the products block shop page', async ( {
		page,
	} ) => {
		await createProductsBlockShopPage();

		const event = trackGtagEvent( page, 'view_item_list' );
		await page.goto( 'products-block-shop' );

		await event.then( ( request ) => {
			const data = getEventData( request, 'view_item_list' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ln: 'Product List',
				ca: 'Uncategorized',
				pr: simpleProductPrice.toString(),
				lp: '1',
			} );
			expect( data[ 'ep.item_list_id' ] ).toEqual( 'engagement' );
			expect( data[ 'ep.item_list_name' ] ).toEqual( 'Viewing products' );
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
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
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
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ln: 'woocommerce/all-products',
				pr: simpleProductPrice.toString(),
				lp: '1',
			} );
			expect( data[ 'ep.item_list_id' ] ).toEqual( 'engagement' );
			expect( data[ 'ep.item_list_name' ] ).toEqual( 'Viewing products' );
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
			expect( data.product1 ).toEqual( {
				id: relatedProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
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
