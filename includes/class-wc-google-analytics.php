<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Google Analytics Integration
 *
 * Allows tracking code to be inserted into store pages.
 *
 * @class   WC_Google_Analytics
 * @extends WC_Integration
 */
class WC_Google_Analytics extends WC_Integration {

	/**
	 * Init and hook in the integration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->id                 = 'google_analytics';
		$this->method_title       = __( 'Google Analytics', 'woocommerce-google-analytics-integration' );
		$this->method_description = __( 'Google Analytics is a free service offered by Google that generates detailed statistics about the visitors to a website.', 'woocommerce-google-analytics-integration' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->ga_id                          = $this->get_option( 'ga_id' );
		$this->ga_set_domain_name             = $this->get_option( 'ga_set_domain_name' );
		$this->ga_standard_tracking_enabled   = $this->get_option( 'ga_standard_tracking_enabled' );
		$this->ga_support_display_advertising = $this->get_option( 'ga_support_display_advertising' );
		$this->ga_use_universal_analytics     = $this->get_option( 'ga_use_universal_analytics' );
		$this->ga_anonymize_enabled           = $this->get_option( 'ga_anonymize_enabled' );
		$this->ga_ecommerce_tracking_enabled  = $this->get_option( 'ga_ecommerce_tracking_enabled' );
		$this->ga_event_tracking_enabled      = $this->get_option( 'ga_event_tracking_enabled' );

		// Actions
		add_action( 'woocommerce_update_options_integration_google_analytics', array( $this, 'process_admin_options') );

		// Tracking code
		add_action( 'wp_head', array( $this, 'tracking_code_display' ), 999999 );

		// Event tracking code
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_to_cart' ) );
		add_action( 'wp_footer', array( $this, 'loop_add_to_cart' ) );

		// utm_nooverride parameter for Google AdWords
		add_filter( 'woocommerce_get_return_url', array( $this, 'utm_nooverride' ) );
	}

	/**
	 * Initialise Settings Form Fields
	 *
	 * @return void
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'ga_id' => array(
				'title' 			=> __( 'Google Analytics ID', 'woocommerce-google-analytics-integration' ),
				'description' 		=> __( 'Log into your google analytics account to find your ID. e.g. <code>UA-XXXXX-X</code>', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'text',
				'default' 			=> get_option( 'woocommerce_ga_id' ) // Backwards compat
			),
			'ga_set_domain_name' => array(
				'title' 			=> __( 'Set Domain Name', 'woocommerce-google-analytics-integration' ),
				'description' 		=> sprintf( __( '(Optional) Sets the <code>_setDomainName</code> variable. <a href="%s">See here for more information</a>.', 'woocommerce-google-analytics-integration' ), 'https://developers.google.com/analytics/devguides/collection/gajs/gaTrackingSite#multipleDomains' ),
				'type' 				=> 'text',
				'default' 			=> ''
			),
			'ga_standard_tracking_enabled' => array(
				'title' 			=> __( 'Tracking code', 'woocommerce-google-analytics-integration' ),
				'label' 			=> __( 'Add tracking code to your site. You don\'t need to enable this if using a 3rd party analytics plugin.', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> 'start',
				'default' 			=> get_option( 'woocommerce_ga_standard_tracking_enabled' ) ? get_option( 'woocommerce_ga_standard_tracking_enabled' ) : 'no'  // Backwards compat
			),
			'ga_support_display_advertising' => array(
				'label' 			=> __( 'Set the Google Analytics code to support Display Advertising. <a href="https://support.google.com/analytics/answer/2700409" target="_blank">Read more about Display Advertising</a>.', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> '',
				'default' 			=> get_option( 'woocommerce_ga_support_display_advertising' ) ? get_option( 'woocommerce_ga_support_display_advertising' ) : 'no'  // Backwards compat
			),
			'ga_use_universal_analytics' => array(
				'label' 			=> __( 'Use Universal Analytics instead of Classic Google Analytics', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> '',
				'default' 			=> get_option( 'woocommerce_ga_use_universal_analytics' ) ? get_option( 'woocommerce_ga_use_universal_analytics' ) : 'no'  // Backwards compat
			),
			'ga_anonymize_enabled' => array(
				'label' 			=> __( 'Anonymize IP addresses. Setting this option is mandatory in certain countries due to national privacy laws. <a href="https://support.google.com/analytics/answer/2763052" target="_blank">Read more about IP Anonymization</a>.', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> '',
				'default' 			=> 'no'
			),
			'ga_ecommerce_tracking_enabled' => array(
				'label' 			=> __( 'Add eCommerce tracking code to the thankyou page', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> '',
				'default' 			=> get_option( 'woocommerce_ga_ecommerce_tracking_enabled' ) ? get_option( 'woocommerce_ga_ecommerce_tracking_enabled' ) : 'no'  // Backwards compat
			),
			'ga_event_tracking_enabled' => array(
				'label' 			=> __( 'Add event tracking code for add to cart actions', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> 'end',
				'default' 			=> 'no'
			)
		);
	}

	/**
	 * Display the tracking codes
	 *
	 * @return string
	 */
	public function tracking_code_display() {
		global $wp;
		$display_ecommerce_tracking = false;

		if ( is_admin() || current_user_can( 'manage_options' ) || ! $this->ga_id ) {
			return;
		}

		// Check if is order received page and stop when the products and not tracked
		if ( is_order_received_page() && 'yes' == $this->ga_ecommerce_tracking_enabled ) {
			$order_id = isset( $wp->query_vars['order-received'] ) ? $wp->query_vars['order-received'] : 0;

			if ( 0 < $order_id && 1 != get_post_meta( $order_id, '_ga_tracked', true ) ) {
				$display_ecommerce_tracking = true;

				echo $this->get_ecommerce_tracking_code( $order_id );
			}
		}

		if ( ! $display_ecommerce_tracking && 'yes' == $this->ga_standard_tracking_enabled ) {
			echo $this->get_google_tracking_code();
		}
	}

