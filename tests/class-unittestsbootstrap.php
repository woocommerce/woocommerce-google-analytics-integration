<?php

namespace GoogleAnalyticsIntegration;

/**
 * Class to setup WooCommerce test environment for unit testing
 */
class UnitTestsBootstrap {

	/** @var string directory where wordpress-tests-lib is installed */
	public $wp_tests_dir;

	/** @var string testing directory */
	public $tests_dir;

	/** @var string plugin directory */
	public $plugin_dir;

	/** @var string plugins directory Directory storing dependency plugins */
	public $plugins_dir;

	/**
	 * Setup the unit testing environment
	 */
	public function init() {
		$this->set_show_errors();
		$this->set_path_props();
		$this->set_server_props();

		// load test function so tests_add_filter() is available
		require_once $this->wp_tests_dir . '/includes/functions.php';

		// Filter doing it wrong errors while bootstrapping.
		tests_add_filter( 'doing_it_wrong_trigger_error', [ $this, 'filter_doing_it_wrong' ], 10, 2 );

		// load WC
		tests_add_filter( 'muplugins_loaded', array( $this, 'load_plugins' ) );
		tests_add_filter( 'setup_theme', array( $this, 'install_wc' ) );
		tests_add_filter( 'option_active_plugins', [ $this, 'filter_active_plugins' ] );

		// load the WP testing environment
		require_once $this->wp_tests_dir . '/includes/bootstrap.php';

		// load testing framework
		$this->includes();
	}


	/**
	 * Show errors.
	 */
	public function set_show_errors() {
		ini_set( 'display_errors', 'on' );
		error_reporting( E_ALL );
	}

	/**
	 * Set directory paths.
	 */
	public function set_path_props() {
		$this->tests_dir    = __DIR__;
		$this->plugin_dir   = dirname( $this->tests_dir );
		$this->plugins_dir  = sys_get_temp_dir() . '/wordpress/wp-content/plugins';
		$this->wp_tests_dir = sys_get_temp_dir() . '/wordpress-tests-lib';
	}

	/**
	 * Set server props
	 */
	public function set_server_props() {
		$_SERVER['REMOTE_ADDR'] = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		$_SERVER['SERVER_NAME'] = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : 'ga_integration_test';
	}

	/**
	 * @param array $option
	 * @return array
	 */
	public function filter_active_plugins( $option ) {
		$option[] = 'woocommerce-google-analytics-integration/woocommerce-google-analytics-integration.php';
		return $option;
	}

	/**
	 * Load WooCommerce
	 */
	public function load_plugins() {
		require_once $this->plugins_dir . '/woocommerce/woocommerce.php';
		require_once $this->plugin_dir . '/woocommerce-google-analytics-integration.php';

		update_option( 'woocommerce_db_version', WC()->version );
		update_option( 'gmt_offset', -4 );
	}

	/**
	 * Load WooCommerce for testing
	 *
	 * @since 2.0
	 */
	public function install_wc() {
		echo 'Installing WooCommerce...' . PHP_EOL;

		define( 'WP_UNINSTALL_PLUGIN', true );
		define( 'WC_REMOVE_ALL_DATA', true );

		include $this->plugins_dir . '/woocommerce/uninstall.php';

		\WC_Install::install();
		new \WP_Roles();
		WC()->init();

		echo 'WooCommerce Finished Installing...' . PHP_EOL;
	}

	/**
	 * Load test cases and factories
	 *
	 * @since 2.0
	 */
	public function includes() {
		$wc_tests_dir = $this->plugins_dir . '/woocommerce/tests';

		if ( file_exists( $this->plugins_dir . '/woocommerce/tests/legacy/bootstrap.php' ) ) {
			$wc_tests_dir .= '/legacy';
		}

		require_once $wc_tests_dir . '/includes/wp-http-testcase.php';

		// Load WC Helper Functions
		require_once $wc_tests_dir . '/framework/helpers/class-wc-helper-coupon.php';
		require_once $wc_tests_dir . '/framework/helpers/class-wc-helper-product.php';
		require_once $wc_tests_dir . '/framework/helpers/class-wc-helper-order.php';
		require_once $wc_tests_dir . '/framework/helpers/class-wc-helper-shipping.php';
		require_once $wc_tests_dir . '/framework/helpers/class-wc-helper-customer.php';
	}

	/**
	 * Filter unwanted doing it wrong messages when installing WooCommerce.
	 *
	 * @since 1.8.11
	 *
	 * @param bool   $trigger Trigger error.
	 * @param string $function_name Calling function name.
	 * @return bool
	 */
	public function filter_doing_it_wrong( $trigger, $function_name ) {
		// WooCommerce requires the FeaturesController to be used after a call to `woocommerce_init`,
		// which is triggered by the WC_Install::install call. We are unable to call install later as
		// that would prevent others early hooks into the `init` call from running, so instead we ignore
		// this warning to preserve the same load order.
		if ( 'FeaturesController::get_compatible_plugins_for_feature' === $function_name ) {
			return false;
		}

		return $trigger;
	}
}
