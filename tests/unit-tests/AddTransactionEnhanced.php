<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;

/**
 * Class AddTransactionEnhanced
 *
 * @since x.x.x
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class AddTransactionEnhanced extends EventsDataTest {

	/**
	 * Run unit test against the `purchase` event
	 *
	 * @return void
	 */
	public function test_purchase_event() {
		$order = $this->get_order();

		$gtag = new WC_Google_Gtag_JS();
		$gtag->add_transaction_enhanced( $order );

		// Confirm woocommerce_gtag_event_data is called by add_transaction_enhanced().
		$this->assertEquals( 1, $this->get_event_data_filter_call_count(), 'woocommerce_gtag_event_data filter was not called for purchase (add_transaction_enhanced()) event' );

		$order_items = $order->get_items();
		$event_items = array();

		if ( ! empty( $order_items ) ) {
			foreach ( $order_items as $item ) {
				$event_items[] = $gtag->add_item( $order, $item );
			}
		}

		// The expected data structure for this event.
		$expected_data = array(
			'transaction_id' => $order->get_order_number(),
			'affiliation'    => get_bloginfo( 'name' ),
			'value'          => $order->get_total(),
			'tax'            => $order->get_total_tax(),
			'shipping'       => $order->get_total_shipping(),
			'currency'       => $order->get_currency(),
			'items'          => $event_items,
		);

		// Confirm data structure matches what's expected.
		$this->assertEquals( $expected_data, $this->get_event_data(), 'Event data does not match expected data structure for purchase (add_transaction_enhanced()) event' );
	}

}
