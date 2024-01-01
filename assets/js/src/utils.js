import { addAction, removeAction } from '@wordpress/hooks';

/**
 * Formats data into the productFieldObject shape.
 *
 * @see https://developers.google.com/analytics/devguides/collection/gtagjs/enhanced-ecommerce#product-data
 * @param {Object} product  - The product data
 * @param {number} quantity - The product quantity
 *
 * @return {Object} The product data
 */
export const getProductFieldObject = ( product, quantity ) => {
	return {
		id: getProductId( product ),
		name: product.name,
		quantity,
		category: getProductCategory( product ),
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
 * @param {Object} product  - The product data
 * @param {string} listName - The list for this product
 *
 * @return {Object} - The product impression data
 */
export const getProductImpressionObject = ( product, listName ) => {
	return {
		id: getProductId( product ),
		name: product.name,
		list_name: listName,
		category: getProductCategory( product ),
		price: formatPrice(
			product.prices.price,
			product.prices.currency_minor_unit
		),
	};
};

/**
 * Returns the price of a product formatted as a string.
 *
 * @param {string} price                 - The price to parse
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
 * @param {string}   hookName  The hook name for the action
 * @param {string}   namespace The unique namespace for the action
 * @param {Function} callback  The function to run when the action happens.
 */
export const addUniqueAction = ( hookName, namespace, callback ) => {
	removeAction( hookName, namespace );
	addAction( hookName, namespace, callback );
};

/**
 * Returns the product ID by checking if the product has a SKU, if not, it returns '#' concatenated with the product ID.
 *
 * @param {Object} product - The product object
 *
 * @return {string} - The product ID
 */
const getProductId = ( product ) => {
	return product.sku ? product.sku : '#' + product.id;
};

/**
 * Returns the name of the first category of a product, or an empty string if the product has no categories.
 *
 * @param {Object} product - The product object
 *
 * @return {string} - The name of the first category of the product or an empty string if the product has no categories.
 */
const getProductCategory = ( product ) => {
	return 'categories' in product && product.categories.length
		? product.categories[ 0 ].name
		: '';
};
