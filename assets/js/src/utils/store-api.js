/*
 * Helpers that read Store API requests and responses, to work out which cart
 * changes to track. blocks.js uses them in its fetch interceptor.
 */

/*
 * Last item observed for each cart line item (keyed by Store API line item
 * key), recorded from every Store API cart response the interceptor sees. A
 * follow-up update-item is reported as the changed delta against this value,
 * which stays correct across repeated cart changes on a single page load — the
 * cart data exposed to the page (wc/store/cart, or the static window.ga4w cart)
 * reflects page-load state and would otherwise go stale.
 */
const lastSeenCartItems = new Map();

// The page has seen a Store API cart, so its items are the whole cart.
let hasSeenCart = false;

export const forgetCartItems = () => {
	lastSeenCartItems.clear();
	hasSeenCart = false;
};

export const rememberCartItems = ( cart ) => {
	if ( ! Array.isArray( cart?.items ) ) {
		return;
	}

	forgetCartItems();
	hasSeenCart = true;
	cart.items.forEach( ( item ) => {
		if ( item?.key !== undefined && item?.key !== null ) {
			lastSeenCartItems.set( item.key, item );
		}
	} );
};

const STORE_API_ADD_ITEM_PATH = /\/wc\/store(?:\/v\d+)?\/cart\/add-item/;
const STORE_API_UPDATE_ITEM_PATH = /\/wc\/store(?:\/v\d+)?\/cart\/update-item/;
const STORE_API_BATCH_PATH = /\/wc\/store(?:\/v\d+)?\/batch/;
const STORE_API_CART_PATH = /\/wc\/store(?:\/v\d+)?\/cart(?:\/|$)/;

/**
 * Resolve the method and pathname of a same-origin fetch call.
 *
 * @param {Request|string|URL} input Fetch input.
 * @param {Object}             init  Fetch options.
 * @return {{ method: string, pathname: string }|null} Request info, or null when
 *                                                      cross-origin or unparseable.
 */
export const getStoreApiRequestInfo = ( input, init = {} ) => {
	let requestUrl;
	try {
		requestUrl = new URL(
			input?.url ?? input?.toString?.(),
			window.location.origin
		);
	} catch {
		return null;
	}

	if ( requestUrl.origin !== window.location.origin ) {
		return null;
	}

	const method = ( init?.method ?? input?.method ?? 'GET' ).toUpperCase();

	// On plain-permalink installs the Store API route lives in the `rest_route`
	// query parameter (e.g. /?rest_route=/wc/store/v1/cart/add-item) and the
	// pathname is just "/", so prefer it when present.
	const restRoute = requestUrl.searchParams.get( 'rest_route' );

	return { method, pathname: restRoute ?? requestUrl.pathname };
};

/**
 * Check whether a fetch call is adding an item through the Store API.
 *
 * @param {Request|string|URL} input Fetch input.
 * @param {Object}             init  Fetch options.
 * @return {boolean} Whether the request is a Store API cart add item request.
 */
export const isStoreApiAddItemRequest = ( input, init = {} ) => {
	const info = getStoreApiRequestInfo( input, init );

	return (
		!! info &&
		info.method === 'POST' &&
		STORE_API_ADD_ITEM_PATH.test( info.pathname )
	);
};

/**
 * Check whether a fetch call is updating an item through the Store API.
 *
 * @param {Request|string|URL} input Fetch input.
 * @param {Object}             init  Fetch options.
 * @return {boolean} Whether the request is a Store API cart update item request.
 */
export const isStoreApiUpdateItemRequest = ( input, init = {} ) => {
	const info = getStoreApiRequestInfo( input, init );

	return (
		!! info &&
		info.method === 'POST' &&
		STORE_API_UPDATE_ITEM_PATH.test( info.pathname )
	);
};

/**
 * Check whether a fetch call is a Store API batch request, which Interactivity
 * API powered add-to-cart blocks use to bundle their cart mutations.
 *
 * @param {Request|string|URL} input Fetch input.
 * @param {Object}             init  Fetch options.
 * @return {boolean} Whether the request is a Store API batch request.
 */
