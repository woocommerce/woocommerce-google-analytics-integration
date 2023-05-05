<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Google_Analytics_Integration as Plugin;

/**
 * WC_Google_Gtag_JS class
 *
 * JS for recording Google Gtag info
 */
class WC_Google_Gtag_JS extends WC_Abstract_Google_Analytics_JS {

	/** @var string $script_handle Handle for the front end JavaScript file */
	public $script_handle = 'woocommerce-google-analytics-integration';

	/**
	 * Get the class instance
	 *
	 * @param array $options Options
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	public static function get_instance( $options = array() ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $options );
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 * Takes our options from the parent class so we can later use them in the JS snippets
	 *
	 * @param array $options Options
	 */
	public function __construct( $options = array() ) {
		self::$options = $options;
		// Setup frontend scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'woocommerce_before_single_product', array( $this, 'setup_frontend_scripts' ) );
	}

	/**
	 * Enqueue the frontend scripts and make formatted variant data available via filter
	 *
	 * @return void
	 */
	public function setup_frontend_scripts() {
		global $product;

		if ( $product instanceof WC_Product_Variable ) {
			// Filter variation data to include formatted strings required for add_to_cart event
			add_filter( 'woocommerce_available_variation', array( $this, 'variant_data' ), 10, 3 );
			// Add default inline product data for add to cart tracking
			wp_enqueue_script( $this->script_handle . '-ga-integration' );
		}
	}

	/**
	 * Register front end JavaScript
	 */
	public function register_scripts() {
		wp_register_script(
			$this->script_handle . '-ga-integration',
			Plugin::get_instance()->get_js_asset_url( 'ga-integration.js' ),
			Plugin::get_instance()->get_js_asset_dependencies( 'ga-integration', [ 'jquery' ] ),
			Plugin::get_instance()->get_js_asset_version( 'ga-integration' ),
			true
		);
		wp_enqueue_script(
			$this->script_handle . '-actions',
			Plugin::get_instance()->get_js_asset_url( 'actions.js' ),
			Plugin::get_instance()->get_js_asset_dependencies( 'actions' ),
			Plugin::get_instance()->get_js_asset_version( 'actions' ),
			true
		);
	}

	/**
	 * Returns the tracker variable this integration should use
	 *
	 * @return string
	 */
	public static function tracker_var() {
		return apply_filters( 'woocommerce_gtag_tracker_variable', 'gtag' );
	}

	/**
	 * Add formatted id and variant to variable product data
	 *
	 * @param array                $data Data accessible via `found_variation` trigger
	 * @param WC_Product_Variable  $product
	 * @param WC_Product_Variation $variation
	 * @return array
	 */
	public function variant_data( $data, $product, $variation ) {
		$data['google_analytics_integration'] = array(
			'id'      => self::get_product_identifier( $variation ),
			'variant' => substr( self::product_get_variant_line( $variation ), 1, -2 ),
		);

		return $data;
	}

	/**
	 * Returns Javascript string for Google Analytics events
	 *
	 * @param string       $event The type of event
	 * @param array|string $data  Event data to be sent. If $data is an array then it will be filtered, escaped, and encoded
	 * @return string
	 */
	public static function get_event_code( string $event, $data ): string {
		return sprintf( "%s('event', '%s', %s)", self::tracker_var(), esc_js( $event ), ( is_array( $data ) ? self::format_event_data( $data ) : $data ) );
	}

	/**
	 * Escape and encode event data
	 *
	 * @param array $data Event data to processed and formatted
	 * @return string
	 */
	public static function format_event_data( array $data ): string {
		$data = apply_filters( 'woocommerce_gtag_event_data', $data );

		// Recursively walk through $data array and escape all values that will be used in JS.
		array_walk_recursive(
			$data,
			function( &$value, $key ) {
				$value = esc_js( $value );
			}
		);

		return wp_json_encode( $data );
	}

