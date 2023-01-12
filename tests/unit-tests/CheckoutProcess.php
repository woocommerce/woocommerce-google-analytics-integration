<?php
// phpcs:ignoreFile

namespace GoogleAnalyticsIntegration\Tests;

use MockAction;
use WP_UnitTestCase;
use WC_Helper_Product;
use WC_Helper_Customer;
use WC_Google_Gtag_JS;

class CheckoutProcessTest extends WP_UnitTestCase {

	public function test_begin_checkout_event() {
		$product  = WC_Helper_Product::create_simple_product();
		$customer = WC_Helper_Customer::create_customer( 'JD', 'pw', 'customer@unit.test' );

		wp_set_current_user( $customer->get_id() );

		$cart = WC()->cart;
		$cart->add_to_cart( $product->get_id() );

		// Mock woocommerce_gtag_event_data filter to ensure it is called and the correct data is processed
		$filter = new MockAction();
		add_filter( 'woocommerce_gtag_event_data', array( &$filter, 'filter' ) );

		( new WC_Google_Gtag_JS() )->checkout_process( $cart->get_cart() );

		// Confirm woocommerce_gtag_event_data is called by checkout_process()
		$this->assertEquals( 1, $filter->get_call_count(), 'woocommerce_gtag_event_data filter was not called for begin_checkout (checkout_process()) event' );

		// The expected data structure for this event
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

		$expected_data = array(
			'items' => array(),
		);

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			$item_data = array(
				'id'       => WC_Google_Gtag_JS::get_product_identifier( $product ),
				'name'     => $product->get_title(),
				'category' => WC_Google_Gtag_JS::product_get_category_line( $product ),
				'price'    => $product->get_price(),
				'quantity' => $cart_item['quantity'],
			);

			$variant = WC_Google_Gtag_JS::product_get_variant_line( $product );
			if ( '' !== $variant ) {
				$item_data['variant'] = $variant;
			}

			$expected_data['items'][] = $item_data;
		}

		// Get data passed to woocommerce_gtag_event_data filter
		$args        = $filter->get_args();
		$actual_data = $args[0][0];

		// Confirm data structure matches what's expected
		$this->assertEquals( $expected_data, $actual_data, 'Event data does not match expected data structure for begin_checkout (checkout_process()) event' );
	}

}
