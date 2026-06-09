<?php

namespace GoogleAnalyticsIntegration\Tests;

use WP_UnitTestCase;

/**
 * Unit tests for plugin asset metadata helpers.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class PluginAssets extends WP_UnitTestCase {

	/** @var \WC_Google_Analytics_Integration */
	private $plugin;

	/**
	 * Set up the plugin instance.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->plugin = \WC_Google_Analytics_Integration::get_instance();
	}

	/**
	 * Test that committed asset metadata is loaded for the main bundle.
	 *
	 * @return void
	 */
	public function test_get_js_asset_file_loads_existing_metadata() {
		$asset = $this->plugin->get_js_asset_file( 'main' );

		$this->assertSame( array( 'wp-hooks', 'wp-i18n' ), $asset['dependencies'] );
		$this->assertIsString( $asset['version'] );
		$this->assertNotEmpty( $asset['version'] );
	}

	/**
	 * Test that missing asset metadata returns the documented empty array.
	 *
	 * @return void
	 */
	public function test_get_js_asset_file_returns_empty_array_for_missing_metadata() {
		$this->assertSame( array(), $this->plugin->get_js_asset_file( 'missing-bundle' ) );
	}

	/**
	 * Test dependency lookup and explicit extra dependencies.
	 *
	 * @return void
	 */
	public function test_get_js_asset_dependencies_merges_extra_dependencies_once() {
		$this->assertSame(
			array( 'wp-hooks', 'wp-i18n', 'jquery' ),
			array_values( $this->plugin->get_js_asset_dependencies( 'main', array( 'wp-hooks', 'jquery' ) ) )
		);
	}

	/**
	 * Test missing asset dependency and version fallbacks.
	 *
	 * @return void
	 */
	public function test_missing_asset_dependency_and_version_fallbacks() {
		$this->assertSame( array( 'jquery' ), $this->plugin->get_js_asset_dependencies( 'missing-bundle', array( 'jquery' ) ) );
		$this->assertFalse( $this->plugin->get_js_asset_version( 'missing-bundle' ) );
	}
}