export const isStoreApiBatchRequest = ( input, init = {} ) => {
	const info = getStoreApiRequestInfo( input, init );

	return (
		!! info &&
		info.method === 'POST' &&
		STORE_API_BATCH_PATH.test( info.pathname )
	);
};

/**
 * Check whether a fetch call goes to a Store API cart route, whose response is the
 * whole cart: applying a coupon or removing an item, for example.
 *
 * @param {Request|string|URL} input Fetch input.
 * @param {Object}             init  Fetch options.
 * @return {boolean} Whether the request is a Store API cart request.
 */
export const isStoreApiCartRequest = ( input, init = {} ) => {
	const info = getStoreApiRequestInfo( input, init );

	return !! info && STORE_API_CART_PATH.test( info.pathname );
};

/**
 * Get the request body without consuming the original Request object.
 *
 * @param {Request|string|URL} input Fetch input.
 * @param {Object}             init  Fetch options.
 * @return {Promise<*>} Request body.
 */
export const getRequestBody = async ( input, init = {} ) => {
	if ( init?.body ) {
		return init.body;
	}

	if ( typeof input?.clone === 'function' ) {
		return await input.clone().text();
	}

	return null;
};

/**
 * Coerce a fetch request body (JSON string, form data, or object) into a plain
 * object of its fields.
 *
 * @param {*} body Request body.
 * @return {Object} Body fields.
 */
export const coerceRequestBodyData = ( body ) => {
	if ( body instanceof FormData || body instanceof URLSearchParams ) {
		return Object.fromEntries( body.entries() );
	}

	if ( typeof body === 'string' ) {
		try {
			return JSON.parse( body );
		} catch {
			return Object.fromEntries( new URLSearchParams( body ).entries() );
		}
	}

	return body && typeof body === 'object' ? body : {};
};

export const toPositiveInteger = ( value, fallback = null ) => {
	const parsed = parseInt( value, 10 );

	return Number.isFinite( parsed ) && parsed > 0 ? parsed : fallback;
};

/**
 * Parse the product and quantity from a Store API add item request body.
 *
 * @param {*} body Request body.
 * @return {{ productId: number|null, quantity: number }} Parsed body data.
 */
export const parseAddItemRequestBody = ( body ) => {
	const data = coerceRequestBodyData( body );

	return {
		productId: toPositiveInteger( data.id ),
		quantity: toPositiveInteger( data.quantity, 1 ),
	};
};

/**
 * Parse the cart line item key from a Store API update item request body.
 *
 * @param {*} body Request body.
 * @return {string|null} Cart line item key, or null when absent.
 */
export const parseUpdateItemRequestKey = ( body ) =>
	coerceRequestBodyData( body )?.key ?? null;

/**
 * Get a stable key for comparing Store API cart items.
 *
 * @param {Object} item Store API cart item.
 * @return {string|number|undefined} Cart item key.
 */
export const getCartItemKey = ( item ) => item?.key ?? item?.id;

/**
 * Use the cart last seen in a Store API response instead of the cart from before
 * a request, which can be the one from page load and hold no later changes.
 *
 * @param {Object} cart Cart data before the request.
 * @return {Object} The cart to compare with.
 */
const withLastSeenItems = ( cart ) =>
	hasSeenCart && Array.isArray( cart?.items )
		? { ...cart, items: [ ...lastSeenCartItems.values() ] }
		: cart;

/**
 * Find the item that was added in the Store API response.
 *
 * Prefers the line that actually changed — a newly added line, or an existing
 * line whose quantity grew — so a repeated add of a product already in the cart
 * resolves to the correct line instead of the first line that happens to share
 * the requested id. Falls back to matching the requested product id when no
 * change is visible (e.g. cart-before data was unavailable).
 *
 * @param {Object} cartAfter        Cart data after the request.
 * @param {Object} cartBefore       Cart data before the request.
 * @param {number} requestProductId Product ID from the request body.
 * @return {Object|undefined} Added cart item.
 */
