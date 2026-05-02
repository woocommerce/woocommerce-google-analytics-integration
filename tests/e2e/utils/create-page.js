/**
 * External dependencies
 */
import { cleanForSlug } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { apiWP } from './api';
import relatedProductPage from './fixtures/related-products.fixture.mjs';

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
 *
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
 *
 * @return {number} Created page ID.
 */
export async function createClassicCartPage() {
	const title = 'Classic Cart';
	const content = '[woocommerce_cart]';

	return (
		( await pageExistsByTitle( title ) ) ||
		( await createPage( title, content ) )
	);
}

/**
 * Creates a classic checkout page using shortcodes.
 *
 * @return {number} Created page ID.
 */
export async function createClassicCheckoutPage() {
	const title = 'Classic Checkout';
	const content = '[woocommerce_checkout]';

	return (
		( await pageExistsByTitle( title ) ) ||
		( await createPage( title, content ) )
	);
}

/**
 * Creates a classic empty-cart page that also shows a product listing via shortcode.
 * Used to test that events (view_item_list, add_to_cart, select_content) fire correctly
 * when the cart is empty but products are visible on the page.
 *
 * @return {number} Created page ID.
 */
export async function createClassicEmptyCartPageWithProducts() {
	const title = 'Classic Empty Cart With Products';
	const content = '[woocommerce_cart]\n[products]';

	return (
		( await pageExistsByTitle( title ) ) ||
		( await createPage( title, content ) )
	);
}

/**
 * Creates a classic shop page using shortcodes.
 *
 * @return {number} Created page ID.
 */
export async function createClassicShopPage() {
	const title = 'Classic Shop';
	const content = '[products]';

	return (
		( await pageExistsByTitle( title ) ) ||
		( await createPage( title, content ) )
	);
}

/**
 * Creates a shop page using the Product Collection block.
 *
 * @return {number} Created page ID.
 */
export async function createProductCollectionBlockShopPage() {
	const {
		title,
		pageContent,
	} = require( './fixtures/product-collection.fixture.json' );

	return (
		( await pageExistsByTitle( title ) ) ||
		( await createPage( title, pageContent ) )
	);
}

/**
 * Creates a shop page using the All Products block.
 *
 * @return {number} Created page ID.
 */
export async function createAllProductsBlockShopPage() {
	const {
		title,
		pageContent,
	} = require( './fixtures/all-products.fixture.json' );

	return (
		( await pageExistsByTitle( title ) ) ||
		( await createPage( title, pageContent ) )
	);
}

/**
 * Creates a page with related products block.
 *
 * @param {number} productId Product ID.
 * @return {Promise<string>} Slug of the created page.
 */
export async function createRelatedProductsPage( productId ) {
	const { title, slug, pageContent } = relatedProductPage( productId );

	if ( ! ( await pageExistsByTitle( title ) ) ) {
		await createPage( title, pageContent );
	}
	return slug;
}
