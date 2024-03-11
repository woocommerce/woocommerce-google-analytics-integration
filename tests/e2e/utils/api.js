/**
 * Helper functions for requests sent through the REST API.
 */

/**
 * External dependencies
 */
const axios = require( 'axios' ).default;

/**
 * Internal dependencies
 */
const config = require( '../config/default.json' );

export function api( version ) {
	const token = Buffer.from(
		`${ config.users.admin.username }:${ config.users.admin.password }`,
		'utf8'
	).toString( 'base64' );

	return axios.create( {
		baseURL: `${ config.url }wp-json/${ version ?? 'wc/v3' }/`,
		headers: {
			'Content-Type': 'application/json',
			Authorization: `Basic ${ token }`,
		},
	} );
}

export function apiWP() {
	return api( 'wp/v2' );
}

/**
 * Creates a simple product.
 *
 * @return {number} Product ID of the created product.
 */
export async function createSimpleProduct() {
	return await api()
		.post( 'products', config.products.simple )
		.then( ( response ) => response.data.id );
}

/**
 * Creates a variable product.
 *
 * @return {number} Product ID of the created product.
 */
export async function createVariableProduct() {
	const parentID = await api()
		.post( 'products', config.products.variable )
		.then( ( response ) => response.data.id );

	config.products.variations.map( async ( variation ) => {
		await api().post( `products/${ parentID }/variations`, variation );
	} );

	return parentID;
}

/**
 * Set test settings.
 */
export async function setSettings() {
	await api().post( 'ga4w-test/settings' );
}

/**
 * Clear test settings.
 */
export async function clearSettings() {
	await api().delete( 'ga4w-test/settings' );
}