	/**
	 * Get the generic Google Analytics code snippet
	 *
	 * @return string
	 */
	protected function get_generic_ga_code() {
		return "
<script>
	var gaProperty = '" . esc_js( $this->ga_id ) . "';
	var disableStr = 'ga-disable-' + gaProperty;
	if (document.cookie.indexOf(disableStr + '=true') > -1) {
		window[disableStr] = true;
	}
	function gaOptout() {
		document.cookie = disableStr + '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/';
		window[disableStr] = true;
	}
</script>
";
	}

	/**
	 * Google Analytics standard tracking
	 *
	 * @return string
	 */
	protected function get_google_tracking_code() {
		$logged_in = ( is_user_logged_in() ) ? 'yes' : 'no';

		if ( 'yes' === $logged_in ) {
			$user_id      = get_current_user_id();
			$current_user = get_user_by('id', $user_id);
			$username     = $current_user->user_login;
		} else {
			$user_id  = '';
			$username = __( 'Guest', 'woocommerce-google-analytics-integration' );
		}

		if ( 'yes' == $this->ga_use_universal_analytics ) {
			if ( ! empty( $this->ga_set_domain_name ) ) {
				$set_domain_name = esc_js( $this->ga_set_domain_name );
			} else {
				$set_domain_name = 'auto';
			}

			$support_display_advertising = '';
			if ( 'yes' == $this->ga_support_display_advertising ) {
				$support_display_advertising = "ga('require', 'displayfeatures');";
			}

			$anonymize_enabled = '';
			if ( 'yes' == $this->ga_anonymize_enabled ) {
				$anonymize_enabled = "ga('set', 'anonymizeIp', true);";
			}

			$code = "
	(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

	ga('create', '" . esc_js( $this->ga_id ) . "', '" . $set_domain_name . "');" .
	$support_display_advertising .
	$anonymize_enabled . "
	ga('set', 'dimension1', '" . $logged_in . "');
	ga('send', 'pageview');
";

		} else {
			if ( 'yes' == $this->ga_support_display_advertising ) {
				$ga_url = "('https:' == document.location.protocol ? 'https://' : 'http://') + 'stats.g.doubleclick.net/dc.js'";
			} else {
				$ga_url = "('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js'";
			}

			$anonymize_enabled = '';
			if ( 'yes' == $this->ga_anonymize_enabled ) {
				$anonymize_enabled = "['_gat._anonymizeIp'],";
			}

			if ( ! empty( $this->ga_set_domain_name ) ) {
				$set_domain_name = "['_setDomainName', '" . esc_js( $this->ga_set_domain_name ) . "'],\n";
			} else {
				$set_domain_name = '';
			}

			$code = "
	var _gaq = _gaq || [];
	_gaq.push(
		['_setAccount', '" . esc_js( $this->ga_id ) . "'], " . $set_domain_name .
		$anonymize_enabled . "
		['_setCustomVar', 1, 'logged-in', '" . $logged_in . "', 1],
		['_trackPageview']
	);

	(function() {
		var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
		ga.src = " . $ga_url . ";
		var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
	})();
";
		}

		return "
<!-- WooCommerce Google Analytics Integration -->
" . $this->get_generic_ga_code() . "
<script type='text/javascript'>$code</script>

<!-- /WooCommerce Google Analytics Integration -->

";

	}

	/**
	 * Google Analytics eCommerce tracking
	 *
	 * @param int $order_id
	 *
	 * @return string
	 */
	protected function get_ecommerce_tracking_code( $order_id ) {
		// Get the order and output tracking code
		$order = new WC_Order( $order_id );

		$logged_in = is_user_logged_in() ? 'yes' : 'no';

		if ( 'yes' === $logged_in ) {
			$user_id      = get_current_user_id();
			$current_user = get_user_by( 'id', $user_id );
			$username     = $current_user->user_login;
		} else {
			$user_id  = '';
			$username = __( 'Guest', 'woocommerce-google-analytics-integration' );
		}

		if ( 'yes' == $this->ga_use_universal_analytics ) {
			if ( ! empty( $this->ga_set_domain_name ) ) {
				$set_domain_name = esc_js( $this->ga_set_domain_name );
			} else {
				$set_domain_name = 'auto';
			}

			$support_display_advertising = '';
			if ( 'yes' == $this->ga_support_display_advertising ) {
				$support_display_advertising = "ga('require', 'displayfeatures');";
			}

			$anonymize_enabled = '';
			if ( 'yes' == $this->ga_anonymize_enabled ) {
				$anonymize_enabled = "ga('set', 'anonymizeIp', true);";
			}

			$code = "
	(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

	ga('create', '" . esc_js( $this->ga_id ) . "', '" . $set_domain_name . "');" .
	$support_display_advertising .
	$anonymize_enabled . "
	ga('set', 'dimension1', '" . $logged_in . "');
	ga('send', 'pageview');

	ga('require', 'ecommerce', 'ecommerce.js');

	ga('ecommerce:addTransaction', {
		'id': '" . esc_js( $order->get_order_number() ) . "',         // Transaction ID. Required
		'affiliation': '" . esc_js( get_bloginfo( 'name' ) ) . "',    // Affiliation or store name
		'revenue': '" . esc_js( $order->get_total() ) . "',           // Grand Total
		'shipping': '" . esc_js( $order->get_total_shipping() ) . "', // Shipping
		'tax': '" . esc_js( $order->get_total_tax() ) . "',           // Tax
		'currency': '" . esc_js( $order->get_order_currency() ) . "'  // Currency
	});
";

			// Order items
			if ( $order->get_items() ) {
				foreach ( $order->get_items() as $item ) {
					$_product = $order->get_product_from_item( $item );

					$code .= "ga('ecommerce:addItem', {";
					$code .= "'id': '" . esc_js( $order->get_order_number() ) . "',";
					$code .= "'name': '" . esc_js( $item['name'] ) . "',";
					$code .= "'sku': '" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->id ) . "',";

					if ( isset( $_product->variation_data ) ) {

						$code .= "'category': '" . esc_js( woocommerce_get_formatted_variation( $_product->variation_data, true ) ) . "',";

					} else {
						$out = array();
						$categories = get_the_terms($_product->id, 'product_cat');
						if ( $categories ) {
							foreach ( $categories as $category ) {
								$out[] = $category->name;
							}
						}
						$code .= "'category': '" . esc_js( join( "/", $out) ) . "',";
					}

					$code .= "'price': '" . esc_js( $order->get_item_total( $item ) ) . "',";
					$code .= "'quantity': '" . esc_js( $item['qty'] ) . "'";
					$code .= "});";
				}
			}

			$code .= "ga('ecommerce:send');      // Send transaction and item data to Google Analytics.";

		} else {
			if ( $this->ga_support_display_advertising == 'yes' ) {
				$ga_url = "('https:' == document.location.protocol ? 'https://' : 'http://') + 'stats.g.doubleclick.net/dc.js'";
			} else {
				$ga_url = "('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js'";
			}

			$anonymize_enabled = '';
			if ( 'yes' == $this->ga_anonymize_enabled ) {
				$anonymize_enabled = "['_gat._anonymizeIp'],";
			}

			if ( ! empty( $this->ga_set_domain_name ) ) {
				$set_domain_name = "['_setDomainName', '" . esc_js( $this->ga_set_domain_name ) . "'],";
			} else {
				$set_domain_name = '';
			}

			$code = "
	var _gaq = _gaq || [];

	_gaq.push(
		['_setAccount', '" . esc_js( $this->ga_id ) . "'], " . $set_domain_name .
		$anonymize_enabled . "
		['_setCustomVar', 1, 'logged-in', '" . esc_js( $logged_in ) . "', 1],
		['_trackPageview'],
		['_set', 'currencyCode', '" . esc_js( $order->get_order_currency() ) . "']
	);

	_gaq.push(['_addTrans',
		'" . esc_js( $order->get_order_number() ) . "', 	// order ID - required
		'" . esc_js( get_bloginfo( 'name' ) ) . "',  		// affiliation or store name
		'" . esc_js( $order->get_total() ) . "',   	    	// total - required
		'" . esc_js( $order->get_total_tax() ) . "',    	// tax
		'" . esc_js( $order->get_total_shipping() ) . "',	// shipping
		'" . esc_js( $order->billing_city ) . "',       	// city
		'" . esc_js( $order->billing_state ) . "',      	// state or province
		'" . esc_js( $order->billing_country ) . "'     	// country
	]);
";

			// Order items
			if ( $order->get_items() ) {
				foreach ( $order->get_items() as $item ) {
					$_product = $order->get_product_from_item( $item );

					$code .= "_gaq.push(['_addItem',";
					$code .= "'" . esc_js( $order->get_order_number() ) . "',";
					$code .= "'" . esc_js( $_product->get_sku() ? $_product->get_sku() : $_product->id ) . "',";
					$code .= "'" . esc_js( $item['name'] ) . "',";

					if ( isset( $_product->variation_data ) ) {

						$code .= "'" . esc_js( woocommerce_get_formatted_variation( $_product->variation_data, true ) ) . "',";

					} else {
						$out = array();
						$categories = get_the_terms($_product->id, 'product_cat');
						if ( $categories ) {
							foreach ( $categories as $category ){
								$out[] = $category->name;
							}
						}
						$code .= "'" . esc_js( join( "/", $out) ) . "',";
					}

					$code .= "'" . esc_js( $order->get_item_total( $item ) ) . "',";
					$code .= "'" . esc_js( $item['qty'] ) . "'";
					$code .= "]);";
				}
			}

			$code .= "
	_gaq.push(['_trackTrans']); // submits transaction to the Analytics servers

	(function() {
		var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
		ga.src = " . $ga_url . ";
		var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
	})();
";
		}

		// Mark the order as tracked
		update_post_meta( $order_id, '_ga_tracked', 1 );

		return "
<!-- WooCommerce Google Analytics Integration -->
" . $this->get_generic_ga_code() . "
<script type='text/javascript'>$code</script>
<!-- /WooCommerce Google Analytics Integration -->
";
	}

	/**
	 * Check if tracking is disabled
	 *
	 * @param string $type
	 *
	 * @return bool
	 */
	private function disable_tracking( $type ) {
		if ( is_admin() || current_user_can( 'manage_options' ) || ( ! $this->ga_id ) || 'no' == $type ) {
			return true;
		}
	}

	/**
	 * Google Analytics event tracking for single product add to cart
	 *
	 * @return void
	 */
	public function add_to_cart() {

		if ( $this->disable_tracking( $this->ga_event_tracking_enabled ) ) {
			return;
		}
		if ( ! is_single() ) {
			return;
		}

		global $product;

		$parameters = array();
		// Add single quotes to allow jQuery to be substituted into _trackEvent parameters
		$parameters['category'] = "'" . __( 'Products', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['action']   = "'" . __( 'Add to Cart', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['label']    = "'" . esc_js( $product->get_sku() ? __( 'SKU:', 'woocommerce-google-analytics-integration' ) . ' ' . $product->get_sku() : "#" . $product->id ) . "'";

		$this->event_tracking_code( $parameters, '.single_add_to_cart_button' );
	}


	/**
	 * Google Analytics event tracking for loop add to cart
	 *
	 * @return void
	 */
	public function loop_add_to_cart() {

		if ( $this->disable_tracking( $this->ga_event_tracking_enabled ) ) {
			return;
		}

		$parameters = array();
		// Add single quotes to allow jQuery to be substituted into _trackEvent parameters
		$parameters['category'] = "'" . __( 'Products', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['action']   = "'" . __( 'Add to Cart', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['label']    = "($(this).data('product_sku')) ? ('SKU: ' + $(this).data('product_sku')) : ('#' + $(this).data('product_id'))"; // Product SKU or ID

		$this->event_tracking_code( $parameters, '.add_to_cart_button:not(.product_type_variable, .product_type_grouped)' );
	}

	/**
	 * Google Analytics event tracking for loop add to cart
	 *
	 * @param array $parameters associative array of _trackEvent parameters
	 * @param string $selector jQuery selector for binding click event
	 *
	 * @return void
	 */
	private function event_tracking_code( $parameters, $selector ) {
		$parameters = apply_filters( 'woocommerce_ga_event_tracking_parameters', $parameters );

		if ( 'yes' == $this->ga_use_universal_analytics ) {
			$track_event = "ga('send', 'event', %s, %s, %s);";
		} else {
			$track_event = "_gaq.push(['_trackEvent', %s, %s, %s]);";
		}

		wc_enqueue_js( "
	$( '" . $selector . "' ).click( function() {
		" . sprintf( $track_event, $parameters['category'], $parameters['action'], $parameters['label'] ) . "
	});
" );
	}

	/**
	 * Add the utm_nooverride parameter to any return urls. This makes sure Google Adwords doesn't mistake the offsite gateway as the referrer.
	 *
	 * @param  string $type
	 *
	 * @return string
	 */
	public function utm_nooverride( $return_url ) {

		// We don't know if the URL already has the parameter so we should remove it just in case
		$return_url = remove_query_arg( 'utm_nooverride', $return_url );

		// Now add the utm_nooverride query arg to the URL
		$return_url = add_query_arg( 'utm_nooverride', '1', $return_url );

		return $return_url;
	}
}
