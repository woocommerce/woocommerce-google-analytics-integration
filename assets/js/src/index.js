import { createTracker } from './tracker';
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
	if ( ! window.ga4w ) {
		throw new Error(
			'Google Analytics for WooCommerce: Configuration and tracking data not found.'
		);
	}
	const eventHandler = createTracker( window.ga4w.settings );

	classicTracking( eventHandler, window.ga4w.data );
	blocksTracking( eventHandler );
}
