import { setupEventHandlers } from './tracker';
import { classicTracking } from './integrations/classic';
import { blocksTracking } from './integrations/blocks';

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

const consentMap = {
	statistics: [
		'analytics_storage',
	],
	marketing: [
		'ad_storage',
		'ad_user_data',
		'ad_personalization',
	],
};

function initializeTracking() {
	if ( typeof wp_has_consent === 'function' ) {
		window.wp_consent_type = 'optin';

		const consentState = {};

		for ( const [ category, types ] of Object.entries( consentMap ) ) {
			if ( wp_has_consent( category ) ) {
				types.forEach( type => {
					consentState[ type ] = 'granted';
				} );
			}
		}

		if ( Object.keys( consentState ).length > 0 ) {
			gtag( 'consent', 'update', consentState );
		}
	}

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
