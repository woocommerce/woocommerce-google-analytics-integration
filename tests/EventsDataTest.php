<?php

namespace GoogleAnalyticsIntegration\Tests;

use WP_UnitTestCase;
use WC_Helper_Product;
use WC_Helper_Customer;
use WC_Helper_Order;
use MockAction;

/**
 * Class EventsDataTest
 *
 * @since 1.6.0
 */
abstract class EventsDataTest extends WP_UnitTestCase {

	/** @var WC_Product */
	private static $product;

	/** @var WC_Customer */
	private static $customer;

	/** @var WC_Order */
	private static $order;

	/** @var MockAction Mock WordPress action to allow us to intercept data */
	public $filter;

	/**
	 * Setup mock filter and dummy product, order, and customer data
	 *
	 * @return void
	 */
	public function set_up() {
		if ( is_null( $this->filter ) ) {
			// Mock woocommerce_gtag_event_data filter to ensure it is called and the correct data is processed.
			$this->filter = new MockAction();
			add_filter( 'woocommerce_gtag_event_data', array( &$this->filter, 'filter' ) );
		}

		if ( is_null( self::$product ) ) {
			self::$product = WC_Helper_Product::create_simple_product();
		}

		if ( is_null( self::$customer ) ) {
			self::$customer = WC_Helper_Customer::create_customer( 'JD', 'pw', 'customer@unit.test' );
		}

		if ( is_null( self::$order ) ) {
			self::$order = WC_Helper_Order::create_order( self::get_customer()->get_id(), self::get_product() );
		}
	}

	/**
	 * Get dummy product to run tests against
	 *
	 * @return WC_Product
	 */
	public function get_product() {
		return self::$product;
	}

	/**
	 * Get dummy customer to run tests against
	 *
	 * @return WC_Customer
	 */
	public function get_customer() {
		return self::$customer;
	}

	/**
	 * Get dummy order to run tests against
	 *
	 * @return WC_Order
	 */
	public function get_order() {
		return static::$order;
	}

	/**
	 * Get filter
	 *
	 * @return MockAction
	 */
	public function get_filter() {
		return $this->filter;
	}

	/**
	 * Return call count for woocommerce_gtag_event_data filter
	 *
	 * @return int
	 */
	public function get_event_data_filter_call_count() {
		return $this->get_filter()->get_call_count();
	}

	/**
	 * Return data passed through woocommerce_gtag_event_data filter
	 *
	 * @return mixed
	 */
	public function get_event_data() {
		$args = $this->get_filter()->get_args();
		return $args[0][0];
	}

}
