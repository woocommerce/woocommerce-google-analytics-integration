<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;
use WC_Helper_Product;

/**
 * Unit tests for `WC_Google_Gtag_JS` class.
 *
 * @since 1.8.1
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class WCGoogleGtagJS extends EventsDataTest {

	/**
	 * Check that `WC_Google_Gtag_JS` registers
	 * the `assets/js/ga-integration.js` script
	 * as `woocommerce-google-analytics-integration-ga-integration` (`->script_handle . '-ga-integration'`)
	 * and enqueues the `assets/js/actions.js` script
	 * as `woocommerce-google-analytics-integration--actions` (`->script_handle . '--actions'`)
	 * on `wp_enqueue_scripts` action.
	 *
	 * @return void
	 */
	public function test_scripts_are_registered() {
		$gtag = new WC_Google_Gtag_JS();

		// Mimic WC action.
		do_action( 'wp_enqueue_scripts' );

		// Assert the handle property.
		$this->assertEquals( 'woocommerce-google-analytics-integration', $gtag->script_handle, '`WC_Google_Gtag_JS->script_handle` is not equal `woocommerce-google-analytics-integration`' );

		// Assert assert `-ga-intregration` is registered with the correct name, but not yet enqueued.
		$integration_handle = $gtag->script_handle . '-ga-integration';
		$this->assertEquals( true, wp_script_is( $integration_handle, 'registered' ), '`…-ga-integration` script was not registered' );
		$this->assertEquals( false, wp_script_is( $integration_handle, 'enqueued' ), 'the script is enqueued too early' );
		$registered_url = wp_scripts()->registered[ $integration_handle ]->src;
		$this->assertStringContainsString( 'assets/js/build/ga-integration.js', $registered_url, 'The script does not point to the correct URL' );

		// Assert assert `-actions` is enqueued with the correct name.
		$actions_handle = $gtag->script_handle . '-actions';
		$this->assertEquals( true, wp_script_is( $actions_handle, 'enqueued' ), '`…-actions` script was not enqueued' );
		$registered_url = wp_scripts()->registered[ $actions_handle ]->src;
		$this->assertStringContainsString( 'assets/js/build/actions.js', $registered_url, 'The script does not point to the correct URL' );
	}

	/**
	 * Check that `WC_Google_Gtag_JS` does not enqueue
	 * the `…-ga-integration` script
	 * on `woocommerce_before_single_product` action, for a simple product.
	 *
	 * @return void
	 */
	public function test_integration_script_is_not_enqueued_for_simple() {
		global $product;
		$product = WC_Helper_Product::create_simple_product();

		$gtag = new WC_Google_Gtag_JS();

		// Mimic WC action.
		do_action( 'wp_enqueue_scripts' );
		ob_start(); // Silence output.
		do_action( 'woocommerce_before_single_product' );
		ob_get_clean();

		// Assert the handle is not enqueued.
		$this->assertEquals( false, wp_script_is( $gtag->script_handle . '-ga-integration', 'enqueued' ), 'the script is enqueued' );
	}

	/**
	 * Check that `WC_Google_Gtag_JS` does not enqueue
	 * the `…-ga-integration` script
	 * on `woocommerce_before_single_product` action, for a bool as a `global $product`.
	 *
	 * @return void
	 */
	public function test_integration_script_is_not_enqueued_for_bool() {
		global $product;
		$product = false;

		$gtag = new WC_Google_Gtag_JS();

		// Mimic WC action.
		do_action( 'wp_enqueue_scripts' );
		ob_start(); // Silence output.
		do_action( 'woocommerce_before_single_product' );
		ob_get_clean();

		// Assert the handle is not enqueued.
		$this->assertEquals( false, wp_script_is( $gtag->script_handle . '-ga-integration', 'enqueued' ), 'the script is enqueued' );
	}

	/**
	 * Check that `WC_Google_Gtag_JS` enqueue
	 * the `…-ga-integration` script
	 * on `woocommerce_before_single_product` action, for a variable product.
	 *
	 * @return void
	 */
	public function test_integration_script_is_enqueued_for_variation() {
		global $product;
		$product = WC_Helper_Product::create_variation_product();

		$gtag = new WC_Google_Gtag_JS();

		// Mimic WC action.
		do_action( 'wp_enqueue_scripts' );
		ob_start(); // Silence output.
		do_action( 'woocommerce_before_single_product' );
		ob_get_clean();

		// Assert the handle is enqueued.
		$this->assertEquals( true, wp_script_is( $gtag->script_handle . '-ga-integration', 'enqueued' ), 'the script is enqueued' );
	}

	/**
	 * Test the get_product_identifier method to verify:
	 *
	 * 1. Product SKU is returned if the `ga_product_identifier` option is set to `product_sku`.
	 * 2. Prefixed (#) product ID is returned if the `ga_product_identifier` option is set to `product_sku` and the product SKU is empty.
	 * 3. Product ID is returned if the `ga_product_identifier` option is set to `product_id`.
	 * 4. The filter `woocommerce_ga_product_identifier` can be used to modify the value.
	 *
	 * @return void
	 */
	public function test_get_product_identifier() {
		$mock_sku = $this->getMockBuilder( WC_Google_Gtag_JS::class )
						 ->setMethods( array( '__construct' ) )
						 ->setConstructorArgs( array( array( 'ga_product_identifier' => 'product_sku' ) ) )
						 ->getMock();

		$this->assertEquals( $this->get_product()->get_sku(), $mock_sku::get_product_identifier( $this->get_product() ) );

		$this->get_product()->set_sku( '' );
		$this->assertEquals( '#' . $this->get_product()->get_id(), $mock_sku::get_product_identifier( $this->get_product() ) );

		$mock_id = $this->getMockBuilder( WC_Google_Gtag_JS::class )
						->setMethods( array( '__construct' ) )
						->setConstructorArgs( array( array( 'ga_product_identifier' => 'product_id' ) ) )
						->getMock();

		$this->assertEquals( $this->get_product()->get_id(), $mock_id::get_product_identifier( $this->get_product() ) );

		add_filter(
			'woocommerce_ga_product_identifier',
			function( $product ) {
				return 'filtered';
			}
		);

		$this->assertEquals( 'filtered', $mock_id::get_product_identifier( $this->get_product() ) );
	}

}
