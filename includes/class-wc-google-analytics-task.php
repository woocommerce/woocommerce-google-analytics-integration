<?php
/**
 * Set up Google Analytics Integration task.
 *
 * Adds a set up Google Analytics Integration task to the task list.
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\Task;

/**
 * Setup Task class.
 */
class WC_Google_Analytics_Task extends Task {

	/**
	 * Get the ID of the task.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'setup-google-analytics-integration';
	}

	/**
	 * Get the title for the task.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Set up Google Analytics', 'woocommerce-google-analytics-integration' );
	}

	/**
	 * Get the content for the task.
	 *
	 * @return string
	 */
	public function get_content() {
		return esc_html__( 'Provides the integration between WooCommerce and Google Analytics.', 'woocommerce-google-analytics-integration' );
	}

	/**
	 * Get the time required to perform the task.
	 *
	 * @return string
	 */
	public function get_time() {
		return esc_html__( '5 minutes', 'woocommerce-google-analytics-integration' );
	}

	/**
	 * Get the action URL for the task.
	 *
	 * @return string
	 */
	public function get_action_url() {
		return WC_Google_Analytics_Integration::get_instance()->get_settings_url();
	}

	/**
	 * Check if the task is complete.
	 *
	 * @return bool
	 */
	public function is_complete() {
		return WC_Google_Analytics_Integration::get_integration()->is_setup_complete();
	}

}


