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
	createClassicCartPage,
	createClassicCheckoutPage,
	createClassicShopPage,
} from '../../utils/create-page';
import {
	checkout,
	simpleProductAddToCart,
	variableProductAddToCart,
} from '../../utils/customer';
import { getEventData, trackGtagEvent } from '../../utils/track-event';

const config = require( '../../config/default' );
const simpleProductPrice = parseFloat( config.products.simple.regular_price );

test.describe( 'GTag events on classic pages', () => {
	let simpleProductID, variableProductID;

	test.beforeAll( async () => {
		await setSettings();
		variableProductID = await createVariableProduct();
		simpleProductID = await createSimpleProduct();
	} );

	test.afterAll( async () => {
		await clearSettings();
	} );

	test( 'Page view event is sent on a frontend page for a guest user', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'page_view' );

		await page.goto( 'shop' );

		await event.then( ( request ) => {
			const data = getEventData( request, 'page_view' );

			// Confirm we are tracking a guest user.
			expect( data[ 'ep.logged_in' ] ).toEqual( 'false' );
		} );
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
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	test( 'Add to cart quantity is sent on a single product page', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'add_to_cart' );

		await page.goto( `?p=${ simpleProductID }` );

		await page.locator( '.quantity input.qty' ).first().fill( '3' );

		const addToCart = `.single_add_to_cart_button[value="${ simpleProductID }"]`;
		const addToCartButton = await page.locator( addToCart ).first();

		await addToCartButton.click();

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '3',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	test( 'Add to cart event is sent on the home page when adding product through URL', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'add_to_cart' );

		// Load home page without products and add product to cart by ID.
		await page.goto( `/?add-to-cart=${ simpleProductID }` );

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

	test( 'Add to cart event is sent on a single product page', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'add_to_cart' );

		await simpleProductAddToCart( page, simpleProductID );
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

	test( 'Add to cart event is sent on a variable product page', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'add_to_cart' );

		await variableProductAddToCart( page, variableProductID );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toEqual( {
				id: variableProductID.toString(),
				nm: 'Variable product',
				ca: 'Uncategorized',
				qt: '1',
				pr: '18.99',
				va: 'colour: Green, size: Medium',
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
				pr: simpleProductPrice.toString(),
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
			const products = [ data.product1, data.product2 ];
			const simple = products.find(
				( p ) => p.id === simpleProductID.toString()
			);
			const variable = products.find(
				( p ) => p.id === variableProductID.toString()
			);
			expect( simple ).toMatchObject( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ln: 'Product List',
				ca: 'Uncategorized',
				pr: simpleProductPrice.toString(),
			} );
			expect( variable ).toMatchObject( {
				id: variableProductID.toString(),
				nm: 'Variable product',
				ln: 'Product List',
				ca: 'Uncategorized',
				pr: '17.99', // Lowest price for variable products.
			} );
			expect( data[ 'ep.item_list_id' ] ).toEqual( 'product_list' );
			expect( data[ 'ep.item_list_name' ] ).toEqual( 'Product List' );
		} );
	} );

	test( 'Remove from cart event is sent from a classic cart page', async ( {
		page,
	} ) => {
		await createClassicCartPage();
		await simpleProductAddToCart( page, simpleProductID );

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
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	test( 'Remove from cart event for a variable product', async ( {
		page,
	} ) => {
		await createClassicCartPage();
		await variableProductAddToCart( page, variableProductID );

		const event = trackGtagEvent( page, 'remove_from_cart' );
		await page.goto( 'classic-cart' );

		await page.locator( '.cart_item .remove' ).first().click();

		await event.then( ( request ) => {
			const data = getEventData( request, 'remove_from_cart' );
			expect( data.product1 ).toEqual( {
				id: variableProductID.toString(),
				nm: 'Variable product',
				ca: 'Uncategorized',
				qt: '1',
				pr: '18.99',
				va: 'colour: Green, size: Medium',
			} );
		} );
	} );

	test( 'Begin checkout event is sent from a classic checkout page', async ( {
		page,
	} ) => {
		await createClassicCheckoutPage();
		await simpleProductAddToCart( page, simpleProductID );
		await variableProductAddToCart( page, variableProductID );

		const event = trackGtagEvent( page, 'begin_checkout' );
		await page.goto( 'classic-checkout' );

		await event.then( ( request ) => {
			const data = getEventData( request, 'begin_checkout' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '1',
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
			expect( data.cu ).toEqual( 'USD' );
			expect( data[ 'epn.value' ] ).toEqual(
				( simpleProductPrice + 18.99 ).toFixed( 2 ).toString()
			);
		} );
	} );

	test( 'Add payment info event is sent when changing payment method on classic checkout', async ( {
		page,
	} ) => {
		await createClassicCheckoutPage();
		await simpleProductAddToCart( page, simpleProductID );

		const event = trackGtagEvent( page, 'add_payment_info' );
		await page.goto( 'classic-checkout' );

		// Simulate a user changing the payment method radio. We don't depend
		// on the exact set of enabled gateways — just trigger a `change` on the
		// first available payment_method input.
		await page.evaluate( () => {
			const input = document.querySelector(
				'input[name="payment_method"]'
			);
			if ( input ) {
				input.checked = true;
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		} );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_payment_info' );
			expect( data.cu ).toEqual( 'USD' );
			expect( data[ 'epn.value' ] ).toEqual(
				simpleProductPrice.toString()
			);
			expect( data[ 'ep.payment_type' ] ).toBeTruthy();
		} );
	} );

	test( 'Purchase event is sent on order complete page', async ( {
		page,
	} ) => {
		// Add simple product twice, and one variable product.
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

			expect( data[ 'epn.transaction_id' ] ).toEqual( orderID );
			expect( data[ 'ep.affiliation' ] ).toEqual(
				'WooCommerce E2E Test Suite'
			);

			const total = simpleProductPrice + simpleProductPrice + 18.99;
			expect( data.cu ).toEqual( 'USD' );
			expect( data[ 'epn.value' ] ).toEqual(
				total.toFixed( 2 ).toString()
			);
		} );
	} );
} );
