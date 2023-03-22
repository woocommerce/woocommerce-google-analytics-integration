<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Google_Analytics_JS class
 *
 * JS for recording Google Analytics info
 */
class WC_Google_Analytics_JS extends WC_Abstract_Google_Analytics_JS {

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
	}

	/**
	 * Returns the tracker variable this integration should use
	 *
	 * @return string
	 */
	public static function tracker_var() {
		return apply_filters( 'woocommerce_ga_tracker_variable', 'ga' );
	}

	/**
	 * Loads the correct Google Analytics code (classic or universal)
	 *
	 * @param  boolean|WC_Order $order Classic analytics needs order data to set the currency correctly
	 */
	public static function load_analytics( $order = false ) {
		$logged_in = is_user_logged_in() ? 'yes' : 'no';
		add_filter( 'wc_google_analytics_send_pageview', array( 'WC_Google_Analytics_JS', 'universal_analytics_footer_filter' ), 10, 1 );
		if ( 'yes' === self::get( 'ga_use_universal_analytics' ) ) {
			self::load_analytics_universal( $logged_in );
			self::load_page_view_footer();
		} else {
			self::load_analytics_classic( $logged_in, $order );
			self::classic_analytics_footer();
		}
	}

	/**
	 * Loads ga.js analytics tracking code
	 *
	 * @param  string           $logged_in      'yes' if the user is logged in, no if not (this is a string so we can pass it to GA)
	 * @param  boolean|WC_Order $order We don't always need to load order data for currency, so we omit
	 *                                 that if false is set, otherwise this is an order object
	 */
	protected static function load_analytics_classic( $logged_in, $order = false ) {
		$anonymize_enabled = '';
		if ( 'yes' === self::get( 'ga_anonymize_enabled' ) ) {
			$anonymize_enabled = "['_gat._anonymizeIp'],";
		}

		$track_404_enabled = '';
		if ( 'yes' === self::get( 'ga_404_tracking_enabled' ) && is_404() ) {
			// See https://developers.google.com/analytics/devguides/collection/gajs/methods/gaJSApiEventTracking#_trackevent
			$track_404_enabled = "['_trackEvent', 'Error', '404 Not Found', 'page: ' + document.location.pathname + document.location.search + ' referrer: ' + document.referrer ],";
		}

		$domainname = self::get( 'ga_set_domain_name' );

		if ( ! empty( $domainname ) ) {
			$set_domain_name = "['_setDomainName', '" . esc_js( self::get( 'ga_set_domain_name' ) ) . "'],";
		} else {
			$set_domain_name = '';
		}

		$code = "var _gaq = _gaq || [];
		_gaq.push(
			['_setAccount', '" . esc_js( self::get( 'ga_id' ) ) . "'], " . $set_domain_name .
			$anonymize_enabled .
			$track_404_enabled . "
			['_setCustomVar', 1, 'logged-in', '" . esc_js( $logged_in ) . "', 1],
			['_trackPageview']";

		if ( false !== $order ) {
			$code .= ",['_set', 'currencyCode', '" . esc_js( version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_order_currency() : $order->get_currency() ) . "']";
		}

		$code .= ');';

		self::load_analytics_code_in_header( apply_filters( 'woocommerce_ga_classic_snippet_output', $code ) );
	}

	/**
	 * Enqueues JavaScript to build the addImpression object
	 *
	 * @param WC_Product $product
	 * @param int        $position
	 */
	public static function listing_impression( $product, $position ) {
		if ( is_search() ) {
			$list = 'Search Results';
		} else {
			$list = 'Product List';
		}

		wc_enqueue_js(
			self::tracker_var() . "( 'ec:addImpression', {
				'id': '" . esc_js( $product->get_id() ) . "',
				'name': '" . esc_js( $product->get_title() ) . "',
				'category': " . self::product_get_category_line( $product ) . "
				'list': '" . esc_js( $list ) . "',
				'position': '" . esc_js( $position ) . "'
			} );
		"
		);
	}

	/**
	 * Enqueues JavaScript to build an addProduct and click object
	 *
	 * @param WC_Product $product
	 * @param int        $position
	 */
	public static function listing_click( $product, $position ) {
		if ( is_search() ) {
			$list = 'Search Results';
		} else {
			$list = 'Product List';
		}

		wc_enqueue_js(
			"
			$( '.products .post-" . esc_js( $product->get_id() ) . " a' ).on( 'click', function() {
				if ( true === $(this).hasClass( 'add_to_cart_button' ) ) {
					return;
				}

				" . self::tracker_var() . "( 'ec:addProduct', {
					'id': '" . esc_js( $product->get_id() ) . "',
					'name': '" . esc_js( $product->get_title() ) . "',
					'category': " . self::product_get_category_line( $product ) . "
					'position': '" . esc_js( $position ) . "'
				});

				" . self::tracker_var() . "( 'ec:setAction', 'click', { list: '" . esc_js( $list ) . "' });
				" . self::tracker_var() . "( 'send', 'event', 'UX', 'click', ' " . esc_js( $list ) . "' );
			});
		"
		);
	}

	/**
	 * Loads in the footer
	 *
	 * @see wp_footer
	 */
	public static function classic_analytics_footer() {
		if ( 'yes' === self::get( 'ga_support_display_advertising' ) ) {
			$ga_url = "('https:' == document.location.protocol ? 'https://' : 'http://') + 'stats.g.doubleclick.net/dc.js'";
		} else {
			$ga_url = "('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js'";
		}

		$code = "(function() {
		var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
		ga.src = " . $ga_url . ";
		var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
		})();";

		wc_enqueue_js( $code );
	}

	/**
	 * Enqueues JavaScript to send the pageview last thing (needed for things like addImpression)
	 */
	public static function load_page_view_footer() {
		if ( apply_filters( 'wc_google_analytics_send_pageview', true ) ) {
			wc_enqueue_js( self::tracker_var() . "( 'send', 'pageview' ); " );
		}
	}

	/**
	 * This was created to fix public facing api typo in a filter name
	 * and inform about the deprecation.
	 *
	 * @param boolean $send_pageview
	 */
	public static function universal_analytics_footer_filter( $send_pageview ) {
		return apply_filters_deprecated( 'wc_goole_analytics_send_pageview', array( $send_pageview ), '1.4.20', 'wc_google_analytics_send_pageview' );
	}

	/**
	 * Loads the universal analytics code
	 *
	 * @param  string $logged_in 'yes' if the user is logged in, no if not (this is a string so we can pass it to GA)
	 */
	protected static function load_analytics_universal( $logged_in ) {
		$domainname = self::get( 'ga_set_domain_name' );

		if ( ! empty( $domainname ) ) {
			$set_domain_name = esc_js( self::get( 'ga_set_domain_name' ) );
		} else {
			$set_domain_name = 'auto';
		}

		$support_display_advertising = '';
		if ( 'yes' === self::get( 'ga_support_display_advertising' ) ) {
			$support_display_advertising = self::tracker_var() . "( 'require', 'displayfeatures' );";
		}

		$support_enhanced_link_attribution = '';
		if ( 'yes' === self::get( 'ga_support_enhanced_link_attribution' ) ) {
			$support_enhanced_link_attribution = self::tracker_var() . "( 'require', 'linkid' );";
		}

		$anonymize_enabled = '';
		if ( 'yes' === self::get( 'ga_anonymize_enabled' ) ) {
			$anonymize_enabled = self::tracker_var() . "( 'set', 'anonymizeIp', true );";
		}

		$track_404_enabled = '';
		if ( 'yes' === self::get( 'ga_404_tracking_enabled' ) && is_404() ) {
			// See https://developers.google.com/analytics/devguides/collection/analyticsjs/events for reference
			$track_404_enabled = self::tracker_var() . "( 'send', 'event', 'Error', '404 Not Found', 'page: ' + document.location.pathname + document.location.search + ' referrer: ' + document.referrer );";
		}

		$src = apply_filters( 'woocommerce_google_analytics_script_src', '//www.google-analytics.com/analytics.js' );

		$ga_snippet_head = "(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		})(window,document,'script', '{$src}','" . self::tracker_var() . "');";

		$ga_id = self::get( 'ga_id' );

		if ( 'yes' === self::get( 'ga_linker_allow_incoming_enabled' ) ) {
			$ga_snippet_create = self::tracker_var() . "( 'create', '" . esc_js( $ga_id ) . "', '" . $set_domain_name . "', { allowLinker: true });";
		} else {
			$ga_snippet_create = self::tracker_var() . "( 'create', '" . esc_js( $ga_id ) . "', '" . $set_domain_name . "' );";
		}

		if ( ! empty( self::DEVELOPER_ID ) ) {
			$ga_snippet_developer_id = "(window.gaDevIds=window.gaDevIds||[]).push('" . self::DEVELOPER_ID . "');";
		} else {
			$ga_snippet_developer_id = '';
		}

		$ga_snippet_require = $support_display_advertising .
			$support_enhanced_link_attribution .
			$anonymize_enabled .
			$track_404_enabled . '
		' . self::tracker_var() . "( 'set', 'dimension1', '" . $logged_in . "' );\n";

		if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) ) {
			$ga_snippet_require .= self::tracker_var() . "( 'require', 'ec' );";
		} else {
			$ga_snippet_require .= self::tracker_var() . "( 'require', 'ecommerce', 'ecommerce.js');";
		}

		$ga_cross_domains = ! empty( self::get( 'ga_linker_cross_domains' ) ) ? array_map( 'esc_js', explode( ',', self::get( 'ga_linker_cross_domains' ) ) ) : false;

		if ( $ga_cross_domains ) {
			$ga_snippet_require .= self::tracker_var() . "( 'require', 'linker' );";
			$ga_snippet_require .= self::tracker_var() . "( 'linker:autoLink', " . wp_json_encode( $ga_cross_domains ) . ');';
		}

		$ga_snippet_head         = apply_filters( 'woocommerce_ga_snippet_head', $ga_snippet_head );
		$ga_snippet_create       = apply_filters( 'woocommerce_ga_snippet_create', $ga_snippet_create, $ga_id );
		$ga_snippet_developer_id = apply_filters( 'woocommerce_ga_snippet_developer_id', $ga_snippet_developer_id );
		$ga_snippet_require      = apply_filters( 'woocommerce_ga_snippet_require', $ga_snippet_require );

		$code = $ga_snippet_head . $ga_snippet_create . $ga_snippet_developer_id . $ga_snippet_require;

		self::load_analytics_code_in_header( apply_filters( 'woocommerce_ga_snippet_output', $code ) );
	}

	/**
	 * Generate code used to pass transaction data to Google Analytics.
	 *
	 * @param  WC_Order $order WC_Order Object.
	 */
	public function add_transaction( $order ) {
		if ( 'yes' === self::get( 'ga_use_universal_analytics' ) ) {
			if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) ) {
				$transaction_code = self::add_transaction_enhanced( $order );
			} else {
				$transaction_code = self::add_transaction_universal( $order );
			}
		} else {
			$transaction_code = self::add_transaction_classic( $order );
		}

		// Check localStorage to avoid duplicate transactions if page is reloaded without hitting server.
		$code = "
			var ga_orders = [];
			try {
				ga_orders = localStorage.getItem( 'ga_orders' );
				ga_orders = ga_orders ? JSON.parse( ga_orders ) : [];
			} catch {}
			if ( -1 === ga_orders.indexOf( '" . esc_js( $order->get_order_number() ) . "' ) ) {
				" . $transaction_code . "
				try {
					ga_orders.push( '" . esc_js( $order->get_order_number() ) . "' );
					localStorage.setItem( 'ga_orders', JSON.stringify( ga_orders ) );
				} catch {}
			}";

		wc_enqueue_js( $code );
	}

	/**
	 * Transaction tracking for ga.js (classic)
	 *
	 * @param  WC_Order $order WC_Order Object
	 * @return string          Add Transaction Code
	 */
	protected function add_transaction_classic( $order ) {
		$code = "_gaq.push(['_addTrans',
			'" . esc_js( $order->get_order_number() ) . "', 	// order ID - required
			'" . esc_js( get_bloginfo( 'name' ) ) . "',  		// affiliation or store name
			'" . esc_js( $order->get_total() ) . "',   	    	// total - required
			'" . esc_js( $order->get_total_tax() ) . "',    	// tax
			'" . esc_js( $order->get_total_shipping() ) . "',	// shipping
			'" . esc_js( version_compare( WC_VERSION, '3.0', '<' ) ? $order->billing_city : $order->get_billing_city() ) . "',       	// city
			'" . esc_js( version_compare( WC_VERSION, '3.0', '<' ) ? $order->billing_state : $order->get_billing_state() ) . "',      	// state or province
			'" . esc_js( version_compare( WC_VERSION, '3.0', '<' ) ? $order->billing_country : $order->get_billing_country() ) . "'     	// country
		]);";

		// Order items
		if ( $order->get_items() ) {
			foreach ( $order->get_items() as $item ) {
				$code .= self::add_item_classic( $order, $item );
			}
		}

		$code .= "_gaq.push(['_trackTrans']);";
		return $code;
	}

	/**
	 * Generate Universal Analytics Enhanced Ecommerce transaction tracking code
	 *
	 * @param  WC_Order $order
	 * @return string
	 */
	protected function add_transaction_enhanced( $order ) {
		$code = self::tracker_var() . "( 'set', '&cu', '" . esc_js( version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_order_currency() : $order->get_currency() ) . "' );";

		// Order items
		if ( $order->get_items() ) {
			foreach ( $order->get_items() as $item ) {
				$code .= self::add_item_enhanced( $order, $item );
			}
		}

		$code .= self::tracker_var() . "( 'ec:setAction', 'purchase', {
			'id': '" . esc_js( $order->get_order_number() ) . "',
			'affiliation': '" . esc_js( get_bloginfo( 'name' ) ) . "',
			'revenue': '" . esc_js( $order->get_total() ) . "',
			'tax': '" . esc_js( $order->get_total_tax() ) . "',
			'shipping': '" . esc_js( $order->get_total_shipping() ) . "'
		} );";

		return $code;
	}

	/**
	 * Add Item (Classic)
	 *
	 * @param  WC_Order $order WC_Order Object
	 * @param  array    $item     The item to add to a transaction/order
	 * @return string
	 */
	protected function add_item_classic( $order, $item ) {
		$_product = version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_product_from_item( $item ) : $item->get_product();

		$code  = "_gaq.push(['_addItem',";
		$code .= "'" . esc_js( $order->get_order_number() ) . "',";
		$code .= "'" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->get_id() ) . "',";
		$code .= "'" . esc_js( $item['name'] ) . "',";
		$code .= self::product_get_category_line( $_product );
		$code .= "'" . esc_js( $order->get_item_total( $item ) ) . "',";
		$code .= "'" . esc_js( $item['qty'] ) . "'";
		$code .= ']);';

		return $code;
	}

	/**
	 * Add Item (Enhanced, Universal)
	 *
	 * @param  WC_Order      $order     WC_Order Object
	 * @param  WC_Order_Item $item The item to add to a transaction/order
	 * @return string
	 */
	protected function add_item_enhanced( $order, $item ) {
		$_product = version_compare( WC_VERSION, '3.0', '<' ) ? $order->get_product_from_item( $item ) : $item->get_product();
		$variant  = self::product_get_variant_line( $_product );

		$code  = self::tracker_var() . "( 'ec:addProduct', {";
		$code .= "'id': '" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->get_id() ) . "',";
		$code .= "'name': '" . esc_js( $item['name'] ) . "',";
		$code .= "'category': " . self::product_get_category_line( $_product );

		if ( '' !== $variant ) {
			$code .= "'variant': " . $variant;
		}

		$code .= "'price': '" . esc_js( $order->get_item_total( $item ) ) . "',";
		$code .= "'quantity': '" . esc_js( $item['qty'] ) . "'";
		$code .= '});';

		return $code;
	}

	/**
	 * Output JavaScript to track an enhanced ecommerce remove from cart action
	 */
	public function remove_from_cart() {
		echo( "
			<script>
			(function($) {
				$( document.body ).off('click', '.remove').on( 'click', '.remove', function() {
					" . esc_js( self::tracker_var() ) . "( 'ec:addProduct', {
						'id': ($(this).data('product_sku')) ? ($(this).data('product_sku')) : ('#' + $(this).data('product_id')),
						'quantity': $(this).parent().parent().find( '.qty' ).val() ? $(this).parent().parent().find( '.qty' ).val() : '1',
					} );
					" . esc_js( self::tracker_var() ) . "( 'ec:setAction', 'remove' );
					" . esc_js( self::tracker_var() ) . "( 'send', 'event', 'UX', 'click', 'remove from cart' );
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

		wc_enqueue_js(
			self::tracker_var() . "( 'ec:addProduct', {
				'id': '" . esc_js( $product->get_sku() ? $product->get_sku() : ( '#' . $product->get_id() ) ) . "',
				'name': '" . esc_js( $product->get_title() ) . "',
				'category': " . self::product_get_category_line( $product ) . "
				'price': '" . esc_js( $product->get_price() ) . "',
			} );

			" . self::tracker_var() . "( 'ec:setAction', 'detail' );"
		);
	}

	/**
	 * Enqueue JS to track when the checkout process is started
	 *
	 * @param array $cart items/contents of the cart
	 */
	public function checkout_process( $cart ) {
		$code = '';

		foreach ( $cart as $cart_item_key => $cart_item ) {
			$product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$variant = self::product_get_variant_line( $product );
			$code   .= self::tracker_var() . "( 'ec:addProduct', {
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

		$code .= self::tracker_var() . "( 'ec:setAction','checkout' );";
		wc_enqueue_js( $code );
	}

	/**
	 * Enqueue JavaScript for Add to cart tracking
	 *
	 * @param array  $parameters associative array of _trackEvent parameters
	 * @param string $selector jQuery selector for binding click event
	 */
	public function event_tracking_code( $parameters, $selector ) {
		$parameters = apply_filters( 'woocommerce_ga_event_tracking_parameters', $parameters );

		if ( 'yes' === self::get( 'ga_use_universal_analytics' ) ) {
			if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) ) {
				wc_enqueue_js(
					"
					$( '" . $selector . "' ).on( 'click', function() {
						" . $parameters['enhanced'] . '
						' . self::tracker_var() . "( 'ec:setAction', 'add' );
						" . self::tracker_var() . "( 'send', 'event', 'UX', 'click', 'add to cart' );
					});
				"
				);
				return;
			} else {
				$track_event = self::tracker_var() . "('send', 'event', %s, %s, %s);";
			}
		} else {
			$track_event = "_gaq.push(['_trackEvent', %s, %s, %s]);";
		}

		wc_enqueue_js(
			"
			$( '" . $selector . "' ).on( 'click', function() {
				" . sprintf( $track_event, $parameters['category'], $parameters['action'], $parameters['label'] ) . '
			});
		'
		);
	}

	/**
	 * Loads a code using the google-analytics handler in the head.
	 *
	 * @param string $code The code to add attached to the google-analytics handler
	 */
	protected static function load_analytics_code_in_header( $code ) {
		wp_register_script( 'google-analytics', '', array( 'google-analytics-opt-out' ), null, false );
		wp_add_inline_script( 'google-analytics', $code );
		wp_enqueue_script( 'google-analytics' );
	}

}
