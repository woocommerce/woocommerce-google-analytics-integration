<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;
use WC_Helper_Product;

/**
 * Class ProductDetail
 *
 * @since x.x.x
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class ProductDetail extends EventsDataTest {

	/**
	 * Run unit test against the `view_item` event
	 *
	 * @return void
	 */
	public function test_view_item_event() {
		$product = $this->get_product();

		( new WC_Google_Gtag_JS() )->product_detail( $product );

		// Confirm woocommerce_gtag_event_data is called by product_detail().
		$this->assertEquals( 1, $this->get_event_data_filter_call_count(), 'woocommerce_gtag_event_data filter was not called for view_item (product_detail()) event' );

		// The expected data structure for this event.
		$expected_data = array(
			'items' => array(
				array(
					'id'       => WC_Google_Gtag_JS::get_product_identifier( $product ),
					'name'     => $product->get_title(),
					'category' => WC_Google_Gtag_JS::product_get_category_line( $product ),
					'price'    => $product->get_price(),
				),
			),
		);

		// Confirm data structure matches what's expected.
		$this->assertEquals( $expected_data, $this->get_event_data(), 'Event data does not match expected data structure for view_item (product_detail()) event' );
	}

	/**
	 * Check that `WC_Google_Gtag_JS` registers
	 * the `assets/js/ga-integration.js` script
	 * as `woocommerce-google-analytics-integration` (`->script_handle`)
	 * on `wp_enqueue_scripts` action.
	 *
	 * @return void
	 */
	public function test_variation_scripts_is_registered() {
		$gtag = new WC_Google_Gtag_JS();

		// Mimic WC action.
		do_action( 'wp_enqueue_scripts' );

		$script_handle = $gtag->script_handle . '-ga-integration';

		// Assert the handle is regeistered with the correct name, but not yet enqueued.
		$this->assertEquals( 'woocommerce-google-analytics-integration', $script_handle, '`WC_Google_Gtag_JS->script_handle` is not equal `woocommerce-google-analytics-integration`' );
		$this->assertEquals( true, wp_script_is( $script_handle, 'registered' ), '`woocommerce-google-analytics-integration` script was not registered' );
		$this->assertEquals( false, wp_script_is( $script_handle, 'enqueued' ), 'the script is enqueued too early' );
		$registered_url = wp_scripts()->registered[ $script_handle ]->src;
		$this->assertStringContainsString( 'assets/js/ga-integration.js', $registered_url, 'The script does not point to the correct URL' );
	}

	/**
	 * Check that `WC_Google_Gtag_JS` does not enqueue
	 * the `woocommerce-google-analytics-integration` script
	 * on `wp_enqueue_scripts` action, for a simple product.
	 *
	 * @return void
	 */
	public function test_variation_scripts_are_not_enqueued_for_simple() {
		global $product;
		$product = WC_Helper_Product::create_simple_product();

		$gtag = new WC_Google_Gtag_JS();

		$script_handle = $gtag->script_handle . '-ga-integration';

		// Mimic WC action.
		do_action( 'wp_enqueue_scripts' );
		ob_start(); // Silence output.
		do_action( 'woocommerce_before_single_product' );
		ob_get_clean();

		// Assert the handle is regeistered with the correct name, but not yet enqueued.
		$this->assertEquals( true, wp_script_is( $script_handle, 'registered' ), '`woocommerce-google-analytics-integration` script was not registered' );
		$this->assertEquals( false, wp_script_is( $script_handle, 'enqueued' ), 'the script is enqueued' );
	}

	/**
	 * Check that `WC_Google_Gtag_JS` does not enqueue
	 * the `woocommerce-google-analytics-integration` script
	 * on `wp_enqueue_scripts` action, for a bool as a `global $product`.
	 *
	 * @return void
	 */
	public function test_variation_scripts_are_not_enqueued_for_bool() {
		global $product;
		$product = false;

		$gtag = new WC_Google_Gtag_JS();

		$script_handle = $gtag->script_handle . '-ga-integration';

		// Mimic WC action.
		do_action( 'wp_enqueue_scripts' );
		ob_start(); // Silence output.
		do_action( 'woocommerce_before_single_product' );
		ob_get_clean();

		// Assert the handle is regeistered with the correct name, but not yet enqueued.
		$this->assertEquals( true, wp_script_is( $script_handle, 'registered' ), '`woocommerce-google-analytics-integration` script was not registered' );
		$this->assertEquals( false, wp_script_is( $script_handle, 'enqueued' ), 'the script is enqueued' );
	}

	/**
	 * Check that `WC_Google_Gtag_JS` enqueue
	 * the `woocommerce-google-analytics-integration` script
	 * on `wp_enqueue_scripts` action, for a variable product.
	 *
	 * @return void
	 */
	public function test_variation_scripts_are_enqueued() {
		global $product;
		$product = WC_Helper_Product::create_variation_product();

		$gtag = new WC_Google_Gtag_JS();

		$script_handle = $gtag->script_handle . '-ga-integration';

		// Mimic WC action.
		do_action( 'wp_enqueue_scripts' );
		ob_start(); // Silence output.
		do_action( 'woocommerce_before_single_product' );
		ob_get_clean();

		// Assert the handle is regeistered with the correct name, but not yet enqueued.
		$this->assertEquals( true, wp_script_is( $script_handle, 'registered' ), '`woocommerce-google-analytics-integration` script was not registered' );
		$this->assertEquals( true, wp_script_is( $script_handle, 'enqueued' ), 'the script is not enqueued' );
	}

}
