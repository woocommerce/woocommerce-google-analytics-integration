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

	/**
	 * Test that events are correctly mapped to WooCommerce hooks and
	 * are added to the script data array when the action happens.
	 *
	 * @return void
	 */
	public function test_map_actions(): void {
		$gtag     = new WC_Google_Gtag_JS;
		$mappings = array(
			'begin_checkout'    => 'woocommerce_before_checkout_form',
			'purchase'          => 'woocommerce_thankyou',
			'view_item_list'    => 'woocommerce_before_shop_loop_item',
			'add_to_cart'       => 'woocommerce_add_to_cart',
			'remove_from_cart'  => 'woocommerce_cart_item_removed',
			'view_item'         => 'woocommerce_after_single_product',
		);
		
		array_map( 'remove_all_actions', $mappings );

		$gtag->map_actions();

		foreach( $mappings as $event => $hook ) {
			do_action( $hook );
	
			$script_data = json_decode( $gtag->get_script_data(), true );
	
			$this->assertEquals(
				$script_data['events'],
				array(
					$event => $event
				)
			);
	
			// Reset event data
			$gtag->set_script_data( 'events', array(), null, true );
		}
	}

	/**
	 * Test that script data is correctly set
	 *
	 * @return void
	 */
	public function test_script_data(): void {
		$gtag    = new WC_Google_Gtag_JS;
		$default = json_decode( $gtag->get_script_data(), true );
		
		$gtag->set_script_data( 'test', 'value' );
		$script_data = json_decode( $gtag->get_script_data(), true );

		$this->assertEquals( $script_data, array(
			...$default,
			'test' => array(
				'value'
			)
		) );
		
		$gtag->set_script_data( 'test', 'value2', 'key' );
		$script_data = json_decode( $gtag->get_script_data(), true );

		$this->assertEquals( $script_data, array(
			...$default,
			'test' => array(
				0     => 'value',
				'key' => 'value2',
			)
		) );
		
		$gtag->set_script_data( 'test', 'value', null, true );
		$script_data = json_decode( $gtag->get_script_data(), true );

		$this->assertEquals( $script_data, array(
			...$default,
			'test' => 'value'
		) );
	}

	/**
	 * Test the tracker_var filter `woocommerce_gtag_tracker_variable`
	 *
	 * @return void
	 */
	public function test_tracker_var(): void {
		$gtag = new WC_Google_Gtag_JS;

		$this->assertEquals( $gtag->tracker_var(), 'gtag' );
		
		add_filter( 'woocommerce_gtag_tracker_variable', function( $var ) {
			return 'filtered';
		} );
		$this->assertEquals( $gtag->tracker_var(), 'filtered' );
	}

	/**
	 * Test only events enabled in settings will be returned for config
	 *
	 * @return void
	 */
	public function test_get_enabled_events(): void {
		$settings = array(
			'purchase'         => 'ga_ecommerce_tracking_enabled',
			'add_to_cart'      => 'ga_event_tracking_enabled',
			'remove_from_cart' => 'ga_enhanced_remove_from_cart_enabled',
			'view_item_list'   => 'ga_enhanced_product_impression_enabled',
			'select_content'   => 'ga_enhanced_product_click_enabled',
			'view_item'        => 'ga_enhanced_product_detail_view_enabled',
			'begin_checkout'   => 'ga_enhanced_checkout_process_enabled',
		);

		foreach( $settings as $event => $option_name ) {
			$gtag = new WC_Google_Gtag_JS( array( $option_name => 'yes' ) );
			$this->assertEquals( $gtag->get_enabled_events(), array( $event ) );
		}
	}
}
