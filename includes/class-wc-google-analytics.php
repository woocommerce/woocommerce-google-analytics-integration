<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists;

/**
 * Google Analytics for WooCommerce
 *
 * Allows tracking code to be inserted into store pages.
 *
 * @class   WC_Google_Analytics
 * @extends WC_Integration
 */
class WC_Google_Analytics extends WC_Integration {

	/** @var string $ga_id Google Analytics Tracking ID */
	public $ga_id;

	/** @var string $ga_standard_tracking_enabled Is standard tracking enabled (yes|no) */
	public $ga_standard_tracking_enabled;

	/** @var string $ga_support_display_advertising Supports display advertising (yes|no) */
	public $ga_support_display_advertising;

	/** @var string $ga_support_enhanced_link_attribution Use enhanced link attribution (yes|no) */
	public $ga_support_enhanced_link_attribution;

	/** @var string $ga_anonymize_enabled Anonymize IP addresses (yes|no) */
	public $ga_anonymize_enabled;

	/** @var string $ga_404_tracking_enabled Track 404 errors (yes|no) */
	public $ga_404_tracking_enabled;

	/** @var string $ga_ecommerce_tracking_enabled Purchase transactions (yes|no) */
	public $ga_ecommerce_tracking_enabled;

	/** @var string $ga_enhanced_remove_from_cart_enabled Track remove from cart events (yes|no) */
	public $ga_enhanced_remove_from_cart_enabled;

	/** @var string $ga_enhanced_product_impression_enabled Track product impressions (yes|no) */
	public $ga_enhanced_product_impression_enabled;

	/** @var string $ga_enhanced_product_click_enabled Track product clicks (yes|no) */
	public $ga_enhanced_product_click_enabled;

	/** @var string $ga_enhanced_checkout_process_enabled Track checkout initiated (yes|no) */
	public $ga_enhanced_checkout_process_enabled;

	/** @var string $ga_enhanced_product_detail_view_enabled Track product detail views (yes|no) */
	public $ga_enhanced_product_detail_view_enabled;

	/** @var string $ga_event_tracking_enabled Track add to cart events (yes|no) */
	public $ga_event_tracking_enabled;

	/** @var string $ga_linker_cross_domains Domains for automatic linking */
	public $ga_linker_cross_domains;

