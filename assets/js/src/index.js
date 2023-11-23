import { tracker } from './tracker';

import {
	trackBeginCheckout,
	trackShippingTier,
	trackListProducts,
	trackAddToCart,
	// trackChangeCartItemQuantity,
	trackRemoveCartItem,
	trackSelectContent,
	trackSearch,
	trackViewItem,
	trackException,
} from './tracker/data-formatting';

/**
 * Register all Google Analytics 4 events that can be tracked
 */
tracker.eventsMap = new Map( [
	[
		/**
		 * @see https://developers.google.com/analytics/devguides/collection/ga4/reference/events?client_type=gtag#begin_checkout
		 */
		'begin_checkout',
		trackBeginCheckout,
	],
	[
		/**
		 * @see https://developers.google.com/analytics/devguides/collection/ga4/reference/events?client_type=gtag#add_shipping_info
		 */
		'add_shipping_info',
		trackShippingTier,
	],
	[
		/**
		 * @see https://developers.google.com/gtagjs/reference/ga4-events#view_item_list
		 */
		'view_item_list',
		trackListProducts,
	],
	[
		/**
		 * @see https://developers.google.com/gtagjs/reference/ga4-events#add_to_cart
		 */
		'add_to_cart',
		trackAddToCart,
	],
	[
		/**
		 * @see https://developers.google.com/gtagjs/reference/ga4-events#remove_from_cart
		 */
		'remove_from_cart',
		trackRemoveCartItem,
	],
	[
		/**
		 * @see https://developers.google.com/gtagjs/reference/ga4-events#select_content
		 */
		'select_content',
		trackSelectContent,
	],
	[
		/**
		 * @see https://developers.google.com/gtagjs/reference/ga4-events#search
		 */
		'search',
		trackSearch,
	],
	[
		/**
		 * @see https://developers.google.com/gtagjs/reference/ga4-events#view_item
		 */
		'view_item',
		trackViewItem,
	],
	[
		/**
		 * @see https://developers.google.com/analytics/devguides/collection/ga4/reference/events?client_type=gtag#exception
		 */
		'exception',
		trackException,
	],
] );

// Initialize tracking for classic WooCommerce pages
import { trackClassicIntegration } from './integrations/classic';
trackClassicIntegration();

// Initialize tracking for Block based WooCommerce pages
import './integrations/blocks';
