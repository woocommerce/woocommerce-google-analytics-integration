import { config } from './config';
import { classicTracking } from './integrations/classic';
import { blocksTracking } from './integrations/blocks';

// Wait for DOMContentLoaded to make sure event data is in place.
if ( document.readyState === 'loading' ) {
	document.addEventListener(
		'DOMContentLoaded',
		eventuallyInitializeTracking
	);
} else {
	eventuallyInitializeTracking();
}
function eventuallyInitializeTracking() {
	if ( ! config() ) {
		throw new Error(
			'Google Analytics for WooCommerce: Configuration and tracking data not found.'
		);
	}

	classicTracking( config() );
	blocksTracking();
}
