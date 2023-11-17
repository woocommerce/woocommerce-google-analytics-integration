import { config } from '../config';

/**
 * A tracking utility for initializing a GA4, managing accepted
 * events, attaching data to those events, and tracking them.
 *
 * @param {Object} config Configuration settings for the tracker.
 *
 * @return {Object} An object containing methods: init, setupEvents, event{ attach, get, track }.
 */
export const tracker = ( () => {
	const events = [];

	return {
		/**
		 * Initializes the tracker and dataLayer if not already done.
		 */
		init: () => {
			if ( window[ config.tracker_var ] ) {
				// Tracker already initialized. Do nothing.
				return;
			}

			window.dataLayer = window.dataLayer || [];
			window[ config.tracker_var ] = function () {
				window.dataLayer.push( arguments );
			};
			window[ config.tracker_var ]( 'js', new Date() );
			window[ config.tracker_var ](
				'set',
				`developer_id.${ config.developer_id }`,
				true
			);
			window[ config.tracker_var ]( 'config', config.gtag_id, {
				allow_google_signals: config.allow_google_signals,
				link_attribution: config.link_attribution,
				anonymize_ip: config.anonymize_ip,
				logged_in: config.logged_in,
				linker: config.linker,
				custom_map: config.custom_map,
			} );
		},
		/**
		 * Each event in the array is added to a global `events` object using its `name` as the key.
		 *
		 * @param {Object[]} eventsArray An array of event objects.
		 */
		setupEvents: ( eventsArray ) => {
			eventsArray.forEach(
				( event ) => ( events[ event.name ] = event )
			);
		},
		/**
		 * An object to control events which exist in the global `events` object.
		 *
		 * @param {string} event - The name of the event.
		 *
		 * @return {Object} An object containing methods to attach, get, and track the event.
		 */
		event: ( event ) => {
			return {
				/**
				 * Attach data to an event and track it.
				 *
				 * @param {*} data - Data to be used for the event.
				 */
				attach: ( data ) => {
					const eventObject = tracker.event( event ).get();
					const eventData = eventObject.callback( data );

					// The experimental hooks from Blocks are sometimes triggered
					// without data so we'll only track events with data.
					if ( eventData ) {
						tracker.event( eventObject.name ).track( eventData );
					}
				},

				/**
				 * Retrieves the event object, or returns false if not found.
				 *
				 * @return {Object|boolean} The event object or false if the event does not exist.
				 */
				get: () => {
					return events[ event ] ?? false;
				},

				/**
				 * Tracks the event with associated data.
				 *
				 * @param {*} data - Data to be tracked with the event.
				 */
				track: ( data ) => {
					window[ config.tracker_var ](
						'event',
						tracker.event( event ).get().name,
						data
					);
				},
			};
		},
	};
} )();

tracker.init();
