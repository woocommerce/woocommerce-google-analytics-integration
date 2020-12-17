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

	/**
	 * Get the class instance
	 *
	 * @param array $options Options
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	public static function get_instance( $options = array() ) {
		return null === self::$instance ? ( self::$instance = new self( $options ) ) : self::$instance;
	}

	/**
	 * Constructor
	 * Takes our options from the parent class so we can later use them in the JS snippets
	 *
	 * @param array $options Options
	 */
	public function __construct( $options = array() ) {
		self::$options = $options;
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
	 * Enqueues JavaScript to build the addImpression event
	 *
	 * @param WC_Product $product
	 * @param int $position
	 */
	public static function listing_impression( $product, $position ) {
		if ( isset( $_GET['s'] ) ) {
			$list = "Search Results";
		} else {
			$list = "Product List";
		}

		wc_enqueue_js( "
			" . self::tracker_var() . "( 'event', 'view_item_list', { 'items': [ {
				'id': '" . esc_js( $product->get_id() ) . "',
				'name': '" . esc_js( $product->get_title() ) . "',
				'category': " . self::product_get_category_line( $product ) . "
				'list': '" . esc_js( $list ) . "',
				'list_position': '" . esc_js( $position ) . "'
			} ] } );
		" );
	}

	/**
	 * Enqueues JavaScript to build an addProduct and click event
	 *
	 * @param WC_Product $product
	 * @param int $position
	 */
	public static function listing_click( $product, $position ) {
		if ( isset( $_GET['s'] ) ) {
			$list = "Search Results";
		} else {
			$list = "Product List";
		}

		wc_enqueue_js( "
			$( '.products .post-" . esc_js( $product->get_id() ) . " a' ).on('click', function() {
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
			});
		" );
	}

	/**
	 * Loads the standard Gtag code
	 *
	 * @param  boolean|WC_Order $order Classic analytics needs order data to set the currency correctly
	 * @return string                  Gtag loading code
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
			$gtag_developer_id = "gtag('set', 'developer_id." . self::DEVELOPER_ID . "', true);";
		}

		$gtag_id      = self::get( 'ga_id' );
		$gtag_snippet = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_js( $gtag_id ) . '"></script>';
		$gtag_snippet .= "
		<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		$gtag_developer_id

		gtag('config', '" . esc_js( $gtag_id ) . "', {
			'allow_google_signals': " . ( 'yes' === self::get( 'ga_support_display_advertising' ) ? 'true' : 'false' ) . ",
			'link_attribution': " . ( 'yes' === self::get( 'ga_support_enhanced_link_attribution' ) ? 'true' : 'false' ) . ",
			'anonymize_ip': " . ( 'yes' === self::get( 'ga_anonymize_enabled' ) ? 'true' : 'false' ) . ",
			'custom_map': {
				'dimension1': 'logged_in'
			},
			'logged_in': '$logged_in'
		} );

		$track_404_enabled
		</script>
		";

		$gtag_snippet = apply_filters( 'woocommerce_gtag_snippet' , $gtag_snippet );

		return $gtag_snippet;
	}

	/**
	 * Generate Gtag transaction tracking code
	 *
	 * @param  WC_Order $order
	 * @return string
	 */
	protected function add_transaction_enhanced( $order ) {
		// Order items
		$items = "[";
		if ( $order->get_items() ) {
			foreach ( $order->get_items() as $item ) {
				$items .= self::add_item( $order, $item );
			}
		}
		$items .= "]";

		$code  = "" . self::tracker_var() . "( 'event', 'purchase', {
			'transaction_id': '" . esc_js( $order->get_order_number() ) . "',
			'affiliation': '" . esc_js( get_bloginfo( 'name' ) ) . "',
			'value': '" . esc_js( $order->get_total() ) . "',
			'tax': '" . esc_js( $order->get_total_tax() ) . "',
			'shipping': '" . esc_js( $order->get_total_shipping() ) . "',
			'currency': '" . esc_js( version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_order_currency() : $order->get_currency() ) . "',  // Currency,
			'items': " . $items . ",
		} );";

		return $code;
	}

	/**
	 * Add Item
	 *
	 * @param WC_Order $order WC_Order Object
	 * @param WC_Order_Item $item    The item to add to a transaction/order
	 */
	protected function add_item( $order, $item ) {
		$_product = version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_product_from_item( $item ) : $item->get_product();
		$variant  = self::product_get_variant_line( $_product );

		$code = "{";
		$code .= "'id': '" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->get_id() ) . "',";
		$code .= "'name': '" . esc_js( $item['name'] ) . "',";
		$code .= "'category': " . self::product_get_category_line( $_product );

		if ( '' !== $variant ) {
			$code .= "'variant': " . $variant;
		}

		$code .= "'price': '" . esc_js( $order->get_item_total( $item ) ) . "',";
		$code .= "'quantity': '" . esc_js( $item['qty'] ) . "'";
		$code .= "},";

		return $code;
	}

	/**
	 * Output JavaScript to track an enhanced ecommerce remove from cart action
	 */
	public function remove_from_cart() {
		echo( "
			<script>
			(function($) {
				$( '.remove' ).off('click', '.remove').on( 'click', function() {
					" . self::tracker_var() . "( 'event', 'remove_from_cart', {
						'items': [ {
							'id': ($(this).data('product_sku')) ? ($(this).data('product_sku')) : ('#' + $(this).data('product_id')),
							'quantity': $(this).parent().parent().find( '.qty' ).val() ? $(this).parent().parent().find( '.qty' ).val() : '1',
						} ]
					} );
				});
			})(jQuery);
			</script>
		" );
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

		wc_enqueue_js( "
			" . self::tracker_var() . "( 'event', 'view_item', {
				'items': [ {
					'id': '" . esc_js( $product->get_sku() ? $product->get_sku() : ( '#' . $product->get_id() ) ) . "',
					'name': '" . esc_js( $product->get_title() ) . "',
					'category': " . self::product_get_category_line( $product ) . "
					'price': '" . esc_js( $product->get_price() ) . "',
				} ]
			} );" );
	}

	/**
	 * Enqueue JS to track when the checkout process is started
	 *
	 * @param array $cart items/contents of the cart
	 */
	public function checkout_process( $cart ) {
		$items = "[";

		foreach ( $cart as $cart_item_key => $cart_item ) {
			$product     = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			$items .= "
				{
					'id': '" . esc_js( $product->get_sku() ? $product->get_sku() : ( '#' . $product->get_id() ) ) . "',
					'name': '" . esc_js( $product->get_title() ) . "',
					'category': " . self::product_get_category_line( $product );

			$variant     = self::product_get_variant_line( $product );
			if ( '' !== $variant ) {
				$items .= "
					'variant': " . $variant;
			}

			$items .= "
					'price': '" . esc_js( $product->get_price() ) . "',
					'quantity': '" . esc_js( $cart_item['quantity'] ) . "'
				},";
		}

		$items .= '
			]';

		$code  = "" . self::tracker_var() . "( 'event', 'begin_checkout', {
			'items': " . $items . ",
		} );";

		wc_enqueue_js( $code );
	}

	/**
	 * Enqueue JavaScript for Add to cart tracking
	 *
	 * @param array $parameters associative array of _trackEvent parameters
	 * @param string $selector jQuery selector for binding click event
	 */
	public function event_tracking_code( $parameters, $selector ) {

		// Called with invalid 'Add to Cart' action, update to sync with Default Google Analytics Event 'add_to_cart'
		$parameters['action']   = '\'add_to_cart\'';
		$parameters['category'] = '\'ecommerce\'';

		$parameters = apply_filters( 'woocommerce_gtag_event_tracking_parameters', $parameters );

		if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) ) {
			$track_event = sprintf(
				self::tracker_var() . "( 'event', %s, { 'event_category': %s, 'event_label': %s, 'items': [ %s ] } );",
				$parameters['action'], $parameters['category'], $parameters['label'], $parameters['item']
			);
		} else {
			$track_event = sprintf(
				self::tracker_var() . "( 'event', %s, { 'event_category': %s, 'event_label': %s } );",
				$parameters['action'], $parameters['category'], $parameters['label']
			);
		}

		wc_enqueue_js( "
			$( '" . $selector . "' ).on( 'click', function() {
				" . $track_event . "
			});
		" );
	}

}
