<?php
/**
 * Google Analytics for WooCommerce abilities loader.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires Google Analytics abilities into WooCommerce's ability loader.
 */
class WC_Google_Analytics_Abilities {

	/**
	 * Initialize ability definition loading when WooCommerce supports it.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! interface_exists( '\Automattic\WooCommerce\Abilities\AbilityDefinition' ) ) {
			return;
		}

		require_once __DIR__ . '/class-wc-google-analytics-tracking-settings-service.php';
		require_once __DIR__ . '/abilities/class-wc-google-analytics-get-tracking-settings-ability.php';

		add_filter( 'woocommerce_ability_definition_classes', array( __CLASS__, 'add_ability_definition_classes' ) );
	}

	/**
	 * Add Google Analytics ability definitions to WooCommerce's loader.
	 *
	 * @param array $classes Ability definition classes.
	 * @return array
	 */
	public static function add_ability_definition_classes( array $classes ): array {
		$classes[] = WC_Google_Analytics_Get_Tracking_Settings_Ability::class;

		return $classes;
	}
}
