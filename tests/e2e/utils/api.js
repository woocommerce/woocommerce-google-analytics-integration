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

	for ( const variation of config.products.variations ) {
		await api().post( `products/${ parentID }/variations`, variation );
	}

	return parentID;
}

/**
 * Creates a grouped product with simple child products.
 *
 * @return {Object} Grouped product ID and child products.
 */
export async function createGroupedProduct() {
	const children = [];

	for ( const child of config.products.grouped.groupedProducts ) {
		const product = await api()
			.post( 'products', {
				name: child.name,
				type: 'simple',
				regular_price: child.regularPrice,
			} )
			.then( ( response ) => response.data );

		children.push( {
			id: product.id,
			name: product.name,
			price: child.regularPrice,
		} );
	}

	const parentID = await api()
		.post( 'products', {
			name: config.products.grouped.name,
			type: 'grouped',
			grouped_products: children.map( ( { id } ) => id ),
		} )
		.then( ( response ) => response.data.id );

	return { id: parentID, children };
}

/**
 * Creates a percentage coupon.
 *
 * @return {string} Coupon code.
 */
export async function createPercentageCoupon() {
	const code = `${ config.coupons.percentage.code }-${ Date.now() }`;

	await api().post( 'coupons', {
		code,
		discount_type: config.coupons.percentage.discountType,
		amount: config.coupons.percentage.amount,
	} );

	return code;
}

/**
 * Creates a tax rate for California orders.
 *
 * @return {number} Tax rate ID.
 */
export async function createCaliforniaTaxRate() {
	return await api()
		.post( 'taxes', {
			country: 'US',
			state: 'CA',
			rate: '10.0000',
			name: 'E2E Tax',
			shipping: false,
		} )
		.then( ( response ) => response.data.id );
}

/**
 * Deletes a tax rate.
 *
 * @param {number} taxRateID Tax rate ID.
 */
export async function deleteTaxRate( taxRateID ) {
	await api().delete( `taxes/${ taxRateID }`, { params: { force: true } } );
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

/**
 * Set whitelisted test options.
 *
 * @param {Object} options Options to set.
 */
export async function setOptions( options ) {
	await api().post( 'ga4w-test/options', options );
}
