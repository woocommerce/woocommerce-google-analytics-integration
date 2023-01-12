<?php
// phpcs:ignoreFile

namespace GoogleAnalyticsIntegration\Tests;

use MockAction;
use WP_UnitTestCase;
use WC_Helper_Product;
use WC_Google_Gtag_JS;

class ListingImpressionTest extends WP_UnitTestCase {

	public function test_view_item_list_event() {
		$product  = WC_Helper_Product::create_simple_product();
		$position = 1;

		// Mock woocommerce_gtag_event_data filter to ensure it is called and the correct data is processed
		$filter = new MockAction();
		add_filter( 'woocommerce_gtag_event_data', array( &$filter, 'filter' ) );

		( new WC_Google_Gtag_JS() )->listing_impression( $product, $position );

		// Confirm woocommerce_gtag_event_data is called by listing_impression()
		$this->assertEquals( 1, $filter->get_call_count(), 'woocommerce_gtag_event_data filter was not called for view_item_list (listing_impression()) event' );

		// The expected data structure for this event
		$expected_data = array(
			'items' => array(
				array(
					'id'            => WC_Google_Gtag_JS::get_product_identifier( $product ),
					'name'          => $product->get_title(),
					'category'      => WC_Google_Gtag_JS::product_get_category_line( $product ),
					'list'          => WC_Google_Gtag_JS::get_list_name(),
					'list_position' => $position,
				),
			),
		);

		// Get data passed to woocommerce_gtag_event_data filter
		$args        = $filter->get_args();
		$actual_data = $args[0][0];

		// Confirm data structure matches what's expected
		$this->assertEquals( $expected_data, $actual_data, 'Event data does not match expected data structure for view_item_list (listing_impression()) event' );
	}

}
