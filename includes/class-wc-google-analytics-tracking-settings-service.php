<?php
/**
 * Google Analytics tracking settings service.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds curated Google Analytics tracking settings snapshots.
 */
class WC_Google_Analytics_Tracking_Settings_Service {

	/**
	 * Google Analytics integration instance.
	 *
	 * @var WC_Google_Analytics
	 */
	private $integration;

	/**
	 * Constructor.
	 *
	 * @param WC_Google_Analytics $integration Google Analytics integration instance.
	 */
	public function __construct( WC_Google_Analytics $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Get a curated tracking settings snapshot.
	 *
	 * @return array
	 */
	public function get_tracking_settings(): array {
		$measurement_id = (string) $this->integration->get_option( 'ga_id' );
		$event_tracking = array();

		foreach ( self::get_event_settings_map() as $event_name => $setting_name ) {
			$event_tracking[ $event_name ] = $this->is_setting_enabled( $setting_name );
		}

		return array(
			'setup_complete'              => $this->integration->is_setup_complete(),
			'measurement_id'              => $measurement_id,
			'measurement_id_prefix'       => self::get_measurement_id_prefix( $measurement_id ),
			'product_identifier'          => (string) $this->integration->get_option( 'ga_product_identifier' ),
			'display_advertising_enabled' => $this->is_setting_enabled( 'ga_support_display_advertising' ),
			'track_404_enabled'           => $this->is_setting_enabled( 'ga_404_tracking_enabled' ),
			'linker'                      => array(
				'allow_incoming' => $this->is_setting_enabled( 'ga_linker_allow_incoming_enabled' ),
				'domains'        => self::parse_linker_domains( (string) $this->integration->get_option( 'ga_linker_cross_domains' ) ),
			),
			'event_tracking'              => $event_tracking,
			'enabled_events'              => $this->get_enabled_events(),
			'plugin_version'              => WC_GOOGLE_ANALYTICS_INTEGRATION_VERSION,
		);
	}

	/**
	 * Get the event setting map for tracking settings summaries.
	 *
	 * @return array
	 */
	public static function get_event_settings_map(): array {
		return array(
			'purchase'         => 'ga_ecommerce_tracking_enabled',
			'add_to_cart'      => 'ga_event_tracking_enabled',
			'remove_from_cart' => 'ga_enhanced_remove_from_cart_enabled',
			'view_item_list'   => 'ga_enhanced_product_impression_enabled',
			'select_content'   => 'ga_enhanced_product_click_enabled',
			'view_item'        => 'ga_enhanced_product_detail_view_enabled',
			'begin_checkout'   => 'ga_enhanced_checkout_process_enabled',
		);
	}

	/**
	 * Parse linker domains for API-oriented output.
	 *
	 * @param string $domains Comma-separated domains.
	 * @return array
	 */
	public static function parse_linker_domains( string $domains ): array {
		if ( empty( $domains ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( string $domain ) {
						$domain = trim( $domain );

						// Keep the same domain validation as the storefront gtag path, but return API data instead of JS-escaped data.
						if ( preg_match( '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/i', $domain ) ) {
							return strtolower( $domain );
						}

						return null;
					},
					explode( ',', $domains )
				)
			)
		);
	}

	/**
	 * Check whether a yes/no setting is enabled.
	 *
	 * @param string $setting Setting key.
	 * @return bool
	 */
	private function is_setting_enabled( string $setting ): bool {
		return 'yes' === $this->integration->get_option( $setting );
	}

	/**
	 * Get enabled event names.
	 *
	 * @return array
	 */
	private function get_enabled_events(): array {
		$events = array();

		foreach ( self::get_event_settings_map() as $event => $setting_name ) {
			if ( $this->is_setting_enabled( $setting_name ) ) {
				$events[] = $event;
			}
		}

		return $events;
	}

	/**
	 * Get the measurement ID prefix used in tracking summaries.
	 *
	 * @param string $measurement_id Measurement ID.
	 * @return string
	 */
	private static function get_measurement_id_prefix( string $measurement_id ): string {
		$prefix = strstr( strtoupper( $measurement_id ), '-', true );

		if ( false === $prefix ) {
			$prefix = '';
		}

		if ( in_array( $prefix, array( 'UA', 'G', 'GT' ), true ) || '' === $prefix ) {
			return $prefix;
		}

		return 'X';
	}
}
