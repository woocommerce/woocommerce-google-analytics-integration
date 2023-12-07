import { tracker } from '../tracker';
import { events, cart, products, product } from '../config.js';

/**
 * The Google Analytics integration for classic WooCommerce pages
 * triggers events using three different methods.
 *
 * 1. Automatically attach events listed in the global `wcgaiData.events` object.
 * 2. Listen for custom jQuery events triggered by core WooCommerce.
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
};
