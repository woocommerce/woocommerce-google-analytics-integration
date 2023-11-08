<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Google_Analytics_Integration as Plugin;

/**
 * WC_Google_Gtag_JS class
 *
 * JS for recording Google Gtag info
 */
class WC_Google_Gtag_JS extends WC_Abstract_Google_Analytics_JS {

	/** @var string $script_handle Handle for the front end JavaScript file */
	public $script_handle = 'woocommerce-google-analytics-integration';

	/** @var string $script_data Data required for frontend event tracking */
	private $script_data = array();

	/**
	 * Constructor
	 * Takes our options from the parent class so we can later use them in the JS snippets
	 *
	 * @param array $options Options
	 */
	public function __construct( $options = array() ) {
		self::$options = $options;

		$this->load_analytics_config();

		// Setup frontend scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
	}

	/**
	 * Register front end scripts and inline script data
	 *
	 * @return void
	 */
	public function register_scripts() {
		wp_enqueue_script(
			'google-tag-manager',
			'https://www.googletagmanager.com/gtag/js?id=' . self::get( 'ga_id' ),
			array(),
			null,
			false
		);

		wp_enqueue_script(
			$this->script_handle,
			Plugin::get_instance()->get_js_asset_url( 'actions.js' ),
			array(
				...Plugin::get_instance()->get_js_asset_dependencies( 'actions' ),
				'google-tag-manager',
			),
			Plugin::get_instance()->get_js_asset_version( 'actions' ),
			true
		);

		wp_add_inline_script(
			$this->script_handle,
			sprintf(
				'const wcgaiData = %s;',
				$this->get_script_data()
			)
		);
	}

	/**
	 * Return a JSON encoded string of all script data for the current page load
	 *
	 * @return string
	 */
	public function get_script_data(): string {
		return wp_json_encode( $this->script_data );
	}

	/**
	 * Returns the tracker variable this integration should use
	 *
	 * @return string
	 */
	public static function tracker_var(): string {
		return apply_filters( 'woocommerce_gtag_tracker_variable', 'gtag' );
	}

	/**
	 * Add Google Analytics configuration data to the script data
	 *
	 * @return void
	 */
	public function load_analytics_config() {
		$this->script_data['config'] = array(
			'developer_id'         => self::DEVELOPER_ID,
			'gtag_id'              => self::get( 'ga_id' ),
			'tracker_var'          => self::tracker_var(),
			'track_404'            => 'yes' === self::get( 'ga_404_tracking_enabled' ),
			'allow_google_signals' => 'yes' === self::get( 'ga_support_display_advertising' ),
			'link_attribution'     => 'yes' === self::get( 'ga_support_enhanced_link_attribution' ),
			'anonymize_ip'         => 'yes' === self::get( 'ga_anonymize_enabled' ),
			'logged_in'            => is_user_logged_in(),
			'linker'               => array(
				'domains'        => ! empty( self::get( 'ga_linker_cross_domains' ) ) ? array_map( 'esc_js', explode( ',', self::get( 'ga_linker_cross_domains' ) ) ) : array(),
				'allow_incoming' => 'yes' === self::get( 'ga_linker_allow_incoming_enabled' ),
			),
			'custom_map'           => array(
				'dimension1' => 'logged_in',
			),
		);
	}

	/**
	 * Get the class instance
	 *
	 * @param array $options Options
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	public static function get_instance( $options = array() ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $options );
		}

		return self::$instance;
	}
}
