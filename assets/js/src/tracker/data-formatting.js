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

const getCheckoutData = ( storeCart ) => {
	if ( ! storeCart?.items?.length ) {
		return false;
	}

	const totals = storeCart.totals;

	/*
	 * Per the GA4 spec, `value` is the sum of (price × quantity) for all items
	 * and must NOT include shipping or tax. WooCommerce's `total_price` bundles
	 * shipping and tax, so summing the per-line totals instead keeps `value`
	 * stable across the whole checkout funnel (begin_checkout vs
	 * add_shipping_info / add_payment_info) and between the block and classic
	 * checkouts — which otherwise read carts in different states (live vs
	 * page-load) and shapes.
	 *
	 * Each line total is already net of discounts and tax. The live cart exposes
	 * it as `item.totals.line_total` (with `prices.price` being the unit price),
	 * while the static server cart has no `totals` and stores the line total
	 * directly in `prices.price` — both in minor units, summed as integers to
	 * avoid floating-point drift.
	 * https://developers.google.com/analytics/devguides/collection/ga4/reference/events#add_shipping_info
	 */
	const value = storeCart.items.reduce(
		( total, item ) =>
			total +
			parseInt( item.totals?.line_total ?? item.prices.price, 10 ),
		0
	);

	return {
		currency: totals.currency_code,
		value: formatPrice( value, totals.currency_minor_unit ),
		...getCartCoupon( storeCart ),
		items: storeCart.items.map( getProductFieldObject ),
	};
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

	return {
		transaction_id: order.id,
		affiliation: order.affiliation,
		currency: order.totals.currency_code,
		value: formatPrice(
			order.totals.total_price,
			order.totals.currency_minor_unit
		),
		tax: formatPrice(
			order.totals.tax_total,
			order.totals.currency_minor_unit
		),
		shipping: formatPrice(
			order.totals.shipping_total,
			order.totals.currency_minor_unit
		),
		items: order.items.map( getProductFieldObject ),
	};
};

/* eslint-enable camelcase */
