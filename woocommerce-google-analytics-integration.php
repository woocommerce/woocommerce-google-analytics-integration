<?php
/**
 * Plugin Name: WooCommerce Google Analytics Integration
 * Plugin URI: https://wordpress.org/plugins/woocommerce-google-analytics-integration/
 * Description: Allows Google Analytics tracking code to be inserted into WooCommerce store pages.
 * Author: WooCommerce
 * Author URI: https://woocommerce.com
 * Version: 1.5.4
 * WC requires at least: 3.2
 * WC tested up to: 5.9
 * Tested up to: 5.8
 * License: GPLv2 or later
 * Text Domain: woocommerce-google-analytics-integration
 * Domain Path: languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Google_Analytics_Integration' ) ) {

	define( 'WC_GOOGLE_ANALYTICS_INTEGRATION_VERSION', '1.5.4' ); // WRCS: DEFINED_VERSION.

	// Maybe show the GA Pro notice on plugin activation.
	register_activation_hook(
		__FILE__,
		function () {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}
			WC_Google_Analytics_Integration::get_instance()->maybe_show_ga_pro_notices();
		}
	);

	/**
	 * WooCommerce Google Analytics Integration main class.
	 */
	class WC_Google_Analytics_Integration {

		/** @var WC_Google_Analytics_Integration $instance Instance of this class. */
		protected static $instance = null;

		/**
		 * Initialize the plugin.
		 */
		public function __construct() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}

			// Load plugin text domain
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			// Track completed orders and determine whether the GA Pro notice should be displayed.
			add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_show_ga_pro_notices' ) );

			// Checks which WooCommerce is installed.
			if ( class_exists( 'WC_Integration' ) && defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '3.2', '>=' ) ) {
				include_once 'includes/class-wc-google-analytics.php';

				// Register the integration.
				add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_links' ) );
		}

		/**
		 * Add links on the plugins page (Settings & Support)
		 *
		 * @param  array $links Default links
		 * @return array        Default + added links
		 */
		public function plugin_links( $links ) {
			$settings_url = add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => 'integration',
					'section' => 'google_analytics',
				),
				admin_url( 'admin.php' )
			);

			$plugin_links = array(
				'<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'woocommerce-google-analytics-integration' ) . '</a>',
				'<a href="https://wordpress.org/support/plugin/woocommerce-google-analytics-integration">' . __( 'Support', 'woocommerce-google-analytics-integration' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Return an instance of this class.
		 *
		 * @return WC_Google_Analytics_Integration A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Load the plugin text domain for translation.
		 */
		public function load_plugin_textdomain() {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-google-analytics-integration' );

			load_textdomain( 'woocommerce-google-analytics-integration', trailingslashit( WP_LANG_DIR ) . 'woocommerce-google-analytics-integration/woocommerce-google-analytics-integration-' . $locale . '.mo' );
			load_plugin_textdomain( 'woocommerce-google-analytics-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * WooCommerce fallback notice.
		 */
		public function woocommerce_missing_notice() {
			echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Google Analytics depends on the last version of %s to work!', 'woocommerce-google-analytics-integration' ), '<a href="https://woocommerce.com/" target="_blank">' . __( 'WooCommerce', 'woocommerce-google-analytics-integration' ) . '</a>' ) . '</p></div>';
		}

		/**
		 * Add a new integration to WooCommerce.
		 *
		 * @param  array $integrations WooCommerce integrations.
		 * @return array               Google Analytics integration added.
		 */
		public function add_integration( $integrations ) {
			$integrations[] = 'WC_Google_Analytics';

			return $integrations;
		}

		/**
		 * Logic for Google Analytics Pro notices.
		 */
		public function maybe_show_ga_pro_notices() {
			// Notice was already shown
			if ( get_option( 'woocommerce_google_analytics_pro_notice_shown', false ) ) {
				return;
			}

			$completed_orders = wc_orders_count( 'completed' );

			// Only show the notice if there are 10 <= completed orders <= 100.
			if ( $completed_orders < 10 || $completed_orders > 100 ) {
				update_option( 'woocommerce_google_analytics_pro_notice_shown', true );

				return;
			}

			$notice_html  = '<strong>' . esc_html__( 'Get detailed insights into your sales with Google Analytics Pro', 'woocommerce-google-analytics-integration' ) . '</strong><br><br>';

			/* translators: 1: href link to GA pro */
			$notice_html .= sprintf( __( 'Add advanced tracking for your sales funnel, coupons and more. [<a href="%s" target="_blank">Learn more</a> &gt;]', 'woocommerce-google-analytics-integration' ), 'https://woocommerce.com/products/woocommerce-google-analytics-pro/?utm_source=woocommerce-google-analytics-integration&utm_medium=product&utm_campaign=google%20analytics%20free%20to%20pro%20extension%20upsell' );

			WC_Admin_Notices::add_custom_notice( 'woocommerce_google_analytics_pro_notice', $notice_html );
			update_option( 'woocommerce_google_analytics_pro_notice_shown', true );
		}
	}

	add_action( 'plugins_loaded', array( 'WC_Google_Analytics_Integration', 'get_instance' ), 0 );

}
