<?php
// phpcs:ignoreFile

namespace GoogleAnalyticsIntegration\Tests;

use MockAction;
use WP_UnitTestCase;
use WC_Helper_Product;
use WC_Helper_Customer;
use WC_Helper_Order;
use WC_Google_Gtag_JS;

class AddTransactionEnhancedTest extends WP_UnitTestCase {

	public function test_purchase_event() {
		$product  = WC_Helper_Product::create_simple_product();
		$customer = WC_Helper_Customer::create_customer( 'JD', 'pw', 'customer@unit.test' );
		$order    = WC_Helper_Order::create_order( $customer->get_id(), $product );

		// Mock woocommerce_gtag_event_data filter to ensure it is called and the correct data is processed
		$filter = new MockAction();
		add_filter( 'woocommerce_gtag_event_data', array( &$filter, 'filter' ) );

		$gtag = new WC_Google_Gtag_JS();
		$gtag->add_transaction_enhanced( $order );

		// Confirm woocommerce_gtag_event_data is called by add_transaction_enhanced()
		$this->assertEquals( 1, $filter->get_call_count(), 'woocommerce_gtag_event_data filter was not called for purchase (add_transaction_enhanced()) event' );

		$order_items = $order->get_items();
		$event_items = array();

		if ( ! empty( $order_items ) ) {
			foreach ( $order_items as $item ) {
				$event_items[] = $gtag->add_item( $order, $item );
			}
		}

		// The expected data structure for this event
		$expected_data = array(
			'transaction_id' => $order->get_order_number(),
			'affiliation'    => get_bloginfo( 'name' ),
			'value'          => $order->get_total(),
			'tax'            => $order->get_total_tax(),
			'shipping'       => $order->get_total_shipping(),
			'currency'       => $order->get_currency(),
			'items'          => $event_items,
		);

		// Get data passed to woocommerce_gtag_event_data filter
		$args        = $filter->get_args();
		$actual_data = $args[0][0];

		// Confirm data structure matches what's expected
		$this->assertEquals( $expected_data, $actual_data, 'Event data does not match expected data structure for purchase (add_transaction_enhanced()) event' );
	}

}
