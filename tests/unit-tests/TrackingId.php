<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Analytics;
use WC_Google_Analytics_Tracking_Settings_Service;
use WP_UnitTestCase;

require_once dirname( __DIR__, 2 ) . '/includes/class-wc-google-analytics-tracking-settings-service.php';

/**
 * Tests for the tracking ID as the integration loads it from the database:
 * stored settings, the legacy option, and what reaches the gtag snippet.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class TrackingId extends WP_UnitTestCase {

	use ResetsGtagInstance;

	/**
	 * Start each test without stored settings and with a fresh tracking singleton.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( 'woocommerce_google_analytics_settings' );
		delete_option( 'woocommerce_ga_id' );
		wp_deregister_script( 'google-tag-manager' );
		$this->reset_gtag_instance();
	}

	/**
	 * Leave no settings or singleton behind for the next test class.
	 *
	 * @return void
	 */
	public function tear_down() {
		delete_option( 'woocommerce_google_analytics_settings' );
		delete_option( 'woocommerce_ga_id' );
		$this->reset_gtag_instance();
		parent::tear_down();
	}

	/**
	 * Spaces around a stored ID, for example from WP-CLI or an import, are not part of it.
	 *
	 * @return void
	 */
	public function test_spaces_around_a_stored_id_are_trimmed() {
		update_option( 'woocommerce_google_analytics_settings', [ 'ga_id' => " G-TEST123 \n" ] );

		$ga = new WC_Google_Analytics();

		$this->assertSame( 'G-TEST123', $ga->get_option( 'ga_id' ) );
		$this->assertSame(
			'https://www.googletagmanager.com/gtag/js?id=G-TEST123',
			wp_scripts()->query( 'google-tag-manager' )->src
		);
		$this->assertSame(
			'G',
			( new WC_Google_Analytics_Tracking_Settings_Service( $ga ) )->get_tracking_settings()['measurement_id_prefix']
		);
	}

	/**
	 * An ID of only spaces is no ID: setup is not complete and nothing is tracked.
	 *
	 * @return void
	 */
	public function test_an_id_of_only_spaces_is_no_id() {
		update_option( 'woocommerce_google_analytics_settings', [ 'ga_id' => '   ' ] );

		$ga = new WC_Google_Analytics();

		$this->assertFalse( $ga->is_setup_complete() );
		$this->assertFalse( wp_scripts()->query( 'google-tag-manager' ) );
	}

	/**
	 * Today’s behaviour: the ID "0" counts as no ID.
	 *
	 * @return void
	 */
	public function test_an_id_of_zero_does_not_complete_setup() {
		update_option( 'woocommerce_google_analytics_settings', [ 'ga_id' => '0' ] );

		$this->assertFalse( ( new WC_Google_Analytics() )->is_setup_complete() );
	}

	/**
	 * A store upgraded from the old settings keeps its ID from the legacy option.
	 *
	 * @return void
	 */
	public function test_the_legacy_option_fills_a_missing_id() {
		update_option( 'woocommerce_ga_id', ' G-LEGACY1 ' );
		update_option( 'woocommerce_google_analytics_settings', [ 'ga_product_identifier' => 'product_id' ] );

		$ga = new WC_Google_Analytics();

		$this->assertSame( 'G-LEGACY1', $ga->get_option( 'ga_id' ) );
		$this->assertTrue( $ga->is_setup_complete() );
	}
}
