import { __ } from '@wordpress/i18n';
import { removeAction } from '@wordpress/hooks';
import { NAMESPACE, ACTION_PREFIX } from './constants';
import {
	trackBeginCheckout,
	trackShippingInfo,
	trackListProducts,
	trackAddToCart,
	trackChangeCartItemQuantity,
	trackRemoveCartItem,
	trackCheckoutStep,
	trackCheckoutOption,
	trackEvent,
	trackSelectContent,
	trackSearch,
	trackViewItem,
	trackException,
} from './tracking';
import { addUniqueAction } from './utils';

/**
 * Track customer progress through steps of the checkout. Triggers the event when the step changes:
 * 	1 - Contact information
 * 	2 - Shipping address
 * 	3 - Billing address
 * 	4 - Shipping options
 * 	5 - Payment options
 *
 * @summary Track checkout progress with begin_checkout and checkout_progress
 * @see https://developers.google.com/analytics/devguides/collection/gtagjs/enhanced-ecommerce#1_measure_checkout_steps
 */
addUniqueAction(
	`${ ACTION_PREFIX }-checkout-render-checkout-form`,
	NAMESPACE,
	trackBeginCheckout
);

addUniqueAction(
	`${ ACTION_PREFIX }-checkout-set-shipping-address`,
	NAMESPACE,
	trackShippingInfo
);

removeAction( `${ ACTION_PREFIX }-checkout-set-email-address`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-set-phone-number`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-set-billing-address`, NAMESPACE );

/**
 * Choose a shipping rate
 *
 * @summary Track the shipping rate being set using set_checkout_option
 * @see https://developers.google.com/analytics/devguides/collection/gtagjs/enhanced-ecommerce#2_measure_checkout_options
 */
addUniqueAction(
	`${ ACTION_PREFIX }-checkout-set-selected-shipping-rate`,
	NAMESPACE,
	( { shippingRateId } ) => {
		trackCheckoutOption( {
			step: 4,
			option: __( 'Shipping Method', 'woo-gutenberg-products-block' ),
			value: shippingRateId,
		} )();
	}
);

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
addUniqueAction( `${ ACTION_PREFIX }-checkout-submit`, NAMESPACE, () => {
	trackEvent( 'add_payment_info' );
} );

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
