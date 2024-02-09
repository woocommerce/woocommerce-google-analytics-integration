import { tracker } from '../tracker';
import { getProductFromID } from '../utils';
import {
	events,
	cart,
	products,
	product,
	addedToCart,
	order,
} from '../config.js';

/**
 * The Google Analytics integration for classic WooCommerce pages
 * triggers events using three different methods.
 *
 * 1. Automatically handle events listed in the global `wcgaiData.events` object.
 * 2. Listen for custom events from WooCommerce core.
 * 3. Listen for various actions (i.e clicks) on specific elements.
 */

export const trackClassicIntegration = () => {
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

	/**
	 * Track the custom add to cart event dispatched by WooCommerce Core
	 *
	 * @param {Event} e - The event object
	 * @param {Object} fragments - An object containing fragments of the updated cart.
	 * @param {string} cartHash - A string representing the hash of the cart after the update.
	 * @param {HTMLElement[]} button - An array of HTML elements representing the add to cart button.
	 */
	document.body.onadded_to_cart = ( e, fragments, cartHash, button ) => {
		tracker.eventHandler( 'add_to_cart' )( {
			product: getProductFromID(
				parseInt( button[ 0 ].dataset.product_id )
			),
		} );
	};

	/**
	 * Attach click event listeners to all remove from cart links on page load and when the cart is updated.
	 */
	const removeFromCartListener = () => {
		document
			.querySelector( '.woocommerce-cart-form' )
			?.addEventListener( 'click', ( e ) => {
				const item = e.target.closest(
					'.woocommerce-cart-form__cart-item .remove'
				);

				if ( ! item || ! item.dataset.product_id ) {
					return;
				}

				tracker.eventHandler( 'remove_from_cart' )( {
					product: getProductFromID(
						parseInt( item.dataset.product_id ),
						true
					),
				} );
			} );
	};

	removeFromCartListener();

	document.body.onupdated_wc_div = () => removeFromCartListener();

	/**
	 * Attach click event listeners to all product listings and send select_content events for specific targets.
	 */
	document
		.querySelectorAll( '.product a[data-product_id]' )
		?.forEach( ( button ) => {
			const productId = button.dataset.product_id;

			button.parentNode.addEventListener( 'click', ( listing ) => {
				const targetLink = listing.target.closest(
					'.woocommerce-loop-product__link'
				);
				const isAddToCartButton =
					button.classList.contains( 'add_to_cart_button' ) &&
					! button.classList.contains( 'product_type_variable' );

				if ( ! targetLink && isAddToCartButton ) {
					return;
				}

				tracker.eventHandler( 'select_content' )( {
					product: getProductFromID( parseInt( productId ) ),
				} );
			} );
		} );
};
