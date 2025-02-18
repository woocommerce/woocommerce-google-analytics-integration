<?php
/**
 * Plugin Name: Google Analytics for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/woocommerce-google-analytics-integration/
 * Description: Allows Google Analytics tracking code to be inserted into WooCommerce store pages.
 * Author: WooCommerce
 * Author URI: https://woocommerce.com
 * Version: 2.1.13
 * WC requires at least: 8.4
 * WC tested up to: 9.7
 * Requires at least: 6.2
 * Requires Plugins: woocommerce
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woocommerce-google-analytics-integration
 * Domain Path: /languages
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Google_Analytics_Integration' ) ) {

	define( 'WC_GOOGLE_ANALYTICS_INTEGRATION_VERSION', '2.1.13' ); // WRCS: DEFINED_VERSION.
	define( 'WC_GOOGLE_ANALYTICS_INTEGRATION_MIN_WC_VER', '6.8' );

	// Maybe show the GA Pro notice on plugin activation.
	register_activation_hook(
		__FILE__,
		function () {
			WC_Google_Analytics_Integration::get_instance()->maybe_show_ga_pro_notices();
			WC_Google_Analytics_Integration::get_instance()->maybe_set_defaults();
		}
	);

	// HPOS compatibility declaration.
	add_action(
		'before_woocommerce_init',
		function () {
			if ( class_exists( FeaturesUtil::class ) ) {
				FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
				FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__ );
				FeaturesUtil::declare_compatibility( 'product_block_editor', __FILE__ );
			}
		}
	);

	/**
	 * Google Analytics for WooCommerce main class.
	 */
	class WC_Google_Analytics_Integration {

		/** @var WC_Google_Analytics_Integration $instance Instance of this class. */
		protected static $instance = null;

		/**
		 * Initialize the plugin.
		 */
		public function __construct() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
				return;
			}

			// Load plugin text domain
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			// Track completed orders and determine whether the GA Pro notice should be displayed.
			add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_show_ga_pro_notices' ) );

			// Checks which WooCommerce is installed.
			if ( class_exists( 'WC_Integration' ) && defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, WC_GOOGLE_ANALYTICS_INTEGRATION_MIN_WC_VER, '>=' ) ) {
				include_once 'includes/class-wc-google-analytics.php';

				// Register the integration.
				add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_links' ) );
		}

		/**
		 * Gets the settings page URL.
		 *
		 * @since 1.5.17
		 *
		 * @return string Settings URL
		 */
		public function get_settings_url() {
			return add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => 'integration',
					'section' => 'google_analytics',
				),
				admin_url( 'admin.php' )
			);
		}

		/**
		 * Add links on the plugins page (Settings & Support)
		 *
		 * @param  array $links Default links
		 * @return array        Default + added links
		 */
		public function plugin_links( $links ) {
			$settings_url = $this->get_settings_url();
			$support_url  = 'https://wordpress.org/support/plugin/woocommerce-google-analytics-integration';
			$docs_url     = 'https://woocommerce.com/document/google-analytics-integration/?utm_source=wordpress&utm_medium=all-plugins-page&utm_campaign=doc-link&utm_content=woocommerce-google-analytics-integration';

			$plugin_links = array(
				'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'woocommerce-google-analytics-integration' ) . '</a>',
				'<a href="' . esc_url( $support_url ) . '">' . esc_html__( 'Support', 'woocommerce-google-analytics-integration' ) . '</a>',
				'<a href="' . esc_url( $docs_url ) . '">' . esc_html__( 'Documentation', 'woocommerce-google-analytics-integration' ) . '</a>',
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
			if ( null === self::$instance ) {
				self::$instance = new self();
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
			if ( defined( 'WOOCOMMERCE_VERSION' ) ) {
				/* translators: 1 is the required component, 2 the Woocommerce version */
				$error = sprintf( __( 'Google Analytics for WooCommerce requires WooCommerce version %1$s or higher. You are using version %2$s', 'woocommerce-google-analytics-integration' ), WC_GOOGLE_ANALYTICS_INTEGRATION_MIN_WC_VER, WOOCOMMERCE_VERSION );
			} else {
				/* translators: 1 is the required component */
				$error = sprintf( __( 'Google Analytics for WooCommerce requires WooCommerce version %1$s or higher.', 'woocommerce-google-analytics-integration' ), WC_GOOGLE_ANALYTICS_INTEGRATION_MIN_WC_VER );
			}

			echo '<div class="error"><p><strong>' . wp_kses_post( $error ) . '</strong></p></div>';
		}

		/**
		 * Add a new integration to WooCommerce.
		 *
		 * @param  array $integrations WooCommerce integrations.
		 * @return array               Google Analytics for WooCommerce added.
		 */
		public function add_integration( $integrations ) {
			$integrations[] = 'WC_Google_Analytics';

			return $integrations;
		}

		/**
		 * Get Google Analytics Integration
		 *
		 * @since 1.5.17
		 *
		 * @return WC_Google_Analytics The Google Analytics integration.
		 */
		public static function get_integration() {
			return \WooCommerce::instance()->integrations->get_integration( 'google_analytics' );
		}


		/**
		 * Logic for Google Analytics Pro notices.
		 */
		public function maybe_show_ga_pro_notices() {
			// Notice was already shown
			if ( ! class_exists( 'WooCommerce' ) || get_option( 'woocommerce_google_analytics_pro_notice_shown', false ) ) {
				return;
			}

			$completed_orders = wc_orders_count( 'completed' );

			// Only show the notice if there are 10 <= completed orders <= 100.
			if ( $completed_orders < 10 || $completed_orders > 100 ) {
				update_option( 'woocommerce_google_analytics_pro_notice_shown', true );

				return;
			}

			$notice_html = '<strong>' . esc_html__( 'Get detailed insights into your sales with Google Analytics Pro', 'woocommerce-google-analytics-integration' ) . '</strong><br><br>';

			/* translators: 1: href link to GA pro */
			$notice_html .= sprintf( __( 'Add advanced tracking for your sales funnel, coupons and more. [<a href="%s" target="_blank">Learn more</a> &gt;]', 'woocommerce-google-analytics-integration' ), 'https://woocommerce.com/products/woocommerce-google-analytics-pro/?utm_source=woocommerce-google-analytics-integration&utm_medium=product&utm_campaign=google%20analytics%20free%20to%20pro%20extension%20upsell' );

			WC_Admin_Notices::add_custom_notice( 'woocommerce_google_analytics_pro_notice', $notice_html );
			update_option( 'woocommerce_google_analytics_pro_notice_shown', true );
		}

		/**
		 * Set default options during activation if no settings exist
		 *
		 * @since 2.0.0
		 *
		 * @return void
		 */
		public function maybe_set_defaults() {
			$settings_key = 'woocommerce_google_analytics_settings';

			if ( false === get_option( $settings_key, false ) ) {
				update_option(
					$settings_key,
					array(
						'ga_product_identifier' => 'product_id',
					)
				);
			}
		}

		/**
		 * Get the path to something in the plugin dir.
		 *
		 * @param string $end End of the path.
		 * @return string
		 */
		public function path( $end = '' ) {
			return untrailingslashit( __DIR__ ) . $end;
		}

		/**
		 * Get the URL to something in the plugin dir.
		 *
		 * @param string $end End of the URL.
		 *
		 * @return string
		 */
		public function url( $end = '' ) {
			return untrailingslashit( plugin_dir_url( plugin_basename( __FILE__ ) ) ) . $end;
		}

		/**
		 * Get the URL to something in the plugin JS assets build dir.
		 *
		 * @param string $end End of the URL.
		 *
		 * @return string
		 */
		public function get_js_asset_url( $end = '' ) {
			return $this->url( '/assets/js/build/' . $end );
		}

		/**
		 * Get the path to something in the plugin JS assets build dir.
		 *
		 * @param string $end End of the path.
		 * @return string
		 */
		public function get_js_asset_path( $end = '' ) {
			return $this->path( '/assets/js/build/' . $end );
		}

		/**
		 * Gets the asset.php generated file for an asset name.
		 *
		 * @param string $asset_name The name of the asset to get the file from.
		 * @return array The asset file. Or an empty array if the file doesn't exist.
		 */
		public function get_js_asset_file( $asset_name ) {
			try {
				// Exclusion reason: No reaching any user input
				// nosemgrep audit.php.lang.security.file.inclusion-arg
				return require $this->get_js_asset_path( $asset_name . '.asset.php' );
			} catch ( Exception $e ) {
				return [];
			}
		}

		/**
		 * Gets the dependencies for an assets based on its asset.php generated file.
		 *
		 * @param string $asset_name The name of the asset to get the dependencies from.
		 * @param array  $extra_dependencies Array containing extra dependencies to include in the dependency array.
		 *
		 * @return array The dependencies array. Empty array if no dependencies.
		 */
		public function get_js_asset_dependencies( $asset_name, $extra_dependencies = array() ) {
			$script_assets = $this->get_js_asset_file( $asset_name );
			$dependencies  = $script_assets['dependencies'] ?? [];
			return array_unique( array_merge( $dependencies, $extra_dependencies ) );
		}

		/**
		 * Gets the version for an assets based on its asset.php generated file.
		 *
		 * @param string $asset_name The name of the asset to get the version from.
		 * @return string|false The version. False in case no version is found.
		 */
		public function get_js_asset_version( $asset_name ) {
			$script_assets = $this->get_js_asset_file( $asset_name );
			return $script_assets['version'] ?? false;
		}
	}

	add_action( 'plugins_loaded', array( 'WC_Google_Analytics_Integration', 'get_instance' ), 0 );

}
