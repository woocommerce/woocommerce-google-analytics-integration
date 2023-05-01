<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WC_Google_Analytics_Integration as Plugin;
use Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists;

/**
 * Google Analytics Integration
 *
 * Allows tracking code to be inserted into store pages.
 *
 * @class   WC_Google_Analytics
 * @extends WC_Integration
 *
 * @property $ga_id
 * @property $ga_set_domain_name
 * @property $ga_gtag_enabled
 * @property $ga_standard_tracking_enabled
 * @property $ga_support_display_advertising
 * @property $ga_support_enhanced_link_attribution
 * @property $ga_use_universal_analytics
 * @property $ga_anonymize_enabled
 * @property $ga_404_tracking_enabled
 * @property $ga_ecommerce_tracking_enabled
 * @property $ga_enhanced_ecommerce_tracking_enabled
 * @property $ga_enhanced_remove_from_cart_enabled
 * @property $ga_enhanced_product_impression_enabled
 * @property $ga_enhanced_product_click_enabled
 * @property $ga_enhanced_checkout_process_enabled
 * @property $ga_enhanced_product_detail_view_enabled
 * @property $ga_event_tracking_enabled
 * @property $ga_linker_cross_domains
 * @property $ga_linker_allow_incoming_enabled
 */
class WC_Google_Analytics extends WC_Integration {

	/**
	 * Defines the script handles that should be async.
	 */
	private const ASYNC_SCRIPT_HANDLES = array( 'google-tag-manager' );

