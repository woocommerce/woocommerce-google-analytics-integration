<?php

namespace GoogleAnalyticsIntegration\Tests;

use Automattic\WooCommerce\Caches\OrderCountCache;
use WC_Admin_Notices;
use WC_Google_Analytics;
use WC_Google_Analytics_Integration;
use WP_UnitTestCase;

/**
 * Unit tests for the admin notices the plugin can show.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class PluginNotices extends WP_UnitTestCase {

	private const PRO_NOTICE = 'woocommerce_google_analytics_pro_notice';
	private const PRO_OPTION = 'woocommerce_google_analytics_pro_notice_shown';

	/**
	 * Start every test without a stored notice or "shown" flag.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( self::PRO_OPTION );
		WC_Admin_Notices::remove_notice( self::PRO_NOTICE );
	}

	/**
	 * Remove the notice so it does not leak into other tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		WC_Admin_Notices::remove_notice( self::PRO_NOTICE );
		parent::tear_down();
	}

	/**
	 * Completed order counts and whether the Pro notice is queued for them.
	 * The notice is for stores with 10 to 100 completed orders, inclusive.
	 *
	 * @return array
	 */
	public function pro_notice_boundaries() {
		return [
			'below the range' => [ 9, false ],
			'lower bound'     => [ 10, true ],
			'upper bound'     => [ 100, true ],
			'above the range' => [ 101, false ],
		];
	}

	/**
	 * The Pro notice is queued only inside the range. The "shown" flag is set in
	 * every case so the check does not run again.
	 *
	 * @dataProvider pro_notice_boundaries
	 *
	 * @param int  $completed_orders Number of completed orders to create.
	 * @param bool $expect_notice    Whether the notice should be queued.
	 *
	 * @return void
	 */
	public function test_pro_notice_depends_on_completed_order_count( $completed_orders, $expect_notice ) {
		$this->create_completed_orders( $completed_orders );

		WC_Google_Analytics_Integration::get_instance()->maybe_show_ga_pro_notices();

		$this->assertSame( $expect_notice, WC_Admin_Notices::has_notice( self::PRO_NOTICE ) );
		$this->assertNotEmpty( get_option( self::PRO_OPTION ) );
	}

	/**
	 * Once the flag is set the notice is never queued again, even for a store
	 * inside the range.
	 *
	 * @return void
	 */
	public function test_pro_notice_is_not_queued_when_already_shown() {
		$this->create_completed_orders( 10 );
		update_option( self::PRO_OPTION, true );

		WC_Google_Analytics_Integration::get_instance()->maybe_show_ga_pro_notices();

		$this->assertFalse( WC_Admin_Notices::has_notice( self::PRO_NOTICE ) );
	}

	/**
	 * The notice output names the missing requirement and the version in use.
	 *
	 * @return void
	 */
	public function test_woocommerce_missing_notice_output() {
		ob_start();
		WC_Google_Analytics_Integration::get_instance()->woocommerce_missing_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div class="error">', $output );
		$this->assertStringContainsString( 'requires WooCommerce version ' . WC_GOOGLE_ANALYTICS_INTEGRATION_MIN_WC_VER . ' or higher', $output );
		$this->assertStringContainsString( 'You are using version ' . WOOCOMMERCE_VERSION, $output );
	}

	/**
	 * A UA- id gets the Universal Analytics error notice, whatever the casing.
	 *
	 * @return void
	 */
	public function test_universal_analytics_notice_shown_for_ua_id() {
		$this->assertStringContainsString( 'Universal Analytics', $this->get_ua_notice( 'UA-12345-1' ) );
		$this->assertStringContainsString( 'notice-error', $this->get_ua_notice( 'ua-12345-1' ) );
	}

	/**
	 * GA4 and empty ids get no notice.
	 *
	 * @return void
	 */
	public function test_universal_analytics_notice_hidden_for_other_ids() {
		$this->assertSame( '', $this->get_ua_notice( 'G-TEST123' ) );
		$this->assertSame( '', $this->get_ua_notice( '' ) );
	}

	/**
	 * Create completed orders.
	 *
	 * @param int $count How many to create.
	 *
	 * @return void
	 */
	private function create_completed_orders( $count ) {
		for ( $i = 0; $i < $count; $i++ ) {
			$order = wc_create_order();
			$order->set_status( 'completed' );
			$order->save();
		}

		// Completing orders fires the plugin's own hook, which sets the flag.
		delete_option( self::PRO_OPTION );
		WC_Admin_Notices::remove_notice( self::PRO_NOTICE );

		( new OrderCountCache() )->flush();
	}

	/**
	 * Render the Universal Analytics notice for an id.
	 *
	 * @param string $ga_id Measurement id.
	 *
	 * @return string
	 */
	private function get_ua_notice( $ga_id ) {
		$ga = $this->getMockBuilder( WC_Google_Analytics::class )
					->disableOriginalConstructor()
					->onlyMethods( [] )
					->getMock();

		$reflection = new \ReflectionProperty( WC_Google_Analytics::class, 'settings' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}
		$reflection->setValue( $ga, [ 'ga_id' => $ga_id ] );

		ob_start();
		$ga->universal_analytics_upgrade_notice();
		return ob_get_clean();
	}
}
