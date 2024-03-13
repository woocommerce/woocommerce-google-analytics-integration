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
	}

	/**
	 * Creates and returns an event handler for a specified event name.
	 *
	 * @param {string} name The name of the event.
	 * @return {function(*): void} Function for processing and tracking the event.
	 * @throws {Error} If the event name is not supported.
	 */
	eventHandler( name ) {
		if ( ! config() ) {
			throw new Error( 'Google Analytics for WooCommerce: eventHandler called too early' );
		}

		/* eslint import/namespace: [ 'error', { allowComputed: true } ] */
		const formatter = formatters[ name ];
		if ( typeof formatter !== 'function' ) {
			throw new Error( `Event ${ name } is not supported.` );
		}

		return function trackerEventHandler( data ) {
			const eventData = formatter( data );
			if ( config().settings.events.includes( name ) && eventData ) {
				window[ config().settings.tracker_function_name ](
					'event',
					name,
					eventData
				);
			}
		};
	}
}

export const tracker = Object.freeze( new Tracker() );
