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

	/**
	 * Constructor
	 * Takes our options from the parent class so we can later use them in the JS snippets
	 *
	 * @param array $options Options
	 */
	public function __construct( $options = array() ) {
		self::$options = $options;
		// Setup frontend scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
	}

	/**
	 * Register front end JavaScript
	 */
	public function register_scripts() {
		wp_enqueue_script(
			$this->script_handle,
			Plugin::get_instance()->get_js_asset_url( 'actions.js' ),
			Plugin::get_instance()->get_js_asset_dependencies( 'actions' ),
			Plugin::get_instance()->get_js_asset_version( 'actions' ),
			true
		);
	}

	/**
	 * Returns the tracker variable this integration should use
	 *
	 * @return string
	 */
	public static function tracker_var() {
		return apply_filters( 'woocommerce_gtag_tracker_variable', 'gtag' );
	}

	/**
	 * Loads the standard Gtag code
	 *
	 * @param WC_Order $order WC_Order Object (not used in this implementation, but mandatory in the abstract class)
	 */
	public static function load_analytics( $order = false ) {
		$logged_in = is_user_logged_in() ? 'yes' : 'no';

		$track_404_enabled = '';
		if ( 'yes' === self::get( 'ga_404_tracking_enabled' ) && is_404() ) {
			// See https://developers.google.com/analytics/devguides/collection/gtagjs/events for reference
			$track_404_enabled = self::tracker_var() . "( 'event', '404_not_found', { 'event_category':'error', 'event_label':'page: ' + document.location.pathname + document.location.search + ' referrer: ' + document.referrer });";
		}

		$gtag_developer_id = '';
		if ( ! empty( self::DEVELOPER_ID ) ) {
			$gtag_developer_id = self::tracker_var() . "('set', 'developer_id." . self::DEVELOPER_ID . "', true);";
		}

		$gtag_id            = self::get( 'ga_id' );
		$gtag_cross_domains = ! empty( self::get( 'ga_linker_cross_domains' ) ) ? array_map( 'esc_js', explode( ',', self::get( 'ga_linker_cross_domains' ) ) ) : array();
		$gtag_snippet       = '
		window.dataLayer = window.dataLayer || [];
		function ' . self::tracker_var() . '(){dataLayer.push(arguments);}
		' . self::tracker_var() . "('js', new Date());
		$gtag_developer_id

		" . self::tracker_var() . "('config', '" . esc_js( $gtag_id ) . "', {
			'allow_google_signals': " . ( 'yes' === self::get( 'ga_support_display_advertising' ) ? 'true' : 'false' ) . ",
			'link_attribution': " . ( 'yes' === self::get( 'ga_support_enhanced_link_attribution' ) ? 'true' : 'false' ) . ",
			'anonymize_ip': " . ( 'yes' === self::get( 'ga_anonymize_enabled' ) ? 'true' : 'false' ) . ",
			'linker':{
				'domains': " . wp_json_encode( $gtag_cross_domains ) . ",
				'allow_incoming': " . ( 'yes' === self::get( 'ga_linker_allow_incoming_enabled' ) ? 'true' : 'false' ) . ",
			},
			'custom_map': {
				'dimension1': 'logged_in'
			},
			'logged_in': '$logged_in'
		} );

		$track_404_enabled
		";

		wp_register_script( 'google-tag-manager', 'https://www.googletagmanager.com/gtag/js?id=' . esc_js( $gtag_id ), array( 'google-analytics-opt-out' ), null, false );
		wp_add_inline_script( 'google-tag-manager', apply_filters( 'woocommerce_gtag_snippet', $gtag_snippet ) );
		wp_enqueue_script( 'google-tag-manager' );
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