export const getAddedCartItem = ( cartAfter, cartBefore, requestProductId ) => {
	if ( ! Array.isArray( cartAfter?.items ) || ! cartAfter.items.length ) {
		return undefined;
	}

	if ( Array.isArray( cartBefore?.items ) ) {
		const changedItem = cartAfter.items.find( ( item ) => {
			const previousItem = cartBefore.items.find(
				( beforeItem ) =>
					getCartItemKey( beforeItem ) === getCartItemKey( item )
			);

			return ! previousItem || item.quantity > previousItem.quantity;
		} );

		if ( changedItem ) {
			return changedItem;
		}
	}

	return requestProductId
		? cartAfter.items.find(
				( item ) => parseInt( item.id, 10 ) === requestProductId
			)
		: undefined;
};

export const getAddedQuantity = ( item, cartBefore, requestedQuantity ) => {
	const itemQuantity = parseInt( item?.quantity, 10 );

	if (
		Array.isArray( cartBefore?.items ) &&
		Number.isFinite( itemQuantity )
	) {
		const previousItem = cartBefore.items.find(
			( beforeItem ) =>
				getCartItemKey( beforeItem ) === getCartItemKey( item )
		);
		const previousQuantity = parseInt( previousItem?.quantity ?? 0, 10 );

		if ( Number.isFinite( previousQuantity ) ) {
			const addedQuantity = itemQuantity - previousQuantity;

			if ( addedQuantity > 0 ) {
				return addedQuantity;
			}
		}
	}

	return Number.isFinite( itemQuantity )
		? Math.min( requestedQuantity, itemQuantity )
		: requestedQuantity;
};

/**
 * Scale a cart line item's line total to the quantity being reported.
 *
 * A Store API cart line's `line_total` covers its current quantity. When the
 * reported quantity differs from that — a partial add, a quantity increase, or
 * a decrease valued from the reduced remaining line — the line total is
 * rescaled to the reported units at the line's per-unit price so the price
 * stays in step with the reported quantity. The reported quantity may exceed
 * the line's quantity (e.g. a decrease whose remaining line is smaller than the
 * removed units), in which case the per-unit price is scaled up accordingly.
 *
 * @param {Object} item     Store API cart item.
 * @param {number} quantity Quantity being reported.
 * @return {Object} The item, with `totals.line_total` scaled to the reported units.
 */
export const scaleLineTotalToQuantity = ( item, quantity ) => {
	const newQuantity = parseInt( item?.quantity, 10 );
	const lineTotal = parseInt( item?.totals?.line_total, 10 );

	if (
		! Number.isFinite( lineTotal ) ||
		! Number.isFinite( newQuantity ) ||
		! Number.isFinite( quantity ) ||
		! newQuantity
	) {
		return item;
	}

	return {
		...item,
		totals: {
			...item.totals,
			line_total: String(
				Math.round( ( lineTotal * quantity ) / newQuantity )
			),
		},
	};
};

/**
 * Resolve the added product and quantity for a single Store API add-item call.
 *
 * @param {*}      requestBody Add-item request body.
 * @param {Object} cartAfter   Cart data returned by the add-item response.
 * @param {Object} cartBefore  Cart data before the add-item call.
 * @return {{ product: Object, quantity: number }|null} Added product, or null when none matched.
 */
export const getAddItemFromResponse = (
	requestBody,
	cartAfter,
	cartBefore
) => {
	const { productId, quantity } = parseAddItemRequestBody( requestBody );
	const before = withLastSeenItems( cartBefore );
	const product = getAddedCartItem( cartAfter, before, productId );
	const addedQuantity = product
		? getAddedQuantity( product, before, quantity )
		: quantity;

	// The requested quantity can exceed what the line actually holds when the
	// add is capped by available stock; value at most the full line so the
	// reported price never exceeds the real line total.
	return product
		? {
				eventName: 'add_to_cart',
				product: scaleLineTotalToQuantity( product, addedQuantity ),
				quantity: addedQuantity,
				isAddItem: true,
			}
		: null;
};

/**
 * Resolve the tracked product delta for a single Store API update-item call.
 * Increasing quantity is tracked as add_to_cart, and decreasing quantity is
 * tracked as remove_from_cart. The quantity reported is the delta between the
 * new and previous quantity, not the new total.
 *
 * @param {*}      requestBody Update-item request body.
 * @param {Object} cartAfter   Cart data returned by the update-item response.
 * @param {Object} cartBefore  Cart data before the update-item call.
 * @return {{ eventName: string, product: Object, quantity: number }|null} Tracked cart change, or null when quantity did not change.
 */
