<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;
use WC_Helper_Product;
use WC_Helper_Customer;
use WC_Helper_Order;

/**
 * Unit tests for data formatting methods on WC_Abstract_Google_Analytics_JS
 * tested via the WC_Google_Gtag_JS concrete class.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class DataFormatting extends EventsDataTest {

	/** @var WC_Google_Gtag_JS */
	private $gtag;

	public function set_up() {
		parent::set_up();
		$this->gtag = new WC_Google_Gtag_JS( [ 'ga_product_identifier' => 'product_id' ] );
	}

	public function tear_down() {
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}
		parent::tear_down();
	}

	// --- get_formatted_price ---

	public function test_get_formatted_price_standard() {
		$this->assertEquals( 1000, $this->gtag->get_formatted_price( 10.00 ) );
	}

	public function test_get_formatted_price_zero() {
		$this->assertEquals( 0, $this->gtag->get_formatted_price( 0 ) );
	}

	public function test_get_formatted_price_string_input() {
		$this->assertEquals( 999, $this->gtag->get_formatted_price( '9.99' ) );
	}

	public function test_get_formatted_price_rounding_up() {
		// 9.995 * 100 = 999.5, rounded = 1000
		$this->assertEquals( 1000, $this->gtag->get_formatted_price( 9.995 ) );
	}

	public function test_get_formatted_price_rounding_down() {
		// 10.004 * 100 = 1000.4, round gives 1000 (not 1001 like ceil would)
		$this->assertEquals( 1000, $this->gtag->get_formatted_price( 10.004 ) );
	}

	public function test_get_formatted_price_negative() {
		$this->assertEquals( -1000, $this->gtag->get_formatted_price( -10.00 ) );
	}

	// --- get_formatted_product ---

	public function test_get_formatted_product_simple_structure_and_values() {
		$product   = $this->get_product();
		$formatted = $this->gtag->get_formatted_product( $product );

		$this->assertEquals( $product->get_id(), $formatted['id'] );
		$this->assertEquals( $product->get_title(), $formatted['name'] );
		$this->assertIsArray( $formatted['categories'] );

		$this->assertEquals( $this->gtag->get_formatted_price( $product->get_price() ), $formatted['prices']['price'] );
		$this->assertEquals( wc_get_price_decimals(), $formatted['prices']['currency_minor_unit'] );

		$this->assertEquals(
			$product->get_id(),
			$formatted['extensions']['woocommerce_google_analytics_integration']['identifier']
		);
	}

	public function test_get_formatted_product_with_sku_identifier() {
		$gtag_sku = new WC_Google_Gtag_JS( [ 'ga_product_identifier' => 'product_sku' ] );
		$product  = WC_Helper_Product::create_simple_product();
		$product->set_sku( 'TEST-SKU-123' );
		$product->save();

		$formatted = $gtag_sku->get_formatted_product( $product );

		$this->assertEquals(
			'TEST-SKU-123',
			$formatted['extensions']['woocommerce_google_analytics_integration']['identifier']
		);
	}

	public function test_get_formatted_product_with_variation_id_uses_variation_price() {
		$variation_product = WC_Helper_Product::create_variation_product();
		$variations        = $variation_product->get_children();
		$variation_id      = $variations[0];
		$variation         = wc_get_product( $variation_id );

		$formatted = $this->gtag->get_formatted_product( $variation_product, $variation_id );

		$expected_price = $this->gtag->get_formatted_price( $variation->get_price() );
		$this->assertEquals( $expected_price, $formatted['prices']['price'] );
	}

	public function test_get_formatted_product_with_variation_array() {
		$product   = $this->get_product();
		$variation = [
			'attribute_color' => 'red',
			'attribute_size'  => 'large',
		];

		$formatted = $this->gtag->get_formatted_product( $product, 0, $variation );

		$this->assertArrayHasKey( 'variation', $formatted );
		$this->assertEquals( 'color: red, size: large', $formatted['variation'] );
	}

	public function test_get_formatted_product_variation_type_uses_parent_id_and_attributes() {
		$variation_product = WC_Helper_Product::create_variation_product();
		$variations        = $variation_product->get_children();
		$variation         = wc_get_product( $variations[0] );

		$formatted = $this->gtag->get_formatted_product( $variation );

		$this->assertEquals( $variation->get_parent_id(), $formatted['id'] );
		$this->assertArrayHasKey( 'variation', $formatted );
		$this->assertNotEmpty( $formatted['variation'], 'Variation string should contain attribute data' );
	}

	public function test_get_formatted_product_with_quantity() {
		$product   = $this->get_product();
		$formatted = $this->gtag->get_formatted_product( $product, 0, false, 3 );

		$this->assertArrayHasKey( 'quantity', $formatted );
		$this->assertEquals( 3, $formatted['quantity'] );
	}

	public function test_get_formatted_product_without_quantity() {
		$product   = $this->get_product();
		$formatted = $this->gtag->get_formatted_product( $product );

		$this->assertArrayNotHasKey( 'quantity', $formatted );
	}

	public function test_get_formatted_product_categories_limited_to_five() {
		$product = WC_Helper_Product::create_simple_product();

		$category_ids = [];
		for ( $i = 0; $i < 7; $i++ ) {
			$term           = wp_insert_term( "CatLimit{$i}_" . uniqid(), 'product_cat' );
			$category_ids[] = $term['term_id'];
		}
		wp_set_object_terms( $product->get_id(), $category_ids, 'product_cat' );
		clean_object_term_cache( $product->get_id(), 'product' );

		$formatted = $this->gtag->get_formatted_product( $product );

		$this->assertCount( 5, $formatted['categories'] );
		foreach ( $formatted['categories'] as $cat ) {
			$this->assertArrayHasKey( 'name', $cat );
		}
	}

	// --- get_formatted_order ---

	private function create_order_with_product() {
		$product  = WC_Helper_Product::create_simple_product();
		$customer = WC_Helper_Customer::create_customer( 'fmt_' . uniqid(), 'pw', uniqid() . '@test.test' );
		return WC_Helper_Order::create_order( $customer->get_id(), $product );
	}

	public function test_get_formatted_order_returns_id_and_affiliation() {
		$order     = $this->create_order_with_product();
		$formatted = $this->gtag->get_formatted_order( $order );

		$this->assertEquals( $order->get_id(), $formatted['id'] );
		$this->assertEquals( get_bloginfo( 'name' ), $formatted['affiliation'] );
	}

	public function test_get_formatted_order_totals_values() {
		$order     = $this->create_order_with_product();
		$formatted = $this->gtag->get_formatted_order( $order );
		$totals    = $formatted['totals'];

		$this->assertEquals( $order->get_currency(), $totals['currency_code'] );
		$this->assertEquals( wc_get_price_decimals(), $totals['currency_minor_unit'] );
		$this->assertEquals( $this->gtag->get_formatted_price( $order->get_total_tax() ), $totals['tax_total'] );
		$this->assertEquals( $this->gtag->get_formatted_price( $order->get_total_shipping() ), $totals['shipping_total'] );
		$this->assertEquals( $this->gtag->get_formatted_price( $order->get_total() ), $totals['total_price'] );
	}

	public function test_get_formatted_order_items_contain_product_data() {
		$order     = $this->create_order_with_product();
		$formatted = $this->gtag->get_formatted_order( $order );

		$this->assertNotEmpty( $formatted['items'] );

		$item       = $formatted['items'][0];
		$order_item = array_values( $order->get_items() )[0];
		$product    = $order_item->get_product();

		// Values from get_formatted_product (merged)
		$this->assertEquals( $product->get_id(), $item['id'] );
		$this->assertEquals( $product->get_title(), $item['name'] );
		$this->assertArrayHasKey( 'categories', $item );
		$this->assertArrayHasKey( 'prices', $item );
		$this->assertArrayHasKey( 'extensions', $item );

		// Order-specific fields
		$this->assertEquals( $order_item->get_quantity(), $item['quantity'] );
		$this->assertEquals(
			$this->gtag->get_formatted_price( $order_item->get_total() ),
			$item['price_after_coupon_discount']
		);
	}

	// --- get_formatted_cart ---

	public function test_get_formatted_cart_null_cart_returns_empty_array() {
		$original_cart = WC()->cart;
		try {
			WC()->cart = null;

			$formatted = $this->gtag->get_formatted_cart();

			$this->assertIsArray( $formatted );
			$this->assertEmpty( $formatted );
		} finally {
			WC()->cart = $original_cart;
		}
	}

	public function test_get_formatted_cart_empty_cart() {
		$formatted = $this->gtag->get_formatted_cart();

		$this->assertArrayHasKey( 'items', $formatted );
		$this->assertEmpty( $formatted['items'] );
		$this->assertArrayHasKey( 'totals', $formatted );
	}

	public function test_get_formatted_cart_with_items() {
		$product = WC_Helper_Product::create_simple_product();
		WC()->cart->add_to_cart( $product->get_id(), 2 );

		$formatted = $this->gtag->get_formatted_cart();

		$this->assertNotEmpty( $formatted['items'] );
		$this->assertArrayHasKey( 'coupons', $formatted );

		$item = $formatted['items'][0];
		$this->assertEquals( $product->get_id(), $item['id'] );
		$this->assertEquals( $product->get_title(), $item['name'] );
		$this->assertEquals( 2, $item['quantity'] );
		$this->assertArrayHasKey( 'prices', $item );
		$this->assertEquals( wc_get_price_decimals(), $item['prices']['currency_minor_unit'] );

		$totals = $formatted['totals'];
		$this->assertEquals( get_woocommerce_currency(), $totals['currency_code'] );
		$this->assertEquals( wc_get_price_decimals(), $totals['currency_minor_unit'] );
		$this->assertIsInt( $totals['total_price'] );
	}
}
