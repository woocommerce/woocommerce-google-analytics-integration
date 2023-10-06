import { addAction, removeAction } from '@wordpress/hooks';

/**
 * Formats data into the productFieldObject shape.
 *
 * @see https://developers.google.com/analytics/devguides/collection/gtagjs/enhanced-ecommerce#product-data
 * @param {Object} product - The product data
 * @param {number} quantity - The product quantity
 *
 * @return {Object} The product data
 */
export const getProductFieldObject = ( product, quantity ) => {
	return {
		item_id: getProductId( product ),
		item_name: product.name,
		quantity: product.quantity ?? quantity,
		...getProductCategories( product ),
		price: formatPrice(
			product.prices.price,
			product.prices.currency_minor_unit
		),
	};
};

/**
 * Formats data into the impressionFieldObject shape.
 *
 * @see https://developers.google.com/analytics/devguides/collection/gtagjs/enhanced-ecommerce#impression-data
 * @param {Object} product - The product data
 * @param {string} listName - The list for this product
 *
 * @return {Object} - The product impression data
 */
export const getProductImpressionObject = ( product, listName ) => {
	return {
		item_id: getProductId( product ),
		item_name: product.name,
		item_list_name: listName,
		...getProductCategories( product ),
		price: formatPrice(
			product.prices.price,
			product.prices.currency_minor_unit
		),
	};
};

/**
 * Returns the price of a product formatted as a string.
 *
 * @param {string} price - The price to parse
 * @param {number} [currencyMinorUnit=2] - The number decimals to show in the currency
 *
 * @return {string} - The price of the product formatted
 */
export const formatPrice = ( price, currencyMinorUnit = 2 ) => {
	return ( parseInt( price, 10 ) / 10 ** currencyMinorUnit ).toString();
};

/**
 * Removes previous actions with the same hookName and namespace and then adds the new action.
 *
 * @param {string} hookName The hook name for the action
 * @param {string} namespace The unique namespace for the action
 * @param {Function} callback The function to run when the action happens.
 */
export const addUniqueAction = ( hookName, namespace, callback ) => {
	removeAction( hookName, namespace );
	addAction( hookName, namespace, callback );
};

/**
 * Listens for jQuery events and triggers a callback
 *
 * @param {string} eventName The event name
 * @param {Function} callback The function to run when the event happens
 */
export const eventListener = ( eventName, callback ) => {
	jQuery( document.body ).on( eventName, function ( event, item ) {
		callback( { product: JSON.parse( item ) } );
	} );
};

/**
 * Returns the product ID by checking if the product has a SKU, if not, it returns '#' concatenated with the product ID.
 *
 * @param {Object} product - The product object
 *
 * @return {string} - The product ID
 */
export const getProductId = ( product ) => {
	return product.sku ? product.sku : '#' + product.id;
};

/**
 * Returns the name of the first category of a product, or an empty string if the product has no categories.
 *
 * @param {Object} product - The product object
 *
 * @return {string} - The name of the first category of the product or an empty string if the product has no categories.
 */
const getProductCategories = ( product ) => {
	return 'categories' in product && product.categories.length
		? getCategoryObject( product.categories )
		: {};
};

/**
 * Returns an object containing up to 5 categories for the product.
 *
 * @param {Object} categories - An array of product categories
 *
 * @return {Object} - An categories object
 */
const getCategoryObject = ( categories ) => {
	return Object.fromEntries(
		categories.slice( 0, 5 ).map( ( category, index ) => {
			return [ formatCategoryKey( index ), category.name ];
		} )
	);
};

/**
 * Returns the correctly formatted key for the category object.
 *
 * @param {number} index Index of the current category
 *
 * @return {string} - A formatted key for the category object
 */
const formatCategoryKey = ( index ) => {
	return 'item_category' + ( index > 0 ? index + 1 : '' );
};
