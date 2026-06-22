<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Analytics;
use WP_UnitTestCase;

/**
 * Unit tests for WC_Google_Analytics::disable_tracking().
 *
 * This method is the gate that decides whether the front end tracking scripts
 * are loaded at all, so each condition that can switch tracking off is worth
 * pinning down.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class DisableTracking extends WP_UnitTestCase {

	/**
	 * Reset the current user that individual tests may change. The framework's
	 * tear_down already restores the admin screen state.
	 *
	 * @return void
	 */
	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Tracking should stay enabled on the front end for a visitor without admin
	 * capabilities when a measurement id is configured and the event type is on.
	 *
	 * @return void
	 */
	public function test_tracking_enabled_on_happy_path() {
		$ga = $this->create_ga_instance( [ 'ga_id' => 'G-TEST123' ] );

		$this->assertFalse( $this->disable_tracking( $ga, 'yes' ) );
	}

	/**
	 * An empty or missing measurement id must disable tracking, otherwise the
	 * gtag config would point at no property.
	 *
	 * @return void
	 */
	public function test_tracking_disabled_when_ga_id_is_empty() {
		$ga = $this->create_ga_instance( [ 'ga_id' => '' ] );
		$this->assertTrue( $this->disable_tracking( $ga, 'yes' ), 'Empty ga_id should disable tracking' );

		$ga = $this->create_ga_instance( [] );
		$this->assertTrue( $this->disable_tracking( $ga, 'yes' ), 'Missing ga_id should disable tracking' );
	}

	/**
	 * A 'no' event type means the merchant turned that event off, so tracking
	 * for it must be disabled even when everything else is in place.
	 *
	 * @return void
	 */
	public function test_tracking_disabled_when_type_is_no() {
		$ga = $this->create_ga_instance( [ 'ga_id' => 'G-TEST123' ] );

		$this->assertTrue( $this->disable_tracking( $ga, 'no' ) );
	}

	/**
	 * Requests inside wp-admin should never emit front end tracking.
	 *
	 * @return void
	 */
	public function test_tracking_disabled_in_admin_area() {
		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin(), 'Sanity check: the admin screen should make is_admin() true' );

		$ga = $this->create_ga_instance( [ 'ga_id' => 'G-TEST123' ] );

		$this->assertTrue( $this->disable_tracking( $ga, 'yes' ) );
	}

	/**
	 * Users with the manage_options capability (administrators) are excluded
	 * from tracking so their own browsing does not pollute the store's
	 * analytics. Note this does not cover shop managers, who only hold
	 * manage_woocommerce.
	 *
	 * @return void
	 */
	public function test_tracking_disabled_for_users_who_can_manage_options() {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$this->assertTrue( current_user_can( 'manage_options' ), 'Sanity check: the admin user should have manage_options' );

		$ga = $this->create_ga_instance( [ 'ga_id' => 'G-TEST123' ] );

		$this->assertTrue( $this->disable_tracking( $ga, 'yes' ) );
	}

	/**
	 * The woocommerce_ga_disable_tracking filter must be able to force tracking
	 * off, and it should receive the event type so integrations can decide per
	 * event.
	 *
	 * @return void
	 */
	public function test_tracking_can_be_disabled_via_filter() {
		$ga = $this->create_ga_instance( [ 'ga_id' => 'G-TEST123' ] );

		$received_type = null;
		$callback      = function ( $disabled, $type ) use ( &$received_type ) {
			$received_type = $type;
			return true;
		};
		add_filter( 'woocommerce_ga_disable_tracking', $callback, 10, 2 );

		$this->assertTrue( $this->disable_tracking( $ga, 'yes' ) );
		$this->assertEquals( 'yes', $received_type, 'The filter should receive the event type' );

		remove_filter( 'woocommerce_ga_disable_tracking', $callback, 10 );
	}

	/**
	 * Invoke the private disable_tracking() method via reflection.
	 *
	 * @param WC_Google_Analytics $instance The integration instance.
	 * @param string              $type     The event type to evaluate.
	 *
	 * @return bool
	 */
	private function disable_tracking( $instance, $type ) {
		$reflection = new \ReflectionMethod( WC_Google_Analytics::class, 'disable_tracking' );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $instance, [ $type ] );
	}

	/**
	 * Build a WC_Google_Analytics instance with injected settings while skipping
	 * the constructor side effects (option reads, hook registration).
	 *
	 * @param array $settings Settings to inject.
	 *
	 * @return WC_Google_Analytics
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
