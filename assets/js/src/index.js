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
tracker.setupEvents( [
	{
		/**
		 * @see https://developers.google.com/analytics/devguides/collection/ga4/reference/events?client_type=gtag#begin_checkout
		 */
		name: 'begin_checkout',
		callback: trackBeginCheckout,
	},
	{
		/**
		 * @see https://developers.google.com/analytics/devguides/collection/ga4/reference/events?client_type=gtag#add_shipping_info
		 */
		name: 'add_shipping_info',
		callback: trackShippingTier,
	},
	{
		/**
		 * @see https://developers.google.com/gtagjs/reference/ga4-events#view_item_list
		 */
		name: 'view_item_list',
		callback: trackListProducts,
	},
	{
		/**
		 * @see https://developers.google.com/gtagjs/reference/ga4-events#add_to_cart
		 */
		name: 'add_to_cart',
		callback: trackAddToCart,
	},
	{
		/**
		 * @see https://developers.google.com/gtagjs/reference/ga4-events#remove_from_cart
		 */
		name: 'remove_from_cart',
		callback: trackRemoveCartItem,
	},
	{
		/**
		 * @see https://developers.google.com/gtagjs/reference/ga4-events#select_content
		 */
		name: 'select_content',
		callback: trackSelectContent,
	},
	{
		/**
		 * @see https://developers.google.com/gtagjs/reference/ga4-events#search
		 */
		name: 'search',
		callback: trackSearch,
	},
	{
		/**
		 * @see https://developers.google.com/gtagjs/reference/ga4-events#view_item
		 */
		name: 'view_item',
		callback: trackViewItem,
	},
	{
		/**
		 * @see https://developers.google.com/analytics/devguides/collection/ga4/reference/events?client_type=gtag#exception
		 */
		name: 'exception',
		callback: trackException,
	},
] );

// Initialize tracking for classic WooCommerce pages
import { trackClassicIntegration } from './integrations/classic';
trackClassicIntegration();

// Initialize tracking for Block based WooCommerce pages
import './integrations/blocks';
