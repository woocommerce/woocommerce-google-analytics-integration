<?php
/**
 * Google Analytics tracking settings ability.
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

if ( ! interface_exists( AbilityDefinition::class ) ) {
	return;
}

/**
 * Registers the read-only Google Analytics tracking settings ability.
 */
class WC_Google_Analytics_Get_Tracking_Settings_Ability implements AbilityDefinition {

	/**
	 * Get the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-google-analytics-integration/get-tracking-settings';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get Google Analytics tracking settings', 'woocommerce-google-analytics-integration' ),
			'description'         => __( 'Read the current Google Analytics for WooCommerce tracking configuration and enabled event settings.', 'woocommerce-google-analytics-integration' ),
			'category'            => 'woocommerce',
			'output_schema'       => self::get_output_schema(),
			'execute_callback'    => array( __CLASS__, 'execute' ),
			'permission_callback' => array( __CLASS__, 'can_read_tracking_settings' ),
			'meta'                => array(
				'show_in_rest' => true,
				'mcp'          => array(
					'public' => true,
					'type'   => 'tool',
				),
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		);
	}

	/**
	 * Read the current Google Analytics tracking settings.
	 *
	 * @return array|WP_Error
	 */
	public static function execute() {
		$integration = self::get_integration();

		if ( is_wp_error( $integration ) ) {
			return $integration;
		}

		$service = new WC_Google_Analytics_Tracking_Settings_Service( $integration );

		return $service->get_tracking_settings();
	}

	/**
	 * Check whether the current user can read the integration settings.
	 *
	 * @return bool
	 */
	public static function can_read_tracking_settings(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Get the active Google Analytics integration instance.
	 *
	 * @return WC_Google_Analytics|WP_Error
	 */
	private static function get_integration() {
		if (
			! class_exists( 'WC_Google_Analytics_Integration' )
			|| ! function_exists( 'WC' )
			|| ! WC()
			|| empty( WC()->integrations )
		) {
			return new WP_Error(
				'woocommerce_google_analytics_integration_not_initialized',
				__( 'Google Analytics for WooCommerce is not initialized.', 'woocommerce-google-analytics-integration' )
			);
		}

		$integration = WC_Google_Analytics_Integration::get_integration();

		if ( ! $integration instanceof WC_Google_Analytics ) {
			return new WP_Error(
				'woocommerce_google_analytics_settings_unavailable',
				__( 'Google Analytics for WooCommerce settings are unavailable.', 'woocommerce-google-analytics-integration' )
			);
		}

		return $integration;
	}

	/**
	 * Get the ability output schema.
	 *
	 * @return array
	 */
	private static function get_output_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'setup_complete'              => array(
					'type'        => 'boolean',
					'description' => __( 'Whether a Google Analytics measurement ID is configured.', 'woocommerce-google-analytics-integration' ),
				),
				'measurement_id'              => array(
					'type'        => 'string',
					'description' => __( 'The configured Google Analytics measurement or tag ID.', 'woocommerce-google-analytics-integration' ),
				),
				'measurement_id_prefix'       => array(
					'type'        => 'string',
					'description' => __( 'The recognized prefix for the configured measurement ID, or X for an unrecognized prefix.', 'woocommerce-google-analytics-integration' ),
					'enum'        => array( '', 'G', 'GT', 'UA', 'X' ),
				),
				'product_identifier'          => array(
					'type' => 'string',
					'enum' => array( 'product_id', 'product_sku' ),
				),
				'display_advertising_enabled' => array(
					'type' => 'boolean',
				),
				'track_404_enabled'           => array(
					'type' => 'boolean',
				),
				'linker'                      => array(
					'type'                 => 'object',
					'properties'           => array(
						'allow_incoming' => array(
							'type' => 'boolean',
						),
						'domains'        => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
							),
						),
					),
					'required'             => array( 'allow_incoming', 'domains' ),
					'additionalProperties' => false,
				),
				'event_tracking'              => array(
					'type'                 => 'object',
					'properties'           => self::get_event_tracking_schema_properties(),
					'required'             => self::get_event_names(),
					'additionalProperties' => false,
				),
				'enabled_events'              => array(
					'type'  => 'array',
					'items' => array(
						'type' => 'string',
						'enum' => self::get_event_names(),
					),
				),
				'plugin_version'              => array(
					'type' => 'string',
				),
			),
			'required'             => array(
				'setup_complete',
				'measurement_id',
				'measurement_id_prefix',
				'product_identifier',
				'display_advertising_enabled',
				'track_404_enabled',
				'linker',
				'event_tracking',
				'enabled_events',
				'plugin_version',
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Get schema properties for event tracking settings.
	 *
	 * @return array
	 */
	private static function get_event_tracking_schema_properties(): array {
		$properties = array();

		foreach ( self::get_event_names() as $event_name ) {
			$properties[ $event_name ] = array(
				'type' => 'boolean',
			);
		}

		return $properties;
	}

	/**
	 * Get all GA4 event names this extension can track.
	 *
	 * @return array
	 */
	private static function get_event_names(): array {
		return array(
			'purchase',
			'add_to_cart',
			'remove_from_cart',
			'view_item_list',
			'select_content',
			'view_item',
			'begin_checkout',
		);
	}
}
