<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;

/**
 * Class ListingClick
 *
 * @since x.x.x
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class ListingClick extends EventsDataTest {

	/**
	 * Run unit test against the `select_content` and `add_to_cart` events
	 *
	 * @return void
	 */
	public function test_select_content_and_add_to_cart_event() {
		$product  = $this->get_product();

		( new WC_Google_Gtag_JS() )->listing_click( $product );

		// Code is generated for two events in listing_click() so we would expect the filter is called twice.
		$this->assertEquals( 2, $this->get_event_data_filter_call_count(), 'woocommerce_gtag_event_data filter was not called for select_content and add_to_cart (listing_click()) events' );

		// The expected data structure for this event.
		$expected_data = array(
			'items' => array(
				array(
					'id'            => WC_Google_Gtag_JS::get_product_identifier( $product ),
					'name'          => $product->get_title(),
					'category'      => WC_Google_Gtag_JS::product_get_category_line( $product ),
					'quantity'      => 1,
				),
			),
		);

		// Confirm data structure matches what's expected.
		$this->assertEquals( $expected_data, $this->get_event_data(), 'Event data does not match expected data structure for select_content and add_to_cart (listing_click()) events' );
	}

}
