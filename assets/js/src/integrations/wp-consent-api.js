const consentMap = {
	statistics: [ 'analytics_storage' ],
	marketing: [ 'ad_storage', 'ad_user_data', 'ad_personalization' ],
};

export const setCurrentConsentState = ( {
	tracker_function_name: trackerFunctionName,
} ) => {
	// eslint-disable-next-line camelcase -- `wp_has_consent` is defined by the WP Consent API plugin.
	if ( typeof wp_has_consent === 'function' ) {
		if ( window.wp_consent_type === undefined ) {
			window.wp_consent_type = 'optin';
		}

		const consentState = {};

		for ( const [ category, types ] of Object.entries( consentMap ) ) {
			// eslint-disable-next-line camelcase, no-undef
			if ( wp_has_consent( category ) ) {
				types.forEach( ( type ) => {
					consentState[ type ] = 'granted';
				} );
			}
		}

		if ( Object.keys( consentState ).length > 0 ) {
			window[ trackerFunctionName ]( 'consent', 'update', consentState );
		}
	}
};

export const addConsentStateChangeEventListener = ( {
	tracker_function_name: trackerFunctionName,
} ) => {
	document.addEventListener( 'wp_listen_for_consent_change', ( event ) => {
		const consentUpdate = {};

		const types = consentMap[ Object.keys( event.detail )[ 0 ] ];
		const state =
			Object.values( event.detail )[ 0 ] === 'allow' ? 'granted' : 'denied';

		if ( types !== undefined ) {
			types.forEach( ( type ) => {
				consentUpdate[ type ] = state;
			} );

			if ( Object.keys( consentUpdate ).length > 0 ) {
				window[ trackerFunctionName ](
					'consent',
					'update',
					consentUpdate
				);
			}
		}
	} );
};
