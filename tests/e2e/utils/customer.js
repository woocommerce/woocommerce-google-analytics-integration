/**
 * Helper functions for handling the cart.
 *
 * @typedef { import( '@playwright/test' ).Page } Page
 */

/**
 * External dependencies
 */
const { expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
import { LOAD_STATE } from './constants';
const config = require( '../config/default.json' );

/**
 * Adds a simple product to the cart.
 *
 * @param {Page}   page
 * @param {number} productID
 */
export async function simpleProductAddToCart( page, productID ) {
	await page.goto( `?p=${ productID }` );

	const addToCart = '.single_add_to_cart_button';
	await page.locator( addToCart ).first().click();
	await expect(
		page.getByText( 'has been added to your cart' )
	).toBeVisible();

	// Wait till all tracking event request have been sent after page reloaded.
	await page.waitForLoadState( LOAD_STATE.DOM_CONTENT_LOADED );
}

/**
 * Adds a variable product to the cart.
 *
 * @param {Page}   page
 * @param {number} productID
 */
export async function variableProductAddToCart( page, productID ) {
	await page.goto( `?p=${ productID }` );

	// Default attributes are set, so we just need to wait for the add to cart button to be enabled.
	await page.waitForTimeout( 3000 );

	const addToCart = '.single_add_to_cart_button:not(.disabled)';
	await page.locator( addToCart ).click();

	await expect(
		page.getByText( 'has been added to your cart' )
	).toBeVisible();

	// Wait till all tracking event request have been sent after page reloaded.
	await page.waitForLoadState( LOAD_STATE.DOM_CONTENT_LOADED );
}

/**
 * Adds a related product to the cart.
 *
 * @param {Page} page
 *
 * @return {number} Product ID of the added product.
 */
export async function relatedProductAddToCart( page ) {
	const addToCart = `.related.products .add_to_cart_button.product_type_simple,
		.wp-block-woocommerce-related-products .add_to_cart_button.product_type_simple,
		[data-collection="woocommerce/product-collection/related"] .add_to_cart_button.product_type_simple`;

	const addToCartButton = await page.locator( addToCart ).first();
	await addToCartButton.click();
	await expect( addToCartButton.getByText( '1 in cart' ) ).toBeVisible();
	return await page.$eval( addToCart, ( el ) => el.dataset.product_id );
}

/**
 * Add a product to the cart from a block shop page.
 *
 * Note: This function will match any product type, so it should not be used for
 * products that can not be added directly from the shop page.
 *
 * @param {Page}   page
 * @param {number} productID
 */
export async function blockProductAddToCart( page, productID ) {
	const addToCart = `[data-product_id="${ productID }"]`;
	const addToCartButton = await page.locator( addToCart ).first();
	await addToCartButton.click();
	await expect( addToCartButton.getByText( '1 in cart' ) ).toBeVisible();
}

/**
 * Waits for the plugin's Store API fetch interceptor to be installed.
 *
 * The interceptor wraps `window.fetch` from within `blocksTracking()`, which
 * runs once `window.ga4w` is ready. We detect the wrap without relying on the
 * function name (which a production build may minify) by checking that
 * `window.fetch` is no longer the browser's native implementation.
 *
 * @param {Page} page
 */
export async function waitForStoreApiInterceptor( page ) {
	await page.waitForFunction(
		() =>
			!! window.ga4w &&
			! window.fetch.toString().includes( 'native code' )
	);
}

/**
 * Adds a product to the cart through the Store API, mirroring how the
 * Interactivity API powered add-to-cart blocks (WooCommerce 10.4+) add items.
 *
 * Waits for the fetch interceptor first, and authenticates the request with a
 * Store API nonce read from a GET /cart response header — the nonce is not
 * reliably exposed on the page (e.g. `wcSettings.storeApiNonce`) for all page
 * types, so reading it from the response header is the robust approach.
 *
 * @param {Page}   page
 * @param {number} productID
 * @param {number} [quantity=1]
 */
export async function storeApiAddToCart( page, productID, quantity = 1 ) {
	await waitForStoreApiInterceptor( page );

	await page.evaluate(
		async ( { id, qty } ) => {
			const cartResponse = await window.fetch(
				'/wp-json/wc/store/v1/cart'
			);
			const nonce =
				cartResponse.headers.get( 'Nonce' ) ||
				cartResponse.headers.get( 'X-WC-Store-API-Nonce' );

			const response = await window.fetch(
				'/wp-json/wc/store/v1/cart/add-item',
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						...( nonce ? { Nonce: nonce } : {} ),
					},
					body: JSON.stringify( { id, quantity: qty } ),
				}
			);

			if ( ! response.ok ) {
				throw new Error( await response.text() );
			}
		},
		{ id: productID, qty: quantity }
	);
}

/**
 * Adds a product to the cart through the Store API batch endpoint, mirroring how
 * the Interactivity API powered add-to-cart blocks (WooCommerce 10.4+) bundle
 * their cart mutations into a single `/wc/store/v1/batch` request.
 *
 * @param {Page}   page
 * @param {number} productID
 * @param {number} [quantity=1]
 */