	/**
	 * Returns the proper class based on Gtag settings.
	 *
	 * @param  array $options                  Options
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	protected function get_tracking_instance( $options = array() ) {
		if ( 'yes' === $this->ga_gtag_enabled ) {
			return WC_Google_Gtag_JS::get_instance( $options );
		}

		return WC_Google_Analytics_JS::get_instance( $options );
	}

	/**
	 * Constructor
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->id                    = 'google_analytics';
		$this->method_title          = __( 'Google Analytics', 'woocommerce-google-analytics-integration' );
		$this->method_description    = __( 'Google Analytics is a free service offered by Google that generates detailed statistics about the visitors to a website.', 'woocommerce-google-analytics-integration' );
		$this->dismissed_info_banner = get_option( 'woocommerce_dismissed_info_banner' );

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();
		$constructor = $this->init_options();

		// Contains snippets/JS tracking code
		include_once 'class-wc-abstract-google-analytics-js.php';
		include_once 'class-wc-google-analytics-js.php';
		include_once 'class-wc-google-gtag-js.php';
		$this->get_tracking_instance( $constructor );

		// Display a task on  "Things to do next section"
		add_action( 'init', array( $this, 'add_wc_setup_task' ), 20 );
		// Admin Options
		add_filter( 'woocommerce_tracker_data', array( $this, 'track_options' ) );
		add_action( 'woocommerce_update_options_integration_google_analytics', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_integration_google_analytics', array( $this, 'show_options_info' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );

		// Tracking code
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking_code' ), 9 );
		add_filter( 'script_loader_tag', array( $this, 'async_script_loader_tags' ), 10, 3 );

		// Event tracking code
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_to_cart' ) );
		add_action( 'wp_footer', array( $this, 'loop_add_to_cart' ) );
		add_action( 'woocommerce_after_cart', array( $this, 'remove_from_cart' ) );
		add_action( 'woocommerce_after_mini_cart', array( $this, 'remove_from_cart' ) );
		add_filter( 'woocommerce_cart_item_remove_link', array( $this, 'remove_from_cart_attributes' ), 10, 2 );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'listing_impression' ) );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'listing_click' ) );
		add_action( 'woocommerce_after_single_product', array( $this, 'product_detail' ) );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'checkout_process' ) );

		// utm_nooverride parameter for Google AdWords
		add_filter( 'woocommerce_get_return_url', array( $this, 'utm_nooverride' ) );
	}

	/**
	 * Loads all of our options for this plugin (stored as properties as well)
	 *
	 * @return array An array of options that can be passed to other classes
	 */
	public function init_options() {
		$options = array(
			'ga_id',
			'ga_set_domain_name',
			'ga_gtag_enabled',
			'ga_standard_tracking_enabled',
			'ga_support_display_advertising',
			'ga_support_enhanced_link_attribution',
			'ga_use_universal_analytics',
			'ga_anonymize_enabled',
			'ga_404_tracking_enabled',
			'ga_ecommerce_tracking_enabled',
			'ga_enhanced_ecommerce_tracking_enabled',
			'ga_enhanced_remove_from_cart_enabled',
			'ga_enhanced_product_impression_enabled',
			'ga_enhanced_product_click_enabled',
			'ga_enhanced_checkout_process_enabled',
			'ga_enhanced_product_detail_view_enabled',
			'ga_event_tracking_enabled',
			'ga_linker_cross_domains',
			'ga_linker_allow_incoming_enabled',
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
		// backwards_compatibility
		if ( get_option( 'woocommerce_ga_use_universal_analytics' ) ) {
			 $ua_default_value = get_option( 'woocommerce_ga_use_universal_analytics' );
		} else {
			// don't enable for extension updates, only default to enabled on new installs
			$ua_default_value = get_option( $this->get_option_key() ) ? 'no' : 'yes';
		}

		$this->form_fields = array(
			'ga_id'                                   => array(
				'title'       => __( 'Google Analytics Tracking ID', 'woocommerce-google-analytics-integration' ),
				'description' => __( 'Log into your Google Analytics account to find your ID. e.g. <code>GT-XXXXX</code> or <code>G-XXXXX</code>', 'woocommerce-google-analytics-integration' ),
				'type'        => 'text',
				'placeholder' => 'GT-XXXXX',
				'default'     => get_option( 'woocommerce_ga_id' ), // Backwards compat
			),
			'ga_set_domain_name'                      => array(
				'title'       => __( 'Set Domain Name', 'woocommerce-google-analytics-integration' ),
				/* translators: Read more link */
				'description' => sprintf( __( '(Optional) Sets the <code>_setDomainName</code> variable. %1$sSee here for more information%2$s.', 'woocommerce-google-analytics-integration' ), '<a href="https://developers.google.com/analytics/devguides/collection/gajs/gaTrackingSite#multipleDomains" target="_blank">', '</a>' ),
				'type'        => 'text',
				'default'     => '',
				'class'       => 'legacy-setting',
			),

			'ga_gtag_enabled'                         => array(
				'title'         => __( 'Tracking Options', 'woocommerce-google-analytics-integration' ),
				'label'         => __( 'Use Global Site Tag', 'woocommerce-google-analytics-integration' ),
				/* translators: Read more link */
				'description'   => sprintf( __( 'The Global Site Tag provides streamlined tagging across Google’s site measurement, conversion tracking, and remarketing products. This must be enabled to use a Google Analytics 4 Measurement ID (e.g., <code>G-XXXXX</code> or <code>GT-XXXXX</code>). %1$sSee here for more information%2$s.', 'woocommerce-google-analytics-integration' ), '<a href="https://support.google.com/analytics/answer/7475631?hl=en" target="_blank">', '</a>' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => get_option( $this->get_option_key() ) ? 'no' : 'yes', // don't enable on updates, only default on new installs
			),

			'ga_use_universal_analytics'              => array(
				'label'         => __( 'Enable Universal Analytics', 'woocommerce-google-analytics-integration' ),
				/* translators: Read more start and end links */
				'description'   => sprintf( __( 'Uses Universal Analytics instead of Classic Google Analytics. If you have <strong>not</strong> previously used Google Analytics on this site, check this box. Otherwise, %1$sfollow step 1 of the Universal Analytics upgrade guide.%2$s Enabling this setting will take care of step 2. %3$sRead more about Universal Analytics%4$s. Universal Analytics or Global Site Tag must be enabled to enable enhanced eCommerce.', 'woocommerce-google-analytics-integration' ), '<a href="https://developers.google.com/analytics/devguides/collection/upgrade/guide" target="_blank">', '</a>', '<a href="https://support.google.com/analytics/answer/2790010?hl=en" target="_blank">', '</a>' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => $ua_default_value,
				'class'         => 'legacy-setting',
			),
			'ga_standard_tracking_enabled'            => array(
				'label'         => __( 'Enable Standard Tracking', 'woocommerce-google-analytics-integration' ),
				'description'   => __( 'This tracks session data such as demographics, system, etc. You don\'t need to enable this if you are using a 3rd party Google analytics plugin.', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => 'start',
				'default'       => get_option( 'woocommerce_ga_standard_tracking_enabled' ) ? get_option( 'woocommerce_ga_standard_tracking_enabled' ) : 'no',  // Backwards compat
			),
			'ga_support_display_advertising'          => array(
				'label'         => __( '"Display Advertising" Support', 'woocommerce-google-analytics-integration' ),
				/* translators: Read more link */
				'description'   => sprintf( __( 'Set the Google Analytics code to support Display Advertising. %1$sRead more about Display Advertising%2$s.', 'woocommerce-google-analytics-integration' ), '<a href="https://support.google.com/analytics/answer/2700409" target="_blank">', '</a>' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => get_option( 'woocommerce_ga_support_display_advertising' ) ? get_option( 'woocommerce_ga_support_display_advertising' ) : 'yes', // Backwards compat
			),
			'ga_support_enhanced_link_attribution'    => array(
				'label'         => __( 'Use Enhanced Link Attribution', 'woocommerce-google-analytics-integration' ),
				/* translators: Read more link */
				'description'   => sprintf( __( 'Set the Google Analytics code to support Enhanced Link Attribution. %1$sRead more about Enhanced Link Attribution%2$s.', 'woocommerce-google-analytics-integration' ), '<a href="https://support.google.com/analytics/answer/7377126?hl=en" target="_blank">', '</a>' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => get_option( 'woocommerce_ga_support_enhanced_link_attribution' ) ? get_option( 'woocommerce_ga_support_enhanced_link_attribution' ) : 'no',  // Backwards compat
			),
			'ga_anonymize_enabled'                    => array(
				'label'         => __( 'Anonymize IP addresses', 'woocommerce-google-analytics-integration' ),
				/* translators: Read more link */
				'description'   => sprintf( __( 'Enabling this option is mandatory in certain countries due to national privacy laws. %1$sRead more about IP Anonymization%2$s.', 'woocommerce-google-analytics-integration' ), '<a href="https://support.google.com/analytics/answer/2763052" target="_blank">', '</a>' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'yes',
			),
			'ga_404_tracking_enabled'                 => array(
				'label'         => __( 'Track 404 (Not found) Errors', 'woocommerce-google-analytics-integration' ),
				/* translators: Read more link */
				'description'   => sprintf( __( 'Enable this to find broken or dead links. An "Event" with category "Error" and action "404 Not Found" will be created in Google Analytics for each incoming pageview to a non-existing page. By setting up a "Custom Goal" for these events within Google Analytics you can find out where broken links originated from (the referrer). %1$sRead how to set up a goal%2$s.', 'woocommerce-google-analytics-integration' ), '<a href="https://support.google.com/analytics/answer/1032415" target="_blank">', '</a>' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'yes',
			),
			'ga_ecommerce_tracking_enabled'           => array(
				'label'         => __( 'Purchase Transactions', 'woocommerce-google-analytics-integration' ),
				'description'   => __( 'This requires a payment gateway that redirects to the thank you/order received page after payment. Orders paid with gateways which do not do this will not be tracked.', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => 'start',
				'default'       => get_option( 'woocommerce_ga_ecommerce_tracking_enabled' ) ? get_option( 'woocommerce_ga_ecommerce_tracking_enabled' ) : 'yes',  // Backwards compat
			),
			'ga_event_tracking_enabled'               => array(
				'label'         => __( 'Add to Cart Events', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'yes',
			),
			'ga_linker_cross_domains'                 => array(
				'title'       => __( 'Cross Domain Tracking', 'woocommerce-google-analytics-integration' ),
				/* translators: Read more link */
				'description' => sprintf( __( 'Add a comma separated list of domains for automatic linking. %1$sRead more about Cross Domain Measurement%2$s', 'woocommerce-google-analytics-integration' ), '<a href="https://support.google.com/analytics/answer/7476333" target="_blank">', '</a>' ),
				'type'        => 'text',
				'placeholder' => 'example.com, example.net',
				'default'     => '',
			),
			'ga_linker_allow_incoming_enabled'        => array(
				'label'         => __( 'Accept Incoming Linker Parameters', 'woocommerce-google-analytics-integration' ),
				'description'   => __( 'Enabling this option will allow incoming linker parameters from other websites.', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'no',
			),
			'ga_enhanced_ecommerce_tracking_enabled'  => array(
				'title'         => __( 'Enhanced eCommerce', 'woocommerce-google-analytics-integration' ),
				'label'         => __( 'Enable Enhanced eCommerce ', 'woocommerce-google-analytics-integration' ),
				/* translators: Read more link */
				'description'   => sprintf( __( 'Enhanced eCommerce allows you to measure more user interactions with your store, including: product impressions, product detail views, starting the checkout process, adding cart items, and removing cart items. Universal Analytics or Global Site Tag must be enabled for Enhanced eCommerce to work. If using Universal Analytics, turn on Enhanced eCommerce in your Google Analytics dashboard before enabling this setting. %1$sSee here for more information%2$s.', 'woocommerce-google-analytics-integration' ), '<a href="https://support.google.com/analytics/answer/6032539?hl=en" target="_blank">', '</a>' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'no',
				'class'         => 'legacy-setting',
			),

			// Enhanced eCommerce Sub-Settings

			'ga_enhanced_remove_from_cart_enabled'    => array(
				'label'         => __( 'Remove from Cart Events', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'yes',
				'class'         => 'enhanced-setting',
			),

			'ga_enhanced_product_impression_enabled'  => array(
				'label'         => __( 'Product Impressions from Listing Pages', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'yes',
				'class'         => 'enhanced-setting',
			),

			'ga_enhanced_product_click_enabled'       => array(
				'label'         => __( 'Product Clicks from Listing Pages', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'yes',
				'class'         => 'enhanced-setting',
			),

			'ga_enhanced_product_detail_view_enabled' => array(
				'label'         => __( 'Product Detail Views', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'yes',
				'class'         => 'enhanced-setting',
			),

			'ga_enhanced_checkout_process_enabled'    => array(
				'label'         => __( 'Checkout Process Initiated', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'yes',
				'class'         => 'enhanced-setting',
			),
		);
	}

	/**
	 * Shows some additional help text after saving the Google Analytics settings
	 */
	public function show_options_info() {
		$this->method_description .= "<div class='notice notice-info'><p>" . __( 'Please allow Google Analytics 24 hours to start displaying results.', 'woocommerce-google-analytics-integration' ) . '</p></div>';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['woocommerce_google_analytics_ga_ecommerce_tracking_enabled'] ) && true === (bool) $_REQUEST['woocommerce_google_analytics_ga_ecommerce_tracking_enabled'] ) {
			$this->method_description .= "<div class='notice notice-info'><p>" . __( 'Please note, for transaction tracking to work properly, you will need to use a payment gateway that redirects the customer back to a WooCommerce order received/thank you page.', 'woocommerce-google-analytics-integration' ) . '</div>';
		}
	}

	/**
	 * Hooks into woocommerce_tracker_data and tracks some of the analytic settings (just enabled|disabled status)
	 * only if you have opted into WooCommerce tracking
	 * https://woocommerce.com/usage-tracking/
	 *
	 * @param  array $data Current WC tracker data.
	 * @return array       Updated WC Tracker data.
	 */
	public function track_options( $data ) {
		$data['wc-google-analytics'] = array(
			'standard_tracking_enabled'           => $this->ga_standard_tracking_enabled,
			'support_display_advertising'         => $this->ga_support_display_advertising,
			'support_enhanced_link_attribution'   => $this->ga_support_enhanced_link_attribution,
			'use_universal_analytics'             => $this->ga_use_universal_analytics,
			'anonymize_enabled'                   => $this->ga_anonymize_enabled,
			'ga_404_tracking_enabled'             => $this->ga_404_tracking_enabled,
			'ecommerce_tracking_enabled'          => $this->ga_ecommerce_tracking_enabled,
			'event_tracking_enabled'              => $this->ga_event_tracking_enabled,
			'gtag_enabled'                        => $this->ga_gtag_enabled,
			'set_domain_name'                     => empty( $this->ga_set_domain_name ) ? 'no' : 'yes',
			'plugin_version'                      => WC_GOOGLE_ANALYTICS_INTEGRATION_VERSION,
			'enhanced_ecommerce_tracking_enabled' => $this->ga_enhanced_ecommerce_tracking_enabled,
			'linker_allow_incoming_enabled'       => empty( $this->ga_linker_allow_incoming_enabled ) ? 'no' : 'yes',
			'linker_cross_domains'                => $this->ga_linker_cross_domains,
		);

		// ID prefix, blank, or X for unknown
		$prefix = strstr( strtoupper( $this->ga_id ), '-', true );
		if ( in_array( $prefix, array( 'UA', 'G', 'GT' ), true ) || empty( $prefix ) ) {
			$data['wc-google-analytics']['ga_id'] = $prefix;
		} else {
			$data['wc-google-analytics']['ga_id'] = 'X';
		}

		return $data;
	}

	/**
	 * Enqueue the admin JavaScript
	 */
	public function load_admin_assets() {
		$screen = get_current_screen();
		if ( 'woocommerce_page_wc-settings' !== $screen->id ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['tab'] ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( 'integration' !== $_GET['tab'] ) {
			return;
		}

		wp_enqueue_script(
			'wc-google-analytics-admin-enhanced-settings',
			Plugin::get_instance()->get_js_asset_url( 'admin-ga-settings.js' ),
			Plugin::get_instance()->get_js_asset_dependencies( 'admin-ga-settings', [ 'jquery' ] ),
			Plugin::get_instance()->get_js_asset_version( 'admin-ga-settings' ),
			true
		);
	}

	/**
	 * Display the tracking codes
	 * Acts as a controller to figure out which code to display
	 */
	public function enqueue_tracking_code() {
		global $wp;
		$display_ecommerce_tracking = false;

		if ( $this->disable_tracking( 'all' ) ) {
			return;
		}

		// Check if is order received page and stop when the products and not tracked
		if ( is_order_received_page() && 'yes' === $this->ga_ecommerce_tracking_enabled ) {
			$order_id = isset( $wp->query_vars['order-received'] ) ? $wp->query_vars['order-received'] : 0;
			$order    = wc_get_order( $order_id );
			if ( $order && ! (bool) $order->get_meta( '_ga_tracked' ) ) {
				$display_ecommerce_tracking = true;
				$this->enqueue_ecommerce_tracking_code( $order_id );
			}
		}

		if ( is_woocommerce() || is_cart() || ( is_checkout() && ! $display_ecommerce_tracking ) ) {
			$display_ecommerce_tracking = true;
			$this->enqueue_standard_tracking_code();
		}

		if ( ! $display_ecommerce_tracking && 'yes' === $this->ga_standard_tracking_enabled ) {
			$this->enqueue_standard_tracking_code();
		}
	}

	/**
	 * Generate Standard Google Analytics tracking
	 */
	protected function enqueue_standard_tracking_code() {
		$this->get_tracking_instance()->load_opt_out();
		$this->get_tracking_instance()->load_analytics();
	}

	/**
	 * Generate eCommerce tracking code
	 *
	 * @param int $order_id The Order ID for adding a transaction.
	 */
	protected function enqueue_ecommerce_tracking_code( $order_id ) {
		// Get the order and output tracking code.
		$order = wc_get_order( $order_id );

		// Make sure we have a valid order object.
		if ( ! $order ) {
			return;
		}

		// Check order key.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$order_key = empty( $_GET['key'] ) ? '' : wc_clean( wp_unslash( $_GET['key'] ) );
		if ( ! $order->key_is_valid( $order_key ) ) {
			return;
		}

		$this->get_tracking_instance()->add_transaction( $order );
		$this->get_tracking_instance()->load_opt_out();
		$this->get_tracking_instance()->load_analytics();

		// Mark the order as tracked.
		$order->update_meta_data( '_ga_tracked', 1 );
		$order->save();
	}

	/**
	 * Check if tracking is disabled
	 *
	 * @param  string $type The setting to check
	 * @return bool         True if tracking for a certain setting is disabled
	 */
	private function disable_tracking( $type ) {
		return is_admin() || current_user_can( 'manage_options' ) || ( ! $this->ga_id ) || 'no' === $type || apply_filters( 'woocommerce_ga_disable_tracking', false, $type );
	}

	/**
	 * Google Analytics event tracking for single product add to cart
	 */
	public function add_to_cart() {
		if ( $this->disable_tracking( $this->ga_event_tracking_enabled ) ) {
			return;
		}
		if ( ! is_single() ) {
			return;
		}

		global $product;

		if ( 'yes' === $this->ga_gtag_enabled ) {
			$this->get_tracking_instance()->add_to_cart( $product );
			return;
		}

		// Add single quotes to allow jQuery to be substituted into _trackEvent parameters
		$parameters             = array();
		$parameters['category'] = "'" . __( 'Products', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['action']   = "'" . __( 'Add to Cart', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['label']    = "'" . esc_js( $product->get_sku() ? __( 'ID:', 'woocommerce-google-analytics-integration' ) . ' ' . $product->get_sku() : '#' . $product->get_id() ) . "'";

		if ( ! $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {

			$item = '{';

			if ( $product->is_type( 'variable' ) ) {
				$item .= "'id': google_analytics_integration_product_data[ $('input[name=\"variation_id\"]').val() ] !== undefined ? google_analytics_integration_product_data[ $('input[name=\"variation_id\"]').val() ].id : false,";
				$item .= "'variant': google_analytics_integration_product_data[ $('input[name=\"variation_id\"]').val() ] !== undefined ? google_analytics_integration_product_data[ $('input[name=\"variation_id\"]').val() ].variant : false,";
			} else {
				$item .= "'id': '" . $this->get_tracking_instance()->get_product_identifier( $product ) . "',";
			}

			$item .= "'name': '" . esc_js( $product->get_title() ) . "',";
			$item .= "'category': " . $this->get_tracking_instance()->product_get_category_line( $product );
			$item .= "'quantity': $( 'input.qty' ).val() ? $( 'input.qty' ).val() : '1'";
			$item .= '}';

			$parameters['item'] = $item;

			$code                   = $this->get_tracking_instance()->tracker_var() . "( 'ec:addProduct', {$item} );";
			$parameters['enhanced'] = $code;
		}

		$this->get_tracking_instance()->event_tracking_code( $parameters, '.single_add_to_cart_button' );
	}

	/**
	 * Enhanced Analytics event tracking for removing a product from the cart
	 */
	public function remove_from_cart() {
		if ( ! $this->enhanced_ecommerce_enabled( $this->ga_enhanced_remove_from_cart_enabled ) ) {
			return;
		}

		$this->get_tracking_instance()->remove_from_cart();
	}

	/**
	 * Adds the product ID and SKU to the remove product link if not present
	 *
	 * @param  string $url
	 * @param  string $key
	 * @return string
	 */
	public function remove_from_cart_attributes( $url, $key ) {
		if ( strpos( $url, 'data-product_id' ) !== false ) {
			return $url;
		}

		if ( ! is_object( WC()->cart ) ) {
			return $url;
		}

		$item    = WC()->cart->get_cart_item( $key );
		$product = $item['data'];

		if ( ! is_object( $product ) ) {
			return $url;
		}

		$url = str_replace( 'href=', 'data-product_id="' . esc_attr( $product->get_id() ) . '" data-product_sku="' . esc_attr( $product->get_sku() ) . '" href=', $url );
		return $url;
	}

	/**
	 * Google Analytics event tracking for loop add to cart
	 */
	public function loop_add_to_cart() {
		if ( $this->disable_tracking( $this->ga_event_tracking_enabled ) || 'yes' === $this->ga_gtag_enabled ) {
			return;
		}

		// Add single quotes to allow jQuery to be substituted into _trackEvent parameters
		$parameters             = array();
		$parameters['category'] = "'" . __( 'Products', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['action']   = "'" . __( 'Add to Cart', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['label']    = "($(this).data('product_sku')) ? ($(this).data('product_sku')) : ('#' + $(this).data('product_id'))"; // Product SKU or ID

		if ( ! $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			$item               = '{';
			$item              .= "'id': ($(this).data('product_sku')) ? ($(this).data('product_sku')) : ('#' + $(this).data('product_id')),";
			$item              .= "'quantity': $(this).data('quantity')";
			$item              .= '}';
			$parameters['item'] = $item;

			$code                   = $this->get_tracking_instance()->tracker_var() . "( 'ec:addProduct', " . $item . ' );';
			$parameters['enhanced'] = $code;
		}

		$this->get_tracking_instance()->event_tracking_code( $parameters, '.add_to_cart_button:not(.product_type_variable, .product_type_grouped)' );
	}

	/**
	 * Determine if the conditions are met for enhanced ecommerce interactions to be displayed.
	 * Currently checks if Global Tags OR Universal Analytics are enabled, plus Enhanced eCommerce.
	 *
	 * @param  array $extra_checks Any extra option values that should be 'yes' to proceed
	 * @return bool                Whether enhanced ecommerce transactions can be displayed.
	 */
	protected function enhanced_ecommerce_enabled( $extra_checks = [] ) {
		if ( ! is_array( $extra_checks ) ) {
			$extra_checks = [ $extra_checks ];
		}

		// False if gtag and UA are disabled.
		if ( $this->disable_tracking( $this->ga_use_universal_analytics ) && $this->disable_tracking( $this->ga_gtag_enabled ) ) {
			return false;
		}

		// False if gtag or UA is enabled, but enhanced ecommerce is disabled.
		if ( $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			return false;
		}

		// False if any specified interaction-level checks are disabled.
		foreach ( $extra_checks as $option_value ) {
			if ( $this->disable_tracking( $option_value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Measures a listing impression (from search results)
	 */
	public function listing_impression() {
		if ( ! $this->enhanced_ecommerce_enabled( $this->ga_enhanced_product_impression_enabled ) ) {
			return;
		}

		global $product, $woocommerce_loop;
		$this->get_tracking_instance()->listing_impression( $product, $woocommerce_loop['loop'] );
	}

	/**
	 * Measure a product click from a listing page
	 */
	public function listing_click() {
		if ( ! $this->enhanced_ecommerce_enabled( $this->ga_enhanced_product_click_enabled ) ) {
			return;
		}

		global $product, $woocommerce_loop;
		$this->get_tracking_instance()->listing_click( $product, $woocommerce_loop['loop'] );
	}

	/**
	 * Measure a product detail view
	 */
	public function product_detail() {
		if ( ! $this->enhanced_ecommerce_enabled( $this->ga_enhanced_product_detail_view_enabled ) ) {
			return;
		}

		global $product;
		$this->get_tracking_instance()->product_detail( $product );
	}

	/**
	 * Tracks when the checkout form is loaded
	 *
	 * @param mixed $checkout (unused)
	 */
	public function checkout_process( $checkout ) {
		if ( ! $this->enhanced_ecommerce_enabled( $this->ga_enhanced_checkout_process_enabled ) ) {
			return;
		}

		$this->get_tracking_instance()->checkout_process( WC()->cart->get_cart() );
	}

	/**
	 * Add the utm_nooverride parameter to any return urls. This makes sure Google Adwords doesn't mistake the offsite gateway as the referrer.
	 *
	 * @param  string $return_url WooCommerce Return URL
	 * @return string URL
	 */
	public function utm_nooverride( $return_url ) {
		// We don't know if the URL already has the parameter so we should remove it just in case
		$return_url = remove_query_arg( 'utm_nooverride', $return_url );

		// Now add the utm_nooverride query arg to the URL
		$return_url = add_query_arg( 'utm_nooverride', '1', $return_url );

		return esc_url( $return_url, null, 'db' );
	}

	/**
	 * Check if the Google Analytics Tracking ID has been set up.
	 *
	 * @since x.x.x
	 *
	 * @return bool Whether the Google Analytics setup is completed.
	 */
	public function is_setup_complete() {
		return (bool) $this->get_option( 'ga_id' );
	}


	/**
	 * Adds the setup task to the Tasklists.
	 *
	 * @since x.x.x
	 */
	public function add_wc_setup_task() {
		require_once 'class-wc-google-analytics-task.php';

		TaskLists::add_task(
			'extended',
			new WC_Google_Analytics_Task(
				TaskLists::get_list( 'extended' )
			)
		);

	}

	/**
	 * Add async to script tags with defined handles.
	 *
	 * @param string $tag HTML for the script tag.
	 * @param string $handle Handle of the script.
	 * @param string $src Src of the script.
	 *
	 * @return string
	 */
	public function async_script_loader_tags( $tag, $handle, $src ) {
		if ( ! in_array( $handle, self::ASYNC_SCRIPT_HANDLES, true ) ) {
			return $tag;
		}

		return str_replace( '<script src', '<script async src', $tag );
	}
}
