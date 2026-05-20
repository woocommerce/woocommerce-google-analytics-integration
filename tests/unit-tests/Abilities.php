<?php

namespace GoogleAnalyticsIntegration\Tests;

use Automattic\WooCommerce\Internal\Abilities\AbilitiesLoader;
use WP_UnitTestCase;

/**
 * Unit tests for Google Analytics abilities.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class Abilities extends WP_UnitTestCase {

	private const ABILITY_ID = 'woocommerce-google-analytics-integration/get-tracking-settings';

	/**
	 * Original action counts captured for restoration in tearDown.
	 *
	 * @var array<string, int|null>
	 */
	private $original_action_counts = array();

	/**
	 * Set up the ability registration boundary.
	 *
	 * @return void
	 */
	public function set_up() {
		global $wp_actions;

		parent::set_up();

		foreach ( array( 'init', 'wp_abilities_api_init', 'wp_abilities_api_categories_init' ) as $action ) {
			$this->original_action_counts[ $action ] = $wp_actions[ $action ] ?? null;
		}

		$this->maybe_bootstrap_abilities_api();

		if (
			! function_exists( 'wp_register_ability' )
			|| ! interface_exists( '\Automattic\WooCommerce\Abilities\AbilityDefinition' )
			|| ! class_exists( AbilitiesLoader::class )
		) {
			$this->markTestSkipped( 'The WooCommerce Abilities API loader is unavailable in this test environment.' );
		}

		// WordPress 6.9+ requires init to have fired before the Abilities API registry can initialize.
		$wp_actions['init'] = max( 1, (int) ( $wp_actions['init'] ?? 0 ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		if ( class_exists( '\WC_Google_Analytics_Abilities' ) ) {
			\WC_Google_Analytics_Abilities::init();
		}

		wp_set_current_user(
			$this->factory->user->create(
				array(
					'role' => 'administrator',
				)
			)
		);

		$this->register_woocommerce_category();
		$this->register_extension_ability();
	}

	/**
	 * Clean up ability state.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $wp_actions;

		if ( function_exists( 'wp_has_ability' ) && wp_has_ability( self::ABILITY_ID ) ) {
			wp_unregister_ability( self::ABILITY_ID );
		}

		foreach ( $this->original_action_counts as $action => $original_count ) {
			if ( null !== $original_count ) {
				$wp_actions[ $action ] = $original_count; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			} elseif ( isset( $wp_actions[ $action ] ) ) {
				unset( $wp_actions[ $action ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}

		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Test that the ability is supplied through WooCommerce's loader path.
	 *
	 * @return void
	 */
	public function test_loader_registers_tracking_settings_ability() {
		$classes = apply_filters( 'woocommerce_ability_definition_classes', array() );

		$this->assertContains( \WC_Google_Analytics_Get_Tracking_Settings_Ability::class, $classes );
		$this->assertTrue(
			is_a(
				\WC_Google_Analytics_Get_Tracking_Settings_Ability::class,
				'\Automattic\WooCommerce\Abilities\AbilityDefinition',
				true
			)
		);

		$ability = wp_get_ability( self::ABILITY_ID );

		$this->assertNotNull( $ability, 'Tracking settings ability should be registered.' );
		$this->assertSame( 'woocommerce', $ability->get_category() );
	}

	/**
	 * Test ability metadata and schemas.
	 *
	 * @return void
	 */
	public function test_tracking_settings_ability_metadata() {
		$ability = wp_get_ability( self::ABILITY_ID );
		$meta    = $ability->get_meta();

		$this->assertTrue( $meta['show_in_rest'] ?? false );
		$this->assertTrue( $meta['mcp']['public'] ?? false );
		$this->assertSame( 'tool', $meta['mcp']['type'] ?? '' );
		$this->assertTrue( $meta['annotations']['readonly'] ?? false );
		$this->assertFalse( $meta['annotations']['destructive'] ?? true );
		$this->assertTrue( $meta['annotations']['idempotent'] ?? false );
		$this->assertArrayNotHasKey( 'expose_in_deprecated_woocommerce_mcp', $meta );
		$this->assertSame( array(), $ability->get_input_schema() );
		$this->assertFalse( $ability->get_output_schema()['additionalProperties'] );
	}

	/**
	 * Test the permission boundary.
	 *
	 * @return void
	 */
	public function test_tracking_settings_ability_requires_manage_woocommerce() {
		$ability = wp_get_ability( self::ABILITY_ID );

		wp_set_current_user(
			$this->factory->user->create(
				array(
					'role' => 'subscriber',
				)
			)
		);

		$result = $ability->execute();

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test the public ability execution output.
	 *
	 * @return void
	 */
	public function test_tracking_settings_ability_executes_with_curated_output() {
		update_option(
			'woocommerce_google_analytics_settings',
			array(
				'ga_product_identifier'                   => 'product_id',
				'ga_id'                                   => 'G-TEST123',
				'ga_support_display_advertising'          => 'yes',
				'ga_404_tracking_enabled'                 => 'yes',
				'ga_linker_allow_incoming_enabled'        => 'yes',
				'ga_ecommerce_tracking_enabled'           => 'yes',
				'ga_event_tracking_enabled'               => 'no',
				'ga_enhanced_remove_from_cart_enabled'    => 'yes',
				'ga_enhanced_product_impression_enabled'  => 'yes',
				'ga_enhanced_product_click_enabled'       => 'no',
				'ga_enhanced_product_detail_view_enabled' => 'yes',
				'ga_enhanced_checkout_process_enabled'    => 'yes',
				'ga_linker_cross_domains'                 => 'example.com, bad domain, shop.example.net',
			)
		);

		WC()->integrations->integrations['google_analytics'] = new \WC_Google_Analytics();

		$result = wp_get_ability( self::ABILITY_ID )->execute();

		$this->assertNotWPError( $result );
		$this->assertTrue( $result['setup_complete'] );
		$this->assertSame( 'G-TEST123', $result['measurement_id'] );
		$this->assertSame( 'G', $result['measurement_id_prefix'] );
		$this->assertSame( 'product_id', $result['product_identifier'] );
		$this->assertTrue( $result['display_advertising_enabled'] );
		$this->assertTrue( $result['track_404_enabled'] );
		$this->assertTrue( $result['linker']['allow_incoming'] );
		$this->assertSame( array( 'example.com', 'shop.example.net' ), $result['linker']['domains'] );
		$this->assertTrue( $result['event_tracking']['purchase'] );
		$this->assertFalse( $result['event_tracking']['add_to_cart'] );
		$this->assertSame(
			array( 'purchase', 'remove_from_cart', 'view_item_list', 'view_item', 'begin_checkout' ),
			$result['enabled_events']
		);
		$this->assertSame( WC_GOOGLE_ANALYTICS_INTEGRATION_VERSION, $result['plugin_version'] );
	}

	/**
	 * Bootstrap the Abilities API package when WooCommerce provides it for older WordPress versions.
	 *
	 * @return void
	 */
	private function maybe_bootstrap_abilities_api(): void {
		if ( function_exists( 'wp_register_ability' ) || ! defined( 'WC_ABSPATH' ) ) {
			return;
		}

		$abilities_bootstrap = WC_ABSPATH . 'vendor/wordpress/abilities-api/includes/bootstrap.php';

		if ( file_exists( $abilities_bootstrap ) ) {
			require_once $abilities_bootstrap;
		}
	}

	/**
	 * Register the shared WooCommerce ability category for this test.
	 *
	 * @return void
	 */
	private function register_woocommerce_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) || ! function_exists( 'wp_has_ability_category' ) || wp_has_ability_category( 'woocommerce' ) ) {
			return;
		}

		$callback = static function () {
			wp_register_ability_category(
				'woocommerce',
				array(
					'label'       => 'WooCommerce',
					'description' => 'Canonical store management abilities.',
				)
			);
		};

		add_action( 'wp_abilities_api_categories_init', $callback );
		do_action( 'wp_abilities_api_categories_init' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Test bootstrap for Abilities API registration.
		remove_action( 'wp_abilities_api_categories_init', $callback );
	}

	/**
	 * Register extension abilities through WooCommerce's loader.
	 *
	 * @return void
	 */
	private function register_extension_ability(): void {
		$callback = array( AbilitiesLoader::class, 'register_abilities' );

		add_action( 'wp_abilities_api_init', $callback );
		do_action( 'wp_abilities_api_init' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Test bootstrap for Abilities API registration.
		remove_action( 'wp_abilities_api_init', $callback );
	}
}
