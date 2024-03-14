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
	if ( ! window.ga4wData ) {
		throw new Error(
			'Google Analytics for WooCommerce: Configuration and tracking data not found.'
		);
	}
	const eventHandler = createTracker( window.ga4wData.settings );

	classicTracking( eventHandler, window.ga4wData );
	blocksTracking( eventHandler );
}
