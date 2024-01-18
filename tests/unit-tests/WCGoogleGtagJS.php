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
	 * Check that `WC_Google_Gtag_JS` registers and enqueues the `assets/js/build/main.js` script
	 *
	 * @return void
	 */
	public function test_scripts_are_registered() {
		$gtag = new WC_Google_Gtag_JS();

		// Mimic WC action.
		do_action( 'wp_enqueue_scripts' );

		// Assert the handle property.
		$this->assertEquals( 'woocommerce-google-analytics-integration', $gtag->script_handle, '`WC_Google_Gtag_JS->script_handle` is not equal `woocommerce-google-analytics-integration`' );

		$this->assertEquals( true, wp_script_is( $gtag->script_handle, 'enqueued' ), '`…-main` script was not enqueued' );
		$registered_url = wp_scripts()->registered[ $gtag->script_handle ]->src;
		$this->assertStringContainsString( 'assets/js/build/main.js', $registered_url, 'The script does not point to the correct URL' );
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
