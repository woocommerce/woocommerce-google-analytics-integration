import { tracker } from '../tracker';
import { getProductFromID } from '../utils';
import { events, cart, products, product } from '../config.js';

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
	};

	Object.values( events ?? {} ).forEach( ( eventName ) => {
		tracker.event( eventName ).handler( eventData );
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
		tracker.event( 'add_to_cart' ).handler( {
			product: getProductFromID(
				parseInt( button[ 0 ].dataset.product_id )
			),
		} );
	};

	/**
	 * Attach click event listeners to all remove from cart links on page load and when the cart is updated.
	 */
	const removeFromCartListener = () => {
		document.querySelector( '.woocommerce-cart-form' )?.addEventListener( 'click', e => {
			const item = e.target.closest( '.woocommerce-cart-form__cart-item .remove' );

			if ( ! item || ! item.dataset.product_id ) {
				return;
			}

			tracker.event( 'remove_from_cart' ).handler( {
				product: getProductFromID(
					parseInt( item.dataset.product_id ),
					true
				),
			} );
		} );
	};

	removeFromCartListener();

	document.body.onupdated_wc_div = () => removeFromCartListener();
};