	/**
	 * Returns a list of category names the product is atttributed to
	 *
	 * @param  WC_Product $product Product to generate category line for
	 * @return string
	 */
	public static function product_get_category_line( $product ) {
		$category_names = array();
		$categories     = get_the_terms( $product->get_id(), 'product_cat' );

		$variation_data = $product->is_type( 'variation' ) ? wc_get_product_variation_attributes( $product->get_id() ) : false;
		if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
			$categories = get_the_terms( $product->get_parent_id(), 'product_cat' );
		}

		if ( false !== $categories && ! is_wp_error( $categories ) ) {
			foreach ( $categories as $category ) {
				$category_names[] = $category->name;
			}
		}

		return join( '/', $category_names );
	}

	/**
	 * Return list name for event
	 *
	 * @return string
	 */
	public static function get_list_name(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['s'] ) ? __( 'Search Results', 'woocommerce-google-analytics-integration' ) : __( 'Product List', 'woocommerce-google-analytics-integration' );
	}

	/**
	 * Enqueues JavaScript to build the view_item_list event
	 *
	 * @param WC_Product $product
	 * @param int        $position
	 */
	public static function listing_impression( $product, $position ) {
		$event_code = self::get_event_code(
			'view_item_list',
			array(
				'items' => array(
					array(
						'id'            => self::get_product_identifier( $product ),
						'name'          => $product->get_title(),
						'category'      => self::product_get_category_line( $product ),
						'list'          => self::get_list_name(),
						'list_position' => $position,
					),
				),
			)
		);

		wc_enqueue_js( $event_code );
	}

	/**
	 * Enqueues JavaScript for select_content and add_to_cart events for the product archive
	 *
	 * @param WC_Product $product
	 * @param int        $position
	 */
	public static function listing_click( $product, $position ) {
		$items = array(
			'id'            => self::get_product_identifier( $product ),
			'name'          => $product->get_title(),
			'category'      => self::product_get_category_line( $product ),
			'list_position' => $position,
			'quantity'      => 1,
		);

		$select_content_event_code = self::get_event_code(
			'select_content',
			array(
				'items' => array( $items ),
			)
		);

		$add_to_cart_event_code = self::get_event_code(
			'add_to_cart',
			array(
				'items' => array( $items ),
			)
		);

		wc_enqueue_js(
			"
			$( '.products .post-" . esc_js( $product->get_id() ) . " a' ).on('click', function() {
				if ( true === $(this).hasClass( 'add_to_cart_button' ) ) {
					$add_to_cart_event_code
				} else {
					$select_content_event_code
				}
			});"
		);
	}

	/**
	 * Output Javascript to track add_to_cart event on single product page
	 *
	 * @param WC_Product $product The product currently being viewed
	 */
	public static function add_to_cart( WC_Product $product ) {
		$items = array(
			'id'       => self::get_product_identifier( $product ),
			'name'     => $product->get_title(),
			'category' => self::product_get_category_line( $product ),
			'quantity' => 1,
		);

		// Set item data as Javascript variable so that quantity, variant, and ID can be updated before sending the event
		$event_code = '
			const item_data    = ' . self::format_event_data( $items ) . ';
			item_data.quantity = $("input.qty").val() ? $("input.qty").val() : 1;';

		if ( $product->is_type( 'variable' ) ) {
			// Check the global google_analytics_integration_product_data Javascript variable contains data
			// for the current variation selection and if it does update the item_data to be sent for this event
			$event_code .= "
			const selected_variation = google_analytics_integration_product_data[ $('input[name=\"variation_id\"]').val() ];
			if ( selected_variation !== undefined ) {
				item_data.id       = selected_variation.id;
				item_data.variant  = selected_variation.variant;
			}
			";
		}

		$event_code .= self::get_event_code(
			'add_to_cart',
			'{"items": [item_data]}',
			false
		);

		wc_enqueue_js(
			"$( '.single_add_to_cart_button' ).on('click', function() {
				$event_code
			});"
		);
	}

	/**
	 * Loads the standard Gtag code
	 *
	 * @param WC_Order $order WC_Order Object (not used in this implementation, but mandatory in the abstract class)
	 */
	public static function load_analytics( $order = false ) {
		$logged_in = is_user_logged_in() ? 'yes' : 'no';

		$track_404_enabled = '';
		if ( 'yes' === self::get( 'ga_404_tracking_enabled' ) && is_404() ) {
			// See https://developers.google.com/analytics/devguides/collection/gtagjs/events for reference
			$track_404_enabled = self::tracker_var() . "( 'event', '404_not_found', { 'event_category':'error', 'event_label':'page: ' + document.location.pathname + document.location.search + ' referrer: ' + document.referrer });";
		}

		$gtag_developer_id = '';
		if ( ! empty( self::DEVELOPER_ID ) ) {
			$gtag_developer_id = self::tracker_var() . "('set', 'developer_id." . self::DEVELOPER_ID . "', true);";
		}

		$gtag_id            = self::get( 'ga_id' );
		$gtag_cross_domains = ! empty( self::get( 'ga_linker_cross_domains' ) ) ? array_map( 'esc_js', explode( ',', self::get( 'ga_linker_cross_domains' ) ) ) : array();
		$gtag_snippet       = '
		window.dataLayer = window.dataLayer || [];
		function ' . self::tracker_var() . '(){dataLayer.push(arguments);}
		' . self::tracker_var() . "('js', new Date());
		$gtag_developer_id

		" . self::tracker_var() . "('config', '" . esc_js( $gtag_id ) . "', {
			'allow_google_signals': " . ( 'yes' === self::get( 'ga_support_display_advertising' ) ? 'true' : 'false' ) . ",
			'link_attribution': " . ( 'yes' === self::get( 'ga_support_enhanced_link_attribution' ) ? 'true' : 'false' ) . ",
			'anonymize_ip': " . ( 'yes' === self::get( 'ga_anonymize_enabled' ) ? 'true' : 'false' ) . ",
			'linker':{
				'domains': " . wp_json_encode( $gtag_cross_domains ) . ",
				'allow_incoming': " . ( 'yes' === self::get( 'ga_linker_allow_incoming_enabled' ) ? 'true' : 'false' ) . ",
			},
			'custom_map': {
				'dimension1': 'logged_in'
			},
			'logged_in': '$logged_in'
		} );

		$track_404_enabled
		";

		wp_register_script( 'google-tag-manager', 'https://www.googletagmanager.com/gtag/js?id=' . esc_js( $gtag_id ), array( 'google-analytics-opt-out' ), null, false );
		wp_add_inline_script( 'google-tag-manager', apply_filters( 'woocommerce_gtag_snippet', $gtag_snippet ) );
		wp_enqueue_script( 'google-tag-manager' );
	}

	/**
	 * Generate Gtag transaction tracking code
	 *
	 * @param  WC_Order $order
	 * @return string
	 */
	public function add_transaction_enhanced( $order ) {
		$event_items = array();
		$order_items = $order->get_items();
		if ( ! empty( $order_items ) ) {
			foreach ( $order_items as $item ) {
				$event_items[] = self::add_item( $order, $item );
			}
		}

		return self::get_event_code(
			'purchase',
			array(
				'transaction_id' => $order->get_order_number(),
				'affiliation'    => get_bloginfo( 'name' ),
				'value'          => $order->get_total(),
				'tax'            => $order->get_total_tax(),
				'shipping'       => $order->get_total_shipping(),
				'currency'       => $order->get_currency(),
				'items'          => $event_items,
			)
		);
	}

	/**
	 * Add Item
	 *
	 * @param WC_Order      $order WC_Order Object
	 * @param WC_Order_Item $item  The item to add to a transaction/order
	 */
	public function add_item( $order, $item ) {
		$product = $item->get_product();
		$variant = self::product_get_variant_line( $product );

		$event_item = array(
			'id'       => self::get_product_identifier( $product ),
			'name'     => $item['name'],
			'category' => self::product_get_category_line( $product ),
			'price'    => $order->get_item_total( $item ),
			'quantity' => $item['qty'],
		);

		if ( '' !== $variant ) {
			$event_item['variant'] = $variant;
		}

		return $event_item;
	}

	/**
	 * Output JavaScript to track an enhanced ecommerce remove from cart action
	 */
	public function remove_from_cart() {
		$event_code = self::get_event_code(
			'remove_from_cart',
			'{"items": [{
				"id": $(this).data("product_sku") ? $(this).data("product_sku") : "#" + $(this).data("product_id"),
				"quantity": $(this).parent().parent().find(".qty").val() ? $(this).parent().parent().find(".qty").val() : "1"
			 }]}'
		);

		wc_enqueue_js(
			"$( '.remove' ).off('click', '.remove').on( 'click', function() {
				$event_code
			});"
		);
	}

	/**
	 * Enqueue JavaScript to track a product detail view
	 *
	 * @param WC_Product $product
	 */
	public function product_detail( $product ) {
		if ( empty( $product ) ) {
			return;
		}

		$event_code = self::get_event_code(
			'view_item',
			array(
				'items' => array(
					array(
						'id'       => self::get_product_identifier( $product ),
						'name'     => $product->get_title(),
						'category' => self::product_get_category_line( $product ),
						'price'    => $product->get_price(),
					),
				),
			)
		);

		wc_enqueue_js( $event_code );
	}

	/**
	 * Enqueue JS to track when the checkout process is started
	 *
	 * @param array $cart items/contents of the cart
	 */
	public function checkout_process( $cart ) {
		$items = array();
		foreach ( $cart as $cart_item_key => $cart_item ) {
			$product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			$item_data = array(
				'id'       => self::get_product_identifier( $product ),
				'name'     => $product->get_title(),
				'category' => self::product_get_category_line( $product ),
				'price'    => $product->get_price(),
				'quantity' => $cart_item['quantity'],
			);

			$variant = self::product_get_variant_line( $product );
			if ( '' !== $variant ) {
				$item_data['variant'] = $variant;
			}

			$items[] = $item_data;
		}

		$event_code = self::get_event_code(
			'begin_checkout',
			array(
				'items' => $items,
			)
		);

		wc_enqueue_js( $event_code );
	}

	/**
	 * @deprecated x.x.x
	 *
	 * Enqueue JavaScript for Add to cart tracking
	 *
	 * @param array  $parameters Associative array of _trackEvent parameters
	 * @param string $selector   jQuery selector for binding click event
	 */
	public function event_tracking_code( $parameters, $selector ) {
		wc_deprecated_function( 'event_tracking_code', '1.6.0', 'get_event_code' );

		// Called with invalid 'Add to Cart' action, update to sync with Default Google Analytics Event 'add_to_cart'
		$parameters['action']   = '\'add_to_cart\'';
		$parameters['category'] = '\'ecommerce\'';

		$parameters = apply_filters( 'woocommerce_gtag_event_tracking_parameters', $parameters );

		if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) ) {
			$track_event = sprintf(
				self::tracker_var() . "( 'event', %s, { 'event_category': %s, 'event_label': %s, 'items': [ %s ] } );",
				$parameters['action'],
				$parameters['category'],
				$parameters['label'],
				$parameters['item']
			);
		} else {
			$track_event = sprintf(
				self::tracker_var() . "( 'event', %s, { 'event_category': %s, 'event_label': %s } );",
				$parameters['action'],
				$parameters['category'],
				$parameters['label']
			);
		}

		wc_enqueue_js(
			"
			$( '" . $selector . "' ).on( 'click', function() {
				" . $track_event . '
			});
		'
		);
	}

}
