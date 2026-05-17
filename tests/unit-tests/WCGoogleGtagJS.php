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
			function ( $product ) {
				return 'filtered';
			}
		);

		$this->assertEquals( 'filtered', $mock_id::get_product_identifier( $this->get_product() ) );
	}

	/**
	 * Test that events are correctly mapped to WooCommerce hooks and
	 * are added to the script data array when the action happens.
	 *
	 * Note: we are testing both actions and filters in the same way
	 * as we are only interested in them being triggered for this test.
	 *
	 * @return void
	 */
	public function test_map_hooks(): void {
		$gtag     = new WC_Google_Gtag_JS();
		$mappings = array(
			'begin_checkout'   => 'woocommerce_before_checkout_form',
			'purchase'         => 'woocommerce_thankyou',
			'view_item_list'   => 'woocommerce_loop_add_to_cart_link',
			'add_to_cart'      => 'woocommerce_add_to_cart',
			'remove_from_cart' => 'woocommerce_cart_item_removed',
			'view_item'        => 'woocommerce_after_single_product',
		);

		array_map( 'remove_all_actions', $mappings );

		$gtag->map_hooks();

		foreach ( $mappings as $event => $hook ) {
			do_action( $hook );

			$script_data = json_decode( $gtag->get_script_data(), true );

			$this->assertTrue( in_array( $event, $script_data['events'], true ) );

			// Reset event data
			$gtag->set_script_data( 'events', array() );
		}
	}

	/**
	 * Test that script data is correctly set
	 *
	 * @return void
	 */
	public function test_set_script_data(): void {
		$gtag         = new WC_Google_Gtag_JS();
		$example_data = array(
			'key' => 'value',
		);

		$gtag->set_script_data( 'test', $example_data );

		$script_data = json_decode( $gtag->get_script_data(), true );
		$this->assertEquals( $script_data['test'], $example_data );
	}

	/**
	 * Test script data can be appended
	 *
	 * @return void
	 */
	public function test_append_script_data(): void {
		$gtag = new WC_Google_Gtag_JS();

		$gtag->append_script_data( 'test', 'first' );
		$gtag->append_script_data( 'test', 'second' );

		$script_data = json_decode( $gtag->get_script_data(), true );

		$this->assertEquals( $script_data['test'], array( 'first', 'second' ) );
	}

	/**
	 * Test that product list data is capped to avoid oversized inline payloads.
	 *
	 * @return void
	 */
	public function test_product_list_data_is_limited(): void {
		remove_all_filters( 'woocommerce_loop_add_to_cart_link' );

		$gtag    = new WC_Google_Gtag_JS();
		$product = WC_Helper_Product::create_simple_product();

		for ( $i = 0; $i < 51; $i++ ) {
			apply_filters( 'woocommerce_loop_add_to_cart_link', '', $product );
		}

		$script_data = json_decode( $gtag->get_script_data(), true );

		$this->assertCount( 50, $script_data['products'] );
	}

	/**
	 * Test that the product list data cap can be customized.
	 *
	 * @return void
	 */
	public function test_product_list_data_limit_is_filterable(): void {
		remove_all_filters( 'woocommerce_loop_add_to_cart_link' );

		add_filter(
			'woocommerce_ga_max_product_list_items',
			function () {
				return 2;
			}
		);

		$gtag    = new WC_Google_Gtag_JS();
		$product = WC_Helper_Product::create_simple_product();

		for ( $i = 0; $i < 3; $i++ ) {
			apply_filters( 'woocommerce_loop_add_to_cart_link', '', $product );
		}

		remove_all_filters( 'woocommerce_ga_max_product_list_items' );

		$script_data = json_decode( $gtag->get_script_data(), true );

		$this->assertCount( 2, $script_data['products'] );
	}

	/**
	 * Test the tracker_var filter `woocommerce_gtag_tracker_variable`
	 *
	 * @return void
	 */
	public function test_tracker_var(): void {
		$gtag = new WC_Google_Gtag_JS();

		$this->assertEquals( $gtag->tracker_function_name(), 'gtag' );

		add_filter(
			'woocommerce_gtag_tracker_variable',
			function ( $variable ) {
				return 'filtered';
			}
		);
		$this->assertEquals( $gtag->tracker_function_name(), 'filtered' );
	}

	/**
	 * Test that the deprecated enquque_tracker() method delegates to enqueue_tracker()
	 * and triggers a deprecation notice.
	 *
	 * @expectedDeprecated WC_Google_Gtag_JS::enquque_tracker
	 *
	 * @return void
	 */
	public function test_enquque_tracker_deprecation(): void {
		$gtag = new WC_Google_Gtag_JS();

		$gtag->enquque_tracker();

		$this->assertTrue( wp_script_is( 'google-tag-manager', 'enqueued' ), 'google-tag-manager script should be enqueued by the deprecated method' );
		$this->assertTrue( wp_script_is( $gtag->script_handle, 'enqueued' ), 'Main script should be enqueued by the deprecated method' );
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

		foreach ( $settings as $event => $option_name ) {
			$gtag = new WC_Google_Gtag_JS( array( $option_name => 'yes' ) );
			$this->assertEquals( $gtag->get_enabled_events(), array( $event ) );
		}
	}
}
