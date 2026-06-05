import { setupEventHandlers } from './tracker';
import * as formatters from './tracker/data-formatting';
import * as utils from './utils';
import { classicTracking } from './integrations/classic';
import { blocksTracking } from './integrations/blocks';
import {
	setCurrentConsentState,
	addConsentStateChangeEventListener,
} from './integrations/wp-consent-api';

/*
 * Public JavaScript API, exposed on `window.wcGoogleAnalyticsIntegration` and
 * mirrored onto `window.ga4w`. This is the supported extension surface:
 *
 * - `formatters`: the GA4 event formatters (`add_to_cart`, `view_item`,
 *   `purchase`, …) used to build event payloads.
 * - `utils`: the product/cart formatting helpers used to shape that data.
 *
 * Only the helpers below are part of the contract. Internal plumbing (e.g.
 * `addUniqueAction`, `cacheBlockProducts`) is intentionally not exposed so it
 * can change without breaking integrations.
 */
const publicApi = {
	formatters,
	utils: {
		getProductFieldObject: utils.getProductFieldObject,
		getProductImpressionObject: utils.getProductImpressionObject,
		formatPrice: utils.formatPrice,
		getProductId: utils.getProductId,
		getCartCoupon: utils.getCartCoupon,
	},
};

window.wcGoogleAnalyticsIntegration = {
	...( window.wcGoogleAnalyticsIntegration ?? {} ),
	...publicApi,
};

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
	Object.assign( window.ga4w, publicApi );

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
