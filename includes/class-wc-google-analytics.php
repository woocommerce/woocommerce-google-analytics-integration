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

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();
		$constructor = $this->init_options();

		// Contains snippets/JS tracking code
		include_once( 'class-wc-google-analytics-js.php' );
		WC_Google_Analytics_JS::get_instance( $constructor );

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
	 * Loads all of our options for this plugin
	 * @return array An array of options that can be passed to other classes
	 */
	public function init_options() {
		$options = array(
			'ga_id',
			'ga_set_domain_name',
			'ga_standard_tracking_enabled',
			'ga_support_display_advertising',
			'ga_use_universal_analytics',
			'ga_anonymize_enabled',
			'ga_ecommerce_tracking_enabled',
			'ga_event_tracking_enabled'
		);

		$constructor = array();
		foreach ( $options as $option ) {
			$constructor[ $option ] = $this->$option = $this->get_option( $option );
		}

		return $constructor;
	}

	/**
	 * Tells WooCommerce which settings to display under the "integration" tab
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'ga_id' => array(
				'title' 			=> __( 'Google Analytics ID', 'woocommerce-google-analytics-integration' ),
				'description' 		=> __( 'Log into your Google Analytics account to find your ID. e.g. <code>UA-XXXXX-X</code>', 'woocommerce-google-analytics-integration' ),
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
				'title' 			=> __( 'Options', 'woocommerce-google-analytics-integration' ),
				'label' 			=> __( 'Enable standard Google Analytics tracking. This tracks session data such as demographics, system, etc. You don\'t need to enable this if you are using a 3rd party analytics plugin.', 'woocommerce-google-analytics-integration' ),
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
				'label' 			=> __( 'Use Universal Analytics instead of Classic Google Analytics. If you have not previously used Google Analytics for this site, check this box. Otherwise, <a href="https://developers.google.com/analytics/devguides/collection/upgrade/guide">follow step 1 of the Universal Analytics upgrade guide.</a> Enabling this setting will take care of step 2. <a href="https://support.google.com/analytics/answer/2790010?hl=en">Read more about Universal Analytics</a>.', 'woocommerce-google-analytics-integration' ),
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
				'title'             => __( 'Data to track', 'woocommerce-google-analytics-integration' ),
				'label' 			=> __( 'Transactions (requires a payment gateway that redirects to the thank you/order received page).', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> '',
				'default' 			=> get_option( 'woocommerce_ga_ecommerce_tracking_enabled' ) ? get_option( 'woocommerce_ga_ecommerce_tracking_enabled' ) : 'no'  // Backwards compat
			),
			'ga_event_tracking_enabled' => array(
				'label' 			=> __( 'Add to Cart Actions.', 'woocommerce-google-analytics-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> 'end',
				'default' 			=> 'no'
			)
		);
	}

	/**
	 * Display the tracking codes
	 * Acts as a controller to figure out which code to display
	 */
	public function tracking_code_display() {
		global $wp;
		$display_ecommerce_tracking = false;

		if ( is_admin() || current_user_can( 'manage_options' ) || ! $this->ga_id ) {
			return;
		}

		// Check if is order received page and stop when the products and not tracked
		if ( is_order_received_page() && 'yes' === $this->ga_ecommerce_tracking_enabled ) {
			$order_id = isset( $wp->query_vars['order-received'] ) ? $wp->query_vars['order-received'] : 0;
			if ( 0 < $order_id && 1 != get_post_meta( $order_id, '_ga_tracked', true ) ) {
				$display_ecommerce_tracking = true;
				echo $this->get_ecommerce_tracking_code( $order_id );
			}
		}

		if ( ! $display_ecommerce_tracking && 'yes' === $this->ga_standard_tracking_enabled ) {
			echo $this->get_standard_tracking_code();
		}
	}

	/**
	 * Standard Google Analytics tracking
	 */
	protected function get_standard_tracking_code() {
		return "<!-- WooCommerce Google Analytics Integration -->
		" . WC_Google_Analytics_JS::get_instance()->header() . "
		<script type='text/javascript'>" . WC_Google_Analytics_JS::get_instance()->load_analytics() . "</script>
		<!-- /WooCommerce Google Analytics Integration -->";
	}

	/**
	 * eCommerce tracking
	 *
	 * @param int $order_id
	 */
	protected function get_ecommerce_tracking_code( $order_id ) {
		// Get the order and output tracking code
		$order = new WC_Order( $order_id );

		$code = WC_Google_Analytics_JS::get_instance()->load_analytics( $order );
		$code .= WC_Google_Analytics_JS::get_instance()->add_transaction( $order );

		// Mark the order as tracked
		update_post_meta( $order_id, '_ga_tracked', 1 );

		return "
		<!-- WooCommerce Google Analytics Integration -->
		" . WC_Google_Analytics_JS::get_instance()->header() . "
		<script type='text/javascript'>$code</script>
		<!-- /WooCommerce Google Analytics Integration -->
		";
	}

	/**
	 * Check if tracking is disabled
	 *
	 * @param string $type The setting to check
	 *
	 * @return bool True if tracking for a certain setting is disabled
	 */
	private function disable_tracking( $type ) {
		if ( is_admin() || current_user_can( 'manage_options' ) || ( ! $this->ga_id ) || 'no' === $type ) {
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

		// Add single quotes to allow jQuery to be substituted into _trackEvent parameters
		$parameters = array();
		$parameters['category'] = "'" . __( 'Products', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['action']   = "'" . __( 'Add to Cart', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['label']    = "'" . esc_js( $product->get_sku() ? __( 'SKU:', 'woocommerce-google-analytics-integration' ) . ' ' . $product->get_sku() : "#" . $product->id ) . "'";

		WC_Google_Analytics_JS::get_instance()->event_tracking_code( $parameters, '.single_add_to_cart_button' );
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

		// Add single quotes to allow jQuery to be substituted into _trackEvent parameters
		$parameters = array();
		$parameters['category'] = "'" . __( 'Products', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['action']   = "'" . __( 'Add to Cart', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['label']    = "($(this).data('product_sku')) ? ('SKU: ' + $(this).data('product_sku')) : ('#' + $(this).data('product_id'))"; // Product SKU or ID

		WC_Google_Analytics_JS::get_instance()->event_tracking_code( $parameters, '.add_to_cart_button:not(.product_type_variable, .product_type_grouped)' );
	}

	/**
	 * Add the utm_nooverride parameter to any return urls. This makes sure Google Adwords doesn't mistake the offsite gateway as the referrer.
	 *
	 * @param  string $return_url WooCommerce Return URL
	 *
	 * @return string URL
	 */
	public function utm_nooverride( $return_url ) {
		// We don't know if the URL already has the parameter so we should remove it just in case
		$return_url = remove_query_arg( 'utm_nooverride', $return_url );

		// Now add the utm_nooverride query arg to the URL
		$return_url = add_query_arg( 'utm_nooverride', '1', $return_url );

		return $return_url;
	}
}
