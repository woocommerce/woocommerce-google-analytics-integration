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

	/**
	 * Returns the proper class based on Gtag settings.
	 *
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	protected function get_tracking_instance() {
		return WC_Google_Gtag_JS::get_instance( $this->settings );
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

		add_action( 'admin_notices', array( $this, 'universal_analytics_upgrade_notice' ) );

		include_once 'class-wc-abstract-google-analytics-js.php';
		include_once 'class-wc-google-gtag-js.php';

		if ( ! $this->disable_tracking( 'all' ) ) {
			$this->get_tracking_instance();
		}

		// Display a task on  "Things to do next section"
		add_action( 'init', array( $this, 'add_wc_setup_task' ), 20 );
		// Admin Options
		add_filter( 'woocommerce_tracker_data', array( $this, 'track_settings' ) );
		add_action( 'woocommerce_update_options_integration_google_analytics', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_integration_google_analytics', array( $this, 'show_options_info' ) );
		add_action( 'admin_init', array( $this, 'privacy_policy' ) );

		// utm_nooverride parameter for Google AdWords
		add_filter( 'woocommerce_get_return_url', array( $this, 'utm_nooverride' ) );

		// Mark extension as compatible with WP Consent API
		add_filter( 'wp_consent_api_registered_' . plugin_basename( __FILE__ ), '__return_true' );

		// Dequeue the WooCommerce Blocks Google Analytics integration,
		// not to let it register its `gtag` function so that we could provide a more detailed configuration.
		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_dequeue_script( 'wc-blocks-google-analytics' );
			}
		);
	}

	/**
	 * Conditionally display an error notice to the merchant if the stored property ID starts with "UA"
	 *
	 * @return void
	 */
	public function universal_analytics_upgrade_notice() {
		if ( 'ua' === substr( strtolower( $this->get_option( 'ga_id' ) ), 0, 2 ) ) {
			printf(
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
			'ga_support_display_advertising'          => array(
				'label'         => __( '"Display Advertising" Support', 'woocommerce-google-analytics-integration' ),
				/* translators: Read more link */
				'description'   => sprintf( __( 'Set the Google Analytics code to support Display Advertising. %1$sRead more about Display Advertising%2$s.', 'woocommerce-google-analytics-integration' ), '<a href="https://support.google.com/analytics/answer/2700409" target="_blank">', '</a>' ),
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'default'       => get_option( 'woocommerce_ga_support_display_advertising' ) ? get_option( 'woocommerce_ga_support_display_advertising' ) : 'yes', // Backwards compat
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
	public function track_settings( $data ) {
		$settings                    = $this->settings;
		$data['wc-google-analytics'] = array(
			'support_display_advertising'   => $settings['ga_support_display_advertising'],
			'ga_404_tracking_enabled'       => $settings['ga_404_tracking_enabled'],
			'ecommerce_tracking_enabled'    => $settings['ga_ecommerce_tracking_enabled'],
			'event_tracking_enabled'        => $settings['ga_event_tracking_enabled'],
			'plugin_version'                => WC_GOOGLE_ANALYTICS_INTEGRATION_VERSION,
			'linker_allow_incoming_enabled' => empty( $settings['ga_linker_allow_incoming_enabled'] ) ? 'no' : 'yes',
			'linker_cross_domains'          => $settings['ga_linker_cross_domains'],
		);

		// ID prefix, blank, or X for unknown
		$prefix = strstr( strtoupper( $settings['ga_id'] ), '-', true );
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
	 * Check if tracking is disabled
	 *
	 * @param  string $type The setting to check
	 * @return bool         True if tracking for a certain setting is disabled
	 */
	private function disable_tracking( $type ) {
		return is_admin() || current_user_can( 'manage_options' ) || empty( $this->settings['ga_id'] ) || 'no' === $type || apply_filters( 'woocommerce_ga_disable_tracking', false, $type );
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
}
