/**
 * Track an event using the global gtag function.
 *
 * @param {string} eventName - Name of the event to track
 * @param {Object} eventParams - Props to send within the event
 */
export const trackEvent = ( eventName, eventParams ) => {
	if ( typeof gtag !== 'function' ) {
		throw new Error( 'Function gtag not implemented.' );
	}

	window.gtag( 'event', eventName, eventParams );
};

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
		id: getProductId( product ),
		name: product.name,
		quantity,
		category: getProductCategory( product ),
		price: getPrice( product ),
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
		id: getProductId( product ),
		name: product.name,
		list_name: listName,
		category: getProductCategory( product ),
		price: getPrice( product ),
	};
};

/**
 * Returns the product ID by checking if the product has a SKU, if not, it returns '#' concatenated with the product ID.
 *
 * @param {Object} product - The product object
 *
 * @return {string} - The product ID
 */
const getProductId = ( product ) => {
	return product?.sku ?? '#' + product.id;
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

/**
 * Returns the price of a product as a string.
 *
 * @param {Object} product - The product object
 *
 * @return {string} - The price of the product
 */
const getPrice = ( product ) => {
	return (
		parseInt( product.prices.price, 10 ) /
		10 ** product.prices.currency_minor_unit
	).toString();
};
