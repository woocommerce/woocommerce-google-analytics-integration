import { __ } from '@wordpress/i18n';
import {
	getProductFieldObject,
	getProductImpressionObject,
	getProductId,
	formatPrice,
	getCartCoupon,
	parseQuantity,
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
	if ( ! products?.length ) {
		return false;
	}

	return {
		item_list_id: listName
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /(^_+|_+$)/g, '' ),
		item_list_name: listName,
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
 * Gets the items value of the cart, in minor units, without tax or shipping.
 * Container extensions also put their children's totals on the parent line, so
 * the cart items value (subtotal less discounts) wins when the line sum is
 * higher by more than the 1 minor unit per line that rounding at subtotal can add.
 *
 * @since x.x.x
 *
 * @param {Object} storeCart The cart object
 * @return {number} The items value.
 */
const getItemsValue = ( storeCart ) => {
	const lineSum = storeCart.items.reduce(
		( total, item ) =>
			total +
			parseInt(
				item.totals?.line_total ??
					Math.round(
						item.prices.price * parseQuantity( item.quantity, 1 )
					),
				10
			),
		0
	);
	const totals = storeCart.totals;

	if ( totals?.total_items === undefined ) {
		return lineSum;
	}

	const cartItemsValue =
		parseInt( totals.total_items, 10 ) -
		parseInt( totals.total_discount ?? 0, 10 );

	return lineSum - cartItemsValue > storeCart.items.length
		? cartItemsValue
		: lineSum;
};

const getCheckoutData = ( storeCart ) => {
	if ( ! storeCart?.items?.length ) {
		return false;
	}

	const totals = storeCart.totals;
	const value = getItemsValue( storeCart );

	return {
		currency: totals.currency_code,
		value: formatPrice( value, totals.currency_minor_unit ),
		...getCartCoupon( storeCart ),
		items: storeCart.items.map( ( item ) => getProductFieldObject( item ) ),
	};
};

/**
 * Formats data for the view_cart event
 *
 * @since x.x.x
 *
 * @param {Object} params           The function params
 * @param {Object} params.storeCart The cart object
 */
export const view_cart = ( { storeCart } ) => {
	return getCheckoutData( storeCart );
};

/**
 * Formats data for the begin_checkout event
 *
 * @param {Object} params           The function params
 * @param {Object} params.storeCart The cart object
 */
export const begin_checkout = ( { storeCart } ) => {
	return getCheckoutData( storeCart );
};

/**
 * Formats data for the add_shipping_info event
 *
 * @param {Object} params              The function params
 * @param {Object} params.storeCart    The cart object
 * @param {string} params.shippingTier The selected shipping tier.
 */
export const add_shipping_info = ( { storeCart, shippingTier } ) => {
	const checkoutData = getCheckoutData( storeCart );

	return checkoutData
		? {
				...checkoutData,
				...( shippingTier ? { shipping_tier: shippingTier } : {} ),
			}
		: false;
};

/**
 * Formats data for the add_payment_info event
 *
 * @param {Object} params             The function params
 * @param {Object} params.storeCart   The cart object
 * @param {string} params.paymentType The selected payment type.
 */
export const add_payment_info = ( { storeCart, paymentType } ) => {
	const checkoutData = getCheckoutData( storeCart );

	return checkoutData
		? {
				...checkoutData,
				...( paymentType ? { payment_type: paymentType } : {} ),
			}
		: false;
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

	const items = order.items.map( ( item ) => getProductFieldObject( item ) );
	const minorUnit = order.totals.currency_minor_unit;

	return {
		transaction_id: order.id,
		affiliation: order.affiliation,
		currency: order.totals.currency_code,
		// GA4 defines purchase value as the sum of price × quantity for the
		// items, excluding tax and shipping.
		// https://developers.google.com/analytics/devguides/collection/ga4/reference/events#purchase
		value: formatPrice(
			items.reduce(
				// Round each line to whole minor units: quantity may be decimal.
				( sum, { price = 0, quantity = 1 } ) =>
					sum +
					Math.round(
						Math.round( price * 10 ** minorUnit ) * quantity
					),
				0
			),
			minorUnit
		),
		tax: formatPrice( order.totals.tax_total, minorUnit ),
		shipping: formatPrice( order.totals.shipping_total, minorUnit ),
		items,
	};
};

/* eslint-enable camelcase */
