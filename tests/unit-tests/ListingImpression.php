<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;

/**
 * Class ListingImpression
 *
 * @since x.x.x
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class ListingImpression extends EventsDataTest {

	/**
	 * Run unit test against the `view_item_list` event
	 *
	 * @return void
	 */
	public function test_view_item_list_event() {
		$product  = $this->get_product();
		$position = 1;

		( new WC_Google_Gtag_JS() )->listing_impression( $product, $position );

		// Confirm woocommerce_gtag_event_data is called by listing_impression().
		$this->assertEquals( 1, $this->get_event_data_filter_call_count(), 'woocommerce_gtag_event_data filter was not called for view_item_list (listing_impression()) event' );

		// The expected data structure for this event.
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

		// Confirm data structure matches what's expected.
		$this->assertEquals( $expected_data, $this->get_event_data(), 'Event data does not match expected data structure for view_item_list (listing_impression()) event' );
	}

}
