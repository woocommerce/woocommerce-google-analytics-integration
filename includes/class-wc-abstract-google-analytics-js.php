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
	 * Constructor
	 * To be called from child classes to setup event data
	 *
	 * @return void
	 */
	public function __construct() {
		$this->attach_event_data();
	}

	/**
	 * Hook into various parts of WooCommerce and set the relevant
	 * script data that the frontend tracking script will use.
	 *
	 * @return void
	 */
	public function attach_event_data() {
		add_action(
			'woocommerce_before_checkout_form',
			function() {
				$this->set_script_data( 'cart', $this->get_formatted_cart() );
			}
		);

		add_action(
			'woocommerce_before_single_product',
			function() {
				global $product;
				$this->set_script_data( 'single', $this->get_formatted_product( $product ) );
			}
		);

		add_action(
			'woocommerce_shop_loop_item_title',
			function() {
				global $product;
				$this->set_script_data( 'products', $this->get_formatted_product( $product ), $product->get_id() );
			}
		);
	}

	/**
	 * Return one of our options
	 *
	 * @param string $option Key/name for the option.
	 *
	 * @return string|null Value of the option or null if not found
	 */
	protected static function get( $option ) {
		return self::$options[ $option ] ?? null;
	}

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
	 * Get item identifier from product data
	 *
	 * @param  WC_Product $product WC_Product Object.
	 * @return string
	 */
	public static function get_product_identifier( $product ) {
		$identifier = $product->get_id();

		if ( 'product_sku' === self::get( 'ga_product_identifier' ) ) {
			if ( ! empty( $product->get_sku() ) ) {
				$identifier = $product->get_sku();
			} else {
				$identifier = '#' . ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() );
			}
		}

		return apply_filters( 'woocommerce_ga_product_identifier', $identifier, $product );
	}

	/**
	 * Returns an array of cart data in the required format
	 *
	 * @return array
	 */
	public function get_formatted_cart(): array {
		return array(
			'items'   => array_map(
				function( $item ) {
					return array(
						'id'       => $this->get_product_identifier( $item['data'] ),
						'name'     => $item['data']->get_name(),
						'quantity' => $item['quantity'],
						'prices'   => array(
							'price'               => $item['data']->get_price(),
							'currency_minor_unit' => wc_get_price_decimals(),
						),
					);
				},
				WC()->cart->get_cart()
			),
			'coupons' => WC()->cart->get_coupons(),
			'totals'  => array(
				'currency_code'       => get_woocommerce_currency(),
				'total_price'         => WC()->cart->get_total( 'edit' ),
				'currency_minor_unit' => wc_get_price_decimals(),
			),
		);
	}

	/**
	 * Returns an array of product data in the required format
	 *
	 * @param WC_Product $product The product to format.
	 *
	 * @return array
	 */
	public function get_formatted_product( WC_Product $product ): array {
		return array(
			'item_id'    => $this->get_product_identifier( $product ),
			'item_name'  => $product->get_name(),
			'categories' => wc_get_product_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) ),
			'prices'     => array(
				'price'               => $product->get_price(),
				'currency_minor_unit' => wc_get_price_decimals(),
			),
		);
	}

	/**
	 * Returns the tracker variable this integration should use
	 *
	 * @return string
	 */
	abstract public static function tracker_var(): string;

	/**
	 * Add an event to the script data
	 *
	 * @param string $type The type of event being added.
	 * @param string $data Event to add.
	 * @param mixed  $key  Key to use for the data.
	 *
	 * @return void
	 */
	abstract public function set_script_data( string $type, $data, $key = false );

	/**
	 * Get the class instance
	 *
	 * @param  array $options Options
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	abstract public static function get_instance( $options = array() ): WC_Abstract_Google_Analytics_JS;
}
