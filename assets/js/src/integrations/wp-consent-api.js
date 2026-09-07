const consentMap = {
	statistics: [ 'analytics_storage' ],
	marketing: [ 'ad_storage', 'ad_user_data', 'ad_personalization' ],
};

/**
 * The consent type in effect, resolved the way the WP Consent API's own
 * `wp_has_consent()` resolves it: the type declared at runtime first, then the
 * one the plugin localizes from `wp_get_consent_type()`. An empty string means
 * no consent management is declared.
 *
 * @return {string} The declared consent type, or an empty string.
 */
const getConsentType = () => {
	const { wp_consent_type: type, wp_fallback_consent_type: fallback } =
		window;

	if ( type !== undefined && type !== null ) {
		return String( type );
	}

	return fallback ? String( fallback ) : '';
};

export const setCurrentConsentState = ( {
	tracker_function_name: trackerFunctionName,
} ) => {
	// eslint-disable-next-line camelcase -- `wp_has_consent` is defined by the WP Consent API plugin.
	if ( typeof wp_has_consent !== 'function' ) {
		return;
	}

	// Read the declared type before the fallback below defines one, otherwise a site
	// that declares opt-out through `wp_get_consent_type()` would be read as opt-in.
	const consentType = getConsentType();
	const optIn = consentType !== '' && ! consentType.includes( 'optout' );

	if ( consentType === '' ) {
		// Nothing declares a consent type, so assume opt-in as this integration always
		// has, which is what makes `wp_has_consent()` honour the cookies a banner sets.
		window.wp_consent_type = 'optin';
	}

	const consentState = {};

	for ( const [ category, types ] of Object.entries( consentMap ) ) {
		// eslint-disable-next-line no-undef -- `consent_api_get_cookie` is defined by the WP Consent API plugin.
		const decision = consent_api_get_cookie(
			window.consent_api.cookie_prefix + '_' + category
		);

		let state;
		if ( decision !== '' ) {
			// eslint-disable-next-line no-undef -- `wp_has_consent` is defined by the WP Consent API plugin.
			state = wp_has_consent( category ) ? 'granted' : 'denied';
		} else if ( optIn ) {
			// No decision yet under opt-in means no consent: say so explicitly
			// instead of leaving gtag on whatever default applies to the region.
			state = 'denied';
		} else {
			// Opt-out, or no consent management at all: the (region-scoped)
			// defaults stand and an undecided visitor keeps being measured.
			continue;
		}

		types.forEach( ( type ) => {
			consentState[ type ] = state;
		} );
	}

	if ( Object.keys( consentState ).length > 0 ) {
		window[ trackerFunctionName ]( 'consent', 'update', consentState );
	}
};

export const addConsentStateChangeEventListener = ( {
	tracker_function_name: trackerFunctionName,
} ) => {
	document.addEventListener( 'wp_listen_for_consent_change', ( event ) => {
		const consentUpdate = {};

		const types = consentMap[ Object.keys( event.detail )[ 0 ] ];
		const state =
			Object.values( event.detail )[ 0 ] === 'allow'
				? 'granted'
				: 'denied';

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
