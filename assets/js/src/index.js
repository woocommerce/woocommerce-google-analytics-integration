import { setupEventHandlers } from './tracker';
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
	const getEventHandler = setupEventHandlers( window.ga4w.settings );

	classicTracking( getEventHandler, window.ga4w.data );
	blocksTracking( getEventHandler );
}
