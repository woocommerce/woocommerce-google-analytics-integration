<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;

/**
 * Class CheckoutProcess
 *
 * @since 1.6.0
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class CheckoutProcess extends EventsDataTest {

	/**
	 * Run unit test against the `begin_checkout` event
	 *
	 * @return void
	 */
	public function test_begin_checkout_event() {
		wp_set_current_user( $this->get_customer()->get_id() );

		$product = $this->get_product();
		$cart    = WC()->cart;

		$add_to = $cart->add_to_cart( $product->get_id() );
		( new WC_Google_Gtag_JS() )->checkout_process( $cart->get_cart() );

		// Confirm woocommerce_gtag_event_data is called by checkout_process().
		$this->assertEquals( 1, $this->get_event_data_filter_call_count(), 'woocommerce_gtag_event_data filter was not called for begin_checkout (checkout_process()) event' );

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

		// Confirm data structure matches what's expected.
		$this->assertEquals( $expected_data, $this->get_event_data(), 'Event data does not match expected data structure for begin_checkout (checkout_process()) event' );
	}
}