	/** @var string $ga_linker_allow_incoming_enabled Accept incoming linker (yes|no) */
	public $ga_linker_allow_incoming_enabled;

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
		return WC_Google_Gtag_JS::get_instance( $options );
	}

	/**
	 * Constructor
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->id                 = 'google_analytics';
		$this->method_title       = __( 'Google Analytics', 'woocommerce-google-analytics-integration' );
		$this->method_description = __( 'Google Analytics is a free service offered by Google that generates detailed statistics about the visitors to a website.', 'woocommerce-google-analytics-integration' );

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();
		$constructor = $this->init_options();

		add_action( 'admin_notices', array( $this, 'universal_analytics_upgrade_notice' ) );

		// Contains snippets/JS tracking code
		include_once 'class-wc-abstract-google-analytics-js.php';
		include_once 'class-wc-google-gtag-js.php';
		$this->get_tracking_instance( $constructor );

		// Display a task on  "Things to do next section"
		add_action( 'init', array( $this, 'add_wc_setup_task' ), 20 );
		// Admin Options
		add_filter( 'woocommerce_tracker_data', array( $this, 'track_options' ) );
		add_action( 'woocommerce_update_options_integration_google_analytics', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_integration_google_analytics', array( $this, 'show_options_info' ) );
		add_action( 'admin_init', array( $this, 'privacy_policy' ) );

		// Tracking code
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking_code' ), 9 );
		add_filter( 'script_loader_tag', array( $this, 'async_script_loader_tags' ), 10, 3 );

		// utm_nooverride parameter for Google AdWords
		add_filter( 'woocommerce_get_return_url', array( $this, 'utm_nooverride' ) );

		// Dequeue the WooCommerce Blocks Google Analytics integration,
		// not to let it register its `gtag` function so that we could provide a more detailed configuration.
		add_action( 'wp_enqueue_scripts', function() {
			wp_dequeue_script( 'wc-blocks-google-analytics' );
		});

	}

	/**
	 * Conditionally display an error notice to the merchant if the stored property ID starts with "UA"
	 *
	 * @return void
	 */
	public function universal_analytics_upgrade_notice() {
		if ( 'ua' === substr( strtolower( $this->get_option( 'ga_id' ) ), 0, 2 ) ) {
			echo sprintf(
				'<div class="%1$s"><p>%2$s</p></div>',
				'notice notice-error',
				sprintf(
					/* translators: 1) URL for Google documentation on upgrading from UA to GA4 2) URL to WooCommerce Google Analytics settings page */
					esc_html__( 'Your website is configured to use Universal Analytics which Google retired in July of 2023. Update your account using the %1$ssetup assistant%2$s and then update your %3$sWooCommerce settings%4$s.', 'woocommerce-google-analytics-integration' ),
					'<a href="https://support.google.com/analytics/answer/9744165" target="_blank">',
					'</a>',
					'<a href="/wp-admin/admin.php?page=wc-settings&tab=integration&section=google_analytics">',
					'</a>'
				)
			);
		}
	}

	/**
	 * Loads all of our options for this plugin (stored as properties as well)
	 *
	 * @return array An array of options that can be passed to other classes
	 */
	public function init_options() {
		$options = array(
			'ga_product_identifier'                   => 'product_sku',
			'ga_id'                                   => null,
			'ga_standard_tracking_enabled'            => null,
			'ga_support_display_advertising'          => null,
			'ga_support_enhanced_link_attribution'    => null,
			'ga_anonymize_enabled'                    => null,
			'ga_404_tracking_enabled'                 => null,
			'ga_enhanced_remove_from_cart_enabled'    => null,
			'ga_enhanced_product_impression_enabled'  => null,
			'ga_enhanced_product_click_enabled'       => null,
			'ga_enhanced_checkout_process_enabled'    => null,
			'ga_enhanced_product_detail_view_enabled' => null,
			'ga_event_tracking_enabled'               => null,
			'ga_linker_cross_domains'                 => null,
			'ga_linker_allow_incoming_enabled'        => null,
		);

		$constructor = array();
		foreach ( $options as $option => $default ) {
			$constructor[ $option ] = $this->$option = $this->get_option( $option, $default );
		}

		return $constructor;
	}

	/**
	 * Tells WooCommerce which settings to display under the "integration" tab
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'ga_product_identifier'                   => array(
				'title'       => __( 'Product Identification', 'woocommerce-google-analytics-integration' ),
				'description' => __( 'Specify how your products will be identified to Google Analytics. Changing this setting may cause issues with historical data if a product was previously identified using a different structure.', 'woocommerce-google-analytics-integration' ),
				'type'        => 'select',
				'options'     => array(
					'product_id'  => __( 'Product ID', 'woocommerce-google-analytics-integration' ),
					'product_sku' => __( 'Product SKU with prefixed (#) ID as fallback', 'woocommerce-google-analytics-integration' ),
				),
				// If the option is not set then the product SKU is used as default for existing installations
				'default'     => 'product_sku',
			),
			'ga_id'                                   => array(
				'title'       => __( 'Google Analytics Tracking ID', 'woocommerce-google-analytics-integration' ),
				'description' => __( 'Log into your Google Analytics account to find your ID. e.g. <code>GT-XXXXX</code> or <code>G-XXXXX</code>', 'woocommerce-google-analytics-integration' ),
				'type'        => 'text',
				'placeholder' => 'GT-XXXXX',
				'default'     => get_option( 'woocommerce_ga_id' ), // Backwards compat
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
			'ga_linker_allow_incoming_enabled'        => array(
				'label'         => __( 'Accept Incoming Linker Parameters', 'woocommerce-google-analytics-integration' ),
				'description'   => __( 'Enabling this option will allow incoming linker parameters from other websites.', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'no',
			),
			'ga_ecommerce_tracking_enabled'           => array(
				'title'         => __( 'Event Tracking', 'woocommerce-google-analytics-integration' ),
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
			'ga_enhanced_remove_from_cart_enabled'    => array(
				'label'         => __( 'Remove from Cart Events', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'yes',
			),

			'ga_enhanced_product_impression_enabled'  => array(
				'label'         => __( 'Product Impressions from Listing Pages', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'yes',
			),

			'ga_enhanced_product_click_enabled'       => array(
				'label'         => __( 'Product Clicks from Listing Pages', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'yes',
			),

			'ga_enhanced_product_detail_view_enabled' => array(
				'label'         => __( 'Product Detail Views', 'woocommerce-google-analytics-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => 'yes',
			),

			'ga_enhanced_checkout_process_enabled'    => array(
				'label'         => __( 'Checkout Process Initiated', 'woocommerce-google-analytics-integration' ),
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
			'standard_tracking_enabled'         => $this->ga_standard_tracking_enabled,
			'support_display_advertising'       => $this->ga_support_display_advertising,
			'support_enhanced_link_attribution' => $this->ga_support_enhanced_link_attribution,
			'anonymize_enabled'                 => $this->ga_anonymize_enabled,
			'ga_404_tracking_enabled'           => $this->ga_404_tracking_enabled,
			'ecommerce_tracking_enabled'        => $this->ga_ecommerce_tracking_enabled,
			'event_tracking_enabled'            => $this->ga_event_tracking_enabled,
			'plugin_version'                    => WC_GOOGLE_ANALYTICS_INTEGRATION_VERSION,
			'linker_allow_incoming_enabled'     => empty( $this->ga_linker_allow_incoming_enabled ) ? 'no' : 'yes',
			'linker_cross_domains'              => $this->ga_linker_cross_domains,
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
	 * Add suggested privacy policy content
	 *
	 * @return void
	 */
	public function privacy_policy() {
		$policy_text = sprintf(
			/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
			esc_html__( 'By using this extension, you may be storing personal data or sharing data with an external service. %1$sLearn more about what data is collected by Google and what you may want to include in your privacy policy%2$s.', 'woocommerce-google-analytics-integration' ),
			'<a href="https://support.google.com/analytics/answer/7318509" target="_blank">',
			'</a>'
		);

		// As the extension doesn't offer suggested privacy policy text, the button to copy it is hidden.
		$content = '
			<p class="privacy-policy-tutorial">' . $policy_text . '</p>
			<style>#privacy-settings-accordion-block-woocommerce-google-analytics-integration .privacy-settings-accordion-actions { display: none }</style>';

		wp_add_privacy_policy_content( 'Google Analytics for WooCommerce', wpautop( $content, false ) );
	}

	/**
	 * Display the tracking codes
	 * Acts as a controller to figure out which code to display
	 */
	public function enqueue_tracking_code() {
		global $wp;
		$display_ecommerce_tracking = false;

		$this->get_tracking_instance()->load_opt_out();

		if ( $this->disable_tracking( 'all' ) ) {
			return;
		}

		// Check if is order received page and stop when the products and not tracked
		if ( is_order_received_page() ) {
			$order_id = isset( $wp->query_vars['order-received'] ) ? $wp->query_vars['order-received'] : 0;
			$order    = wc_get_order( $order_id );
			if ( $order && ! (bool) $order->get_meta( '_ga_tracked' ) ) {
				$display_ecommerce_tracking = true;
				$this->enqueue_ecommerce_tracking_code( $order_id );
			}
		}
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
	 * @since 1.5.17
	 *
	 * @return bool Whether the Google Analytics setup is completed.
	 */
	public function is_setup_complete() {
		return (bool) $this->get_option( 'ga_id' );
	}


	/**
	 * Adds the setup task to the Tasklists.
	 *
	 * @since 1.5.17
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

		// Check if the script has the async attribute already. If so, don't add it again.
		$has_async_tag = preg_match( '/\basync\b/', $tag );
		if ( ! empty( $has_async_tag ) ) {
			return $tag;
		}

		// Add the async attribute
		return str_replace( ' src', ' async src', $tag );
	}
}
