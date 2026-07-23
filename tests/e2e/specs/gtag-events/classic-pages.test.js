/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
import {
	api,
	createCaliforniaTaxRate,
	createGroupedProduct,
	createPercentageCoupon,
	createSimpleProduct,
	createVariableProduct,
	deleteTaxRate,
	setSettings,
	clearSettings,
	setOptions,
} from '../../utils/api';
import {
	createClassicCartPage,
	createClassicCheckoutPage,
	createClassicEmptyCartPageWithProducts,
	createClassicShopPage,
} from '../../utils/create-page';
import {
	blockProductAddToCart,
	checkout,
	classicCheckout,
	simpleProductAddToCart,
	variableProductAddToCart,
} from '../../utils/customer';
import {
	getAllEventData,
	getEventData,
	trackGtagEvent,
} from '../../utils/track-event';

const config = require( '../../config/default' );
const simpleProductPrice = parseFloat( config.products.simple.regular_price );

test.describe( 'GTag events on classic pages', () => {
	let simpleProductID, variableProductID;

	test.beforeAll( async () => {
		await setSettings();
		variableProductID = await createVariableProduct();
		simpleProductID = await createSimpleProduct();
		await createClassicEmptyCartPageWithProducts();
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

	test( 'Add to cart event falls back to single product data when the event has no button', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'add_to_cart' );

		await page.goto( `?p=${ simpleProductID }` );
		await page.evaluate( () => {
			window
				.jQuery( document.body )
				.trigger( 'added_to_cart', [ {}, 'test-cart-hash' ] );
		} );

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

	test( 'Add to cart event uses the final cart item price', async ( {
		page,
	} ) => {
		const finalPrice = '4.99';
		const event = trackGtagEvent( page, 'add_to_cart' );

		await page.goto(
			`/?add-to-cart=${ simpleProductID }&ga4w_e2e_cart_item_price=${ finalPrice }`
		);

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '1',
				pr: finalPrice,
			} );
		} );
	} );

	test( 'Add to cart event is sent after redirecting to the cart page', async ( {
		page,
	} ) => {
		await setOptions( { woocommerce_cart_redirect_after_add: 'yes' } );

		try {
			const event = trackGtagEvent( page, 'add_to_cart' );

			await page.goto( `?p=${ simpleProductID }` );
			await page.locator( '.single_add_to_cart_button' ).first().click();

			await expect( page ).toHaveURL( /\/cart\/?/ );

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
		} finally {
			await setOptions( { woocommerce_cart_redirect_after_add: 'no' } );
		}
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

	test( 'Select content event is sent from a classic shop page before navigation', async ( {
		page,
	} ) => {
		await createClassicShopPage();

		const listEvent = trackGtagEvent( page, 'view_item_list' );
		await page.goto( 'classic-shop?orderby=date' );
		await listEvent;

		const event = trackGtagEvent( page, 'select_content' );
		const productLink = page
			.locator(
				`li.post-${ simpleProductID } .woocommerce-loop-product__link`
			)
			.first();

		await Promise.all( [ event, productLink.click() ] );

		await event.then( ( request ) => {
			const data = getEventData( request, 'select_content' );
			expect( data[ 'ep.content_type' ] ).toEqual( 'product' );
			expect( data[ 'ep.content_id' ] ).toEqual(
				simpleProductID.toString()
			);
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

	test( 'Add to cart event is sent when increasing quantity on a classic cart page', async ( {
		page,
	} ) => {
		await createClassicCartPage();
		await simpleProductAddToCart( page, simpleProductID );
		await page.goto( 'classic-cart' );

		await page.locator( '.quantity input.qty' ).first().fill( '3' );

		const event = trackGtagEvent( page, 'add_to_cart' );
		await page.locator( 'button[name="update_cart"]' ).click();

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '2',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	test( 'Add to cart event keeps tracking after classic cart AJAX replacement', async ( {
		page,
	} ) => {
		await createClassicCartPage();
		await simpleProductAddToCart( page, simpleProductID );
		await page.goto( 'classic-cart' );

		await page.locator( '.quantity input.qty' ).first().fill( '2' );

		const firstEvent = trackGtagEvent( page, 'add_to_cart' );
		await page.locator( 'button[name="update_cart"]' ).click();
		await firstEvent;
		await expect(
			page.locator( '.woocommerce-cart-form .quantity input.qty' ).first()
		).toHaveValue( '2' );

		await page.locator( '.quantity input.qty' ).first().fill( '4' );

		const secondEvent = trackGtagEvent( page, 'add_to_cart' );
		await page.locator( 'button[name="update_cart"]' ).click();

		await secondEvent.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '2',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	test( 'Add to cart event uses cart data when the classic cart row falls back to product ID', async ( {
		page,
	} ) => {
		await createClassicCartPage();
		await simpleProductAddToCart( page, simpleProductID );
		await page.goto( 'classic-cart' );

		await page.evaluate( ( productID ) => {
			const row = document.querySelector(
				'.woocommerce-cart-form .woocommerce-cart-form__cart-item'
			);
			const quantityInput = row?.querySelector( 'input.qty' );

			if ( ! row || ! quantityInput ) {
				throw new Error( 'Classic cart row was not found.' );
			}

			row.classList.remove( 'cart_item' );
			quantityInput.removeAttribute( 'name' );
			window.ga4w.data.products = [
				{
					id: productID,
					name: 'Catalog Simple product',
					categories: [],
					prices: {
						price: 999,
						currency_minor_unit: 2,
					},
				},
				...( window.ga4w.data.products || [] ),
			];
		}, simpleProductID );

		await page
			.locator( '.woocommerce-cart-form__cart-item input.qty' )
			.first()
			.fill( '3' );

		const event = trackGtagEvent( page, 'add_to_cart' );
		await page.evaluate( () => {
			document.querySelector( '.woocommerce-cart-form' ).dispatchEvent(
				new Event( 'submit', {
					bubbles: true,
					cancelable: true,
				} )
			);
		} );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_to_cart' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '2',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	test( 'Add to cart event uses the changed variation when increasing quantity on a classic cart page', async ( {
		page,
	} ) => {
		await createClassicCartPage();

		const variations = await api()
			.get( `products/${ variableProductID }/variations` )
			.then( ( response ) => response.data );
		const findVariation = ( attributes ) =>
			variations.find( ( variation ) =>
				attributes.every( ( { name, option } ) =>
					variation.attributes.some(
						( attribute ) =>
							attribute.name === name &&
							attribute.option === option
					)
				)
			);
		const addVariationToCart = async ( variation ) => {
			const params = new URLSearchParams( {
				'add-to-cart': variableProductID,
				variation_id: variation.id,
			} );

			variation.attributes.forEach( ( { name, option } ) => {
				params.set( `attribute_${ name.toLowerCase() }`, option );
			} );

			const event = trackGtagEvent( page, 'add_to_cart' );
			await page.goto( `?${ params.toString() }` );
			await event;
		};

		await addVariationToCart(
			findVariation( [
				{ name: 'Colour', option: 'Red' },
				{ name: 'Size', option: 'Large' },
			] )
		);
		await addVariationToCart(
			findVariation( [
				{ name: 'Colour', option: 'Green' },
				{ name: 'Size', option: 'Medium' },
			] )
		);
		await page.goto( 'classic-cart' );

		await expect(
			page.locator( '.woocommerce-cart-form .cart_item' )
		).toHaveCount( 2 );

		const greenVariationRow = page
			.locator( '.woocommerce-cart-form .cart_item' )
			.filter( { hasText: 'Green' } );
		const greenVariationQuantity = greenVariationRow.locator( 'input.qty' );
		const previousQuantity = parseInt(
			await greenVariationQuantity.inputValue(),
			10
		);

		expect( previousQuantity ).toBeGreaterThan( 0 );
		await greenVariationQuantity.fill(
			( previousQuantity + 1 ).toString()
		);

		const event = trackGtagEvent( page, 'add_to_cart' );
		await page.locator( 'button[name="update_cart"]' ).click();

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

	test( 'Add shipping info event is sent when changing shipping method on classic checkout', async ( {
		page,
	} ) => {
		await createClassicCheckoutPage();
		await simpleProductAddToCart( page, simpleProductID );

		await page.goto( 'classic-checkout' );
		await page.locator( 'form.checkout' ).waitFor();

		const event = trackGtagEvent( page, 'add_shipping_info' );
		await page.evaluate( () => {
			const shipping = document.querySelector(
				'form.checkout input[name^="shipping_method"], form.checkout select[name^="shipping_method"]'
			);

			if ( ! shipping ) {
				throw new Error( 'Shipping method field was not found.' );
			}

			if ( shipping.tagName === 'SELECT' ) {
				shipping.selectedIndex = 0;
			} else {
				shipping.checked = true;
			}

			shipping.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		await event.then( ( request ) => {
			const data = getEventData( request, 'add_shipping_info' );
			expect( data.product1 ).toEqual( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '1',
				pr: simpleProductPrice.toString(),
			} );
			expect( data.cu ).toEqual( 'USD' );
			expect( data[ 'epn.value' ] ).toEqual(
				simpleProductPrice.toString()
			);
			expect( data[ 'ep.shipping_tier' ] ).toBeTruthy();
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

	test( 'View item list event is sent on empty cart page with products', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'view_item_list' );

		// Navigate without adding any items — cart is empty.
		await page.goto(
			'classic-empty-cart-with-product-collection?orderby=date'
		);

		await event.then( ( request ) => {
			const data = getEventData( request, 'view_item_list' );
			expect( data[ 'ep.item_list_id' ] ).toEqual( 'product_list' );
			expect( data[ 'ep.item_list_name' ] ).toEqual( 'Product List' );
			const products = [ data.product1, data.product2 ];
			const simple = products.find(
				( p ) => p?.id === simpleProductID.toString()
			);
			expect( simple ).toMatchObject( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ln: 'Product List',
				ca: 'Uncategorized',
				pr: simpleProductPrice.toString(),
			} );
		} );
	} );

	test( 'Add to cart event is sent from empty cart page', async ( {
		page,
	} ) => {
		const event = trackGtagEvent( page, 'add_to_cart' );

		// Navigate without adding any items — cart is empty.
		await page.goto(
			'classic-empty-cart-with-product-collection?orderby=date'
		);
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

	test( 'Select content event is sent from empty cart page', async ( {
		page,
	} ) => {
		const listEvent = trackGtagEvent( page, 'view_item_list' );

		// Navigate without adding any items — cart is empty.
		await page.goto(
			'classic-empty-cart-with-product-collection?orderby=date'
		);
		await listEvent;

		// Click the product image link — wc-blocks_viewed_product fires
		// before the browser navigates away.
		const event = trackGtagEvent( page, 'select_content' );
		const productLink = page
			.locator(
				`li.post-${ simpleProductID } .wc-block-components-product-image a`
			)
			.first();
		await Promise.all( [ event, productLink.click() ] );

		await event.then( ( request ) => {
			const data = getEventData( request, 'select_content' );
			expect( data[ 'ep.content_type' ] ).toEqual( 'product' );
			expect( data[ 'ep.content_id' ] ).toEqual(
				simpleProductID.toString()
			);
		} );
	} );

	test( 'Add to cart events are sent for each grouped product child added to cart', async ( {
		page,
	} ) => {
		const groupedProduct = await createGroupedProduct();
		const trackedRequests = [];
		page.on( 'request', ( request ) => {
			if ( request.url().includes( 'google-analytics.com/g/collect' ) ) {
				trackedRequests.push( request );
			}
		} );

		await page.goto( `?p=${ groupedProduct.id }` );

		for ( const child of groupedProduct.children.slice( 0, 2 ) ) {
			await page
				.locator( `input[name="quantity[${ child.id }]"]` )
				.fill( '1' );
		}

		const event = trackGtagEvent( page, 'add_to_cart' );
		await page.locator( '.single_add_to_cart_button' ).click();
		await event;
		await page.waitForTimeout( 1000 );

		const events = trackedRequests.flatMap( ( request ) =>
			getAllEventData( request, 'add_to_cart' )
		);
		const trackedProducts = events.map( ( data ) => data.product1 );

		for ( const child of groupedProduct.children.slice( 0, 2 ) ) {
			expect( trackedProducts ).toContainEqual( {
				id: child.id.toString(),
				nm: child.name,
				ca: 'Uncategorized',
				qt: '1',
				pr: parseFloat( child.price ).toString(),
			} );
		}
	} );

	// The default checkout page is the block checkout, so the block purchase
	// test in the blocks spec covers that form. This test drives the purchase
	// through the classic `[woocommerce_checkout]` shortcode form instead
	// (a wc-ajax=checkout submission rather than the Store API).
	test( 'Purchase event is sent after completing the classic shortcode checkout', async ( {
		page,
	} ) => {
		await createClassicCheckoutPage();

		// Add simple product twice, and one variable product.
		await simpleProductAddToCart( page, simpleProductID );
		await simpleProductAddToCart( page, simpleProductID );
		await variableProductAddToCart( page, variableProductID );

		const event = trackGtagEvent( page, 'purchase', 'checkout' );
		const orderID = await classicCheckout( page );

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

	test( 'Purchase event includes tax total when tax is charged', async ( {
		page,
	} ) => {
		await setOptions( {
			woocommerce_calc_taxes: 'yes',
			woocommerce_prices_include_tax: 'no',
			woocommerce_tax_based_on: 'billing',
		} );
		const taxRateID = await createCaliforniaTaxRate();

		try {
			await simpleProductAddToCart( page, simpleProductID );

			const event = trackGtagEvent( page, 'purchase', 'checkout' );
			await checkout( page );

			await event.then( ( request ) => {
				const data = getEventData( request, 'purchase' );
				expect( data.product1 ).toMatchObject( {
					id: simpleProductID.toString(),
					nm: 'Simple product',
					ca: 'Uncategorized',
					qt: '1',
					pr: simpleProductPrice.toString(),
				} );
				expect( data[ 'epn.tax' ] ).toEqual( '1' );
				expect( data[ 'epn.shipping' ] ).toEqual( '10' );
			} );
		} finally {
			await deleteTaxRate( taxRateID );
			await setOptions( { woocommerce_calc_taxes: 'no' } );
		}
	} );

	test( 'Begin checkout and purchase events use discounted item price', async ( {
		page,
	} ) => {
		const couponCode = await createPercentageCoupon();
		const discountedPrice = ( simpleProductPrice - 2 ).toFixed( 2 );

		await simpleProductAddToCart( page, simpleProductID );
		await page.goto( 'cart' );
		await page.evaluate( async ( code ) => {
			const cartResponse = await window.fetch(
				'/wp-json/wc/store/v1/cart'
			);
			const nonce =
				cartResponse.headers.get( 'Nonce' ) ||
				cartResponse.headers.get( 'X-WC-Store-API-Nonce' );

			const response = await window.fetch(
				'/wp-json/wc/store/v1/cart/apply-coupon',
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						...( nonce ? { Nonce: nonce } : {} ),
					},
					body: JSON.stringify( { code } ),
				}
			);

			if ( ! response.ok ) {
				throw new Error( await response.text() );
			}
		}, couponCode );

		const beginCheckoutEvent = trackGtagEvent( page, 'begin_checkout' );
		await page.goto( 'checkout' );

		await beginCheckoutEvent.then( ( request ) => {
			const data = getEventData( request, 'begin_checkout' );
			expect( data.product1 ).toMatchObject( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				qt: '1',
				pr: discountedPrice,
			} );
			expect( data[ 'epn.value' ] ).toEqual( discountedPrice );
		} );

		const purchaseEvent = trackGtagEvent( page, 'purchase', 'checkout' );
		await checkout( page );

		await purchaseEvent.then( ( request ) => {
			const data = getEventData( request, 'purchase' );
			expect( data.product1 ).toMatchObject( {
				id: simpleProductID.toString(),
				nm: 'Simple product',
				ca: 'Uncategorized',
				qt: '1',
				pr: discountedPrice,
				ds: '2',
			} );
		} );
	} );
} );
