import { getProductFromID } from '../utils';

/**
 * The Google Analytics integration for classic WooCommerce pages
 * triggers events using three different methods.
 *
 * 1. Instantly handle events listed in the `events` object.
 * 2. Listen for custom events from WooCommerce core.
 * 3. Listen for various actions (i.e clicks) on specific elements.
 *
 * To be executed once data set is complete, and `document` is ready.
 *
 * It also handles some Block events that are not fired reliably for `woocommerce/all-products` block.
 * @param {Function} eventHandler
 * @param {Object}   data               - The tracking data from the current page load, containing the following properties:
 * @param {Object}   data.events        - An object containing the events to be instantly tracked.
 * @param {Object}   data.cart          - The cart object.
 * @param {Object[]} data.products      - An array of all product from the current page.
 * @param {Object}   data.product       - The single product object.
 * @param {Object}   data.added_to_cart - The product added to cart.
 * @param {Object}   data.order         - The order object.
 */
export function classicTracking(
	eventHandler,
	{ events, cart, products, product, added_to_cart: addedToCart, order }
) {
	// Instantly track the events listed in the `events` object.
	Object.values( events ?? {} ).forEach( ( eventName ) => {
		if ( eventName === 'add_to_cart' ) {
			eventHandler( eventName )( { product: addedToCart } );
		} else {
			eventHandler( eventName )( {
				storeCart: cart,
				products,
				product,
				order,
			} );
		}
	} );

	// Handle runtime cart events.
	/**
	 * Track the custom add to cart event dispatched by WooCommerce Core
	 *
	 * @param {Event}         e         - The event object
	 * @param {Object}        fragments - An object containing fragments of the updated cart.
	 * @param {string}        cartHash  - A string representing the hash of the cart after the update.
	 * @param {HTMLElement[]} button    - An array of HTML elements representing the add to cart button.
	 */
	document.body.onadded_to_cart = ( e, fragments, cartHash, button ) => {
		// Get product ID from data attribute (archive pages) or value (single product pages).
		const productID = parseInt(
			button[ 0 ].dataset.product_id || button[ 0 ].value
		);

		// If the current product doesn't match search by ID.
		const productToHandle =
			product?.id === productID
				? product
				: getProductFromID( parseInt( productID ), products, cart );

		// Confirm we found a product to handle.
		if ( ! productToHandle ) {
			return;
		}

		eventHandler( 'add_to_cart' )( { product: productToHandle } );
	};

	/**
	 * Attaches click event listeners to all remove from cart links
	 */
	const removeFromCartListener = () => {
		document
			.querySelectorAll(
				'.woocommerce-cart-form .woocommerce-cart-form__cart-item .remove[data-product_id]'
			)
			.forEach( ( item ) =>
				item.addEventListener( 'click', removeFromCartHandler )
			);
	};

	/**
	 * Handle remove from cart events
	 *
	 * @param {HTMLElement|Object} element - The HTML element clicked on to trigger this event
	 */
	function removeFromCartHandler( element ) {
		eventHandler( 'remove_from_cart' )( {
			product: getProductFromID(
				parseInt( element.target.dataset.product_id ),
				products,
				cart
			),
		} );
	}

	// Attach event listeners on initial page load and when the cart div is updated
	removeFromCartListener();
	const oldOnupdatedWcDiv = document.body.onupdated_wc_div;
	document.body.onupdated_wc_div = ( ...args ) => {
		if ( typeof oldOnupdatedWcDiv === 'function' ) {
			oldOnupdatedWcDiv( ...args );
		}
		removeFromCartListener();
	};

	// Trigger the handler when an item is removed from the mini-cart and WooCommerce dispatches the `removed_from_cart` event.
	const oldOnRemovedFromCart = document.body.onremoved_from_cart;
	document.body.onremoved_from_cart = ( ...args ) => {
		if ( typeof oldOnRemovedFromCart === 'function' ) {
			oldOnRemovedFromCart( ...args );
		}
		removeFromCartHandler( { target: args[ 3 ][ 0 ] } );
	};

	// Handle product selection events.
	// Attach click event listeners to non-block product listings
	// to send a `select_content` event if the target link takes the user to the product page.
	document
		.querySelectorAll( '.products .product:not(.wp-block-post)' )
		?.forEach( ( productCard ) => {
			// Get the Product ID from a child node containing the relevant attribute
			const productId = productCard
				.querySelector( 'a[data-product_id]' )
				?.getAttribute( 'data-product_id' );

			if ( ! productId ) {
				return;
			}

			productCard.addEventListener( 'click', ( event ) => {
				// Return early if the user has clicked on an
				// "Add to cart" button or anything other than a product link
				const targetLink = event.target.closest(
					'.woocommerce-loop-product__link'
				);

				const isProductButton =
					event.target.classList.contains( 'button' ) &&
					event.target.hasAttribute( 'data-product_id' );

				const isAddToCartButton =
					event.target.classList.contains( 'add_to_cart_button' ) &&
					! event.target.classList.contains(
						'product_type_variable'
					);

				if (
					! targetLink &&
					( ! isProductButton || isAddToCartButton )
				) {
					return;
				}

				eventHandler( 'select_content' )( {
					product: getProductFromID(
						parseInt( productId ),
						products,
						cart
					),
				} );
			} );
		} );

	// Handle select_content and add_to_cart in Products (Beta) block, Product Collection (Beta) block.
	// Attach click event listeners to a whole product card, as some links may not have the product_id data attribute.
	document
		.querySelectorAll(
			'.products-block-post-template .product, .wc-block-product-template .product'
		)
		?.forEach( ( productCard ) => {
			// Get the Product ID from a child node containing the relevant attribute
			const productId = productCard
				.querySelector( '[data-product_id]' )
				?.getAttribute( 'data-product_id' );

			if ( ! productId ) {
				return;
			}

			productCard.addEventListener( 'click', ( event ) => {
				const target = event.target;
				// `product-view-link` has no serilized HTML identifier/selector, so we look for the parent block element.
				const viewLink = target.closest(
					'.wc-block-components-product-image a'
				);

				// Catch name click
				const nameLink = target.closest( '.wp-block-post-title a' );

				// Catch the enclosing product button.
				const button = target.closest(
					'.wc-block-components-product-button [data-product_id]'
				);

				const isAddToCartButton =
					button &&
					button.classList.contains( 'add_to_cart_button' ) &&
					! button.classList.contains( 'product_type_variable' );

				if ( isAddToCartButton ) {
					// Add to cart.
					eventHandler( 'add_to_cart' )( {
						product: getProductFromID(
							parseInt( productId ),
							products,
							cart
						),
					} );
				} else if ( viewLink || button || nameLink ) {
					// Product image or add-to-cart-like button.
					eventHandler( 'select_content' )( {
						product: getProductFromID(
							parseInt( productId ),
							products,
							cart
						),
					} );
				}
			} );
		} );
}
