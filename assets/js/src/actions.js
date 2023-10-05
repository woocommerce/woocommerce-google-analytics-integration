import { __ } from '@wordpress/i18n';
import { removeAction } from '@wordpress/hooks';
import { NAMESPACE, ACTION_PREFIX } from './constants';
import {
	trackBeginCheckout,
	trackShippingTier,
	trackPaymentMethod,
	trackListProducts,
	trackAddToCart,
	trackChangeCartItemQuantity,
	trackRemoveCartItem,
	trackCheckoutOption,
	trackEvent,
	trackSelectContent,
	trackSearch,
	trackViewItem,
	trackException,
} from './tracking';
import { addUniqueAction } from './utils';

/**
 * Track begin_checkout
 *
 * @summary Track the customer has started the checkout process
 * @see https://developers.google.com/analytics/devguides/collection/ga4/reference/events?client_type=gtag#begin_checkout
 */
addUniqueAction(
	`${ ACTION_PREFIX }-checkout-render-checkout-form`,
	NAMESPACE,
	trackBeginCheckout
);

/**
 * Track add_shipping_info
 *
 * @summary Track the selected shipping tier when the checkout form is submitted
 * @see https://developers.google.com/analytics/devguides/collection/ga4/reference/events?client_type=gtag#add_shipping_info
 */
addUniqueAction(
	`${ ACTION_PREFIX }-checkout-submit`,
	NAMESPACE,
	trackShippingTier
);

/**
 * The following actions were previously tracked using]checkout_progress
 * in UA but there is no comparable event in GA4.
 */
removeAction( `${ ACTION_PREFIX }-checkout-set-email-address`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-set-phone-number`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-set-billing-address`, NAMESPACE );

/**
 * Choose a payment method
 *
 * @summary Track the payment method being set using set_checkout_option
 * @see https://developers.google.com/analytics/devguides/collection/gtagjs/enhanced-ecommerce#2_measure_checkout_options
 */
addUniqueAction(
	`${ ACTION_PREFIX }-checkout-set-active-payment-method`,
	NAMESPACE,
	( { paymentMethodSlug } ) => {
		trackCheckoutOption( {
			step: 5,
			option: __( 'Payment Method', 'woo-gutenberg-products-block' ),
			value: paymentMethodSlug,
		} )();
	}
);

/**
 * Product List View
 *
 * @summary Track the view_item_list event
 * @see https://developers.google.com/gtagjs/reference/ga4-events#view_item_list
 */
addUniqueAction(
	`${ ACTION_PREFIX }-product-list-render`,
	NAMESPACE,
	trackListProducts
);

/**
 * Add to cart.
 *
 * This event signifies that an item was added to a cart for purchase.
 *
 * @summary Track the add_to_cart event
 * @see https://developers.google.com/gtagjs/reference/ga4-events#add_to_cart
 */
addUniqueAction(
	`${ ACTION_PREFIX }-cart-add-item`,
	NAMESPACE,
	trackAddToCart
);

/**
 * Change cart item quantities
 *
 * @summary Custom change_cart_quantity event.
 */
addUniqueAction(
	`${ ACTION_PREFIX }-cart-set-item-quantity`,
	NAMESPACE,
	trackChangeCartItemQuantity
);

/**
 * Remove item from the cart
 *
 * @summary Track the remove_from_cart event
 * @see https://developers.google.com/gtagjs/reference/ga4-events#remove_from_cart
 */
addUniqueAction(
	`${ ACTION_PREFIX }-cart-remove-item`,
	NAMESPACE,
	trackRemoveCartItem
);

/**
 * Add Payment Information
 *
 * This event signifies a user has submitted their payment information. Note, this is used to indicate checkout
 * submission, not `purchase` which is triggered on the thanks page.
 *
 * @summary Track the add_payment_info event
 * @see https://developers.google.com/gtagjs/reference/ga4-events#add_payment_info
 */
// addUniqueAction( `${ ACTION_PREFIX }-checkout-submit`, NAMESPACE, ( c ) => {
// 	console.log( c );
// 	trackEvent( 'add_payment_info' );
// } );

/**
 * Product View Link Clicked
 *
 * @summary Track the select_content event
 * @see https://developers.google.com/gtagjs/reference/ga4-events#select_content
 */
addUniqueAction(
	`${ ACTION_PREFIX }-product-view-link`,
	NAMESPACE,
	trackSelectContent
);

/**
 * Product Search
 *
 * @summary Track the search event
 * @see https://developers.google.com/gtagjs/reference/ga4-events#search
 */
addUniqueAction( `${ ACTION_PREFIX }-product-search`, NAMESPACE, trackSearch );

/**
 * Single Product View
 *
 * @summary Track the view_item event
 * @see https://developers.google.com/gtagjs/reference/ga4-events#view_item
 */
addUniqueAction(
	`${ ACTION_PREFIX }-product-render`,
	NAMESPACE,
	trackViewItem
);

/**
 * Track notices as Exception events.
 *
 * @summary Track the exception event
 * @see https://developers.google.com/analytics/devguides/collection/gtagjs/exceptions
 */
addUniqueAction(
	`${ ACTION_PREFIX }-store-notice-create`,
	NAMESPACE,
	trackException
);
