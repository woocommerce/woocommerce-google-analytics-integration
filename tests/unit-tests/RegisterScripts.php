<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Google_Gtag_JS;
use WC_Abstract_Google_Analytics_JS;
use WP_UnitTestCase;

/**
 * Unit tests for the inline gtag setup snippet produced by
 * WC_Google_Gtag_JS::register_scripts().
 *
 * The measurement id baked into the GTM URL and the re-registration on
 * rehydration are already covered in WCGoogleGtagJS. These tests focus on the
 * inline config snippet, which is what actually boots gtag with the property id,
 * the consent defaults, and the tracker function name. If that snippet is wrong,
 * no events are recorded even though the script handles register fine.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
class RegisterScripts extends WP_UnitTestCase {

	/**
	 * Start each test from a clean script registry and singleton so the
	 * constructor re-runs register_scripts() against a known state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->reset_gtag_instance();
		wp_deregister_script( 'google-tag-manager' );
		wp_deregister_script( 'woocommerce-google-analytics-integration-gtag' );
		wp_deregister_script( 'woocommerce-google-analytics-integration' );
	}

	/**
	 * Clear the singleton installed by the constructors above so it does not
	 * leak into test classes that run after this one.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->reset_gtag_instance();
		parent::tear_down();
	}

	/**
	 * The setup script should carry an inline snippet that boots the dataLayer,
	 * defines the tracker function, and configures the property with the
	 * measurement id from settings.
	 *
	 * @return void
	 */
	public function test_inline_snippet_configures_the_measurement_id() {
		$gtag    = new WC_Google_Gtag_JS( [ 'ga_id' => 'G-TEST123' ] );
		$snippet = $this->get_inline_snippet( $gtag->gtag_script_handle );

		$this->assertStringContainsString( 'window.dataLayer = window.dataLayer', $snippet, 'Snippet should initialize the dataLayer' );
		$this->assertStringContainsString( 'function gtag(', $snippet, 'Snippet should define the default gtag function' );
		$this->assertStringContainsString( 'gtag("config", "G-TEST123"', $snippet, 'Snippet should configure the property with the measurement id' );
	}

	/**
	 * The plugin identifies itself to Google through a developer id, which must be
	 * present in the snippet.
	 *
	 * @return void
	 */
	public function test_inline_snippet_sets_the_developer_id() {
		$gtag    = new WC_Google_Gtag_JS( [ 'ga_id' => 'G-TEST123' ] );
		$snippet = $this->get_inline_snippet( $gtag->gtag_script_handle );

		$this->assertStringContainsString(
			'developer_id.' . WC_Abstract_Google_Analytics_JS::DEVELOPER_ID,
			$snippet,
			'Snippet should register the plugin developer id'
		);
	}

	/**
	 * Consent Mode defaults must be emitted so the EEA region is denied until the
	 * visitor updates consent.
	 *
	 * @return void
	 */
	public function test_inline_snippet_includes_consent_defaults() {
		$gtag    = new WC_Google_Gtag_JS( [ 'ga_id' => 'G-TEST123' ] );
		$snippet = $this->get_inline_snippet( $gtag->gtag_script_handle );

		$this->assertStringContainsString( '"consent", "default"', $snippet, 'Snippet should set default consent state' );
		$this->assertStringContainsString( 'analytics_storage', $snippet, 'Consent defaults should reference analytics_storage' );
	}

	/**
	 * The tracker function name is filterable, and register_scripts() must honor
	 * the override so the config call and the function definition stay in sync.
	 *
	 * @return void
	 */
	public function test_inline_snippet_uses_custom_tracker_function_name() {
		$callback = function () {
			return 'customGtag';
		};
		add_filter( 'woocommerce_gtag_tracker_variable', $callback );

		$gtag    = new WC_Google_Gtag_JS( [ 'ga_id' => 'G-TEST123' ] );
		$snippet = $this->get_inline_snippet( $gtag->gtag_script_handle );

		remove_filter( 'woocommerce_gtag_tracker_variable', $callback );

		$this->assertStringContainsString( 'function customGtag(', $snippet, 'Snippet should define the filtered tracker function' );
		$this->assertStringContainsString( 'customGtag("config", "G-TEST123"', $snippet, 'Snippet should configure the property through the filtered function' );
	}

	/**
	 * The woocommerce_gtag_snippet filter must be able to replace the snippet
	 * entirely so advanced integrations can supply their own bootstrap.
	 *
	 * @return void
	 */
	public function test_inline_snippet_can_be_replaced_by_filter() {
		$callback = function () {
			return '/* replaced snippet */';
		};
		add_filter( 'woocommerce_gtag_snippet', $callback );

		$gtag    = new WC_Google_Gtag_JS( [ 'ga_id' => 'G-TEST123' ] );
		$snippet = $this->get_inline_snippet( $gtag->gtag_script_handle );

		remove_filter( 'woocommerce_gtag_snippet', $callback );

		$this->assertStringContainsString( '/* replaced snippet */', $snippet );
		$this->assertStringNotContainsString( 'gtag("config"', $snippet, 'The default snippet should be replaced, not appended' );
	}

	/**
	 * The main bundle must depend on the gtag library so gtag is defined before
	 * the tracker runs.
	 *
	 * @return void
	 */
	public function test_main_script_depends_on_google_tag_manager() {
		$gtag = new WC_Google_Gtag_JS( [ 'ga_id' => 'G-TEST123' ] );

		$registered = wp_scripts()->query( $gtag->script_handle );
		$this->assertNotFalse( $registered, 'The main script should be registered' );
		$this->assertContains( 'google-tag-manager', $registered->deps, 'The main script should depend on the gtag library' );
	}

	/**
	 * Read the concatenated inline "after" data attached to a script handle.
	 *
	 * @param string $handle The registered script handle.
	 *
	 * @return string
	 */
	private function get_inline_snippet( $handle ) {
		// query() returns false for an unregistered handle instead of raising an
		// undefined-array-key warning like a direct registered[] access would.
		$registered = wp_scripts()->query( $handle );
		$this->assertNotFalse( $registered, "Script handle '{$handle}' should be registered" );

		$after = $registered->extra['after'] ?? [];
		return implode( "\n", array_filter( (array) $after, 'is_string' ) );
	}

	/**
	 * Reset the shared tracking singleton.
	 *
	 * @return void
	 */
	private function reset_gtag_instance(): void {
		$property = new \ReflectionProperty( WC_Abstract_Google_Analytics_JS::class, 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}
}
