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

	/**
	 * Get the class instance
	 */
	abstract public static function get_instance( $options = array() );

	/**
	 * Return one of our options
	 * @param  string $option Key/name for the option
	 * @return string         Value of the option
	 */
	abstract public static function get( $option );

	/**
	 * Returns the tracker variable this integration should use
	 */
	abstract public static function tracker_var();

	/**
	 * Generic GA / header snippet for opt out
	 */
	public static function header() {
		return "<script type='text/javascript'>
			var gaProperty = '" . esc_js( self::get( 'ga_id' ) ) . "';
			var disableStr = 'ga-disable-' + gaProperty;
			if ( document.cookie.indexOf( disableStr + '=true' ) > -1 ) {
				window[disableStr] = true;
			}
			function gaOptout() {
				document.cookie = disableStr + '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/';
				window[disableStr] = true;
			}
		</script>";
	}

	/**
	 * Builds the addImpression object
	 */
	abstract public static function listing_impression( $product, $position );

	/**
	 * Builds an addProduct and click object
	 */
	abstract public static function listing_click( $product, $position ) ;

	/**
	 * Loads the correct Google Gtag code (classic or universal)
	 * @param  boolean $order Classic analytics needs order data to set the currency correctly
	 * @return string         Gtag loading code
	 */
	abstract public static function load_analytics( $order = false );

	/**
	 * Used to pass transaction data to Google Gtag
	 * @param object $order WC_Order Object
	 * @return string Add Transaction code
	 */
	public function add_transaction( $order ) {
		if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) ) {
			return self::add_transaction_enhanced( $order );
		} else {
			return self::add_transaction_universal( $order );
		}
	}

	/**
	 * Enhanced Gtag transaction tracking
	 * @param object $order WC_Order object
	 * @return string Add Transaction Code
	 */
	abstract public function add_transaction_enhanced( $order );

	/**
	 * Add Item (Universal)
	 * @param object $order WC_Order Object
	 * @param array $item  The item to add to a transaction/order
	 */
	public function add_item_universal( $order, $item ) {
		$_product = version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_product_from_item( $item ) : $item->get_product();

		$code = "ga('ecommerce:addItem', {";
		$code .= "'id': '" . esc_js( $order->get_order_number() ) . "',";
		$code .= "'name': '" . esc_js( $item['name'] ) . "',";
		$code .= "'sku': '" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->get_id() ) . "',";
		$code .= "'category': " . self::product_get_category_line( $_product );
		$code .= "'price': '" . esc_js( $order->get_item_total( $item ) ) . "',";
		$code .= "'quantity': '" . esc_js( $item['qty'] ) . "'";
		$code .= "});";

		return $code;
	}

	/**
	 * Universal Gtag transaction tracking
	 * @param object $order WC_Order object
	 * @return string Add Transaction Code
	 */
	public function add_transaction_universal( $order ) {
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
	 * @param  object $product  Product to pull info for
	 * @return string          Line of JSON
	 */
	protected static function product_get_category_line( $_product ) {
		$out            = array();
		$variation_data = version_compare( WC_VERSION, '3.0', '<' ) ? $_product->variation_data : ( $_product->is_type( 'variation' ) ? wc_get_product_variation_attributes( $_product->get_id() ) : '' );
		$categories     = get_the_terms( $_product->get_id(), 'product_cat' );

		if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
			$parent_product = wc_get_product( version_compare( WC_VERSION, '3.0', '<' ) ? $_product->parent->id : $_product->get_parent_id() );
			$categories = get_the_terms( $parent_product->get_id(), 'product_cat' );
		}

		if ( $categories ) {
			foreach ( $categories as $category ) {
				$out[] = $category->name;
			}
		}

		return "'" . esc_js( join( "/", $out ) ) . "',";
	}

	/**
	 * Returns a 'variant' JSON line based on $product
	 * @param  object $product  Product to pull info for
	 * @return string          Line of JSON
	 */
	private static function product_get_variant_line( $_product ) {
		$out            = '';
		$variation_data = version_compare( WC_VERSION, '3.0', '<' ) ? $_product->variation_data : ( $_product->is_type( 'variation' ) ? wc_get_product_variation_attributes( $_product->get_id() ) : '' );

		if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
			$out = "'" . esc_js( wc_get_formatted_variation( $variation_data, true ) ) . "',";
		}

		return $out;
	}

	/**
	 * Tracks an enhanced ecommerce remove from cart action
	 */
	abstract public function remove_from_cart();

	/**
	 * Tracks a product detail view
	 */
	abstract public function product_detail( $product );

	/**
	 * Tracks when the checkout process is started
	 */
	abstract public function checkout_process( $cart );

	/**
	 * Add to cart
	 *
	 * @param array $parameters associative array of _trackEvent parameters
	 * @param string $selector jQuery selector for binding click event
	 *
	 * @return void
	 */
	abstract public function event_tracking_code( $parameters, $selector );
}
