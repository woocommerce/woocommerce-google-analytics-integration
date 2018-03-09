<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Google_Gtag_JS class
 *
 * JS for recording Google Gtag info
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
	abstract public static function header();

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
	abstract public function add_transaction( $order );

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
