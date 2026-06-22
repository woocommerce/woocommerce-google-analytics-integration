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
	protected $settings = array();

	/** @var string Developer ID */
	public const DEVELOPER_ID = 'dOGY3NW';

	/** @var string WC session key used to carry add_to_cart event data across an add-to-cart redirect. */
	protected const PENDING_ADDED_TO_CART_SESSION_KEY = '_ga_pending_added_to_cart';

	/** @var array|null Formatted product data captured during the current request's woocommerce_add_to_cart action. */
	protected $pending_added_to_cart = null;

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
				if ( $product instanceof WC_Product ) {
					$this->set_script_data( 'product', $this->get_formatted_product( $product ) );
				}
			}
		);

		add_action( 'woocommerce_add_to_cart', array( $this, 'capture_added_to_cart' ), 10, 5 );

		// When WC redirects after a successful add-to-cart, in-memory script data is lost before render.
		// Stash the formatted product in the WC session so the next request can re-emit it. Issue #427 / STORMA-42.
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'persist_added_to_cart_for_redirect' ) );
		add_action( 'woocommerce_ajax_added_to_cart', array( $this, 'persist_added_to_cart_for_ajax_redirect' ) );

		// On the redirected page, restore the captured event data and mark add_to_cart for firing.
		add_action( 'wp_head', array( $this, 'restore_added_to_cart_from_session' ), 1 );

		add_action(
			'wp_head',
			function () {
				$this->set_script_data( 'list_name', $this->get_list_name() );
			}
		);

		$product_list_items     = 0;
		$max_product_list_items = absint( apply_filters( 'woocommerce_ga_max_product_list_items', 50 ) );

		add_filter(
			'woocommerce_loop_add_to_cart_link',
			function ( $button, $product ) use ( &$product_list_items, $max_product_list_items ) {
				if ( $product instanceof WC_Product && $product_list_items < $max_product_list_items ) {
					$this->append_script_data( 'products', $this->get_formatted_product( $product ) );
					++$product_list_items;
				}

				return $button;
			},
			10,
			2
		);

		add_action(
			'woocommerce_thankyou',
			function ( $order_id ) {
				if ( 'yes' === $this->get_setting( 'ga_ecommerce_tracking_enabled' ) ) {
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
	 * Returns the product list name for the current page context.
	 * Used to populate item_list_name in view_item_list GA4 events for classic pages.
	 *
	 * @return string
	 */
	public function get_list_name(): string {
		if ( is_shop() ) {
			return __( 'Shop', 'woocommerce-google-analytics-integration' );
		}

		if ( is_product_category() ) {
			return sprintf(
				/* translators: %s: Product category name */
				__( 'Category: %s', 'woocommerce-google-analytics-integration' ),
				single_term_title( '', false )
			);
		}

		if ( is_product_tag() ) {
			return sprintf(
				/* translators: %s: Product tag name */
				__( 'Tag: %s', 'woocommerce-google-analytics-integration' ),
				single_term_title( '', false )
			);
		}

		if ( is_search() ) {
			return __( 'Search Results', 'woocommerce-google-analytics-integration' );
		}

		return __( 'Product List', 'woocommerce-google-analytics-integration' );
	}

	/**
	 * Return one of our settings.
	 *
	 * @param string $setting Key/name for the setting.
	 *
	 * @return mixed|null Value of the setting or null if not found.
	 */
	protected function get_setting( string $setting ) {
		return $this->settings[ $setting ] ?? null;
	}

	/**
	 * Generic GA snippet for opt out.
	 *
	 * @return void
	 */
	public function load_opt_out_script(): void {
		$code = "
			var gaProperty = '" . esc_js( $this->get_setting( 'ga_id' ) ) . "';
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
	 * Compatibility wrapper for the formerly static opt-out loader.
	 *
	 * @return void
	 */
	public static function load_opt_out(): void {
		static::get_compatibility_instance()->load_opt_out_script();
	}

	/**
	 * Get item identifier from product data.
	 *
	 * @param WC_Product $product WC_Product Object.
	 *
	 * @return string
	 */
	public function get_product_identifier_for_product( WC_Product $product ): string {
		$identifier = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

		if ( 'product_sku' === $this->get_setting( 'ga_product_identifier' ) ) {
			if ( ! empty( $product->get_sku() ) ) {
				$identifier = $product->get_sku();
			} else {
				$identifier = '#' . ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() );
			}
		}

		return apply_filters( 'woocommerce_ga_product_identifier', $identifier, $product );
	}

	/**
	 * Compatibility wrapper for the formerly static product identifier formatter.
	 *
	 * @param WC_Product $product WC_Product Object.
	 *
	 * @return string
	 */
	public static function get_product_identifier( WC_Product $product ): string {
		return static::get_compatibility_instance()->get_product_identifier_for_product( $product );
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

		$items = array();
		foreach ( $cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'] ?? null;
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$items[] = array_merge(
				$this->get_formatted_product( $product ),
				array(
					'key'      => $cart_item_key,
					'quantity' => $item['quantity'],
					'prices'   => array(
						'price'               => $this->get_formatted_price( $item['line_total'] ),
						'currency_minor_unit' => wc_get_price_decimals(),
					),
				)
			);
		}

		return array(
			'items'   => $items,
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
	 * Returns an empty array when the product cannot be resolved. Several
	 * callers pass a value that is not guaranteed to be a WC_Product (e.g. a
	 * deleted product, or a product whose type class is not registered). The
	 * argument is intentionally untyped so that such values degrade gracefully
	 * instead of fataling the front-end render with a TypeError on PHP 8.
	 *
	 * @param WC_Product|false|null $product   The product to format.
	 * @param int                   $variation_id Variation product ID.
	 * @param array|bool            $variation An array containing product variation attributes to include in the product data.
	 *                              For the "variation" type products, we'll use product->get_attributes.
	 * @param bool|int              $quantity  Quantity to include in the formatted product object
	 *
	 * @return array
	 */
	public function get_formatted_product( $product, $variation_id = 0, $variation = false, $quantity = false ): array {
		if ( ! $product instanceof WC_Product ) {
			return array();
		}

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
			'categories' => $this->get_formatted_product_categories( $product_id ),
			'prices'     => array(
				'price'               => $this->get_formatted_price( $price ),
				'currency_minor_unit' => wc_get_price_decimals(),
			),
			'extensions' => array(
				'woocommerce_google_analytics_integration' => array(
					'identifier' => $this->get_product_identifier_for_product( $product ),
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
	 * Return product categories with assigned terms expanded into parent-first hierarchy order.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return array
	 */
	private function get_formatted_product_categories( int $product_id ): array {
		$assigned_terms = wc_get_product_terms(
			$product_id,
			'product_cat',
			array(
				'orderby' => 'name',
				'order'   => 'ASC',
			)
		);

		if ( ! is_array( $assigned_terms ) ) {
			return array();
		}

		$category_paths = array_map(
			function ( $term ) {
				$ancestors = array_reverse( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) );
				$path      = array();

				foreach ( $ancestors as $ancestor_id ) {
					$ancestor = get_term( $ancestor_id, 'product_cat' );

					if ( $ancestor && ! is_wp_error( $ancestor ) ) {
						$path[] = $ancestor;
					}
				}

				$path[] = $term;

				// GA4 supports at most 5 category levels. When the path is deeper, keep the
				// most specific terms (including the assigned leaf) instead of the topmost ancestors.
				return array_slice( $path, -5 );
			},
			$assigned_terms
		);

		usort(
			$category_paths,
			function ( $left, $right ) {
				return strnatcasecmp(
					implode( '/', wp_list_pluck( $left, 'name' ) ),
					implode( '/', wp_list_pluck( $right, 'name' ) )
				);
			}
		);

		$categories = array();
		$seen_terms = array();

		foreach ( $category_paths as $path ) {
			foreach ( $path as $category ) {
				if ( isset( $seen_terms[ $category->term_id ] ) ) {
					continue;
				}

				$categories[]                     = array( 'name' => $category->name );
				$seen_terms[ $category->term_id ] = true;

				if ( 5 === count( $categories ) ) {
					break 2;
				}
			}
		}

		return $categories;
	}

	/**
	 * Capture the product added to the cart so it can either be rendered now
	 * (no redirect) or persisted into the WC session (if WC will redirect).
	 *
	 * @param string     $cart_item_key Cart item key.
	 * @param int        $product_id    Product ID being added.
	 * @param int|string $quantity      Quantity added.
	 * @param int        $variation_id  Variation ID (0 if not a variation).
	 * @param array      $variation     Variation attributes.
	 *
	 * @return void
	 */
	public function capture_added_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation ): void {
		$cart_item = null;
		if ( WC()->cart && isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
			$cart_item = WC()->cart->cart_contents[ $cart_item_key ];
		}

		$product = $cart_item['data'] ?? wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$formatted = $this->get_formatted_product( $product, $variation_id, $variation, $quantity );

		if ( null === $this->pending_added_to_cart ) {
			$this->set_script_data( 'added_to_cart', $formatted );
			$this->pending_added_to_cart = $formatted;
			return;
		}

		$is_grouped_capture = isset( $this->pending_added_to_cart[0] ) && is_array( $this->pending_added_to_cart[0] );
		$products           = $is_grouped_capture
			? $this->pending_added_to_cart
			: array( $this->pending_added_to_cart );
		$products[]         = $formatted;

		$this->set_script_data( 'added_to_cart', $products );
		$this->pending_added_to_cart = $products;
	}

	/**
	 * Persist the captured `added_to_cart` payload into the WC session when a
	 * classic add-to-cart request is going to redirect. The next page request
	 * will pick it up via `restore_added_to_cart_from_session()` and fire the
	 * event.
	 *
	 * @param string|false $url Redirect URL (returned unchanged).
	 *
	 * @return string|false
	 */
	public function persist_added_to_cart_for_redirect( $url ) {
		if ( $url || 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
			$this->persist_pending_added_to_cart();
		}
		return $url;
	}

	/**
	 * Persist the captured `added_to_cart` payload when WooCommerce's legacy
	 * AJAX add-to-cart handler will redirect the browser before it fires the
	 * `added_to_cart` JavaScript event.
	 *
	 * @return void
	 */
	public function persist_added_to_cart_for_ajax_redirect(): void {
		if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
			$this->persist_pending_added_to_cart();
		}
	}

	/**
	 * Persist the pending add-to-cart payload into the WC session.
	 *
	 * @return void
	 */
	private function persist_pending_added_to_cart(): void {
		if ( $this->pending_added_to_cart && WC()->session ) {
			WC()->session->set( self::PENDING_ADDED_TO_CART_SESSION_KEY, $this->pending_added_to_cart );
		}
	}

	/**
	 * Restore an `added_to_cart` payload that was stashed before a redirect,
	 * append `add_to_cart` to the events array, and consume the session key
	 * so the event only fires once.
	 *
	 * @return void
	 */
	public function restore_added_to_cart_from_session(): void {
		if ( ! WC()->session ) {
			return;
		}
		$pending = WC()->session->get( self::PENDING_ADDED_TO_CART_SESSION_KEY );
		if ( ! $pending ) {
			return;
		}
		$this->set_script_data( 'added_to_cart', $pending );
		$this->append_script_data( 'events', 'add_to_cart' );
		WC()->session->__unset( self::PENDING_ADDED_TO_CART_SESSION_KEY );
	}

	/**
	 * Returns an array of order data in the required format
	 *
	 * @param WC_Abstract_Order $order An instance of the WooCommerce Order object.
	 *
	 * @return array
	 */
	public function get_formatted_order( $order ): array {
		/**
		 * Filter the order identifier sent to Google Analytics as `transaction_id`.
		 *
		 * Defaults to `WC_Abstract_Order::get_order_number()`, which honors sequential
		 * and custom order number plugins via the `woocommerce_order_number` filter.
		 *
		 * @param string            $order_id Order identifier sent to GA.
		 * @param WC_Abstract_Order $order    Order being formatted.
		 */
		$order_id = apply_filters( 'woocommerce_ga_order_id', $order->get_order_number(), $order );

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$items[] = array_merge(
				$this->get_formatted_product( $product ),
				array(
					'quantity'                    => $item->get_quantity(),
					// The method get_total() will return the price after coupon discounts.
					// https://github.com/woocommerce/woocommerce/blob/54eba223b8dec015c91a13423f9eced09e96f399/plugins/woocommerce/includes/class-wc-order-item-product.php#L308-L310
					'price_after_coupon_discount' => $this->get_formatted_price( $item->get_total() ),
				)
			);
		}

		return array(
			'id'          => $order_id,
			'affiliation' => get_bloginfo( 'name' ),
			'totals'      => array(
				'currency_code'       => $order->get_currency(),
				'currency_minor_unit' => wc_get_price_decimals(),
				'tax_total'           => $this->get_formatted_price( $order->get_total_tax() ),
				'shipping_total'      => $this->get_formatted_price( $order->get_total_shipping() ),
				'total_price'         => $this->get_formatted_price( $order->get_total() ),
			),
			'items'       => $items,
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
				wc_add_number_precision( (float) wc_format_decimal( $value ), false ),
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
			'identifier' => (string) $this->get_product_identifier_for_product( $product ),
		);
	}

	/**
	 * Return an instance for the static compatibility wrappers to read settings from.
	 *
	 * If a tracking instance has already been bootstrapped, reuse it. Otherwise build a
	 * lightweight, settings-only instance without invoking the constructor, so reading a
	 * setting never registers scripts/hooks, enqueues anything, or installs a live
	 * singleton. This mirrors the pre-refactor static helpers, which read settings with
	 * no side effects even when the integration skipped get_tracking_instance() (e.g.
	 * `ga_id` is unset).
	 *
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	protected static function get_compatibility_instance(): WC_Abstract_Google_Analytics_JS {
		if ( static::$instance ) {
			return static::$instance;
		}

		return ( new \ReflectionClass( static::class ) )->newInstanceWithoutConstructor();
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
