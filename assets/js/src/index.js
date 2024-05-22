import { setupEventHandlers } from './tracker';
import { classicTracking } from './integrations/classic';
import { blocksTracking } from './integrations/blocks';
import {
	setCurrentConsentState,
	addConsentStateChangeEventListener
} from './integrations/wp-consent-api';

// Wait for 'ga4w:ready' event if `window.ga4w` is not there yet.
if ( window.ga4w ) {
	initializeTracking();
} else {
	document.addEventListener( 'ga4w:ready', initializeTracking );

	// Warn if there is still nothing after the document is fully loded.
	if ( document.readyState === 'complete' ) {
		warnIfDataMissing();
	} else {
		window.addEventListener( 'load', warnIfDataMissing );
	}
}

function initializeTracking() {
	setCurrentConsentState( window.ga4w.settings );
	addConsentStateChangeEventListener( window.ga4w.settings );

	const getEventHandler = setupEventHandlers( window.ga4w.settings );

	classicTracking( getEventHandler, window.ga4w.data );
	blocksTracking( getEventHandler );
}

function warnIfDataMissing() {
	if ( ! window.ga4w ) {
		// eslint-disable-next-line no-console -- It's not an error, as one may load the script later, but we'd like to warn developers if it's about to be missing.
		console.warn(
			'Google Analytics for WooCommerce: Configuration and tracking data not found after the page was fully loaded. Make sure the `woocommerce-google-analytics-integration-data` script gets eventually loaded.'
		);
	}
}
