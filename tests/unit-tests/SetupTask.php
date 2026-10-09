<?php

namespace GoogleAnalyticsIntegration\Tests;

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists;
use WC_Google_Analytics;
use WC_Google_Analytics_Integration;
use WC_Google_Analytics_Task;
use WP_UnitTestCase;

/**
 * Unit tests for the WooCommerce Home setup task.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class SetupTask extends WP_UnitTestCase {

	/** @var array Stored integration settings, restored after each test. */
	private $settings;

	/**
	 * Remember the stored settings.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->settings = get_option( 'woocommerce_google_analytics_settings' );
		require_once dirname( __DIR__, 2 ) . '/includes/class-wc-google-analytics-task.php';
	}

	/**
	 * Restore the stored settings.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( false === $this->settings ) {
			delete_option( 'woocommerce_google_analytics_settings' );
		} else {
			update_option( 'woocommerce_google_analytics_settings', $this->settings );
		}
		parent::tear_down();
	}

	/**
	 * The task is not complete until a measurement id is saved.
	 *
	 * @return void
	 */
	public function test_is_complete_follows_measurement_id() {
		$task = $this->create_task();

		$this->save_ga_id( '' );
		$this->assertFalse( $task->is_complete() );

		$this->save_ga_id( 'G-TEST123' );
		$this->assertTrue( $task->is_complete() );
	}

	/**
	 * The task links to the integration settings page.
	 *
	 * @return void
	 */
	public function test_action_url_points_to_integration_settings() {
		$url = $this->create_task()->get_action_url();

		$this->assertSame( WC_Google_Analytics_Integration::get_instance()->get_settings_url(), $url );
		$this->assertStringContainsString( 'page=wc-settings', $url );
		$this->assertStringContainsString( 'tab=integration', $url );
		$this->assertStringContainsString( 'section=google_analytics', $url );
	}

	/**
	 * The task text shown on WooCommerce Home.
	 *
	 * @return void
	 */
	public function test_task_labels() {
		$task = $this->create_task();

		$this->assertSame( 'setup-google-analytics-integration', $task->get_id() );
		$this->assertSame( 'Set up Google Analytics', $task->get_title() );
		$this->assertSame( 'Provides the integration between WooCommerce and Google Analytics.', $task->get_content() );
		$this->assertSame( '5 minutes', $task->get_time() );
	}

	/**
	 * The integration registers the task in the extended task list.
	 *
	 * @return void
	 */
	public function test_integration_registers_task_in_extended_list() {
		if ( null === TaskLists::get_list( 'extended' ) ) {
			TaskLists::init_default_lists();
		}

		WC()->integrations->get_integration( 'google_analytics' )->add_wc_setup_task();

		$this->assertNotNull( TaskLists::get_task( 'setup-google-analytics-integration', 'extended' ) );
	}

	/**
	 * Create the task for the extended list.
	 *
	 * @return WC_Google_Analytics_Task
	 */
	private function create_task() {
		if ( null === TaskLists::get_list( 'extended' ) ) {
			TaskLists::init_default_lists();
		}

		return new WC_Google_Analytics_Task( TaskLists::get_list( 'extended' ) );
	}

	/**
	 * Save a measurement id and reload the integration so it reads it.
	 *
	 * @param string $ga_id Measurement id.
	 *
	 * @return void
	 */
	private function save_ga_id( $ga_id ) {
		update_option( 'woocommerce_google_analytics_settings', [ 'ga_id' => $ga_id ] );
		WC()->integrations->get_integration( 'google_analytics' )->init_settings();
	}
}
