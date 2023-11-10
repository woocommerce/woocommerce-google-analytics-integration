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

	/** @var array $mappings A map of Blocks Actions to classic WooCommerce hooks to use for events */
	private $mappings = array(
		'checkout-render-checkout-form' => 'woocommerce_before_checkout_form',
		'checkout-submit'               => 'woocommerce_thankyou',
		'product-list-render'           => 'woocommerce_shop_loop',
		'cart-add-item'                 => 'woocommerce_add_to_cart',
		'cart-set-item-quantity'        => 'woocommerce_after_cart_item_quantity_update',
		'cart-remove-item'              => 'woocommerce_cart_item_removed',
		'product-view-link'             => 'woocommerce_after_single_product',
		'product-render'                => 'woocommerce_after_single_product',
	);

	/**
	 * Constructor
	 * Takes our options from the parent class so we can later use them in the JS snippets
	 *
	 * @param array $options Options
	 */
	public function __construct( $options = array() ) {
		parent::__construct();
		self::$options = $options;

		$this->load_analytics_config();
		$this->map_actions();

		// Setup frontend scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_footer', array( $this, 'inline_script_data' ) );
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
	}

	/**
	 * Add inline script data to the front end
	 *
	 * @return void
	 */
	public function inline_script_data() {
		wp_add_inline_script(
			$this->script_handle,
			sprintf(
				'const wcgaiData = %s;',
				$this->get_script_data()
			),
			'before'
		);
	}

	/**
	 * Hook into WooCommerce and add corresponding Blocks Actions to our event data
	 *
	 * @return void
	 */
	public function map_actions() {
		array_walk(
			$this->mappings,
			function( $hook, $block_action ) {
				add_action(
					$hook,
					function() use ( $block_action ) {
						$this->set_script_data( 'events', $block_action );
					}
				);
			}
		);
	}

	/**
	 * Add an event to the script data
	 *
	 * @param string $type The type of event this data is related to.
	 * @param mixed  $data The event data to add.
	 * @param mixed  $key  If not false then the $data will be added as a new array item with this key.
	 *
	 * @return void
	 */
	public function set_script_data( string $type, $data, $key = false ) {
		if ( ! $key ) {
			$this->script_data[ $type ] = $data;
		} else {
			$this->script_data[ $type ][ $key ] = $data;
		}
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
	public static function get_instance( $options = array() ): WC_Abstract_Google_Analytics_JS {
		if ( null === self::$instance ) {
			self::$instance = new self( $options );
		}

		return self::$instance;
	}
}
