import { config } from '../config';

/**
 * A tracking utility for initializing a GA4, managing accepted
 * events, attaching data to those events, and tracking them.
 */
class Tracker {
	constructor() {
		this.eventsMap = new Map();

		if ( window[ config.tracker_var ] ) {
			// Tracker already initialized. Do nothing.
			return;
		}

		window.dataLayer = window.dataLayer || [];
		function gtag() {
			window.dataLayer.push( arguments );
		}
		window[ config.tracker_var ] = gtag;
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

		this.eventsMap = new Map();
	}

	/**
	 * Fires the `gtag` funciton with the provided arguments.
	 *
	 * @param {string} eventName Event name to be tracked.
	 * @param {*} data Data to be sent
	 */
	trackEvent( eventName, data ) {
		window[ config.tracker_var ]( 'event', eventName, data );
	}

	/**
	 * Returns a function to fire `trackEvent` with the provided event name
	 * and data translated by the respective registered callback.
	 *
	 * @param {string} eventName Event name
	 * @returns {(data: any) => void)}
	 */
	attachEvent( eventName ) {
		return ( data ) => {
			const eventCallback = this.eventsMap.get( eventName );
			const eventData = eventCallback( data );

			// The experimental hooks from Blocks are sometimes triggered
			// without data so we'll only track events with data.
			if ( eventData ) {
				this.trackEvent( eventName, eventData );
			}
		};
	}
}

export const tracker = new Tracker();
