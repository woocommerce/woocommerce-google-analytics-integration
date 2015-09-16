<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Google_Analytics_JS class
 *
 * JS for recording Google Analytics info
 */
class WC_Google_Analytics_JS {

	/** @var object Class Instance */
	private static $instance;

	/** @var array Inherited Analytics options */
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
		return apply_filters( 'woocommerce_ga_tracker_variable', 'ga' );
	}

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
	 * Loads the correct Google Analytics code (classic or universal)
	 * @param  boolean $order Classic analytics needs order data to set the currency correctly
	 * @return string         Analytics loading code
	 */
	public static function load_analytics( $order = false ) {
		$logged_in = is_user_logged_in() ? 'yes' : 'no';
		if ( 'yes' === self::get( 'ga_use_universal_analytics' ) ) {
			add_action( 'wp_footer', array( 'WC_Google_Analytics_JS', 'universal_analytics_footer' ) );
			return self::load_analytics_universal( $logged_in );
		} else {
			add_action( 'wp_footer', array( 'WC_Google_Analytics_JS', 'classic_analytics_footer' ) );
			return self::load_analytics_classic( $logged_in, $order );
		}
	}

	/**
	 * Loads ga.js analytics tracking code
	 * @param  string  $logged_in 'yes' if the user is logged in, no if not (this is a string so we can pass it to GA)
	 * @param  boolean|object $order  We don't always need to load order data for currency, so we omit that if false is set, otherwise this is an order object
	 * @return string         Classic Analytics loading code
	 */
	public static function load_analytics_classic( $logged_in, $order = false ) {
		$anonymize_enabled = '';
		if ( 'yes' === self::get( 'ga_anonymize_enabled' ) ) {
			$anonymize_enabled = "['_gat._anonymizeIp'],";
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
			$anonymize_enabled . "
			['_setCustomVar', 1, 'logged-in', '" . esc_js( $logged_in ) . "', 1],
			['_trackPageview']";

		if ( false !== $order ) {
			$code .= ",['_set', 'currencyCode', '" . esc_js( $order->get_order_currency() ) . "']";
		}

		$code .= ");";

		return $code;
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
			" . self::tracker_var() . "( 'ec:addImpression', {
				'id': '" . esc_js( $product->id ) . "',
				'name': '" . esc_js( $product->get_title() ) . "',
				'category': " . self::product_get_category_line( $product ) . "
				'list': '" . esc_js( $list ) . "',
				'position': " . esc_js( $position ) . "
			} );
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
				$( '.products .post-" . esc_js( $product->id ) . " a' ).click( function() {
					if ( true === $(this).hasClass( 'add_to_cart_button' ) ) {
						return;
					}

					" . self::tracker_var() . "( 'ec:addProduct', {
						'id': '" . esc_js( $product->id ) . "',
						'name': '" . esc_js( $product->get_title() ) . "',
						'category': " . self::product_get_category_line( $product ) . "
						'position': " . esc_js( $position ) . "
					});

					" . self::tracker_var() . "( 'ec:setAction', 'click', { list: '" . esc_js( $list ) . "' });
					" . self::tracker_var() . "( 'send', 'event', 'UX', 'click', ' " . esc_js( $list ) . "' );
				});
			})(jQuery);
			</script>
		" );
	}

	/**
	 * Asyncronously loads the classic Google Analytics code, and does so after all of our properties are loaded
	 * Loads in the footer
	 * @see wp_footer
	 */
	public static function classic_analytics_footer() {
		if ( 'yes' === self::get( 'ga_support_display_advertising' ) ) {
			$ga_url = "('https:' == document.location.protocol ? 'https://' : 'http://') + 'stats.g.doubleclick.net/dc.js'";
		} else {
			$ga_url = "('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js'";
		}

		echo "<script type='text/javascript'>(function() {
		var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
		ga.src = " . $ga_url . ";
		var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
		})();</script>";
	}

	/**
	 * Sends the pageview last thing (needed for things like addImpression)
	 */
	public static function universal_analytics_footer() {
		wc_enqueue_js( "" . self::tracker_var() . "( 'send', 'pageview' ); ");
	}

	/**
	 * Loads the universal analytics code
	 * @param  string $logged_in 'yes' if the user is logged in, no if not (this is a string so we can pass it to GA)
	 * @return string Universal Analytics Code
	 */
	public static function load_analytics_universal( $logged_in ) {
		
		$domainname = self::get( 'ga_set_domain_name' );
		
		if ( ! empty( $domainname ) ) {
			$set_domain_name = esc_js( self::get( 'ga_set_domain_name' ) );
		} else {
			$set_domain_name = 'auto';
		}

		$support_display_advertising = '';
		if ( 'yes' === self::get( 'ga_support_display_advertising' ) ) {
			$support_display_advertising = "" . self::tracker_var() . "( 'require', 'displayfeatures' );";
		}

		$anonymize_enabled = '';
		if ( 'yes' === self::get( 'ga_anonymize_enabled' ) ) {
			$anonymize_enabled = "" . self::tracker_var() . "( 'set', 'anonymizeIp', true );";
		}

		$code = "(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		})(window,document,'script','//www.google-analytics.com/analytics.js','" . self::tracker_var() . "');

		" . self::tracker_var() . "( 'create', '" . esc_js( self::get( 'ga_id' ) ) . "', '" . $set_domain_name . "' );" .
		$support_display_advertising .
		$anonymize_enabled . "
		" . self::tracker_var() . "( 'set', 'dimension1', '" . $logged_in . "' );\n";

		if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) ) {
			$code .= "" . self::tracker_var() . "( 'require', 'ec' );";
		} else {
			$code .= "" . self::tracker_var() . "( 'require', 'ecommerce', 'ecommerce.js');";
		}

		return $code;
	}

	/**
	 * Used to pass transaction data to Google Analytics
	 * @param object $order WC_Order Object
	 * @return string Add Transaction code
	 */
	function add_transaction( $order ) {
		if ( 'yes' == self::get( 'ga_use_universal_analytics' ) ) {
			if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) ) {
				return self::add_transaction_enhanced( $order );
			} else {
				return self::add_transaction_universal( $order );
			}
		} else {
			return self::add_transaction_classic( $order );
		}
	}

	/**
	 * ga.js (classic) transaction tracking
	 * @param object $order WC_Order Object
	 * @return string Add Transaction Code
	 */
	function add_transaction_classic( $order ) {
		$code = "_gaq.push(['_addTrans',
			'" . esc_js( $order->get_order_number() ) . "', 	// order ID - required
			'" . esc_js( get_bloginfo( 'name' ) ) . "',  		// affiliation or store name
			'" . esc_js( $order->get_total() ) . "',   	    	// total - required
			'" . esc_js( $order->get_total_tax() ) . "',    	// tax
			'" . esc_js( $order->get_total_shipping() ) . "',	// shipping
			'" . esc_js( $order->billing_city ) . "',       	// city
			'" . esc_js( $order->billing_state ) . "',      	// state or province
			'" . esc_js( $order->billing_country ) . "'     	// country
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
	 * Universal Analytics transaction tracking
	 * @param object $order WC_Order object
	 * @return string Add Transaction Code
	 */
	function add_transaction_universal( $order ) {
		$code = "" . self::tracker_var() . "('ecommerce:addTransaction', {
			'id': '" . esc_js( $order->get_order_number() ) . "',         // Transaction ID. Required
			'affiliation': '" . esc_js( get_bloginfo( 'name' ) ) . "',    // Affiliation or store name
			'revenue': '" . esc_js( $order->get_total() ) . "',           // Grand Total
			'shipping': '" . esc_js( $order->get_total_shipping() ) . "', // Shipping
			'tax': '" . esc_js( $order->get_total_tax() ) . "',           // Tax
			'currency': '" . esc_js( $order->get_order_currency() ) . "'  // Currency
		});";

		// Order items
		if ( $order->get_items() ) {
			foreach ( $order->get_items() as $item ) {
				$code .= self::add_item_universal( $order, $item );
			}
		}

		$code .= "" . self::tracker_var() . "('ecommerce:send');";
		return $code;
	}

	/**
	 * Enhanced Ecommerce Universal Analytics transaction tracking
	 */
	function add_transaction_enhanced( $order ) {
		$code = "" . self::tracker_var() . "( 'set', '&cu', '" . esc_js( $order->get_order_currency() ) . "' );";

		// Order items
		if ( $order->get_items() ) {
			foreach ( $order->get_items() as $item ) {
				$code .= self::add_item_enhanced( $order, $item );
			}
		}

		$code .= "" . self::tracker_var() . "( 'ec:setAction', 'purchase', {
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
	 * @param object $order WC_Order Object
	 * @param array $item  The item to add to a transaction/order
	 */
	function add_item_classic( $order, $item ) {
		$_product = $order->get_product_from_item( $item );

		$code = "_gaq.push(['_addItem',";
		$code .= "'" . esc_js( $order->get_order_number() ) . "',";
		$code .= "'" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->id ) . "',";
		$code .= "'" . esc_js( $item['name'] ) . "',";
		$code .= self::product_get_category_line( $_product );
		$code .= "'" . esc_js( $order->get_item_total( $item ) ) . "',";
		$code .= "'" . esc_js( $item['qty'] ) . "'";
		$code .= "]);";

		return $code;
	}

	/**
	 * Add Item (Universal)
	 * @param object $order WC_Order Object
	 * @param array $item  The item to add to a transaction/order
	 */
	function add_item_universal( $order, $item ) {
		$_product = $order->get_product_from_item( $item );

		$code = "" . self::tracker_var() . "('ecommerce:addItem', {";
		$code .= "'id': '" . esc_js( $order->get_order_number() ) . "',";
		$code .= "'name': '" . esc_js( $item['name'] ) . "',";
		$code .= "'sku': '" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->id ) . "',";
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
	function add_item_enhanced( $order, $item ) {
		$_product = $order->get_product_from_item( $item );

		$code = "" . self::tracker_var() . "( 'ec:addProduct', {";
		$code .= "'id': '" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->id ) . "',";
		$code .= "'name': '" . esc_js( $item['name'] ) . "',";
		$code .= "'category': " . self::product_get_category_line( $_product );
		$code .= "'price': '" . esc_js( $order->get_item_total( $item ) ) . "',";
		$code .= "'quantity': '" . esc_js( $item['qty'] ) . "'";
		$code .= "});";

		return $code;
	}

	/**
	 * Returns a 'category' JSON line based on $product
	 * @param  object $product  Product to pull info for
	 * @return string          Line of JSON
	 */
	private static function product_get_category_line( $_product ) {
		if ( is_array( $_product->variation_data ) && ! empty( $_product->variation_data ) ) {
			$code = "'" . esc_js( woocommerce_get_formatted_variation( $_product->variation_data, true ) ) . "',";
		} else {
			$out = array();
			$categories = get_the_terms( $_product->id, 'product_cat' );
			if ( $categories ) {
				foreach ( $categories as $category ) {
					$out[] = $category->name;
				}
			}
			$code = "'" . esc_js( join( "/", $out ) ) . "',";
		}

		return $code;
	}

	/**
	 * Tracks an enhanced ecommerce remove from cart action
	 */
	function remove_from_cart() {
		echo( "
			<script>
			(function($) {
				$( '.remove' ).click( function() {
					" . self::tracker_var() . "( 'ec:addProduct', {
						'id': ($(this).data('product_sku')) ? ('SKU: ' + $(this).data('product_sku')) : ('#' + $(this).data('product_id')),
						'quantity': $(this).parent().parent().find( '.qty' ).val() ? $(this).parent().parent().find( '.qty' ).val() : '1',
					} );
					" . self::tracker_var() . "( 'ec:setAction', 'remove' );
					" . self::tracker_var() . "( 'send', 'event', 'UX', 'click', 'remove from cart' );
				});
			})(jQuery);
			</script>
		" );
	}

	/**
	 * Tracks a product detail view
	 */
	function product_detail( $product ) {
		wc_enqueue_js( "
			" . self::tracker_var() . "( 'ec:addProduct', {
				'id': '" . esc_js( $product->get_sku() ? $product->get_sku() : $product->id ) . "',
				'name': '" . esc_js( $product->get_title() ) . "',
				'category': " . self::product_get_category_line( $product ) . "
				'price': '" . esc_js( $product->get_price() ) . "',
			} );

			" . self::tracker_var() . "( 'ec:setAction', 'detail' );" );
	}

	/**
	 * Tracks when the checkout process is started
	 */
	function checkout_process( $cart ) {
		$code = "";

		foreach ( $cart as $cart_item_key => $cart_item ) {
			$product     = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$code .= "" . self::tracker_var() . "( 'ec:addProduct', {
				'id': '" . esc_js( $product->get_sku() ? $product->get_sku() : $product->id ) . "',
				'name': '" . esc_js( $product->get_title() ) . "',
				'category': " . self::product_get_category_line( $product ) . "
				'price': '" . esc_js( $product->get_price() ) . "',
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
		$parameters = apply_filters( 'woocommerce_ga_event_tracking_parameters', $parameters );

		if ( 'yes' === self::get( 'ga_use_universal_analytics' ) ) {
			if ( 'yes' === self::get( 'ga_enhanced_ecommerce_tracking_enabled' ) ) {
				wc_enqueue_js( "
					$( '" . $selector . "' ).click( function() {
						" . $parameters['enhanced'] . "
						" . self::tracker_var() . "( 'ec:setAction', 'add' );
						" . self::tracker_var() . "( 'send', 'event', 'UX', 'click', 'add to cart' );
					});
				" );
				return;
			} else {
				$track_event = "" . self::tracker_var() . "('send', 'event', %s, %s, %s);";
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
