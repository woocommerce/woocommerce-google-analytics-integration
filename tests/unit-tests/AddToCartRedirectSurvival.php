<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;
use WC_Helper_Product;

/**
 * Unit tests for the redirect-survival of the add_to_cart event.
 *
 * Covers STORMA-42 / #427: when "Redirect to the cart page after successful addition"
 * is enabled, in-memory script data is lost before render. The fix persists the
 * formatted product through the WC session and restores it on the next request.
 *
 * Tests exercise the named handler methods directly rather than firing
 * `do_action()`, to avoid coupling assertions to global-state side-effects from
 * other tests' gtag instances.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class AddToCartRedirectSurvival extends EventsDataTest {

	/** @var WC_Google_Gtag_JS */
	private $gtag;

	/**
	 * Set up the gtag instance and ensure a clean WC session for each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->gtag = new WC_Google_Gtag_JS( [ 'ga_product_identifier' => 'product_id' ] );

		if ( WC()->session === null && class_exists( 'WC_Session_Handler' ) ) {
			WC()->initialize_session();
		}
		if ( WC()->session ) {
			WC()->session->__unset( '_ga_pending_added_to_cart' );
		}
	}

	/**
	 * Drop any pending session entry so it doesn't leak into the next test.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( WC()->session ) {
			WC()->session->__unset( '_ga_pending_added_to_cart' );
		}
		parent::tear_down();
	}

	/**
	 * Helper: decode the gtag instance's current script_data JSON.
	 *
	 * @return array
	 */
	private function script_data(): array {
		return json_decode( $this->gtag->get_script_data(), true );
	}

	/**
	 * The redirect filter stashes the formatted product into WC session,
	 * so the next request can restore it after the redirect.
	 *
	 * @return void
	 */
	public function test_redirect_filter_persists_added_to_cart_to_session() {
		$product = WC_Helper_Product::create_simple_product();
		$this->gtag->capture_added_to_cart( 'mock-key', $product->get_id(), 1, 0, [] );
		$this->gtag->persist_added_to_cart_for_redirect( 'http://example.test/cart' );

		$pending = WC()->session->get( '_ga_pending_added_to_cart' );
		$this->assertIsArray( $pending );
		$this->assertSame( $product->get_id(), $pending['id'] );
		$this->assertSame( $product->get_title(), $pending['name'] );
	}

	/**
	 * Without the redirect filter firing (e.g. AJAX or Store API add), nothing
	 * is persisted to session — otherwise a subsequent unrelated page load
	 * would over-fire add_to_cart.
	 *
	 * @return void
	 */
	public function test_no_session_stash_when_redirect_filter_does_not_fire() {
		$product = WC_Helper_Product::create_simple_product();
		$this->gtag->capture_added_to_cart( 'mock-key', $product->get_id(), 1, 0, [] );
		// Intentionally skip persist_added_to_cart_for_redirect().

		$pending = WC()->session->get( '_ga_pending_added_to_cart' );
		$this->assertEmpty( $pending, 'No session entry should be created when WC does not redirect.' );
	}

	/**
	 * On the redirected page, the restore method restores the formatted product
	 * as `added_to_cart` script data, appends `add_to_cart` to the events list,
	 * and clears the session key so it only fires once.
	 *
	 * @return void
	 */
	public function test_restore_from_session_populates_script_data_and_clears() {
		$fake_product = [
			'id'         => 999,
			'name'       => 'Test Product',
			'categories' => [],
			'prices'     => [
				'price'               => 1999,
				'currency_minor_unit' => 2,
			],
			'extensions' => [],
			'quantity'   => 1,
		];
		WC()->session->set( '_ga_pending_added_to_cart', $fake_product );

		$this->gtag->restore_added_to_cart_from_session();

		$data = $this->script_data();

		$this->assertArrayHasKey( 'added_to_cart', $data );
		$this->assertEquals( $fake_product, $data['added_to_cart'] );

		$this->assertArrayHasKey( 'events', $data );
		$this->assertContains( 'add_to_cart', $data['events'] );

		$this->assertEmpty(
			WC()->session->get( '_ga_pending_added_to_cart' ),
			'Session key should be cleared after consumption (one-shot).'
		);
	}

	/**
	 * If no session entry exists, the restore method is a no-op.
	 *
	 * @return void
	 */
	public function test_restore_is_noop_without_session_entry() {
		$this->gtag->restore_added_to_cart_from_session();

		$data = $this->script_data();

		$this->assertArrayNotHasKey( 'added_to_cart', $data );
		$events = $data['events'] ?? [];
		$this->assertNotContains( 'add_to_cart', $events );
	}
}
