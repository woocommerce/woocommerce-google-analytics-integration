<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;

/**
 * Class ProductDetail
 *
 * @since 1.6.0
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
		global $product;
		$product = $this->get_product();

		$mock = $this->getMockBuilder( WC_Google_Gtag_JS::class )
					 ->setMethods( array( '__construct' ) )
					 ->setConstructorArgs( array( array( 'ga_enhanced_product_detail_view_enabled' => 'yes' ) ) )
					 ->getMock();

		$mock->product_detail();

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
}
