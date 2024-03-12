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

	/** @var string $script_handle Handle for the event data inline script */
	public $data_script_handle = 'woocommerce-google-analytics-integration-data';

	/** @var string $script_data Data required for frontend event tracking */
	private $script_data = array();

	/** @var array $mappings A map of the GA4 events and the classic WooCommerce hooks that trigger them */
	private $mappings = array(
		'actions' => array(
			'begin_checkout'   => 'woocommerce_before_checkout_form',
			'purchase'         => 'woocommerce_thankyou',
			'add_to_cart'      => 'woocommerce_add_to_cart',
			'remove_from_cart' => 'woocommerce_cart_item_removed',
			'view_item'        => 'woocommerce_after_single_product',
		),
		'filters' => array(
			'view_item_list' => 'woocommerce_loop_add_to_cart_link',
		),
	);

	/**
	 * Constructor
	 * Takes our settings from the parent class so we can later use them in the JS snippets
	 *
	 * @param array $settings Settings
	 */
	public function __construct( $settings = array() ) {
		parent::__construct();
		self::$settings = $settings;

		$this->map_hooks();

		// Setup frontend scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enquque_tracker' ), 5 );
		add_action( 'wp_footer', array( $this, 'inline_script_data' ) );
	}

	/**
	 * Register tracker scripts and its inline config.
	 * We need to execute tracker.js w/ `gtag` configuration before any trackable action may happen.
	 *
	 * @return void
	 */
	public function enquque_tracker(): void {
		wp_enqueue_script(
			'google-tag-manager',
			'https://www.googletagmanager.com/gtag/js?id=' . self::get( 'ga_id' ),
			array(),
			null,
			array(
				'strategy' => 'async',
			)
		);
		// tracker.js needs to be executed ASAP, the remaining bits for main.js could be deffered,
		// but to reduce the traffic, we ship it all together.
		wp_enqueue_script(
			$this->script_handle,
			Plugin::get_instance()->get_js_asset_url( 'main.js' ),
			array(
				...Plugin::get_instance()->get_js_asset_dependencies( 'main' ),
				'google-tag-manager',
			),
			Plugin::get_instance()->get_js_asset_version( 'main' ),
			true
		);
		// Provide tracker's configuration.
		wp_add_inline_script(
			$this->script_handle,
			sprintf(
				'var wcgai = {config: %s};',
				wp_json_encode( $this->get_analytics_config() )
			),
			'before'
		);
	}

	/**
	 * Feed classic tracking with event data via inline script.
	 * Make sure it's added at the bottom of the page, so all the data is collected.
	 *
	 * @return void
	 */
	public function inline_script_data(): void {
		wp_register_script(
			$this->data_script_handle,
			'',
			array( $this->script_handle ),
			null,
			array(
				'in_footer' => true,
			)
		);

		wp_add_inline_script(
			$this->data_script_handle,
			sprintf(
				'wcgai.trackClassicPages( %s );',
				$this->get_script_data()
			)
		);

		wp_enqueue_script( $this->data_script_handle );
	}

	/**
	 * Hook into WooCommerce and add corresponding Blocks Actions to our event data
	 *
	 * @return void
	 */
	public function map_hooks(): void {
		array_walk(
			$this->mappings['actions'],
			function ( $hook, $gtag_event ) {
				add_action(
					$hook,
					function () use ( $gtag_event ) {
						$this->append_event( $gtag_event );
					}
				);
			}
		);

		array_walk(
			$this->mappings['filters'],
			function ( $hook, $gtag_event ) {
				add_action(
					$hook,
					function ( $filtered_value ) use ( $gtag_event ) {
						$this->append_event( $gtag_event );
						return $filtered_value;
					}
				);
			}
		);
	}

	/**
	 * Appends a specific event, if it's not included yet.
	 *
	 * @param string $gtag_event
	 * @return void
	 */
	private function append_event( string $gtag_event ) {
		if ( ! in_array( $gtag_event, $this->script_data['events'] ?? [], true ) ) {
			$this->append_script_data( 'events', $gtag_event );
		}
	}

	/**
	 * Set script data for a specific event
	 *
	 * @param string       $type The type of event this data is related to.
	 * @param string|array $data The event data to add.
	 *
	 * @return void
	 */
	public function set_script_data( string $type, $data ): void {
		$this->script_data[ $type ] = $data;
	}

	/**
	 * Append data to an existing script data array
	 *
	 * @param string       $type The type of event this data is related to.
	 * @param string|array $data The event data to add.
	 *
	 * @return void
	 */
	public function append_script_data( string $type, $data ): void {
		if ( ! isset( $this->script_data[ $type ] ) ) {
			$this->script_data[ $type ] = array();
		}
		$this->script_data[ $type ][] = $data;
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
	public static function tracker_function_name(): string {
		return apply_filters( 'woocommerce_gtag_tracker_variable', 'gtag' );
	}

	/**
	 * Return Google Analytics configuration, for JS to read.
	 *
	 * @return array
	 */
	public function get_analytics_config(): array {
		$defaults = array(
			'gtag_id'               => self::get( 'ga_id' ),
			'tracker_function_name' => self::tracker_function_name(),
			'track_404'             => 'yes' === self::get( 'ga_404_tracking_enabled' ),
			'allow_google_signals'  => 'yes' === self::get( 'ga_support_display_advertising' ),
			'logged_in'             => is_user_logged_in(),
			'linker'                => array(
				'domains'        => ! empty( self::get( 'ga_linker_cross_domains' ) ) ? array_map( 'esc_js', explode( ',', self::get( 'ga_linker_cross_domains' ) ) ) : array(),
				'allow_incoming' => 'yes' === self::get( 'ga_linker_allow_incoming_enabled' ),
			),
			'custom_map'            => array(
				'dimension1' => 'logged_in',
			),
			'events'                => self::get_enabled_events(),
			'identifier'            => self::get( 'ga_product_identifier' ),
			'consent_modes'         => self::get_consent_modes(),
		);

		$config                 = apply_filters( 'woocommerce_ga_gtag_config', $defaults );
		$config['developer_id'] = self::DEVELOPER_ID;

		return $config;
	}

	/**
	 * Get an array containing the names of all enabled events
	 *
	 * @return array
	 */
	public static function get_enabled_events(): array {
		$events   = array();
		$settings = array(
			'purchase'         => 'ga_ecommerce_tracking_enabled',
			'add_to_cart'      => 'ga_event_tracking_enabled',
			'remove_from_cart' => 'ga_enhanced_remove_from_cart_enabled',
			'view_item_list'   => 'ga_enhanced_product_impression_enabled',
			'select_content'   => 'ga_enhanced_product_click_enabled',
			'view_item'        => 'ga_enhanced_product_detail_view_enabled',
			'begin_checkout'   => 'ga_enhanced_checkout_process_enabled',
		);

		foreach ( $settings as $event => $setting_name ) {
			if ( 'yes' === self::get( $setting_name ) ) {
				$events[] = $event;
			}
		}

		return $events;
	}

	/**
	 * Get the default state configuration of consent mode.
	 */
	protected static function get_consent_modes(): array {
		$consent_modes = array(
			array(
				'analytics_storage'  => 'denied',
				'ad_storage'         => 'denied',
				'ad_user_data'       => 'denied',
				'ad_personalization' => 'denied',
				'region'             => array(
					'AT',
					'BE',
					'BG',
					'HR',
					'CY',
					'CZ',
					'DK',
					'EE',
					'FI',
					'FR',
					'DE',
					'GR',
					'HU',
					'IS',
					'IE',
					'IT',
					'LV',
					'LI',
					'LT',
					'LU',
					'MT',
					'NL',
					'NO',
					'PL',
					'PT',
					'RO',
					'SK',
					'SI',
					'ES',
					'SE',
					'GB',
					'CH',
				),
			),
		);

		/**
		 * Filters the default gtag consent mode configuration.
		 *
		 * @param array $consent_modes Array of default state configuration of consent mode.
		 */
		return apply_filters( 'woocommerce_ga_gtag_consent_modes', $consent_modes );
	}

	/**
	 * Get the class instance
	 *
	 * @param array $settings Settings
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	public static function get_instance( $settings = array() ): WC_Abstract_Google_Analytics_JS {
		if ( null === self::$instance ) {
			self::$instance = new self( $settings );
		}

		return self::$instance;
	}
}
