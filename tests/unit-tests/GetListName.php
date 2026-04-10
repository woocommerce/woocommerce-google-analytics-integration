<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;

/**
 * Unit tests for WC_Abstract_Google_Analytics_JS::get_list_name().
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class GetListName extends \WP_UnitTestCase {

	/** @var WC_Google_Gtag_JS */
	private $gtag;

	/** @var \WP_Query */
	private $original_wp_query;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->gtag = new WC_Google_Gtag_JS();

		global $wp_query;
		$this->original_wp_query = clone $wp_query;
	}

	/**
	 * Restore original $wp_query after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $wp_query;
		$wp_query = clone $this->original_wp_query;
		parent::tear_down();
	}

	/**
	 * Returns 'Product List' when no specific page context is detected.
	 *
	 * @return void
	 */
	public function test_returns_product_list_as_default() {
		$this->assertEquals(
			'Product List',
			$this->gtag->get_list_name()
		);
	}

	/**
	 * Returns 'Search Results' on a WordPress search page.
	 *
	 * @return void
	 */
	public function test_returns_search_results_on_search_page() {
		global $wp_query;
		$wp_query->is_search = true;

		$this->assertEquals(
			'Search Results',
			$this->gtag->get_list_name()
		);
	}

	/**
	 * Returns 'Shop' on the WooCommerce shop page.
	 *
	 * @return void
	 */
	public function test_returns_shop_on_shop_page() {
		$page_id = wp_insert_post(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Shop',
			]
		);
		update_option( 'woocommerce_shop_page_id', $page_id );

		global $wp_query;
		$wp_query->is_page           = true;
		$wp_query->queried_object    = get_post( $page_id );
		$wp_query->queried_object_id = $page_id;

		$this->assertEquals(
			'Shop',
			$this->gtag->get_list_name()
		);
	}

	/**
	 * Returns 'Category: <name>' on a product category archive page.
	 *
	 * @return void
	 */
	public function test_returns_category_name_on_product_category_page() {
		$term = wp_insert_term( 'Women', 'product_cat' );

		global $wp_query;
		$wp_query->is_tax            = true;
		$wp_query->queried_object    = get_term( $term['term_id'], 'product_cat' );
		$wp_query->queried_object_id = $term['term_id'];

		$this->assertEquals(
			'Category: Women',
			$this->gtag->get_list_name()
		);
	}

	/**
	 * Returns 'Tag: <name>' on a product tag archive page.
	 *
	 * @return void
	 */
	public function test_returns_tag_name_on_product_tag_page() {
		$term = wp_insert_term( 'Sale', 'product_tag' );

		global $wp_query;
		$wp_query->is_tax            = true;
		$wp_query->queried_object    = get_term( $term['term_id'], 'product_tag' );
		$wp_query->queried_object_id = $term['term_id'];

		$this->assertEquals(
			'Tag: Sale',
			$this->gtag->get_list_name()
		);
	}
}
