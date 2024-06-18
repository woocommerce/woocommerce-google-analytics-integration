<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;

/**
 * WC_Abstract_Google_Analytics_JS class
 *
 * Abstract JS for recording Google Analytics/Gtag info
 */
abstract class WC_Abstract_Google_Analytics_JS {

	/** @var WC_Abstract_Google_Analytics_JS $instance Class Instance */
	protected static $instance;

	/** @var array $settings Inherited Analytics settings */
	protected static $settings;

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

		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => ProductSchema::IDENTIFIER,
					'namespace'       => 'woocommerce_google_analytics_integration',
					'data_callback'   => array( $this, 'data_callback' ),
					'schema_callback' => array( $this, 'schema_callback' ),
					'schema_type'     => ARRAY_A,
				)
			);

			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => CartItemSchema::IDENTIFIER,
					'namespace'       => 'woocommerce_google_analytics_integration',
					'data_callback'   => array( $this, 'data_callback' ),
					'schema_callback' => array( $this, 'schema_callback' ),
					'schema_type'     => ARRAY_A,
				)
			);
		}
	}

	/**
	 * Hook into various parts of WooCommerce and set the relevant
	 * script data that the frontend tracking script will use.
	 *
	 * @return void
	 */
	public function attach_event_data(): void {
		add_action(
			'wp_head',
			function () {
				$this->set_script_data( 'cart', $this->get_formatted_cart() );
			}
		);

		add_action(
			'woocommerce_before_single_product',
			function () {
				global $product;
				$this->set_script_data( 'product', $this->get_formatted_product( $product ) );
			}
		);

		add_action(
			'woocommerce_add_to_cart',
			function ( $cart_item_key, $product_id, $quantity, $variation_id, $variation ) {
				$this->set_script_data( 'added_to_cart', $this->get_formatted_product( wc_get_product( $product_id ), $variation_id, $variation, $quantity ) );
			},
			10,
			5
		);

		add_filter(
			'woocommerce_loop_add_to_cart_link',
			function ( $button, $product ) {
				$this->append_script_data( 'products', $this->get_formatted_product( $product ) );
				return $button;
			},
			10,
			2
		);

		add_action(
			'woocommerce_thankyou',
			function ( $order_id ) {
				if ( 'yes' === self::get( 'ga_ecommerce_tracking_enabled' ) ) {
					$order = wc_get_order( $order_id );
					if ( $order && $order->get_meta( '_ga_tracked' ) !== '1' ) {
						// Check order key.
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
						$order_key = empty( $_GET['key'] ) ? '' : wc_clean( wp_unslash( $_GET['key'] ) );
						if ( $order->key_is_valid( $order_key ) ) {
							// Mark the order as tracked.
							$order->update_meta_data( '_ga_tracked', 1 );
							$order->save();

							$this->set_script_data( 'order', $this->get_formatted_order( $order ) );
						}
					}
				}
			}
		);
	}

	/**
	 * Return one of our settings
	 *
	 * @param string $setting Key/name for the setting.
	 *
	 * @return string|null Value of the setting or null if not found
	 */
	protected static function get( $setting ): ?string {
		return self::$settings[ $setting ] ?? null;
	}

	/**
	 * Generic GA snippet for opt out
	 */
	public static function load_opt_out(): void {
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
	 * @param WC_Product $product WC_Product Object.
	 *
	 * @return string
	 */
	public static function get_product_identifier( WC_Product $product ): string {
		$identifier = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

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
		$cart = WC()->cart;

		if ( is_null( $cart ) ) {
			return array();
		}

		return array(
			'items'   => array_map(
				function ( $item ) {
					return array_merge(
						$this->get_formatted_product( $item['data'] ),
						array(
							'quantity' => $item['quantity'],
							'prices'   => array(
								'price'               => $this->get_formatted_price( $item['line_total'] ),
								'currency_minor_unit' => wc_get_price_decimals(),
							),
						)
					);
				},
				array_values( $cart->get_cart() )
			),
			'coupons' => $cart->get_coupons(),
			'totals'  => array(
				'currency_code'       => get_woocommerce_currency(),
				'total_price'         => $this->get_formatted_price( $cart->get_total( 'edit' ) ),
				'currency_minor_unit' => wc_get_price_decimals(),
			),
		);
	}

	/**
	 * Returns an array of product data in the required format
	 *
	 * @param WC_Product $product   The product to format.
	 * @param int        $variation_id Variation product ID.
	 * @param array|bool $variation An array containing product variation attributes to include in the product data.
	 *                              For the "variation" type products, we'll use product->get_attributes.
	 * @param bool|int   $quantity  Quantity to include in the formatted product object
	 *
	 * @return array
	 */
	public function get_formatted_product( WC_Product $product, $variation_id = 0, $variation = false, $quantity = false ): array {
		$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$price      = $product->get_price();

		// Get product price from chosen variation if set.
		if ( $variation_id ) {
			$variation_product = wc_get_product( $variation_id );
			if ( $variation_product ) {
				$price = $variation_product->get_price();
			}
		}

		// Integration with Product Bundles.
		// Get the minimum price, as `get_price` may return 0 if the product is a bundle and the price is potentially a range.
		// Even a range containing a single value.
		if ( $product->is_type( 'bundle' ) && is_callable( [ $product, 'get_bundle_price' ] ) ) {
			$price = $product->get_bundle_price( 'min' );
		}

		$formatted = array(
			'id'         => $product_id,
			'name'       => $product->get_title(),
			'categories' => array_map(
				fn( $category ) => array( 'name' => $category->name ),
				wc_get_product_terms( $product_id, 'product_cat', array( 'number' => 5 ) )
			),
			'prices'     => array(
				'price'               => $this->get_formatted_price( $price ),
				'currency_minor_unit' => wc_get_price_decimals(),
			),
			'extensions' => array(
				'woocommerce_google_analytics_integration' => array(
					'identifier' => $this->get_product_identifier( $product ),
				),
			),
		);

		if ( $quantity ) {
			$formatted['quantity'] = (int) $quantity;
		}

		if ( $product->is_type( 'variation' ) ) {
			$variation = $product->get_attributes();
		}

		if ( is_array( $variation ) ) {
			$formatted['variation'] = implode(
				', ',
				array_map(
					function ( $attribute, $value ) {
						return sprintf(
							'%s: %s',
							str_replace( 'attribute_', '', $attribute ),
							$value
						);
					},
					array_keys( $variation ),
					array_values( $variation )
				)
			);
		}

		return $formatted;
	}

	/**
	 * Returns an array of order data in the required format
	 *
	 * @param WC_Abstract_Order $order An instance of the WooCommerce Order object.
	 *
	 * @return array
	 */
	public function get_formatted_order( $order ): array {
		return array(
			'id'          => $order->get_id(),
			'affiliation' => get_bloginfo( 'name' ),
			'totals'      => array(
				'currency_code'       => $order->get_currency(),
				'currency_minor_unit' => wc_get_price_decimals(),
				'tax_total'           => $this->get_formatted_price( $order->get_total_tax() ),
				'shipping_total'      => $this->get_formatted_price( $order->get_total_shipping() ),
				'total_price'         => $this->get_formatted_price( $order->get_total() ),
			),
			'items'       => array_map(
				function ( $item ) {
					return array_merge(
						$this->get_formatted_product( $item->get_product() ),
						array(
							'quantity'                    => $item->get_quantity(),
							// The method get_total() will return the price after coupon discounts.
							// https://github.com/woocommerce/woocommerce/blob/54eba223b8dec015c91a13423f9eced09e96f399/plugins/woocommerce/includes/class-wc-order-item-product.php#L308-L310
							'price_after_coupon_discount' => $this->get_formatted_price( $item->get_total() ),
						)
					);
				},
				array_values( $order->get_items() ),
			),
		);
	}

	/**
	 * Formats a price the same way WooCommerce Blocks does
	 *
	 * @param mixed $value The price value for format
	 *
	 * @return int
	 */
	public function get_formatted_price( $value ): int {
		return intval(
			round(
				( (float) wc_format_decimal( $value ) ) * ( 10 ** absint( wc_get_price_decimals() ) ),
				0
			)
		);
	}

	/**
	 * Add product identifier to StoreAPI
	 *
	 * @param WC_Product|array $product Either an instance of WC_Product or a cart item array depending on the endpoint
	 *
	 * @return array
	 */
	public function data_callback( $product ): array {
		$product = is_a( $product, 'WC_Product' ) ? $product : $product['data'];

		return array(
			'identifier' => (string) $this->get_product_identifier( $product ),
		);
	}

	/**
	 * Schema for the extended StoreAPI data
	 *
	 * @return array
	 */
	public function schema_callback(): array {
		return array(
			'identifier' => array(
				'description' => __( 'The formatted product identifier to use in Google Analytics events.', 'woocommerce-google-analytics-integration' ),
				'type'        => 'string',
				'readonly'    => true,
			),
		);
	}

	/**
	 * Returns the tracker variable this integration should use
	 *
	 * @return string
	 */
	abstract public static function tracker_function_name(): string;

	/**
	 * Add an event to the script data
	 *
	 * @param string       $type The type of event this data is related to.
	 * @param string|array $data The event data to add.
	 *
	 * @return void
	 */
	abstract public function set_script_data( string $type, $data ): void;

	/**
	 * Append data to an existing script data array
	 *
	 * @param string       $type The type of event this data is related to.
	 * @param string|array $data The event data to add.
	 *
	 * @return void
	 */
	abstract public function append_script_data( string $type, $data ): void;

	/**
	 * Get the class instance
	 *
	 * @param  array $settings Settings
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	abstract public static function get_instance( $settings = array() ): WC_Abstract_Google_Analytics_JS;
}
