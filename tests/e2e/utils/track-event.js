/**
 * Tracking of Gtag events.
 *
 * @typedef { import( '@playwright/test' ).Request } Request
 * @typedef { import( '@playwright/test' ).Page } Page
 */

/**
 * Returns the data for a specific event from a set of multiple events seperated by newlines.
 *
 * @param {string} data      All event data combined.
 * @param {string} eventName Event name to match.
 *
 * @return {Object} Single event data.
 */
function parseMultipleEvents( data, eventName ) {
	const events = data.split( '\r\n' ).map( ( eventData ) => {
		return Object.fromEntries( new URLSearchParams( eventData ).entries() );
	} );

	return events.find( ( e ) => e.en === eventName );
}

/**
 * Splits the product data using the ~ seperator and uses the first 2 characters as the key.
 *
 * @param {string} data
 * @return {Object} Product data split into key value pairs.
 */
function splitProductData( data ) {
	return Object.fromEntries(
		data.split( '~' ).map( ( pair ) => {
			return [ pair.slice( 0, 2 ), pair.slice( 2 ) ];
		} )
	);
}

function withProductData( data ) {
	// Split data for first product.
	if ( data.pr1 ) {
		data.product1 = splitProductData( data.pr1 );
	}

	// Split data for second product.
	if ( data.pr2 ) {
		data.product2 = splitProductData( data.pr2 );
	}

	return data;
}

/**
 * Whether the `dl` (page_location) of a hit points at the given path on the
 * current site. Only hostname and path are compared: the plugin sets
 * page_location from document.location.href, which keeps the port, while
 * gtag's own default omits it.
 *
 * @param {string|null} pageLocation The `dl` parameter of the hit.
 * @param {string}      pageUrl      The current page URL.
 * @param {string}      urlPath      Path prefix without the leading slash, e.g. `checkout`.
 *
 * @return {boolean} Whether the hit was sent from that path.
 */
function pageLocationMatches( pageLocation, pageUrl, urlPath ) {
	try {
		const sent = new URL( pageLocation );
		return (
			sent.hostname === new URL( pageUrl ).hostname &&
			( sent.pathname === `/${ urlPath }` ||
				sent.pathname.startsWith( `/${ urlPath }/` ) )
		);
	} catch {
		return false;
	}
}

/**
 * Tracks when the Gtag Event request matching a specific name is sent.
 *
 * @param {Page}        page
 * @param {string}      eventName Event name to match.
 * @param {string|null} urlPath   Path prefix (without leading slash) the hit's page location must start with.
 *
 * @return {Promise<Request>} Matching request.
 */
export function trackGtagEvent( page, eventName, urlPath = null ) {
	const eventPath = 'google-analytics.com/g/collect';
	return page.waitForRequest( ( request ) => {
		const url = request.url();
		const pathMatches = url.includes( eventPath );

		// Return early if the path doesn't match the request we expect.
		if ( ! pathMatches ) {
			return false;
		}

		const params = new URL( url ).searchParams;
		const urlPathMatches = urlPath
			? pageLocationMatches( params.get( 'dl' ), page.url(), urlPath )
			: true;

		// Match a single event sent in query parameters.
		if ( params.get( 'en' ) ) {
			return params.get( 'en' ) === eventName && urlPathMatches;
		}

		// Match multiple events sent in the body.
		const event = parseMultipleEvents( request.postData(), eventName );
		return event && urlPathMatches;
	} );
}

/**
 * Retrieve data from a Gtag event.
 *
 * @param {Request} request
 * @param {string}  eventName Event name to match.
 *
 * @return {Object} Data sent with the event.
 */
export function getEventData( request, eventName ) {
	const url = new URL( request.url() );
	const params = new URLSearchParams( url.search );
	let data = Object.fromEntries( params.entries() );

	// If event name is not present then find matching event in body.
	if ( ! data.en ) {
		data = {
			...data,
			...parseMultipleEvents( request.postData(), eventName ),
		};
	}

	return withProductData( data );
}

/**
 * Retrieve all matching events from a Gtag request.
 *
 * @param {Request} request
 * @param {string}  eventName Event name to match.
 *
 * @return {Object[]} Event data objects.
 */
export function getAllEventData( request, eventName ) {
	const url = new URL( request.url() );
	const params = new URLSearchParams( url.search );
	const baseData = Object.fromEntries( params.entries() );

	if ( baseData.en ) {
		return baseData.en === eventName ? [ withProductData( baseData ) ] : [];
	}

	return ( request.postData() || '' )
		.split( /\r?\n/ )
		.map( ( eventData ) => ( {
			...baseData,
			...Object.fromEntries( new URLSearchParams( eventData ).entries() ),
		} ) )
		.filter( ( data ) => data.en === eventName )
		.map( withProductData );
}
