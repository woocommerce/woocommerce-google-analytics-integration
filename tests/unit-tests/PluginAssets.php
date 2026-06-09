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

	/** @var string */
	private $asset_dir;

	/**
	 * Set up a plugin instance with controlled asset metadata fixtures.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->asset_dir = trailingslashit( sys_get_temp_dir() ) . 'wc-ga-assets-' . uniqid();
		wp_mkdir_p( $this->asset_dir );

		$this->plugin = $this->getMockBuilder( \WC_Google_Analytics_Integration::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_js_asset_path' ) )
			->getMock();

		$this->plugin->method( 'get_js_asset_path' )->willReturnCallback(
			function ( $end = '' ) {
				return trailingslashit( $this->asset_dir ) . $end;
			}
		);
	}

	/**
	 * Clean up temporary asset metadata fixtures.
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( glob( trailingslashit( $this->asset_dir ) . '*' ) as $asset_file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture cleanup.
			unlink( $asset_file );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture cleanup.
		rmdir( $this->asset_dir );

		parent::tear_down();
	}

	/**
	 * Test that existing asset metadata is loaded.
	 *
	 * @return void
	 */
	public function test_get_js_asset_file_loads_existing_metadata() {
		$this->write_asset_file(
			'main',
			array(
				'dependencies' => array( 'wp-hooks', 'wp-i18n' ),
				'version'      => 'test-version',
			)
		);

		$asset = $this->plugin->get_js_asset_file( 'main' );

		$this->assertSame( array( 'wp-hooks', 'wp-i18n' ), $asset['dependencies'] );
		$this->assertSame( 'test-version', $asset['version'] );
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
		$this->write_asset_file(
			'main',
			array(
				'dependencies' => array( 'wp-hooks', 'wp-i18n' ),
				'version'      => 'test-version',
			)
		);

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

	/**
	 * Write a temporary asset metadata fixture.
	 *
	 * @param string $asset_name Asset name.
	 * @param array  $asset      Asset metadata.
	 *
	 * @return void
	 */
	private function write_asset_file( string $asset_name, array $asset ): void {
		$asset_path = $this->plugin->get_js_asset_path( $asset_name . '.asset.php' );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Test fixture setup.
		$contents = '<?php return ' . var_export( $asset, true ) . ';';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.
		file_put_contents( $asset_path, $contents );
	}
}
