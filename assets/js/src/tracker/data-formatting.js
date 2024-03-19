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
		items: [ getProductFieldObject( product, quantity ) ],
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
		items: [ getProductFieldObject( product, quantity ) ],
	};
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
 * Formats data for the select_content event.
 *
 * @param {Object} params         The function params
 * @param {Object} params.product The product to track
 */
export const select_content = ( { product } ) => {
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
		currency: order.currency,
		value: parseInt( order.value ),
		items: order.items.map( getProductFieldObject ),
	};
};

/* eslint-enable camelcase */
