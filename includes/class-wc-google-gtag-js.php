<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Google_Gtag_JS class
 *
 * JS for recording Google Gtag info
 */
class WC_Google_Gtag_JS extends WC_Abstract_Google_Analytics_JS {

	/** @var object Class Instance */
	private static $instance;

	/** @var array Inherited Gtag options */
	private static $options;

	/**
	 * Get the class instance
	 */
	public static function get_instance( $options = array() ) {
		return null === self::$instance ? ( self::$instance = new self( $options ) ) : self::$instance;
	}

	/**
	 * Constructor
	 * Takes our options from the parent class so we can later use them in the JS snippets
	 */
	public function __construct( $options = array() ) {
		self::$options = $options;
	}

	/**
	 * Return one of our options
	 * @param  string $option Key/name for the option
	 * @return string         Value of the option
	 */
	public static function get( $option ) {
		return self::$options[$option];
	}

	/**
	 * Returns the tracker variable this integration should use
	 */
	public static function tracker_var() {
		return apply_filters( 'woocommerce_gtag_tracker_variable', 'gtag' );
	}

	/**
	 * Builds the addImpression object
	 */
	public static function listing_impression( $product, $position ) {
		if ( isset( $_GET['s'] ) ) {
			$list = "Search Results";
		} else {
			$list = "Product List";
		}

		wc_enqueue_js( "
			" . self::tracker_var() . "( 'event', 'view_item_list', 'items': [ {
				'id': '" . esc_js( $product->get_id() ) . "',
				'name': '" . esc_js( $product->get_title() ) . "',
				'category': " . self::product_get_category_line( $product ) . "
				'list': '" . esc_js( $list ) . "',
				'list_position': '" . esc_js( $position ) . "'
			} ] );
		" );
	}

	/**
	 * Builds an addProduct and click object
	 */
	public static function listing_click( $product, $position ) {
		if ( isset( $_GET['s'] ) ) {
			$list = "Search Results";
		} else {
			$list = "Product List";
		}

		echo( "
			<script>
			(function($) {
				$( '.products .post-" . esc_js( $product->get_id() ) . " a' ).click( function() {
					if ( true === $(this).hasClass( 'add_to_cart_button' ) ) {
						return;
					}

					" . self::tracker_var() . "( 'event', 'select_content', {
						'content_type': 'product',
						'items': [ {
							'id': '" . esc_js( $product->get_id() ) . "',
							'name': '" . esc_js( $product->get_title() ) . "',
							'category': " . self::product_get_category_line( $product ) . "
							'list_position': '" . esc_js( $position ) . "'
						} ],
					} );

					" . self::tracker_var() . "( 'ec:setAction', 'click', { list: '" . esc_js( $list ) . "' });
					" . self::tracker_var() . "( 'event', 'UX', 'click', ' " . esc_js( $list ) . "' );
				});
			})(jQuery);
			</script>
		" );
	}

	/**
	 * Loads the correct Google Gtag code (classic or universal)
	 * @param  boolean $order Classic analytics needs order data to set the currency correctly
	 * @return string         Gtag loading code
	 */
	public static function load_analytics( $order = false ) {
		$logged_in = is_user_logged_in() ? 'yes' : 'no';

		$gtag_id = self::get( 'ga_id' );
		$gtag_snippet_head = "<script async src='https://www.googletagmanager.com/gtag/js?id=" . esc_js( $gtag_id ) . "'></script>";
		$gtag_snipped_head .= "
		<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());

		gtag('config', '" . esc_js( $gtag_id ) . "', {
			'allow_display_features': " . ( 'yes' === self::get( 'ga_support_display_advertising' ) ? 'true' : 'false' ) . ",
			'link_attribution': " . ( 'yes' === self::get( 'ga_support_enhanced_link_attribution' ) ? 'true' : 'false' ) . ",
			'anonymize_ip': " . ( 'yes' === self::get( 'ga_anonymize_enabled' ) ? 'true' : 'false' ) . ",
			'custom_map': {
				'dimension1': " . ( $logged_in ? 'true' : 'false' ) . ",
			},
		} );
		</script>
		";

		$gtag_snippet_head = apply_filters( 'woocommerce_gtag_snippet_head' , $gtag_snippet_head );
		$gtag_snippet_create = apply_filters( 'woocommerce_gtag_snippet_create' , $gtag_snippet_create, $gtag_id );

		$code = $gtag_snippet_head . $gtag_snippet_create;
		$code = apply_filters( 'woocommerce_gtag_snippet_output', $code );

		return $code;
	}

	/**
	 * Enhanced Ecommerce Universal Gtag transaction tracking
	 */
	public function add_transaction_enhanced( $order ) {
		// Order items
		if ( $order->get_items() ) {
			foreach ( $order->get_items() as $item ) {
				$code .= self::add_item_enhanced( $order, $item );
			}
		}

		$code .= "" . self::tracker_var() . "( 'event', 'purchase', {
			'transaction_id': '" . esc_js( $order->get_order_number() ) . "',
			'affiliation': '" . esc_js( get_bloginfo( 'name' ) ) . "',
			'value': '" . esc_js( $order->get_total() ) . "',
			'tax': '" . esc_js( $order->get_total_tax() ) . "',
			'shipping': '" . esc_js( $order->get_total_shipping() ) . "',
			'currency': '" . esc_js( version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_order_currency() : $order->get_currency() ) . "'  // Currency
		} );";

