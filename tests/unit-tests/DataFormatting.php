<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;
use WC_Helper_Product;
use WC_Helper_Customer;
use WC_Helper_Order;
use WC_Product_Simple;

/**
 * Unit tests for data formatting methods on WC_Abstract_Google_Analytics_JS
 * tested via the WC_Google_Gtag_JS concrete class.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class DataFormatting extends EventsDataTest {

	/** @var WC_Google_Gtag_JS */
	private $gtag;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->gtag = new WC_Google_Gtag_JS( [ 'ga_product_identifier' => 'product_id' ] );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}
		parent::tear_down();
	}

	/**
	 * Test that a standard price is formatted correctly.
	 *
	 * @return void
	 */
	public function test_get_formatted_price_standard() {
		$this->assertEquals( 1000, $this->gtag->get_formatted_price( 10.00 ) );
	}

	/**
	 * Test that zero price returns zero.
	 *
	 * @return void
	 */
	public function test_get_formatted_price_zero() {
		$this->assertEquals( 0, $this->gtag->get_formatted_price( 0 ) );
	}

	/**
	 * Test that string input is cast to float before formatting.
	 *
	 * @return void
	 */
	public function test_get_formatted_price_string_input() {
		$this->assertEquals( 999, $this->gtag->get_formatted_price( '9.99' ) );
	}

	/**
	 * Test that half-cent values round up correctly.
	 *
	 * @return void
	 */
	public function test_get_formatted_price_rounding_up() {
		$this->assertEquals( 1000, $this->gtag->get_formatted_price( 9.995 ) );
	}

	/**
	 * Test that sub-half-cent values round down (not ceil).
	 *
	 * @return void
	 */
	public function test_get_formatted_price_rounding_down() {
		$this->assertEquals( 1000, $this->gtag->get_formatted_price( 10.004 ) );
	}

	/**
	 * Test that negative prices (refunds) are formatted correctly.
	 *
	 * @return void
	 */
	public function test_get_formatted_price_negative() {
		$this->assertEquals( -1000, $this->gtag->get_formatted_price( -10.00 ) );
	}

	/**
	 * Test that the method returns an empty array for a non-product value.
	 *
	 * This is the central safety net: even if a caller (including third-party
	 * or future code) passes a non-WC_Product, the method must not fatal.
	 *
	 * @return void
	 */
	public function test_get_formatted_product_returns_empty_for_non_product() {
		$this->assertSame( array(), $this->gtag->get_formatted_product( false ) );
		$this->assertSame( array(), $this->gtag->get_formatted_product( null ) );
	}

	/**
	 * Test that a simple product returns correct structure and values.
	 *
	 * @return void
	 */
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

	/**
	 * Test that the SKU-based identifier is used when ga_product_identifier is product_sku.
	 *
	 * @return void
	 */
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

	/**
	 * Test that passing a variation_id uses the variation's price.
	 *
	 * @return void
	 */
	public function test_get_formatted_product_with_variation_id_uses_variation_price() {
		$variation_product = WC_Helper_Product::create_variation_product();
		$variations        = $variation_product->get_children();
		$variation_id      = $variations[0];
		$variation         = wc_get_product( $variation_id );

		$formatted = $this->gtag->get_formatted_product( $variation_product, $variation_id );

		$expected_price = $this->gtag->get_formatted_price( $variation->get_price() );
		$this->assertEquals( $variation_product->get_id(), $formatted['id'] );
		$this->assertEquals( $expected_price, $formatted['prices']['price'] );
	}

	/**
	 * Test that bundle products use their minimum bundle price when Product Bundles exposes it.
	 *
	 * @return void
	 */
	public function test_get_formatted_product_bundle_uses_minimum_bundle_price() {
		$product = WC_Helper_Product::create_simple_product();
		$bundle  = $this->getMockBuilder( WC_Product_Simple::class )
			->setConstructorArgs( [ $product->get_id() ] )
			->onlyMethods( [ 'get_type' ] )
			->addMethods( [ 'get_bundle_price' ] )
			->getMock();
		$bundle->method( 'get_type' )->willReturn( 'bundle' );
		$bundle->method( 'get_bundle_price' )->with( 'min' )->willReturn( '12.34' );

		$formatted = $this->gtag->get_formatted_product( $bundle );

		$this->assertEquals( $product->get_id(), $formatted['id'] );
		$this->assertEquals( $product->get_title(), $formatted['name'] );
		$this->assertEquals( 1234, $formatted['prices']['price'] );
	}

	/**
	 * Test that a variation array is formatted as "attr: value, attr2: value2".
	 *
	 * @return void
	 */
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

	/**
	 * Test that a variation-type product uses the parent ID and includes attribute data.
	 *
	 * @return void
	 */
	public function test_get_formatted_product_variation_type_uses_parent_id_and_attributes() {
		$variation_product = WC_Helper_Product::create_variation_product();
		$variations        = $variation_product->get_children();
		$variation         = wc_get_product( $variations[0] );

		$formatted = $this->gtag->get_formatted_product( $variation );

		$this->assertEquals( $variation->get_parent_id(), $formatted['id'] );
		$this->assertArrayHasKey( 'variation', $formatted );
		$this->assertNotEmpty( $formatted['variation'], 'Variation string should contain attribute data' );
	}

	/**
	 * Test that quantity is included when passed.
	 *
	 * @return void
	 */
	public function test_get_formatted_product_with_quantity() {
		$product   = $this->get_product();
		$formatted = $this->gtag->get_formatted_product( $product, 0, false, 3 );

		$this->assertArrayHasKey( 'quantity', $formatted );
		$this->assertEquals( 3, $formatted['quantity'] );
	}

	/**
	 * Test that quantity is omitted when not passed.
	 *
	 * @return void
	 */
	public function test_get_formatted_product_without_quantity() {
		$product   = $this->get_product();
		$formatted = $this->gtag->get_formatted_product( $product );

		$this->assertArrayNotHasKey( 'quantity', $formatted );
	}

	/**
	 * Test that categories are limited to 5.
	 *
	 * @return void
	 */
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

	/**
	 * Test that a child category includes its parent before the child term.
	 *
	 * @return void
	 */
	public function test_get_formatted_product_categories_include_parent_names() {
		$product = WC_Helper_Product::create_simple_product();
		$parent  = wp_insert_term( 'Parent_' . uniqid(), 'product_cat' );
		$child   = wp_insert_term(
			'Child_' . uniqid(),
			'product_cat',
			[
				'parent' => $parent['term_id'],
			]
		);

		wp_set_object_terms( $product->get_id(), [ $child['term_id'] ], 'product_cat' );
		clean_object_term_cache( $product->get_id(), 'product' );

		$formatted = $this->gtag->get_formatted_product( $product );

		$this->assertEquals(
			[ $parent['term_id'], $child['term_id'] ],
			array_map(
				function ( $category ) {
					return get_term_by( 'name', $category['name'], 'product_cat' )->term_id;
				},
				$formatted['categories']
			)
		);
	}

	/**
	 * Test that multiple category trees are flattened deterministically without duplicate parents.
	 *
	 * @return void
	 */
	public function test_get_formatted_product_categories_flatten_multiple_trees() {
		$product      = WC_Helper_Product::create_simple_product();
		$clothing     = wp_insert_term( 'Clothing_' . uniqid(), 'product_cat' );
		$hoodies      = wp_insert_term(
			'Hoodies_' . uniqid(),
			'product_cat',
			[
				'parent' => $clothing['term_id'],
			]
		);
		$fan_gear     = wp_insert_term( 'Fan Gear_' . uniqid(), 'product_cat' );
		$real_madrid  = wp_insert_term(
			'Real Madrid_' . uniqid(),
			'product_cat',
			[
				'parent' => $fan_gear['term_id'],
			]
		);
		$category_ids = [ $real_madrid['term_id'], $hoodies['term_id'] ];
		$expected_ids = [ $clothing['term_id'], $hoodies['term_id'], $fan_gear['term_id'], $real_madrid['term_id'] ];

		wp_set_object_terms( $product->get_id(), $category_ids, 'product_cat' );
		clean_object_term_cache( $product->get_id(), 'product' );

		$formatted = $this->gtag->get_formatted_product( $product );

		$this->assertEquals(
			$expected_ids,
			array_map(
				function ( $category ) {
					return get_term_by( 'name', $category['name'], 'product_cat' )->term_id;
				},
				$formatted['categories']
			)
		);
	}

	/**
	 * Test that the assigned leaf term is kept when its category path is deeper than five levels.
	 *
	 * @return void
	 */
	public function test_get_formatted_product_categories_deep_path_keeps_leaf() {
		$product   = WC_Helper_Product::create_simple_product();
		$parent_id = 0;
		$level_ids = [];

		// Build a single chain six levels deep (root -> ... -> leaf).
		for ( $i = 0; $i < 6; $i++ ) {
			$term        = wp_insert_term(
				"Level{$i}_" . uniqid(),
				'product_cat',
				$parent_id ? [ 'parent' => $parent_id ] : []
			);
			$parent_id   = $term['term_id'];
			$level_ids[] = $term['term_id'];
		}

		$leaf_id = end( $level_ids );

		wp_set_object_terms( $product->get_id(), [ $leaf_id ], 'product_cat' );
		clean_object_term_cache( $product->get_id(), 'product' );

		$formatted = $this->gtag->get_formatted_product( $product );

		$category_ids = array_map(
			function ( $category ) {
				return get_term_by( 'name', $category['name'], 'product_cat' )->term_id;
			},
			$formatted['categories']
		);

		// GA4 supports five levels: the five most specific terms, ending with the assigned leaf.
		$this->assertEquals( array_slice( $level_ids, -5 ), $category_ids );
		$this->assertContains( $leaf_id, $category_ids );
		$this->assertSame( $leaf_id, end( $category_ids ) );
	}

	/**
	 * Create a fresh order with its own product and customer for test isolation.
	 *
	 * @return \WC_Order
	 */
	private function create_order_with_product() {
		$product  = WC_Helper_Product::create_simple_product();
		$customer = WC_Helper_Customer::create_customer( 'fmt_' . uniqid(), 'pw', uniqid() . '@test.test' );
		return WC_Helper_Order::create_order( $customer->get_id(), $product );
	}

	/**
	 * Test that the WooCommerce order number and blog name affiliation are returned.
	 *
	 * @return void
	 */
	public function test_get_formatted_order_returns_order_number_and_affiliation() {
		$order     = $this->create_order_with_product();
		$formatted = $this->gtag->get_formatted_order( $order );

		$this->assertEquals( $order->get_order_number(), $formatted['id'] );
		$this->assertEquals( get_bloginfo( 'name' ), $formatted['affiliation'] );
	}

	/**
	 * Test that a custom order number (e.g., from a sequential order numbers plugin)
	 * is honored via the woocommerce_order_number filter.
	 *
	 * @return void
	 */
	public function test_get_formatted_order_honors_woocommerce_order_number_filter() {
		$order  = $this->create_order_with_product();
		$custom = 'CUSTOM-' . $order->get_id();

		$callback = function ( $order_number, $passed_order ) use ( $order, $custom ) {
			return $passed_order->get_id() === $order->get_id() ? $custom : $order_number;
		};
		add_filter( 'woocommerce_order_number', $callback, 10, 2 );

		try {
			$formatted = $this->gtag->get_formatted_order( $order );
			$this->assertEquals( $custom, $formatted['id'] );
		} finally {
			remove_filter( 'woocommerce_order_number', $callback, 10 );
		}
	}

	/**
	 * Test that the woocommerce_ga_order_id filter overrides the order identifier
	 * sent to GA without affecting WooCommerce's order number elsewhere.
	 *
	 * @return void
	 */
	public function test_get_formatted_order_honors_woocommerce_ga_order_id_filter() {
		$order = $this->create_order_with_product();

		$callback = function () {
			return 'GA-OVERRIDE';
		};
		add_filter( 'woocommerce_ga_order_id', $callback );

		try {
			$formatted = $this->gtag->get_formatted_order( $order );
			$this->assertEquals( 'GA-OVERRIDE', $formatted['id'] );
		} finally {
			remove_filter( 'woocommerce_ga_order_id', $callback );
		}
	}

	/**
	 * Test that order totals contain correct currency, decimal, and price values.
	 *
	 * @return void
	 */
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

	/**
	 * Test that order items contain merged product data and order-specific fields.
	 *
	 * @return void
	 */
	public function test_get_formatted_order_items_contain_product_data() {
		$order     = $this->create_order_with_product();
		$formatted = $this->gtag->get_formatted_order( $order );

		$this->assertNotEmpty( $formatted['items'] );

		$item       = $formatted['items'][0];
		$order_item = array_values( $order->get_items() )[0];
		$product    = $order_item->get_product();

		$this->assertEquals( $product->get_id(), $item['id'] );
		$this->assertEquals( $product->get_title(), $item['name'] );
		$this->assertArrayHasKey( 'categories', $item );
		$this->assertArrayHasKey( 'prices', $item );
		$this->assertArrayHasKey( 'extensions', $item );

		$this->assertEquals( $order_item->get_quantity(), $item['quantity'] );
		$this->assertEquals(
			$this->gtag->get_formatted_price( $order_item->get_subtotal() / $order_item->get_quantity() ),
			$item['prices']['price']
		);
		$this->assertEquals(
			$this->gtag->get_formatted_price( $order_item->get_total() / $order_item->get_quantity() ),
			$item['price_after_coupon_discount']
		);
	}

	/**
	 * Create an order containing one line item with explicit subtotal/total values.
	 *
	 * @param float $catalog_price Product catalog price (may be tax-inclusive in the simulated store).
	 * @param float $quantity      Line item quantity (decimal if woocommerce_stock_amount allows it).
	 * @param float $subtotal      Tax-exclusive line subtotal (before coupon discounts).
	 * @param float $total         Tax-exclusive line total (after coupon discounts).
	 *
	 * @return \WC_Order
	 */
	private function create_order_with_line_values( $catalog_price, $quantity, $subtotal, $total ) {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( $catalog_price );
		$product->set_price( $catalog_price );
		$product->save();

		$order = wc_create_order();
		$order->add_product(
			$product,
			$quantity,
			[
				'subtotal' => $subtotal,
				'total'    => $total,
			]
		);
		$order->save();

		return $order;
	}

	/**
	 * Test that item prices come from the tax-exclusive order line, not the
	 * catalog price. With prices entered inclusive of tax, using the catalog
	 * price produced a false discount equal to the tax (ticket case 1:
	 * 95 incl. 6% tax, line subtotal 89.62).
	 *
	 * @return void
	 */
	public function test_get_formatted_order_item_price_is_per_unit_tax_exclusive() {
		$order = $this->create_order_with_line_values( 95, 1, 89.62, 89.62 );
		$item  = $this->gtag->get_formatted_order( $order )['items'][0];

		$this->assertEquals( 8962, $item['prices']['price'] );
		$this->assertEquals( 8962, $item['price_after_coupon_discount'] );
	}

	/**
	 * Test that with a coupon both amounts stay per-unit and tax-exclusive
	 * (ticket case 3: 10% coupon on a 89.62 line).
	 *
	 * @return void
	 */
	public function test_get_formatted_order_item_price_after_coupon_is_per_unit() {
		$order = $this->create_order_with_line_values( 95, 1, 89.62, 80.66 );
		$item  = $this->gtag->get_formatted_order( $order )['items'][0];

		$this->assertEquals( 8962, $item['prices']['price'] );
		$this->assertEquals( 8066, $item['price_after_coupon_discount'] );
	}

	/**
	 * Test that a line total not evenly divisible by quantity rounds to the
	 * nearest minor unit (179.25 / 2 = 89.625 -> 89.63).
	 *
	 * @return void
	 */
	public function test_get_formatted_order_item_price_rounds_per_unit_amounts() {
		$order = $this->create_order_with_line_values( 95, 2, 179.25, 179.25 );
		$item  = $this->gtag->get_formatted_order( $order )['items'][0];

		$this->assertEquals( 8963, $item['prices']['price'] );
		$this->assertEquals( 8963, $item['price_after_coupon_discount'] );
	}

	/**
	 * Test that decimal quantities (enabled by extensions via the
	 * woocommerce_stock_amount filter) produce the correct per-unit price
	 * instead of being truncated to an integer.
	 *
	 * @return void
	 */
	public function test_get_formatted_order_item_price_supports_decimal_quantity() {
		// Core registers intval on this filter; decimal-quantity extensions swap it for floatval.
		remove_filter( 'woocommerce_stock_amount', 'intval' );
		add_filter( 'woocommerce_stock_amount', 'floatval' );

		try {
			// 0.5 units of a 10.00 product: line subtotal and total are 5.00.
			$order = $this->create_order_with_line_values( 10, 0.5, 5, 5 );
			$item  = $this->gtag->get_formatted_order( $order )['items'][0];

			$this->assertEquals( 0.5, $item['quantity'] );
			$this->assertEquals( 1000, $item['prices']['price'] );
			$this->assertEquals( 1000, $item['price_after_coupon_discount'] );
		} finally {
			remove_filter( 'woocommerce_stock_amount', 'floatval' );
			add_filter( 'woocommerce_stock_amount', 'intval' );
		}
	}

	/**
	 * Test that a null cart returns an empty array.
	 *
	 * @return void
	 */
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

	/**
	 * Test that an empty (non-null) cart returns the expected structure.
	 *
	 * @return void
	 */
	public function test_get_formatted_cart_empty_cart() {
		$formatted = $this->gtag->get_formatted_cart();

		$this->assertArrayHasKey( 'items', $formatted );
		$this->assertEmpty( $formatted['items'] );
		$this->assertArrayHasKey( 'totals', $formatted );
	}

	/**
	 * Test that a cart with items returns correct product data and totals.
	 *
	 * @return void
	 */
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

	/**
	 * Test that a cart item whose 'data' is not a WC_Product is skipped
	 * instead of fataling the whole render.
	 *
	 * Core drops unresolvable items while rebuilding the cart from session, so
	 * a non-product 'data' in practice comes from third-party code mutating the
	 * cart contents. Simulated here via the woocommerce_get_cart_contents
	 * filter.
	 *
	 * @return void
	 */
	public function test_get_formatted_cart_skips_unresolvable_product() {
		$product = WC_Helper_Product::create_simple_product();
		WC()->cart->add_to_cart( $product->get_id(), 2 );

		// Simulate third-party code leaving a cart item without a valid product.
		$callback = function ( $contents ) {
			foreach ( $contents as $key => $item ) {
				$contents[ $key ]['data'] = false;
			}
			return $contents;
		};
		add_filter( 'woocommerce_get_cart_contents', $callback );

		try {
			$formatted = $this->gtag->get_formatted_cart();

			$this->assertIsArray( $formatted );
			$this->assertArrayHasKey( 'items', $formatted );
			$this->assertEmpty( $formatted['items'], 'Unresolvable cart items should be skipped.' );
		} finally {
			remove_filter( 'woocommerce_get_cart_contents', $callback );
		}
	}

	/**
	 * Test that an order line whose product was deleted is skipped instead
	 * of fataling the whole render.
	 *
	 * WC_Order_Item_Product::get_product() returns false for a deleted
	 * order-line product.
	 *
	 * @return void
	 */
	public function test_get_formatted_order_skips_deleted_line_product() {
		$order = $this->create_order_with_product();

		// Delete the underlying product so get_product() returns false.
		foreach ( $order->get_items() as $item ) {
			wp_delete_post( $item->get_product_id(), true );
		}

		// Reload the order so the line items resolve their products fresh.
		$order = wc_get_order( $order->get_id() );

		$formatted = $this->gtag->get_formatted_order( $order );

		$this->assertIsArray( $formatted['items'] );
		$this->assertEmpty( $formatted['items'], 'Order items with a deleted product should be skipped.' );
	}

	/**
	 * Test that the woocommerce_loop_add_to_cart_link callback ignores a
	 * non-WC_Product value instead of fataling the page render.
	 *
	 * Third-party themes/extensions can apply this filter with a value that is
	 * not a product.
	 *
	 * @return void
	 */
	public function test_loop_add_to_cart_link_filter_ignores_non_product() {
		$button = '<a href="#">Add to cart</a>';

		$result = apply_filters( 'woocommerce_loop_add_to_cart_link', $button, false );

		$this->assertEquals( $button, $result, 'The filter should return the button unchanged for a non-product value.' );

		$data = (array) json_decode( $this->gtag->get_script_data(), true );
		$this->assertArrayNotHasKey( 'products', $data, 'No product data should be appended for a non-product value.' );
	}

	/**
	 * Test that capturing an add-to-cart for an unresolvable product neither
	 * fatals nor emits empty added_to_cart data.
	 *
	 * Covers the woocommerce_add_to_cart capture path: with no matching cart
	 * item, wc_get_product() returns false for a product id that no longer
	 * resolves.
	 *
	 * @return void
	 */
	public function test_capture_added_to_cart_ignores_unresolvable_product() {
		$this->gtag->capture_added_to_cart( 'missing_key', 999999999, 1, 0, array() );

		$data = (array) json_decode( $this->gtag->get_script_data(), true );

		$this->assertArrayNotHasKey( 'added_to_cart', $data, 'No added_to_cart data should be set for an unresolvable product.' );
	}

	/**
	 * Test that the woocommerce_before_single_product hook neither fatals nor
	 * emits product data when the global $product is not a WC_Product.
	 *
	 * Covers the single-product render path.
	 *
	 * @return void
	 */
	public function test_before_single_product_action_ignores_non_product() {
		global $product;
		$original = $product;
		$product  = false;

		try {
			do_action( 'woocommerce_before_single_product' );

			$data = (array) json_decode( $this->gtag->get_script_data(), true );
			$this->assertArrayNotHasKey( 'product', $data, 'No product data should be set when the global product is not a WC_Product.' );
		} finally {
			$product = $original;
		}
	}
}
