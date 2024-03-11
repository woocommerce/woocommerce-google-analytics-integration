import { __ } from '@wordpress/i18n';
import {
	getProductFieldObject,
	getProductImpressionObject,
	getProductId,
	formatPrice,
	getCartCoupon,
} from '../utils';

/* eslint-disable camelcase */

/**
 * Formats data for the view_item_list event
 *
 * @param {Object} params            The function params
 * @param {Array}  params.products   The products to track
 * @param {string} [params.listName] The name of the list in which the item was presented to the user.
 */
export const view_item_list = ( {
	products,
	listName = __( 'Product List', 'woocommerce-google-analytics-integration' ),
} ) => {
	if ( products.length === 0 ) {
		return false;
	}

	return {
		item_list_id: 'engagement',
		item_list_name: __(
			'Viewing products',
			'woocommerce-google-analytics-integration'
		),
		items: products.map( ( product, index ) => ( {
			...getProductImpressionObject( product, listName ),
			index: index + 1,
		} ) ),
	};
};

/**
 * Formats data for the add_to_cart event
 *
 * @param {Object} params              The function params
 * @param {Array}  params.product      The product to track
 * @param {number} [params.quantity=1] The quantity of that product in the cart.
 */
export const add_to_cart = ( { product, quantity = 1 } ) => {
	return {
		items: product ? [ getProductFieldObject( product, quantity ) ] : [],
	};
};

/**
 * Formats data for the remove_from_cart event
 *
 * @param {Object} params              The function params
 * @param {Array}  params.product      The product to track
 * @param {number} [params.quantity=1] The quantity of that product in the cart.
 */
export const remove_from_cart = ( { product, quantity = 1 } ) => {
	return {
		items: product ? [ getProductFieldObject( product, quantity ) ] : [],
	};
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
 * Formats data for the begin_checkout event
 *
 * @param {Object} params           The function params
 * @param {Object} params.storeCart The cart object
 */
export const begin_checkout = ( { storeCart } ) => {
	return {
		currency: storeCart.totals.currency_code,
		value: formatPrice(
			storeCart.totals.total_price,
			storeCart.totals.currency_minor_unit
		),
		...getCartCoupon( storeCart ),
		items: storeCart.items.map( getProductFieldObject ),
	};
};

/**
 * Formats data for the add_shipping_info event
 *
 * @param {Object} params           The function params
 * @param {Object} params.storeCart The cart object
 */
export const add_shipping_info = ( { storeCart } ) => {
	return {
		currency: storeCart.totals.currency_code,
		value: formatPrice(
			storeCart.totals.total_price,
			storeCart.totals.currency_minor_unit
		),
		shipping_tier:
			storeCart.shippingRates[ 0 ]?.shipping_rates?.find(
				( rate ) => rate.selected
			)?.name || '',
		...getCartCoupon( storeCart ),
		items: storeCart.items.map( getProductFieldObject ),
	};
};

/**
 * Formats data for the select_content event.
 *
 * @param {Object} params         The function params
 * @param {Object} params.product The product to track
 */
export const select_content = ( { product } ) => {
	if ( ! product ) {
		return false;
	}

	return {
		content_type: 'product',
		content_id: getProductId( product ),
	};
};

/**
 * Formats data for the search event.
 *
 * @param {Object} params            The function params
 * @param {string} params.searchTerm The search term to track
 */
export const search = ( { searchTerm } ) => {
	return {
		search_term: searchTerm,
	};
};

/**
 * Formats data for the view_item event
 *
 * @param {Object} params            The function params
 * @param {Object} params.product    The product to track
 * @param {string} [params.listName] The name of the list in which the item was presented to the user.
 */
export const view_item = ( {
	product,
	listName = __( 'Product List', 'woocommerce-google-analytics-integration' ),
} ) => {
	if ( ! product ) {
		return false;
	}

	return {
		items: [ getProductImpressionObject( product, listName ) ],
	};
};

/**
 * Formats order data for the purchase event
 *
 * @param {Object} params       The function params
 * @param {Object} params.order The order object
 */
export const purchase = ( { order } ) => {
	if ( order === undefined ) {
		return false;
	}

	return {
		currency: order.totals.currency_code,
		value: formatPrice(
			order.totals.total_price,
			order.totals.currency_minor_unit
		),
		items: order.items.map( getProductFieldObject ),
	};
};

/* eslint-enable camelcase */

/**
 * Formats data for the exception event
 *
 * @param {Object} params         The function params
 * @param {string} params.status  The status of the exception. It should be "error" for tracking it.
 * @param {string} params.content The exception description
 */
export const trackException = ( { status, content } ) => {
	if ( status === 'error' ) {
		return {
			description: content,
			fatal: false,
		};
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
