import { __ } from '@wordpress/i18n';
import {
	getProductFieldObject,
	getProductImpressionObject,
	formatPrice,
} from './utils';

/**
 * Variable holding the current checkout step. It will be modified by trackCheckoutOption and trackCheckoutStep methods.
 *
 * @type {number}
 */
let currentStep = -1;

/**
 * Tracks view_item_list event
 *
 * @param {Object} params            The function params
 * @param {Array}  params.products   The products to track
 * @param {string} [params.listName] The name of the list in which the item was presented to the user.
 */
export const trackListProducts = ( {
	products,
	listName = __( 'Product List', 'woocommerce-google-analytics-integration' ),
} ) => {
	trackEvent( 'view_item_list', {
		event_category: 'engagement',
		event_label: __(
			'Viewing products',
			'woocommerce-google-analytics-integration'
		),
		items: products.map( ( product, index ) => ( {
			...getProductImpressionObject( product, listName ),
			list_position: index + 1,
		} ) ),
	} );
};

/**
 * Tracks add_to_cart event
 *
 * @param {Object} data The product to track if WC >= 8.5 or product and quantity if WC < 8.5
 */
export const trackAddToCart = ( data ) => {
	let product, quantity;

	if ( ! data ) {
		return;
	}

	if ( data?.product ) {
		// WC < 8.5
		product = data.product;
		quantity = data.quantity;
	} else {
		product = data;
		quantity = 1;
	}

	trackEvent( 'add_to_cart', {
		event_category: 'ecommerce',
		event_label: __(
			'Add to Cart',
			'woocommerce-google-analytics-integration'
		),
		items: [ getProductFieldObject( product, quantity ) ],
	} );
};

/**
 * Tracks remove_from_cart event
 *
 * @param {Object} params              The function params
 * @param {Array}  params.product      The product to track
 * @param {number} [params.quantity=1] The quantity of that product in the cart.
 */
export const trackRemoveCartItem = ( { product, quantity = 1 } ) => {
	trackEvent( 'remove_from_cart', {
		event_category: 'ecommerce',
		event_label: __(
			'Remove Cart Item',
			'woocommerce-google-analytics-integration'
		),
		items: [ getProductFieldObject( product, quantity ) ],
	} );
};

/**
 * Tracks change_cart_quantity event
 *
 * @param {Object} params              The function params
 * @param {Array}  params.product      The product to track
 * @param {number} [params.quantity=1] The quantity of that product in the cart.
 */
export const trackChangeCartItemQuantity = ( { product, quantity = 1 } ) => {
	trackEvent( 'change_cart_quantity', {
		event_category: 'ecommerce',
		event_label: __(
			'Change Cart Item Quantity',
			'woocommerce-google-analytics-integration'
		),
		items: [ getProductFieldObject( product, quantity ) ],
	} );
};

/**
 * Track a begin_checkout and checkout_progress event
 * Notice calling this will set the current checkout step as the step provided in the parameter.
 *
 * @param {number} step The checkout step for to track
 * @return {(function( { storeCart: Object } ): void)} A callable receiving the cart to track the checkout event.
 */
export const trackCheckoutStep =
	( step ) =>
	( { storeCart } ) => {
		if ( currentStep === step ) {
			return;
		}

		// compatibility-code "WC >= 8.1" -- The data structure of `storeCart` was (accidentally) changed.
		if ( ! storeCart.hasOwnProperty( 'cartTotals' ) ) {
			storeCart = {
				cartCoupons: storeCart.coupons,
				cartItems: storeCart.items,
				cartTotals: storeCart.totals,
			};
		}

		trackEvent( step === 0 ? 'begin_checkout' : 'checkout_progress', {
			items: storeCart.cartItems.map( ( item ) =>
				getProductFieldObject( item, item.quantity )
			),
			coupon: storeCart.cartCoupons[ 0 ]?.code || '',
			currency: storeCart.cartTotals.currency_code,
			value: formatPrice(
				storeCart.cartTotals.total_price,
				storeCart.cartTotals.currency_minor_unit
			),
			checkout_step: step,
		} );

		currentStep = step;
	};

/**
 * Track a set_checkout_option event
 * Notice calling this will set the current checkout step as the step provided in the parameter.
 *
 * @param {Object} params        The params from the option.
 * @param {number} params.step   The step to track
 * @param {string} params.option The option to set in checkout
 * @param {string} params.value  The value for the option
 *
 * @return {(function() : void)} A callable to track the checkout event.
 */
export const trackCheckoutOption =
	( { step, option, value } ) =>
	() => {
		trackEvent( 'set_checkout_option', {
			checkout_step: step,
			checkout_option: option,
			value,
		} );

		currentStep = step;
	};

/**
 * Tracks select_content event.
 *
 * @param {Object} params          The function params
 * @param {Object} params.product  The product to track
 * @param {string} params.listName The name of the list in which the item was presented to the user.
 */
export const trackSelectContent = ( {
	product,
	listName = __( 'Product List', 'woocommerce-google-analytics-integration' ),
} ) => {
	trackEvent( 'select_content', {
		content_type: 'product',
		items: [ getProductImpressionObject( product, listName ) ],
	} );
};

/**
 * Tracks search event.
 *
 * @param {Object} params            The function params
 * @param {string} params.searchTerm The search term to track
 */
export const trackSearch = ( { searchTerm } ) => {
	trackEvent( 'search', {
		search_term: searchTerm,
	} );
};

/**
 * Tracks view_item event
 *
 * @param {Object} params            The function params
 * @param {Object} params.product    The product to track
 * @param {string} [params.listName] The name of the list in which the item was presented to the user.
 */
export const trackViewItem = ( {
	product,
	listName = __( 'Product List', 'woocommerce-google-analytics-integration' ),
} ) => {
	if ( product ) {
		trackEvent( 'view_item', {
			items: [ getProductImpressionObject( product, listName ) ],
		} );
	}
};

/**
 * Track exception event
 *
 * @param {Object} params         The function params
 * @param {string} params.status  The status of the exception. It should be "error" for tracking it.
 * @param {string} params.content The exception description
 */
export const trackException = ( { status, content } ) => {
	if ( status === 'error' ) {
		trackEvent( 'exception', {
			description: content,
			fatal: false,
		} );
	}
};

/**
 * Track an event using the global gtag function.
 *
 * @param {string} eventName     - Name of the event to track
 * @param {Object} [eventParams] - Props to send within the event
 */
export const trackEvent = ( eventName, eventParams ) => {
	if ( typeof gtag !== 'function' ) {
		throw new Error( 'Function gtag not implemented.' );
	}

	window.gtag( 'event', eventName, eventParams );
};