export const getUpdateItemChangeFromResponse = (
	requestBody,
	cartAfter,
	cartBefore
) => {
	const key = parseUpdateItemRequestKey( requestBody );

	if ( ! key ) {
		return null;
	}

	const updatedItem = Array.isArray( cartAfter?.items )
		? cartAfter.items.find( ( item ) => item.key === key )
		: null;
	const previousItem = Array.isArray( cartBefore?.items )
		? cartBefore.items.find( ( item ) => item.key === key )
		: null;
	const lastSeenItem = lastSeenCartItems.get( key );

	let previousQuantity = 0;
	if ( lastSeenItem ) {
		previousQuantity = lastSeenItem.quantity ?? 0;
	} else if ( previousItem ) {
		previousQuantity = previousItem.quantity ?? 0;
	}

	const newQuantity = parseInt( updatedItem?.quantity ?? 0, 10 );
	const quantityDelta = newQuantity - parseInt( previousQuantity, 10 );

	if ( ! Number.isFinite( quantityDelta ) || quantityDelta === 0 ) {
		return null;
	}

	const product =
		quantityDelta > 0 ? updatedItem : ( lastSeenItem ?? previousItem );

	if ( ! product ) {
		return null;
	}

	const quantity = Math.abs( quantityDelta );

	return {
		eventName: quantityDelta > 0 ? 'add_to_cart' : 'remove_from_cart',
		product: scaleLineTotalToQuantity( product, quantity ),
		quantity,
	};
};

/**
 * Parse a Store API batch request body into an object.
 *
 * @param {*} body Request body (JSON string or already-parsed object).
 * @return {Object|null} Parsed body, or null when it cannot be parsed.
 */
export const parseBatchRequestBody = ( body ) => {
	if ( typeof body === 'string' ) {
		try {
			return JSON.parse( body );
		} catch {
			return null;
		}
	}

	return body && typeof body === 'object' ? body : null;
};

/**
 * Resolve every tracked cart change from a Store API batch request/response pair.
 *
 * A batch bundles sub-requests under `requests` and returns their results, in
 * the same order, under `responses`. Each cart sub-response body is the full
 * cart at that point, matched against the previous cart state to pick out the
 * change.
 *
 * @param {*}      requestBody  Batch request body.
 * @param {Object} responseJson Batch response JSON.
 * @param {Object} cartBefore   Cart data before the batch request.
 * @return {Array<{ eventName: string, product: Object, quantity: number }>} Tracked cart changes.
 */
export const getBatchCartChanges = (
	requestBody,
	responseJson,
	cartBefore
) => {
	const requests = parseBatchRequestBody( requestBody )?.requests;
	const responses = responseJson?.responses;

	if ( ! Array.isArray( requests ) || ! Array.isArray( responses ) ) {
		return [];
	}

	const changes = [];
	let previousCart = cartBefore;

	requests.forEach( ( request, index ) => {
		const path = request?.path ?? '';
		if ( ! STORE_API_CART_PATH.test( path ) ) {
			return;
		}

		const subResponse = responses[ index ];
		const status = subResponse?.status;

		if ( ! subResponse || status < 200 || status >= 300 ) {
			return;
		}

		const cartAfter = subResponse.body;
		const isAddItem = STORE_API_ADD_ITEM_PATH.test( path );
		const isUpdateItem = STORE_API_UPDATE_ITEM_PATH.test( path );

		if ( ! isAddItem && ! isUpdateItem ) {
			rememberCartItems( cartAfter );
			previousCart = cartAfter;
			return;
		}

		const change = isAddItem
			? getAddItemFromResponse( request.body, cartAfter, previousCart )
			: getUpdateItemChangeFromResponse(
					request.body,
					cartAfter,
					previousCart
				);

		if ( change ) {
			changes.push( change );
		}

		// Record items after reading the delta so a later update-item (in this
		// batch or a subsequent request) compares against the new state.
		rememberCartItems( cartAfter );
		previousCart = cartAfter;
	} );

	return changes;
};