export async function storeApiBatchAddToCart( page, productID, quantity = 1 ) {
	await waitForStoreApiInterceptor( page );

	await page.evaluate(
		async ( { id, qty } ) => {
			const cartResponse = await window.fetch(
				'/wp-json/wc/store/v1/cart'
			);
			const nonce =
				cartResponse.headers.get( 'Nonce' ) ||
				cartResponse.headers.get( 'X-WC-Store-API-Nonce' );

			const response = await window.fetch( '/wp-json/wc/store/v1/batch', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					...( nonce ? { Nonce: nonce } : {} ),
				},
				body: JSON.stringify( {
					requests: [
						{
							method: 'POST',
							path: '/wc/store/v1/cart/add-item',
							// Store API verifies the nonce per sub-request, so it
							// must be repeated here, not only on the batch request.
							headers: nonce ? { Nonce: nonce } : {},
							body: { id, quantity: qty },
						},
					],
				} ),
			} );

			if ( ! response.ok ) {
				throw new Error( await response.text() );
			}
		},
		{ id: productID, qty: quantity }
	);
}

/**
 * Raises the quantity of a cart item through the Store API batch endpoint,
 * mirroring how the Interactivity API powered add-to-cart blocks increase the
 * quantity of a product that is already in the cart (a `cart/update-item`
 * request carrying the new total quantity).
 *
 * @param {Page}   page
 * @param {number} productID
 * @param {number} increaseBy How many units to add on top of the current quantity.
 */
export async function storeApiBatchIncreaseCartQuantity(
	page,
	productID,
	increaseBy = 1
) {
	await waitForStoreApiInterceptor( page );

	await page.evaluate(
		async ( { id, delta } ) => {
			const cartResponse = await window.fetch(
				'/wp-json/wc/store/v1/cart'
			);
			const nonce =
				cartResponse.headers.get( 'Nonce' ) ||
				cartResponse.headers.get( 'X-WC-Store-API-Nonce' );
			const cart = await cartResponse.json();
			const item = cart.items.find(
				( cartItem ) => parseInt( cartItem.id, 10 ) === id
			);

			if ( ! item ) {
				throw new Error( `Product ${ id } is not in the cart` );
			}

			const response = await window.fetch( '/wp-json/wc/store/v1/batch', {
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
								quantity: item.quantity + delta,
							},
						},
					],
				} ),
			} );

			if ( ! response.ok ) {
				throw new Error( await response.text() );
			}
		},
		{ id: productID, delta: increaseBy }
	);
}

/**
 * Perform checkout steps to purchase a product.
 *
 * @param {Page} page
 *
 * @return {number} Order number.
 */
export async function checkout( page ) {
	const user = config.addresses.customer.billing;

	await page.goto( 'checkout' );

	if ( await page.locator( '#billing_first_name' ).isVisible() ) {
		await page.locator( '#billing_first_name' ).fill( user.firstname );
		await page.locator( '#billing_last_name' ).fill( user.lastname );
		await page
			.locator( '#billing_address_1' )
			.fill( user.addressfirstline );
		await page.locator( '#billing_city' ).fill( user.city );
		await page.locator( '#billing_state' ).selectOption( user.state );
		await page.locator( '#billing_postcode' ).fill( user.postcode );
		await page.locator( '#billing_phone' ).fill( user.phone );
		await page.locator( '#billing_email' ).fill( user.email );

		await page.locator( 'text=Cash on delivery' ).click();
		await expect( page.locator( 'div.payment_method_cod' ) ).toBeVisible();
	} else {
		await page.getByLabel( 'Email address' ).fill( user.email );
		await page.getByLabel( 'First name' ).fill( user.firstname );
		await page.getByLabel( 'Last name' ).fill( user.lastname );
		await page
			.getByLabel( 'Address', { exact: true } )
			.fill( user.addressfirstline );
		await page.getByLabel( 'City' ).fill( user.city );
		await page.getByLabel( 'ZIP Code' ).fill( user.postcode );

		const stateField = page.getByRole( 'combobox', { name: /State$/ } );
		const stateFieldTagName = await stateField.evaluate(
			( element ) => element.tagName
		);
		if ( stateFieldTagName === 'SELECT' ) {
			stateField.selectOption( user.statename );
		} else {
			// compatibility-code "WC < 9.2"
			stateField.fill( user.statename );
		}
	}

	//TODO: See if there's an alternative method to click the button without relying on waitForTimeout.
	await page.waitForTimeout( 3000 );

	await page.locator( 'text=Place order' ).click();

	await expect(
		page.locator( '.wc-block-order-confirmation-status' )
	).toContainText( 'order has been received' );

	// Return order number from page.
	return await page.$eval(
		'.wc-block-order-confirmation-summary-list-item__value',
		( el ) => el.textContent
	);
}
