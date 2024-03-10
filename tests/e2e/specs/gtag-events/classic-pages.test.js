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
	createClassicCartPage,
	createClassicCheckoutPage,
	createClassicShopPage,
} from '../../utils/create-page';
import { checkout, singleProductAddToCart } from '../../utils/customer';
import { getEventData, trackGtagEvent } from '../../utils/track-event';

const config = require( '../../config/default' );
const productPrice = config.products.simple.regularPrice;

let simpleProductID;

test.describe( 'GTag events on classic pages', () => {
	test.beforeAll( async () => {
		await setSettings();
		simpleProductID = await createSimpleProduct();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	test( 'GTag scripts are loaded on a frontend page', async ( { page } ) => {
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

	test( 'Page view event is sent on a frontend page', async ( { page } ) => {
		const event = trackGtagEvent( page, 'page_view' );

		await page.goto( 'shop' );
		await expect( event ).resolves.toBeTruthy();
	} );

	test( 'View item event is sent on a single product page', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'view_item' );

		await page.goto( `?p=${ simpleProductID }` );

		await event.then( ( request ) => {
			const data = getEventData( request, 'view_item' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ln: 'Product List',
				ca: 'Uncategorized',
				pr: productPrice.toString(),
			} );
		} );
	} );

	test( 'Add to cart event is sent on a single product page', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'add_to_cart' );

		await singleProductAddToCart( page, simpleProductID );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '1',
				pr: productPrice.toString(),
			} );
		} );
	} );

	test( 'Add to cart event is sent from a classic shop page', async ( {
		page,
	} ) => {
		await createClassicShopPage();

		const event = trackGtagEvent( page, 'add_to_cart' );

		// Go to shop page (newest first)
		await page.goto( 'classic-shop?orderby=date' );
		const addToCart = `[data-product_id="${ simpleProductID }"]`;
		const addToCartButton = await page.locator( addToCart ).first();
		await addToCartButton.click();
		await expect( addToCartButton ).toHaveClass( /added/ );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '1',
				pr: productPrice.toString(),
			} );
		} );
	} );

	test( 'View item list event is sent from a classic shop page', async ( {
		page,
	} ) => {
		await createClassicShopPage();

		const event = trackGtagEvent( page, 'view_item_list' );

		// Go to shop page (newest first)
		await page.goto( 'classic-shop?orderby=date' );

		await event.then( ( request ) => {
			const data = getEventData( request, 'view_item_list' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ln: 'Product List',
				ca: 'Uncategorized',
				pr: productPrice.toString(),
				lp: '1',
			} );
			expect( data[ 'ep.item_list_id' ] ).toEqual( 'engagement' );
			expect( data[ 'ep.item_list_name' ] ).toEqual( 'Viewing products' );
		} );
	} );

	test( 'Remove from cart event is sent from a classic cart page', async ( {
		page,
	} ) => {
		await createClassicCartPage();
		await singleProductAddToCart( page, simpleProductID );

		const event = trackGtagEvent( page, 'remove_from_cart' );
		await page.goto( 'classic-cart' );

		await page.locator( '.cart_item .remove' ).first().click();

		await event.then( ( request ) => {
			const data = getEventData( request, 'remove_from_cart' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '1',
				pr: productPrice.toString(),
			} );
		} );
	} );

	test( 'Begin checkout event is sent from a classic checkout page', async ( {
		page,
	} ) => {
		await createClassicCheckoutPage();
		await singleProductAddToCart( page, simpleProductID );

		const event = trackGtagEvent( page, 'begin_checkout' );
		await page.goto( 'classic-checkout' );

		await event.then( ( request ) => {
			const data = getEventData( request, 'begin_checkout' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '1',
				pr: productPrice.toString(),
			} );
			expect( data.cu ).toEqual( 'USD' );
			expect( data[ 'epn.value' ] ).toEqual( productPrice.toString() );
		} );
	} );

	test( 'Purchase event is sent on order complete page', async ( {
		page,
	} ) => {
		await singleProductAddToCart( page, simpleProductID );

		const event = trackGtagEvent( page, 'purchase', 'checkout' );
		await checkout( page );

		await event.then( ( request ) => {
			const data = getEventData( request, 'purchase' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '1',
				pr: productPrice.toString(),
			} );
			expect( data.cu ).toEqual( 'USD' );
			expect( data[ 'epn.value' ] ).toEqual( productPrice.toString() );
		} );
	} );
} );
