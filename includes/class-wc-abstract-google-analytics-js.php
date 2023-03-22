<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Abstract_Google_Analytics_JS class
 *
 * Abstract JS for recording Google Analytics/Gtag info
 */
abstract class WC_Abstract_Google_Analytics_JS {

	/** @var WC_Abstract_Google_Analytics_JS $instance Class Instance */
	protected static $instance;

	/** @var array $options Inherited Analytics options */
	protected static $options;

	/** @var string Developer ID */
	public const DEVELOPER_ID = 'dOGY3NW';

	/**
	 * Get the class instance
	 *
	 * @param  array $options Options
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	abstract public static function get_instance( $options = array() );

	/**
	 * Return one of our options
	 *
	 * @param  string $option Key/name for the option
	 * @return string         Value of the option
	 */
	protected static function get( $option ) {
		return self::$options[ $option ];
	}

	/**
	 * Returns the tracker variable this integration should use
	 *
	 * @return string
	 */
	abstract public static function tracker_var();

	/**
	 * Generic GA snippet for opt out
	 */
	public static function load_opt_out() {
		$code = "
			var gaProperty = '" . esc_js( self::get( 'ga_id' ) ) . "';
			var disableStr = 'ga-disable-' + gaProperty;
			if ( document.cookie.indexOf( disableStr + '=true' ) > -1 ) {
				window[disableStr] = true;
			}
			function gaOptout() {
				document.cookie = disableStr + '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/';
				window[disableStr] = true;
			}";

		wp_register_script( 'google-analytics-opt-out', '', array(), null, false );
		wp_add_inline_script( 'google-analytics-opt-out', $code );
		wp_enqueue_script( 'google-analytics-opt-out' );
	}

	/**
	 * Enqueues JavaScript to build the addImpression object
	 *
	 * @param WC_Product $product
	 * @param int        $position
	 */
	abstract public static function listing_impression( $product, $position );

	/**
	 * Enqueues JavaScript to build an addProduct and click object
	 *
	 * @param WC_Product $product
	 * @param int        $position
	 */
	abstract public static function listing_click( $product, $position );

	/**
	 * Loads the correct Google Gtag code (classic or universal)
	 *
	 * @param  boolean|WC_Order $order Classic analytics needs order data to set the currency correctly
	 */
	abstract public static function load_analytics( $order = false );

	/**
	 * Generate code used to pass transaction data to Google Analytics.
	 *
	 * @param  WC_Order $order WC_Order Object
	 */
	public function add_transaction( $order ) {
		if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) || 'yes' === self::get( 'ga_gtag_enabled' ) ) {
			wc_enqueue_js( static::add_transaction_enhanced( $order ) );
		} else {
			wc_enqueue_js( self::add_transaction_universal( $order ) );
		}
	}

	/**
	 * Generate Enhanced eCommerce transaction tracking code
	 *
	 * @param  WC_Order $order WC_Order object
	 * @return string          Add Transaction Code
	 */
	abstract protected function add_transaction_enhanced( $order );

	/**
	 * Get item identifier from product data
	 *
	 * @param  WC_Product $product WC_Product Object
	 * @return string
	 */
	public static function get_product_identifier( $product ) {
		if ( ! empty( $product->get_sku() ) ) {
			return esc_js( $product->get_sku() );
		} else {
			return esc_js( '#' . ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ) );
		}
	}

	/**
	 * Generate Universal Analytics add item tracking code
	 *
	 * @param  WC_Order      $order     WC_Order Object
	 * @param  WC_Order_Item $item The item to add to a transaction/order
	 * @return string
	 */
	protected function add_item_universal( $order, $item ) {
		$_product = version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_product_from_item( $item ) : $item->get_product();

		$code  = "ga('ecommerce:addItem', {";
		$code .= "'id': '" . esc_js( $order->get_order_number() ) . "',";
		$code .= "'name': '" . esc_js( $item['name'] ) . "',";
		$code .= "'sku': '" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->get_id() ) . "',";
		$code .= "'category': " . self::product_get_category_line( $_product );
		$code .= "'price': '" . esc_js( $order->get_item_total( $item ) ) . "',";
		$code .= "'quantity': '" . esc_js( $item['qty'] ) . "'";
		$code .= '});';

		return $code;
	}

	/**
	 * Generate Universal Analytics transaction tracking code
	 *
	 * @param  WC_Order $order WC_Order object
	 * @return string          Add Transaction tracking code
	 */
	protected function add_transaction_universal( $order ) {
		$code = "ga('ecommerce:addTransaction', {
			'id': '" . esc_js( $order->get_order_number() ) . "',         // Transaction ID. Required
			'affiliation': '" . esc_js( get_bloginfo( 'name' ) ) . "',    // Affiliation or store name
			'revenue': '" . esc_js( $order->get_total() ) . "',           // Grand Total
			'shipping': '" . esc_js( $order->get_total_shipping() ) . "', // Shipping
			'tax': '" . esc_js( $order->get_total_tax() ) . "',           // Tax
			'currency': '" . esc_js( version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_order_currency() : $order->get_currency() ) . "'  // Currency
		});";

		// Order items
		if ( $order->get_items() ) {
			foreach ( $order->get_items() as $item ) {
				$code .= self::add_item_universal( $order, $item );
			}
		}

		$code .= "ga('ecommerce:send');";
		return $code;
	}

	/**
	 * Returns a 'category' JSON line based on $product
	 *
	 * @param  WC_Product $_product  Product to pull info for
	 * @return string                Line of JSON
	 */
	public static function product_get_category_line( $_product ) {
		$out            = [];
		$variation_data = $_product->is_type( 'variation' ) ? wc_get_product_variation_attributes( $_product->get_id() ) : false;
		$categories     = get_the_terms( $_product->get_id(), 'product_cat' );

		if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
			$parent_product = wc_get_product( $_product->get_parent_id() );
			$categories     = get_the_terms( $parent_product->get_id(), 'product_cat' );
		}

		if ( $categories ) {
			foreach ( $categories as $category ) {
				$out[] = $category->name;
			}
		}

		return "'" . esc_js( join( '/', $out ) ) . "',";
	}

	/**
	 * Returns a 'variant' JSON line based on $product
	 *
	 * @param  WC_Product $_product  Product to pull info for
	 * @return string                Line of JSON
	 */
	public static function product_get_variant_line( $_product ) {
		$out            = '';
		$variation_data = $_product->is_type( 'variation' ) ? wc_get_product_variation_attributes( $_product->get_id() ) : false;

		if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
			$out = "'" . esc_js( wc_get_formatted_variation( $variation_data, true ) ) . "',";
		}

		return $out;
	}

	/**
	 * Echo JavaScript to track an enhanced ecommerce remove from cart action
	 */
	abstract public function remove_from_cart();

	/**
	 * Enqueue JavaScript to track a product detail view
	 *
	 * @param WC_Product $product
	 */
	abstract public function product_detail( $product );

	/**
	 * Enqueue JS to track when the checkout process is started
	 *
	 * @param array $cart items/contents of the cart
	 */
	abstract public function checkout_process( $cart );

	/**
	 * Enqueue JavaScript for Add to cart tracking
	 *
	 * @param array  $parameters associative array of _trackEvent parameters
	 * @param string $selector jQuery selector for binding click event
	 */
	abstract public function event_tracking_code( $parameters, $selector );
}
