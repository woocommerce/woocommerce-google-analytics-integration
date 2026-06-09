<?php

namespace GoogleAnalyticsIntegration\Tests;

use WP_UnitTestCase;

require_once dirname( __DIR__, 2 ) . '/includes/class-wc-google-analytics-tracking-settings-service.php';

/**
 * Unit tests for WC_Google_Analytics_Tracking_Settings_Service.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class TrackingSettingsService extends WP_UnitTestCase {

	/**
	 * Test the public event settings contract and order.
	 *
	 * @return void
	 */
	public function test_get_event_settings_map_returns_expected_contract() {
		$this->assertSame(
			array(
				'purchase'         => 'ga_ecommerce_tracking_enabled',
				'add_to_cart'      => 'ga_event_tracking_enabled',
				'remove_from_cart' => 'ga_enhanced_remove_from_cart_enabled',
				'view_item_list'   => 'ga_enhanced_product_impression_enabled',
				'select_content'   => 'ga_enhanced_product_click_enabled',
				'view_item'        => 'ga_enhanced_product_detail_view_enabled',
				'begin_checkout'   => 'ga_enhanced_checkout_process_enabled',
			),
			\WC_Google_Analytics_Tracking_Settings_Service::get_event_settings_map()
		);
	}

	/**
	 * Test linker domain parsing trims values, lowercases domains, and drops invalid entries.
	 *
	 * @return void
	 */
	public function test_parse_linker_domains_sanitizes_values() {
		$this->assertSame(
			array( 'example.com', 'sub.example.org', 'ok-store.co.uk', 'shop.xn--p1ai' ),
			\WC_Google_Analytics_Tracking_Settings_Service::parse_linker_domains(
				' EXAMPLE.com, bad domain, https://example.net, sub.Example.ORG, example, -bad.com, ok-store.co.uk, shop.xn--p1ai '
			)
		);
	}

	/**
	 * Test an empty settings snapshot returns stable false/empty values.
	 *
	 * @return void
	 */
	public function test_get_tracking_settings_returns_disabled_snapshot_when_settings_empty() {
		$service  = $this->create_service( array(), false );
		$settings = $service->get_tracking_settings();

		$this->assertFalse( $settings['setup_complete'] );
		$this->assertSame( '', $settings['measurement_id'] );
		$this->assertSame( '', $settings['measurement_id_prefix'] );
		$this->assertSame( '', $settings['product_identifier'] );
		$this->assertFalse( $settings['display_advertising_enabled'] );
		$this->assertFalse( $settings['track_404_enabled'] );
		$this->assertFalse( $settings['linker']['allow_incoming'] );
		$this->assertSame( array(), $settings['linker']['domains'] );
		$this->assertSame( array(), $settings['enabled_events'] );
		$this->assertSame( WC_GOOGLE_ANALYTICS_INTEGRATION_VERSION, $settings['plugin_version'] );

		foreach ( array_keys( \WC_Google_Analytics_Tracking_Settings_Service::get_event_settings_map() ) as $event ) {
			$this->assertFalse( $settings['event_tracking'][ $event ], "Expected {$event} to be disabled." );
		}
	}

	/**
	 * Test a mixed settings snapshot returns sanitized output and enabled events in contract order.
	 *
	 * @return void
	 */
	public function test_get_tracking_settings_returns_enabled_events_in_contract_order() {
		$service  = $this->create_service(
			array(
				'ga_id'                                   => 'ZZ-12345',
				'ga_product_identifier'                   => 'product_sku',
				'ga_support_display_advertising'          => 'yes',
				'ga_404_tracking_enabled'                 => 'no',
				'ga_linker_allow_incoming_enabled'        => 'yes',
				'ga_linker_cross_domains'                 => 'STORE.EXAMPLE.COM, bad domain, checkout.example.net',
				'ga_ecommerce_tracking_enabled'           => 'yes',
				'ga_event_tracking_enabled'               => 'no',
				'ga_enhanced_remove_from_cart_enabled'    => 'yes',
				'ga_enhanced_product_impression_enabled'  => 'no',
				'ga_enhanced_product_click_enabled'       => 'yes',
				'ga_enhanced_product_detail_view_enabled' => 'no',
				'ga_enhanced_checkout_process_enabled'    => 'yes',
			)
		);
		$settings = $service->get_tracking_settings();

		$this->assertTrue( $settings['setup_complete'] );
		$this->assertSame( 'ZZ-12345', $settings['measurement_id'] );
		$this->assertSame( 'X', $settings['measurement_id_prefix'] );
		$this->assertSame( 'product_sku', $settings['product_identifier'] );
		$this->assertTrue( $settings['display_advertising_enabled'] );
		$this->assertFalse( $settings['track_404_enabled'] );
		$this->assertTrue( $settings['linker']['allow_incoming'] );
		$this->assertSame( array( 'store.example.com', 'checkout.example.net' ), $settings['linker']['domains'] );
		$this->assertSame( array( 'purchase', 'remove_from_cart', 'select_content', 'begin_checkout' ), $settings['enabled_events'] );
		$this->assertTrue( $settings['event_tracking']['purchase'] );
		$this->assertFalse( $settings['event_tracking']['add_to_cart'] );
		$this->assertTrue( $settings['event_tracking']['remove_from_cart'] );
		$this->assertFalse( $settings['event_tracking']['view_item_list'] );
		$this->assertTrue( $settings['event_tracking']['select_content'] );
		$this->assertFalse( $settings['event_tracking']['view_item'] );
		$this->assertTrue( $settings['event_tracking']['begin_checkout'] );
	}

	/**
	 * Create the service with a mocked integration.
	 *
	 * @param array $settings Settings returned by get_option().
	 * @param bool  $setup_complete Whether the integration reports setup as complete.
	 *
	 * @return \WC_Google_Analytics_Tracking_Settings_Service
	 */
	private function create_service( array $settings, bool $setup_complete = true ): \WC_Google_Analytics_Tracking_Settings_Service {
		$integration = $this->getMockBuilder( \WC_Google_Analytics::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_option', 'is_setup_complete' ) )
			->getMock();

		$integration->method( 'get_option' )->willReturnCallback(
			function ( $key ) use ( $settings ) {
				return $settings[ $key ] ?? '';
			}
		);
		$integration->method( 'is_setup_complete' )->willReturn( $setup_complete );

		return new \WC_Google_Analytics_Tracking_Settings_Service( $integration );
	}
}
