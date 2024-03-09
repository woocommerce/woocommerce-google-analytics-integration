/**
 * External dependencies
 */
import { cleanForSlug } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { apiWP } from './api';

/**
 * Check if a page exists from a title.
 *
 * @param {string} title
 * @return {Promise<number>} Existing page ID.
 */
export async function pageExistsByTitle( title ) {
	const slug = cleanForSlug( title );

	return await apiWP()
		.get( `pages?slug=${ slug }` )
		.then( ( response ) => response.data[ 0 ]?.id );
}

/**
 * Creates a WP page with content and title.
 *
 * @param {string} title
 * @param {string} content
 * @return {Promise<number>} Created page ID.
 */
export async function createPage( title, content ) {
	return await apiWP()
		.post( 'pages', {
			title,
			content,
			status: 'publish',
		} )
		.then( ( response ) => response.data.id );
}

/**
 * Creates a classic cart page using shortcodes.
 */
export async function createClassicCartPage() {
	const title = 'Classic Cart';
	const content = '[woocommerce_cart]';

	if ( ! ( await pageExistsByTitle( title ) ) ) {
		await createPage( title, content );
	}
}

/**
 * Creates a classic checkout page using shortcodes.
 */
export async function createClassicCheckoutPage() {
	const title = 'Classic Checkout';
	const content = '[woocommerce_checkout]';

	if ( ! ( await pageExistsByTitle( title ) ) ) {
		await createPage( title, content );
	}
}

/**
 * Creates a classic shop page using shortcodes.
 */
export async function createClassicShopPage() {
	const title = 'Classic Shop';
	const content = '[products]';

	if ( ! ( await pageExistsByTitle( title ) ) ) {
		await createPage( title, content );
	}
}
