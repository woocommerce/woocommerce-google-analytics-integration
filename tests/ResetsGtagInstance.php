<?php

namespace GoogleAnalyticsIntegration\Tests;

use WC_Abstract_Google_Analytics_JS;

/**
 * Clears the shared tracking singleton, so it does not leak between tests.
 *
 * @package GoogleAnalyticsIntegration\Tests
 */
trait ResetsGtagInstance {

	/**
	 * Reset the shared tracking singleton.
	 *
	 * @return void
	 */
	private function reset_gtag_instance(): void {
		$property = new \ReflectionProperty( WC_Abstract_Google_Analytics_JS::class, 'instance' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( null, null );
	}
}
