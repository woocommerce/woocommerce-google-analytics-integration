import { addAction, removeAction } from '@wordpress/hooks';
import { config, products, cart } from '../config.js';

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
	const variantData = {};
	if ( product.variation ) {
		variantData.item_variant = product.variation;
	}

	return {
		item_id: getProductId( product ),
		item_name: product.name,
		...getProductCategories( product ),
		quantity: product.quantity ?? quantity,
		price: formatPrice(
			product.prices.price,
			product.prices.currency_minor_unit
		),
		...variantData,
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
 * @return {number} - The price of the product formatted
 */
export const formatPrice = ( price, currencyMinorUnit = 2 ) => {
	return parseInt( price, 10 ) / 10 ** currencyMinorUnit;
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
 * Returns the product ID by checking if the product data includes the formatted
 * identifier. If the identifier is not present then it will return either the product
 * SKU, the product ID prefixed with #, or the product ID depending on the site settings
 *
 * @param {Object} product - The product object
 *
 * @return {string} - The product ID
 */
export const getProductId = ( product ) => {
	const identifier =
		product.extensions?.woocommerce_google_analytics_integration
			?.identifier;

	if ( identifier !== undefined ) {
		return identifier;
	}

	if ( config.identifier === 'product_sku' ) {
		return product.sku ? product.sku : '#' + product.id;
	}

	return product.id;
};

/**
 * Returns an Object containing the cart coupon if one has been applied
 *
 * @param {Object} storeCart - The cart to check for coupons
 *
 * @return {Object} - Either an empty Object or one containing the coupon
 */
export const getCartCoupon = ( storeCart ) => {
	return storeCart.coupons[ 0 ]?.code
		? {
				coupon: storeCart.coupons[ 0 ]?.code,
		  }
		: {};
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

/**
 * Searches through the global wcgaiData.products object to find a single product by its ID
 *
 * @param {number} search The ID of the product to search for
 * @return {Object|undefined} The product object or undefined if not found
 */
export const getProductFromID = ( search ) => {
	return products?.find( ( { id } ) => id === search ) ?? cart?.items?.find( ( { id } ) => id === search );
};
