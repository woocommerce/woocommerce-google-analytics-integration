<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;
use WC_Google_Analytics;

/**
 * Unit tests for configuration methods on WC_Google_Gtag_JS and WC_Google_Analytics.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class Configuration extends EventsDataTest {

	/**
	 * Test that default config contains expected keys and values.
	 *
	 * @return void
	 */
	public function test_get_site_tag_config_default_values() {
		$gtag   = new WC_Google_Gtag_JS();
		$config = $gtag->get_site_tag_config();

		$this->assertFalse( $config['track_404'], 'track_404 should be false when setting is unset' );
		$this->assertFalse( $config['allow_google_signals'], 'allow_google_signals should be false when setting is unset' );
		$this->assertFalse( $config['logged_in'], 'logged_in should be false in test context' );
		$this->assertIsArray( $config['linker'] );
		$this->assertEquals( [], $config['linker']['domains'] );
		$this->assertFalse( $config['linker']['allow_incoming'] );
		$this->assertEquals( 'logged_in', $config['custom_map']['dimension1'] );
	}

	/**
	 * Test that track_404 reflects the ga_404_tracking_enabled setting.
	 *
	 * @return void
	 */
	public function test_get_site_tag_config_track_404_reflects_setting() {
		$gtag   = new WC_Google_Gtag_JS( [ 'ga_404_tracking_enabled' => 'yes' ] );
		$config = $gtag->get_site_tag_config();
		$this->assertTrue( $config['track_404'] );

		$gtag   = new WC_Google_Gtag_JS( [ 'ga_404_tracking_enabled' => 'no' ] );
		$config = $gtag->get_site_tag_config();
		$this->assertFalse( $config['track_404'] );
	}

	/**
	 * Test that allow_google_signals reflects the display advertising setting.
	 *
	 * @return void
	 */
	public function test_get_site_tag_config_allow_google_signals_reflects_setting() {
		$gtag   = new WC_Google_Gtag_JS( [ 'ga_support_display_advertising' => 'yes' ] );
		$config = $gtag->get_site_tag_config();
		$this->assertTrue( $config['allow_google_signals'] );

		$gtag   = new WC_Google_Gtag_JS( [ 'ga_support_display_advertising' => 'no' ] );
		$config = $gtag->get_site_tag_config();
		$this->assertFalse( $config['allow_google_signals'] );
	}

	/**
	 * Test that logged_in reflects the current user state.
	 *
	 * @return void
	 */
	public function test_get_site_tag_config_logged_in_reflects_user_state() {
		$gtag = new WC_Google_Gtag_JS();

		$config = $gtag->get_site_tag_config();
		$this->assertFalse( $config['logged_in'] );

		wp_set_current_user( 1 );
		$config = $gtag->get_site_tag_config();
		$this->assertTrue( $config['logged_in'] );

		wp_set_current_user( 0 );
	}

	/**
	 * Test that linker domains are parsed from comma-separated setting.
	 *
	 * @return void
	 */
	public function test_get_site_tag_config_linker_domains_parses_comma_separated() {
		$gtag   = new WC_Google_Gtag_JS( [ 'ga_linker_cross_domains' => 'example.com,example.net' ] );
		$config = $gtag->get_site_tag_config();

		$this->assertEquals( [ 'example.com', 'example.net' ], $config['linker']['domains'] );
	}

	/**
	 * Test that whitespace in linker domains is trimmed.
	 *
	 * @return void
	 */
	public function test_get_site_tag_config_linker_domains_with_whitespace() {
		$gtag   = new WC_Google_Gtag_JS( [ 'ga_linker_cross_domains' => 'example.com, example.net' ] );
		$config = $gtag->get_site_tag_config();

		$this->assertEquals( [ 'example.com', 'example.net' ], $config['linker']['domains'] );
	}

	/**
	 * Test that invalid linker domains are ignored.
	 *
	 * @return void
	 */
	public function test_get_site_tag_config_linker_domains_rejects_invalid_domains() {
		$gtag   = new WC_Google_Gtag_JS( [ 'ga_linker_cross_domains' => 'example.com, bad domain, https://example.net, sub.example.org, example, -bad.com, ok-store.co.uk' ] );
		$config = $gtag->get_site_tag_config();

		$this->assertEquals( [ 'example.com', 'sub.example.org', 'ok-store.co.uk' ], $config['linker']['domains'] );
	}

	/**
	 * Test that linker domains is empty when setting is empty.
	 *
	 * @return void
	 */
	public function test_get_site_tag_config_linker_domains_empty_when_setting_empty() {
		$gtag   = new WC_Google_Gtag_JS( [ 'ga_linker_cross_domains' => '' ] );
		$config = $gtag->get_site_tag_config();

		$this->assertEquals( [], $config['linker']['domains'] );
	}

	/**
	 * Test that linker allow_incoming reflects the setting.
	 *
	 * @return void
	 */
	public function test_get_site_tag_config_linker_allow_incoming_reflects_setting() {
		$gtag   = new WC_Google_Gtag_JS( [ 'ga_linker_allow_incoming_enabled' => 'yes' ] );
		$config = $gtag->get_site_tag_config();
		$this->assertTrue( $config['linker']['allow_incoming'] );

		$gtag   = new WC_Google_Gtag_JS( [ 'ga_linker_allow_incoming_enabled' => 'no' ] );
		$config = $gtag->get_site_tag_config();
		$this->assertFalse( $config['linker']['allow_incoming'] );
	}

	/**
	 * Test that the woocommerce_ga_gtag_config filter can modify config.
	 *
	 * @return void
	 */
	public function test_get_site_tag_config_filter_can_modify() {
		$gtag     = new WC_Google_Gtag_JS();
		$callback = function ( $config ) {
			$config['custom_key'] = 'custom_value';
			return $config;
		};

		add_filter( 'woocommerce_ga_gtag_config', $callback );

		$config = $gtag->get_site_tag_config();
		$this->assertEquals( 'custom_value', $config['custom_key'] );

		remove_filter( 'woocommerce_ga_gtag_config', $callback );
	}

	/**
	 * Test that get_consent_modes returns an array with one mode.
	 *
	 * @return void
	 */
	public function test_get_consent_modes_returns_array_with_one_mode() {
		$gtag  = new WC_Google_Gtag_JS();
		$modes = $this->call_protected_method( $gtag, 'get_consent_modes' );

		$this->assertIsArray( $modes );
		$this->assertCount( 1, $modes );
	}

	/**
	 * Test that all consent categories are denied by default.
	 *
	 * @return void
	 */
	public function test_get_consent_modes_all_categories_denied() {
		$gtag  = new WC_Google_Gtag_JS();
		$modes = $this->call_protected_method( $gtag, 'get_consent_modes' );
		$mode  = $modes[0];

		$this->assertEquals( 'denied', $mode['analytics_storage'] );
		$this->assertEquals( 'denied', $mode['ad_storage'] );
		$this->assertEquals( 'denied', $mode['ad_user_data'] );
		$this->assertEquals( 'denied', $mode['ad_personalization'] );
	}

	/**
	 * Test that the region list contains 32 countries (27 EU + 3 EEA + GB + CH).
	 *
	 * @return void
	 */
	public function test_get_consent_modes_region_list() {
		$gtag  = new WC_Google_Gtag_JS();
		$modes = $this->call_protected_method( $gtag, 'get_consent_modes' );
		$mode  = $modes[0];

		$this->assertCount( 32, $mode['region'] );
		$this->assertContains( 'GB', $mode['region'] );
		$this->assertContains( 'CH', $mode['region'] );
		$this->assertContains( 'DE', $mode['region'] );
		$this->assertContains( 'FR', $mode['region'] );
		$this->assertContains( 'IS', $mode['region'] );
		$this->assertContains( 'LI', $mode['region'] );
		$this->assertContains( 'NO', $mode['region'] );

		$this->assertCount( count( $mode['region'] ), array_unique( $mode['region'] ) );
	}

	/**
	 * Test that the woocommerce_ga_gtag_consent_modes filter can modify modes.
	 *
	 * @return void
	 */
	public function test_get_consent_modes_filter_can_modify() {
		$gtag     = new WC_Google_Gtag_JS();
		$callback = function ( $modes ) {
			$modes[0]['analytics_storage'] = 'granted';
			return $modes;
		};

		add_filter( 'woocommerce_ga_gtag_consent_modes', $callback );

		$modes = $this->call_protected_method( $gtag, 'get_consent_modes' );
		$this->assertEquals( 'granted', $modes[0]['analytics_storage'] );

		remove_filter( 'woocommerce_ga_gtag_consent_modes', $callback );
	}

	/**
	 * Test that utm_nooverride adds the parameter to a URL.
	 *
	 * @return void
	 */
	public function test_utm_nooverride_adds_parameter() {
		$ga  = $this->create_ga_instance();
		$url = $ga->utm_nooverride( 'https://example.com/checkout/' );

		$this->assertStringContainsString( 'utm_nooverride=1', $url );
	}

	/**
	 * Test that utm_nooverride replaces an existing value without duplicates.
	 *
	 * @return void
	 */
	public function test_utm_nooverride_replaces_existing_value() {
		$ga  = $this->create_ga_instance();
		$url = $ga->utm_nooverride( 'https://example.com/checkout/?utm_nooverride=0' );

		$this->assertStringContainsString( 'utm_nooverride=1', $url );
		$this->assertEquals( 1, substr_count( $url, 'utm_nooverride' ) );
	}

	/**
	 * Test that utm_nooverride preserves existing query parameters.
	 *
	 * @return void
	 */
	public function test_utm_nooverride_preserves_existing_query_params() {
		$ga  = $this->create_ga_instance();
		$url = $ga->utm_nooverride( 'https://example.com/checkout/?foo=bar&baz=qux' );

		$this->assertStringContainsString( 'utm_nooverride=1', $url );
		$this->assertStringContainsString( 'foo=bar', $url );
		$this->assertStringContainsString( 'baz=qux', $url );
	}

	/**
	 * Test that utm_nooverride escapes dangerous characters.
	 *
	 * @return void
	 */
	public function test_utm_nooverride_escapes_dangerous_characters() {
		$ga  = $this->create_ga_instance();
		$url = $ga->utm_nooverride( 'https://example.com/checkout/?key=val&x=<script>' );

		$this->assertStringNotContainsString( '<script>', $url );
		$this->assertStringContainsString( 'utm_nooverride=1', $url );
	}

	/**
	 * Test that track_settings returns all expected fields.
	 *
	 * @return void
	 */
	public function test_track_settings_returns_all_fields() {
		$ga      = $this->create_ga_instance(
			[
				'ga_id'                            => 'G-TEST123',
				'ga_support_display_advertising'   => 'yes',
				'ga_404_tracking_enabled'          => 'yes',
				'ga_ecommerce_tracking_enabled'    => 'yes',
				'ga_event_tracking_enabled'        => 'no',
				'ga_linker_allow_incoming_enabled' => 'no',
				'ga_linker_cross_domains'          => 'example.com',
			]
		);
		$data    = $ga->track_settings( [] );
		$ga_data = $data['wc-google-analytics'];

		$this->assertEquals( 'yes', $ga_data['support_display_advertising'] );
		$this->assertEquals( 'yes', $ga_data['ga_404_tracking_enabled'] );
		$this->assertEquals( 'yes', $ga_data['ecommerce_tracking_enabled'] );
		$this->assertEquals( 'no', $ga_data['event_tracking_enabled'] );
		$this->assertEquals( WC_GOOGLE_ANALYTICS_INTEGRATION_VERSION, $ga_data['plugin_version'] );
		$this->assertEquals( 'example.com', $ga_data['linker_cross_domains'] );
		$this->assertEquals( 'G', $ga_data['ga_id'] );
	}

	/**
	 * Test that GA ID prefix is extracted correctly for various formats.
	 *
	 * @return void
	 */
	public function test_track_settings_ga_id_prefix_extraction() {
		$cases = [
			'G-XXXX'  => 'G',
			'GT-XXXX' => 'GT',
			'UA-XXXX' => 'UA',
			''        => '',
			'ZZ-XXXX' => 'X',
		];

		foreach ( $cases as $ga_id => $expected_prefix ) {
			$ga   = $this->create_ga_instance(
				[
					'ga_id'                            => $ga_id,
					'ga_support_display_advertising'   => 'yes',
					'ga_404_tracking_enabled'          => 'yes',
					'ga_ecommerce_tracking_enabled'    => 'yes',
					'ga_event_tracking_enabled'        => 'yes',
					'ga_linker_allow_incoming_enabled' => 'no',
					'ga_linker_cross_domains'          => '',
				]
			);
			$data = $ga->track_settings( [] );

			$this->assertEquals(
				$expected_prefix,
				$data['wc-google-analytics']['ga_id'],
				"GA ID prefix for '{$ga_id}' should be '{$expected_prefix}'"
			);
		}
	}

	/**
	 * Test that linker_allow_incoming is normalized to yes/no.
	 *
	 * @return void
	 */
	public function test_track_settings_linker_allow_incoming_normalization() {
		$ga   = $this->create_ga_instance(
			[
				'ga_id'                            => 'G-TEST',
				'ga_support_display_advertising'   => 'yes',
				'ga_404_tracking_enabled'          => 'yes',
				'ga_ecommerce_tracking_enabled'    => 'yes',
				'ga_event_tracking_enabled'        => 'yes',
				'ga_linker_allow_incoming_enabled' => 'yes',
				'ga_linker_cross_domains'          => '',
			]
		);
		$data = $ga->track_settings( [] );
		$this->assertEquals( 'yes', $data['wc-google-analytics']['linker_allow_incoming_enabled'] );

		$ga   = $this->create_ga_instance(
			[
				'ga_id'                            => 'G-TEST',
				'ga_support_display_advertising'   => 'yes',
				'ga_404_tracking_enabled'          => 'yes',
				'ga_ecommerce_tracking_enabled'    => 'yes',
				'ga_event_tracking_enabled'        => 'yes',
				'ga_linker_allow_incoming_enabled' => '',
				'ga_linker_cross_domains'          => '',
			]
		);
		$data = $ga->track_settings( [] );
		$this->assertEquals( 'no', $data['wc-google-analytics']['linker_allow_incoming_enabled'] );
	}

	/**
	 * Test that $this->settings is fully populated when the database contains only the partial
	 * defaults written by maybe_set_defaults() on a fresh activation.
	 *
	 * @return void
	 */
	public function test_settings_are_complete_after_partial_activation_defaults() {
		// Simulate what maybe_set_defaults() writes on a fresh install.
		update_option( 'woocommerce_google_analytics_settings', [ 'ga_product_identifier' => 'product_id' ] );

		$ga = new WC_Google_Analytics();

		$reflection = new \ReflectionProperty( WC_Google_Analytics::class, 'settings' );
		$reflection->setAccessible( true );
		$settings = $reflection->getValue( $ga );

		$expected_keys = [
			'ga_product_identifier',
			'ga_id',
			'ga_support_display_advertising',
			'ga_404_tracking_enabled',
			'ga_linker_allow_incoming_enabled',
			'ga_ecommerce_tracking_enabled',
			'ga_event_tracking_enabled',
			'ga_enhanced_remove_from_cart_enabled',
			'ga_enhanced_product_impression_enabled',
			'ga_enhanced_product_click_enabled',
			'ga_enhanced_product_detail_view_enabled',
			'ga_enhanced_checkout_process_enabled',
			'ga_linker_cross_domains',
		];

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $settings, "Expected '{$key}' to be present in settings after construction with partial defaults." );
		}

		// The value set by maybe_set_defaults() should be preserved, not overwritten.
		$this->assertEquals( 'product_id', $settings['ga_product_identifier'] );

		delete_option( 'woocommerce_google_analytics_settings' );
	}

	/**
	 * Call a protected or private method via reflection.
	 *
	 * @param mixed  $instance    The object instance to call the method on.
	 * @param string $method_name The method name to call.
	 * @param array  $args        Arguments to pass to the method.
	 *
	 * @return mixed The method return value.
	 */
	private function call_protected_method( $instance, $method_name, $args = [] ) {
		$reflection = new \ReflectionMethod( get_class( $instance ), $method_name );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $instance, $args );
	}

	/**
	 * Create a WC_Google_Analytics instance with test settings using a mock
	 * to avoid the full constructor side effects.
	 *
	 * @param array $settings Settings to inject.
	 *
	 * @return WC_Google_Analytics The mock instance.
	 */
	private function create_ga_instance( $settings = [] ) {
		$ga = $this->getMockBuilder( WC_Google_Analytics::class )
					->disableOriginalConstructor()
					->onlyMethods( [] )
					->getMock();

		$reflection = new \ReflectionProperty( WC_Google_Analytics::class, 'settings' );
		$reflection->setAccessible( true );
		$reflection->setValue( $ga, $settings );

		return $ga;
	}
}
