import { __ } from '@wordpress/i18n';
import {
	getProductFieldObject,
	getProductImpressionObject,
	getProductId,
	formatPrice,
} from './utils';

/**
 * Tracks view_item_list event
 *
 * @param {Object} params The function params
 * @param {Array} params.products The products to track
 * @param {string} [params.listName] The name of the list in which the item was presented to the user.
 */
export const trackListProducts = ( {
	products,
	listName = __( 'Product List', 'woocommerce-google-analytics-integration' ),
} ) => {
	trackEvent( 'view_item_list', {
		item_list_id: 'engagement',
		item_list_name: __(
			'Viewing products',
			'woocommerce-google-analytics-integration'
		),
		items: products.map( ( product, index ) => ( {
			...getProductImpressionObject( product, listName ),
			index: index + 1,
		} ) ),
	} );
};

/**
 * Tracks add_to_cart event
 *
 * @param {Object} params The function params
 * @param {Array} params.product The product to track
 * @param {number} [params.quantity=1] The quantity of that product in the cart.
 */
export const trackAddToCart = ( { product, quantity = 1 } ) => {
	trackEvent( 'add_to_cart', {
		items: [ getProductFieldObject( product, quantity ) ],
	} );
};

/**
 * Tracks remove_from_cart event
 *
 * @param {Object} params The function params
 * @param {Array} params.product The product to track
 * @param {number} [params.quantity=1] The quantity of that product in the cart.
 */
export const trackRemoveCartItem = ( { product, quantity = 1 } ) => {
	trackEvent( 'remove_from_cart', {
		items: [ getProductFieldObject( product, quantity ) ],
	} );
};

/**
 * Tracks change_cart_quantity event
 *
 * @param {Object} params The function params
 * @param {Array} params.product The product to track
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
 * Track begin_checkout event
 *
 * @param {Object} params The function params
 * @param {Object} params.storeCart The cart object
 */
export const trackBeginCheckout = ( { storeCart } ) => {
	trackEvent( 'begin_checkout', {
		currency: storeCart.totals.currency_code,
		value: formatPrice(
			storeCart.totals.total_price,
			storeCart.totals.currency_minor_unit
		),
		coupon: storeCart.coupons[ 0 ]?.code || '',
		items: storeCart.items.map( getProductFieldObject ),
	} );
};

/**
 * Track add_shipping_info event
 *
 * @param {Object} params The function params
 * @param {Object} params.storeCart The cart object
 */
export const trackShippingTier = ( { storeCart } ) => {
	trackEvent( 'add_shipping_info', {
		currency: storeCart.totals.currency_code,
		value: formatPrice(
			storeCart.totals.total_price,
			storeCart.totals.currency_minor_unit
		),
		coupon: storeCart.coupons[ 0 ]?.code || '',
		shipping_tier:
			storeCart.shippingRates[ 0 ]?.shipping_rates?.find(
				( rate ) => rate.selected
			)?.name || '',
		items: storeCart.items.map( getProductFieldObject ),
	} );
};

/**
 * Tracks select_content event.
 *
 * @param {Object} params The function params
 * @param {Object} params.product The product to track
 */
export const trackSelectContent = ( { product } ) => {
	trackEvent( 'select_content', {
		content_type: 'product',
		content_id: getProductId( product ),
	} );
};

/**
 * Tracks search event.
 *
 * @param {Object} params The function params
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
 * @param {Object} params The function params
 * @param {Object} params.product The product to track
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
 * @param {Object} params The function params
 * @param {string} params.status The status of the exception. It should be "error" for tracking it.
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
 * @param {string} eventName - Name of the event to track
 * @param {Object} [eventParams] - Props to send within the event
 */
export const trackEvent = ( eventName, eventParams ) => {
	if ( typeof gtag !== 'function' ) {
		throw new Error( 'Function gtag not implemented.' );
	}

	window.gtag( 'event', eventName, eventParams );
};
