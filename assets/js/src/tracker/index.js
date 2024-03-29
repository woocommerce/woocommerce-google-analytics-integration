import { config } from '../config';
import * as formatters from './data-formatting';

let instance;

/**
 * A tracking utility for initializing a GA4 and tracking accepted events.
 *
 * @class
 */
class Tracker {
	/**
	 * Constructs a new instance of the Tracker class.
	 *
	 * @throws {Error} If an instance of the Tracker already exists.
	 */
	constructor() {
		if ( instance ) {
			throw new Error( 'Cannot instantiate more than one Tracker' );
		}
		instance = this;

		window.dataLayer = window.dataLayer || [];

		function gtag() {
			window.dataLayer.push( arguments );
		}

		window[ config.tracker_function_name ] = gtag;

		// Set up default consent state, denying all for EEA visitors.
		for ( const mode of config.consent_modes || [] ) {
			gtag( 'consent', 'default', mode );
		}

		gtag( 'js', new Date() );
		gtag( 'set', `developer_id.${ config.developer_id }`, true );
		gtag( 'config', config.gtag_id, {
			allow_google_signals: config.allow_google_signals,
			link_attribution: config.link_attribution,
			anonymize_ip: config.anonymize_ip,
			logged_in: config.logged_in,
			linker: config.linker,
			custom_map: config.custom_map,
		} );
	}

	/**
	 * Creates and returns an event handler for a specified event name.
	 *
	 * @param {string} name The name of the event.
	 * @return {function(*): void} Function for processing and tracking the event.
	 * @throws {Error} If the event name is not supported.
	 */
	eventHandler( name ) {
		/* eslint import/namespace: [ 'error', { allowComputed: true } ] */
		const formatter = formatters[ name ];
		if ( typeof formatter !== 'function' ) {
			throw new Error( `Event ${ name } is not supported.` );
		}

		return function trackerEventHandler( data ) {
			const eventData = formatter( data );
			if ( config.events.includes( name ) && eventData ) {
				window[ config.tracker_function_name ](
					'event',
					name,
					eventData
				);
			}
		};
	}
}

export const tracker = Object.freeze( new Tracker() );
