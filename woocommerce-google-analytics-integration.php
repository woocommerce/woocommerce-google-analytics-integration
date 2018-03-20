<?php
/**
 * Plugin Name: WooCommerce Google Analytics Integration
 * Plugin URI: http://wordpress.org/plugins/woocommerce-google-analytics-integration/
 * Description: Allows Google Analytics tracking code to be inserted into WooCommerce store pages.
 * Author: WooCommerce
 * Author URI: https://woocommerce.com
 * Version: 1.4.4
 * WC requires at least: 2.1
 * WC tested up to: 3.3
 * License: GPLv2 or later
 * Text Domain: woocommerce-google-analytics-integration
 * Domain Path: languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Google_Analytics_Integration' ) ) {

	/**
	 * WooCommerce Google Analytics Integration main class.
	 */
	class WC_Google_Analytics_Integration {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		const VERSION = '1.4.4';

		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Initialize the plugin.
		 */
		private function __construct() {
			// Load plugin text domain
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			// Checks with WooCommerce is installed.
			if ( class_exists( 'WC_Integration' ) && defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.1-beta-1', '>=' ) ) {
				include_once 'includes/class-wc-google-analytics.php';

				// Register the integration.
				add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_links' ) );
		}

		public function plugin_links( $links ) {
			$settings_url = add_query_arg(
				array(
					'page' => 'wc-settings',
					'tab' => 'integration',
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
		 * @return object A single instance of this class.
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
		 *
		 * @return void
		 */
		public function load_plugin_textdomain() {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-google-analytics-integration' );

			load_textdomain( 'woocommerce-google-analytics-integration', trailingslashit( WP_LANG_DIR ) . 'woocommerce-google-analytics-integration/woocommerce-google-analytics-integration-' . $locale . '.mo' );
			load_plugin_textdomain( 'woocommerce-google-analytics-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * WooCommerce fallback notice.
		 *
		 * @return string
		 */
		public function woocommerce_missing_notice() {
			echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Google Analytics depends on the last version of %s to work!', 'woocommerce-google-analytics-integration' ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __( 'WooCommerce', 'woocommerce-google-analytics-integration' ) . '</a>' ) . '</p></div>';
		}

		/**
		 * Add a new integration to WooCommerce.
		 *
		 * @param  array $integrations WooCommerce integrations.
		 *
		 * @return array               Google Analytics integration.
		 */
		public function add_integration( $integrations ) {
			$integrations[] = 'WC_Google_Analytics';

			return $integrations;
		}
	}

	add_action( 'plugins_loaded', array( 'WC_Google_Analytics_Integration', 'get_instance' ), 0 );

}
