import { tracker } from '../tracker';
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
 * @param {Object}   data               - The tracking data from the current page load, containing the following properties:
 * @param {Object}   data.events        - An object containing the events to be instantly tracked.
 * @param {Object}   data.cart          - The cart object.
 * @param {Object[]} data.products      - An array of all product from the current page.
 * @param {Object}   data.product       - The single product object.
 * @param {Object}   data.added_to_cart - The product added to cart.
 * @param {Object}   data.order         - The order object.
 */
export function trackClassicPages( {
	events,
	cart,
	products,
	product,
	added_to_cart: addedToCart,
	order,
} ) {
	// Instantly track the events listed in the `events` object.
	const eventData = {
		storeCart: cart,
		products,
		product,
		order,
	};
	Object.values( events ?? {} ).forEach( ( eventName ) => {
		if ( eventName === 'add_to_cart' ) {
			tracker.eventHandler( eventName )( { product: addedToCart } );
		} else {
			tracker.eventHandler( eventName )( eventData );
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
		tracker.eventHandler( 'add_to_cart' )( {
			product: getProductFromID(
				parseInt( button[ 0 ].dataset.product_id ),
				products,
				cart
			),
		} );
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
		tracker.eventHandler( 'remove_from_cart' )( {
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
		.querySelectorAll(
			'.products .product, .products-block-post-template .product'
		)
		?.forEach( ( item ) => {
			// Get the Product ID from a child node containing the relevant attribute
			const productId = item
				.querySelector( '[data-product_id]' )
				?.getAttribute( 'data-product_id' );

			if ( ! productId ) {
				return;
			}

			item.addEventListener( 'click', ( event ) => {
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
					tracker.eventHandler( 'add_to_cart' )( {
						product: getProductFromID(
							parseInt( productId ),
							products,
							cart
						),
					} );
				}

				tracker.eventHandler( 'select_content' )( {
					product: getProductFromID(
						parseInt( productId ),
						products,
						cart
					),
				} );
			} );
		} );
}