		return $code;
	}

	/**
	 * Add Item (Universal)
	 * @param object $order WC_Order Object
	 * @param array $item  The item to add to a transaction/order
	 */
	public function add_item_universal( $order, $item ) {
		$_product = version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_product_from_item( $item ) : $item->get_product();

		$code = "" . self::tracker_var() . "('ecommerce:addItem', {";
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
	 * Add Item (Enhanced, Universal)
	 * @param object $order WC_Order Object
	 * @param array $item The item to add to a transaction/order
	 */
	public function add_item_enhanced( $order, $item ) {
		$_product = version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_product_from_item( $item ) : $item->get_product();
		$variant  = self::product_get_variant_line( $_product );

		$code = "" . self::tracker_var() . "( 'ec:addProduct', {";
		$code .= "'id': '" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->get_id() ) . "',";
		$code .= "'name': '" . esc_js( $item['name'] ) . "',";
		$code .= "'category': " . self::product_get_category_line( $_product );

		if ( '' !== $variant ) {
			$code .= "'variant': " . $variant;
		}

		$code .= "'price': '" . esc_js( $order->get_item_total( $item ) ) . "',";
		$code .= "'quantity': '" . esc_js( $item['qty'] ) . "'";
		$code .= "});";

		return $code;
	}

	/**
	 * Tracks an enhanced ecommerce remove from cart action
	 */
	public function remove_from_cart() {
		echo( "
			<script>
			(function($) {
				$( '.remove' ).click( function() {
					" . self::tracker_var() . "( 'ec:addProduct', {
						'id': ($(this).data('product_sku')) ? ($(this).data('product_sku')) : ('#' + $(this).data('product_id')),
						'quantity': $(this).parent().parent().find( '.qty' ).val() ? $(this).parent().parent().find( '.qty' ).val() : '1',
					} );
					" . self::tracker_var() . "( 'ec:setAction', 'remove' );
					" . self::tracker_var() . "( 'event', 'UX', 'click', 'remove from cart' );
				});
			})(jQuery);
			</script>
		" );
	}

	/**
	 * Tracks a product detail view
	 */
	public function product_detail( $product ) {
		if ( empty( $product ) ) {
			return;
		}

		wc_enqueue_js( "
			" . self::tracker_var() . "( 'ec:addProduct', {
				'id': '" . esc_js( $product->get_sku() ? $product->get_sku() : ( '#' . $product->get_id() ) ) . "',
				'name': '" . esc_js( $product->get_title() ) . "',
				'category': " . self::product_get_category_line( $product ) . "
				'price': '" . esc_js( $product->get_price() ) . "',
			} );

			" . self::tracker_var() . "( 'ec:setAction', 'detail' );" );
	}

	/**
	 * Tracks when the checkout process is started
	 */
	public function checkout_process( $cart ) {
		$code = "";

		foreach ( $cart as $cart_item_key => $cart_item ) {
			$product     = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$variant     = self::product_get_variant_line( $product );
			$code .= "" . self::tracker_var() . "( 'ec:addProduct', {
				'id': '" . esc_js( $product->get_sku() ? $product->get_sku() : ( '#' . $product->get_id() ) ) . "',
				'name': '" . esc_js( $product->get_title() ) . "',
				'category': " . self::product_get_category_line( $product );

			if ( '' !== $variant ) {
				$code .= "'variant': " . $variant;
			}

			$code .= "'price': '" . esc_js( $product->get_price() ) . "',
				'quantity': '" . esc_js( $cart_item['quantity'] ) . "'
			} );";
		}

		$code .= "" . self::tracker_var() . "( 'ec:setAction','checkout' );";
		wc_enqueue_js( $code );
	}

	/**
	 * Add to cart
	 *
	 * @param array $parameters associative array of _trackEvent parameters
	 * @param string $selector jQuery selector for binding click event
	 *
	 * @return void
	 */
	public function event_tracking_code( $parameters, $selector ) {
		$parameters = apply_filters( 'woocommerce_gtag_event_tracking_parameters', $parameters );

		if ( 'yes' === self::get( 'ga_use_universal_analytics' ) ) {
			if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) ) {
				wc_enqueue_js( "
					$( '" . $selector . "' ).click( function() {
						" . $parameters['enhanced'] . "
						" . self::tracker_var() . "( 'ec:setAction', 'add' );
						" . self::tracker_var() . "( 'event', 'UX', 'click', 'add to cart' );
					});
				" );
				return;
			} else {
				$track_event = "" . self::tracker_var() . "('event', %s, %s, %s);";
			}
		} else {
			$track_event = "_gaq.push(['_trackEvent', %s, %s, %s]);";
		}

		wc_enqueue_js( "
			$( '" . $selector . "' ).click( function() {
				" . sprintf( $track_event, $parameters['category'], $parameters['action'], $parameters['label'] ) . "
			});
		" );
	}

}
