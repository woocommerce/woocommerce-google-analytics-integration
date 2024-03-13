import * as formatters from './data-formatting';

/**
 * Get a new event handler constructing function, based on given settings.
 *
 * @param {Object} settings                       - The settings object.
 * @param {Array}  settings.events                - The list of supported events.
 * @param {string} settings.tracker_function_name - The name of the global function to call for tracking.
 * @return {function(string): Function} - A function to create event handlers for specific events.
 */
export function createTracker( {
	events,
	tracker_function_name: trackerFunctionName,
} ) {
	/**
	 * Creates and returns an event handler for a specified event name.
	 *
	 * @param {string} eventName The name of the event.
	 * @return {function(*): void} Function for processing and tracking the event.
	 * @throws {Error} If the event name is not supported.
	 */
	function eventHandler( eventName ) {
		/* eslint import/namespace: [ 'error', { allowComputed: true } ] */
		const formatter = formatters[ eventName ];
		if ( typeof formatter !== 'function' ) {
			throw new Error( `Event ${ eventName } is not supported.` );
		}

		return function trackerEventHandler( data ) {
			const eventData = formatter( data );
			if ( events.includes( eventName ) && eventData ) {
				window[ trackerFunctionName ]( 'event', eventName, eventData );
			}
		};
	}
	return eventHandler;
}
