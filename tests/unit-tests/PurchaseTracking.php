<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;
use WC_Helper_Order;
use WC_Helper_Product;

/**
 * Unit tests for sending the purchase event once per order.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class PurchaseTracking extends EventsDataTest {

	/** @var WC_Google_Gtag_JS */
	private $gtag;

	/** @var \WC_Order */
	private $order;

	/**
	 * Create an order and a fresh gtag instance that is the only one on the thank you hook.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->order = WC_Helper_Order::create_order( 0, WC_Helper_Product::create_simple_product() );

		remove_all_actions( 'woocommerce_thankyou' );
		$this->gtag = new WC_Google_Gtag_JS(
			[
				'ga_product_identifier'         => 'product_id',
				'ga_ecommerce_tracking_enabled' => 'yes',
			]
		);
	}

	/**
	 * Clear the request key.
	 *
	 * @return void
	 */
	public function tear_down() {
		unset( $_GET['key'] );
		parent::tear_down();
	}

	/**
	 * The first visit with a valid key tracks the order and marks it.
	 *
	 * @return void
	 */
	public function test_first_visit_tracks_order_and_sets_meta() {
		$_GET['key'] = $this->order->get_order_key();

		do_action( 'woocommerce_thankyou', $this->order->get_id() );

		$data = json_decode( $this->gtag->get_script_data(), true );
		$this->assertSame( (string) $this->order->get_id(), (string) $data['order']['id'] );
		$this->assertSame( '1', wc_get_order( $this->order->get_id() )->get_meta( '_ga_tracked' ) );
	}

	/**
	 * A later visit for the same order is not tracked again.
	 *
	 * @return void
	 */
	public function test_second_visit_does_not_track_again() {
		$_GET['key'] = $this->order->get_order_key();
		do_action( 'woocommerce_thankyou', $this->order->get_id() );

		$second = new WC_Google_Gtag_JS( [ 'ga_ecommerce_tracking_enabled' => 'yes' ] );
		do_action( 'woocommerce_thankyou', $this->order->get_id() );

		$data = json_decode( $second->get_script_data(), true );
		$this->assertArrayNotHasKey( 'order', $data );
	}

	/**
	 * A wrong or missing key neither tracks the order nor marks it.
	 *
	 * @return void
	 */
	public function test_invalid_key_does_not_track() {
		foreach ( [ 'wc_order_wrong', null ] as $key ) {
			if ( null === $key ) {
				unset( $_GET['key'] );
			} else {
				$_GET['key'] = $key;
			}

			do_action( 'woocommerce_thankyou', $this->order->get_id() );

			$data = json_decode( $this->gtag->get_script_data(), true );
			$this->assertArrayNotHasKey( 'order', $data );
			$this->assertSame( '', wc_get_order( $this->order->get_id() )->get_meta( '_ga_tracked' ) );
		}
	}

	/**
	 * With purchase tracking turned off nothing is tracked.
	 *
	 * @return void
	 */
	public function test_disabled_setting_does_not_track() {
		remove_all_actions( 'woocommerce_thankyou' );
		$gtag        = new WC_Google_Gtag_JS( [ 'ga_ecommerce_tracking_enabled' => 'no' ] );
		$_GET['key'] = $this->order->get_order_key();

		do_action( 'woocommerce_thankyou', $this->order->get_id() );

		$data = json_decode( $gtag->get_script_data(), true );
		$this->assertArrayNotHasKey( 'order', $data );
		$this->assertSame( '', wc_get_order( $this->order->get_id() )->get_meta( '_ga_tracked' ) );
	}
}
