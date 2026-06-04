import { removeAction } from '@wordpress/hooks';
import {
	addUniqueAction,
	getProductFromID,
	cacheBlockProducts,
} from '../utils';
import { ACTION_PREFIX, NAMESPACE } from '../constants';

const recentlyRemovedProducts = new Set();
let addShippingInfoTracked = false;
let addPaymentInfoTracked = false;

/*
 * Track recently dispatched add_to_cart events so the cart-add-item hook and
 * the fetch interceptor don't both fire for the same Store API add. Entries
 * auto-expire after 50ms, which is long enough for the second code path to
 * short-circuit but short enough that a deliberate second add of the same
 * product is not swallowed.
 */
const recentlyAdded = new Set();
const DEDUP_WINDOW = 50;
let addItemStoreApiTrackingStarted = false;
let addItemEventHandler = null;

/*
 * Last quantity observed for each cart line item (keyed by Store API line item
 * key), recorded from every Store API cart response the interceptor sees. A
 * follow-up update-item is reported as the added delta against this value, which
 * stays correct across repeated adds of the same product on a single page load —
 * the cart data exposed to the page (wc/store/cart, or the static window.ga4w
 * cart) reflects page-load state and would otherwise go stale.
 */
const lastSeenCartQuantities = new Map();

const rememberCartQuantities = ( cart ) => {
	if ( ! Array.isArray( cart?.items ) ) {
		return;
	}

	cart.items.forEach( ( item ) => {
		if ( item?.key !== undefined && item?.key !== null ) {
			lastSeenCartQuantities.set( item.key, item.quantity );
		}
	} );
};

const warnTrackingError = ( message, error ) => {
	// eslint-disable-next-line no-console
	console.warn( `Google Analytics for WooCommerce: ${ message }`, error );
};

// Dispatch an event through its gtag handler without letting a tracking error
// propagate back into the caller (a WooCommerce hook or the fetch interceptor).
const safeTrackEvent = ( getEventHandler, eventName, data ) => {
	try {
		getEventHandler( eventName )( data );
	} catch ( error ) {
		warnTrackingError( `could not track the ${ eventName } event.`, error );
	}
};

/*
 * How long to wait before the batch interceptor dispatches its add_to_cart
 * event. The All Products Block bundles its add into a Store API batch request
 * and fires the cart-add-item hook ~2ms after the batch response resolves, so
 * the interceptor must give the hook time to claim the add (via recentlyAdded)
 * before falling back to firing the event itself. Comfortably larger than the
 * hook latency yet well within the 50ms de-dup window above.
 */
const BATCH_ADD_TRACK_DELAY = 25;

// The cart-add-item hook and the fetch interceptor receive differently-shaped
// product objects for the same add, so we match on whichever identifiers are
// available: the Store API line item `key` (most precise — distinguishes
// variations of the same parent product) and an `id|name` token (the common
// ground when one path lacks a key, e.g. the hook payload). Marking/checking
// every available token means a match on any one of them dedupes the add.
const getAddDedupTokens = ( product ) => {
	const tokens = [];

	if ( product?.key !== undefined && product?.key !== null ) {
		tokens.push( `key:${ product.key }` );
	}

	if ( product?.id || product?.name ) {
		tokens.push( `id:${ product?.id ?? '' }|${ product?.name ?? '' }` );
	}

	return tokens;
};

const markRecentlyAdded = ( product ) => {
	getAddDedupTokens( product ).forEach( ( token ) => {
		recentlyAdded.add( token );
		setTimeout( () => recentlyAdded.delete( token ), DEDUP_WINDOW );
	} );
};

const wasRecentlyAdded = ( product ) =>
	getAddDedupTokens( product ).some( ( token ) =>
		recentlyAdded.has( token )
	);

let viewedProductListenerAttached = false;

/**
 * Get currency settings from our plugin's settings.
 *
 * @return {Object|undefined} Currency object with decimalSeparator, thousandSeparator, precision.
 */
const getCurrencySettings = () => window.ga4w?.settings?.currency;

/**
 * Get the current cart data from WooCommerce's store (if available) or fallback to static data.
 * The store provides fresh cart data that updates when items are added/removed via AJAX.
 *
 * @return {Object|null} Cart object with items array, or null if unavailable.
 */
const getCartData = () => {
	try {
		// Try to get fresh cart data from WooCommerce Blocks store.
		if ( window.wp?.data?.select?.( 'wc/store/cart' ) ) {
			const storeCart = window.wp.data
				.select( 'wc/store/cart' )
				.getCartData();
			if ( storeCart?.items?.length > 0 ) {
				return storeCart;
			}
		}

		// Fallback to static cart data from page load.
		return window.ga4w?.data?.cart ?? null;
	} catch ( error ) {
		warnTrackingError( 'could not read cart data.', error );
		return null;
	}
};

const getProductDedupKey = ( product ) =>
	product?.key ?? product?.id ?? product?.name;

const getCheckoutCartData = ( storeCart ) =>
	storeCart?.items?.length > 0 ? storeCart : window.ga4w?.data?.cart;

const getActivePaymentMethod = () =>
	window.wp?.data?.select?.( 'wc/store/payment' )?.getActivePaymentMethod?.();

// The live cart from the Blocks data store. This is the source of truth for the
// available shipping rates (with their human-readable names) — the set-selected
// shipping-rate hook only carries the rate id, and the static server cart has no
// rates at all, which is why we previously leaked the machine rate id as
// shipping_tier.
const getStoreCartData = () =>
	window.wp?.data?.select?.( 'wc/store/cart' )?.getCartData?.();

// The Blocks data store exposes the shipping packages under the camelCased
// `shippingRates`, while hook payloads and the static server cart use the
// snake_cased `shipping_rates`. Each package's inner rate array (and the rate
// fields rate_id / name / selected) is snake_cased in both. Normalise so we can
// read the rates from whichever source actually has them.
const getShippingPackages = ( cart ) =>
	cart?.shippingRates ?? cart?.shipping_rates ?? [];

// Resolve the user-facing payment method title (e.g. "Cash on delivery") from
// the server-provided gateway data, keyed by the payment method slug. This is
// the same `WC_Payment_Gateway::get_title()` value the classic checkout renders
// in its radio labels, so both checkout types report a consistent payment_type.
// Falls back to the slug if the title is unavailable.
const getPaymentTypeLabel = ( slug ) =>
	window.wc?.wcSettings?.getSetting?.( 'paymentMethodData' )?.[ slug ]
		?.title || slug;

const getAllShippingRates = ( storeCart ) => {
	const livePackages = getShippingPackages( getStoreCartData() );
	const packages = livePackages.length
		? livePackages
		: getShippingPackages( storeCart );

	return packages.flatMap(
		( shippingPackage ) => shippingPackage.shipping_rates ?? []
	);
};

const findShippingRateName = ( storeCart, rateId ) =>
	getAllShippingRates( storeCart ).find(
		( shippingRate ) => shippingRate.rate_id === rateId
	)?.name;

// Returns the human-readable name of the selected shipping rate when available
// (GA4's `shipping_tier` is meant to be the user-facing tier label, e.g. "Free
// Shipping" — not the machine rate id "free_shipping:1"). Falls back to the
// rate id if no name is available.
const getSelectedShippingRate = ( storeCart ) => {
	const selected = getAllShippingRates( storeCart ).find(
		( shippingRate ) => shippingRate.selected
	);

	return selected?.name ?? selected?.rate_id;
};

const trackCheckoutEvent = ( eventName, getEventHandler, data = {} ) => {
	const storeCart = getCheckoutCartData( data.storeCart );

	if ( ! storeCart?.items?.length ) {
		return false;
	}

	safeTrackEvent( getEventHandler, eventName, { ...data, storeCart } );
	return true;
};

/**
 * Parse a price string into a numeric value using currency settings.
 *
 * @param {string} priceText - The raw price text from DOM (e.g., "$1,234.56" or "1.234,56 €").
 * @return {number} The parsed price as a float, or 0 if parsing failed.
 */
const parsePriceFromDOM = ( priceText ) => {
	if ( ! priceText ) {
		return 0;
	}

	const currency = getCurrencySettings();
	// Currency settings should be always available this is only safe check
	if ( ! currency ) {
		return 0;
	}

	const { decimalSeparator = '.', thousandSeparator = ',' } = currency;

	// Use WooCommerce's accounting.js library if available (most reliable)
	if ( typeof window.accounting?.unformat === 'function' ) {
		return window.accounting.unformat( priceText, decimalSeparator );
	}

	// Manual parsing using currency settings
	// Remove currency symbols and whitespace, keeping only digits and separators
	let cleaned = priceText.replace( /[^\d.,]/g, '' ).trim();

	// Remove thousand separators
	if ( thousandSeparator ) {
		const escapedThousand = thousandSeparator.replace(
			/[.*+?^${}()|[\]\\]/g,
			'\\$&'
		);
		cleaned = cleaned.replace( new RegExp( escapedThousand, 'g' ), '' );
	}

	// Convert decimal separator to standard period for parseFloat
	if ( decimalSeparator !== '.' ) {
		cleaned = cleaned.replace( decimalSeparator, '.' );
	}

	return parseFloat( cleaned ) || 0;
};

/**
 * Extract product data from DOM elements in a cart item row.
 * This is a last-resort fallback when:
 * - WooCommerce Blocks store (wc/store/cart) is unavailable or empty
 * - Static cart data from server is unavailable
 * - Product cannot be matched by ID or name in cart data
 *
 * @param {Element} cartItem - The cart item row element.
 * @return {Object|null} Product object or null if extraction failed.
 */
const getProductFromDOM = ( cartItem ) => {
	const productLink = cartItem.querySelector(
		'.wc-block-components-product-name'
	);
	const productName = productLink?.textContent?.trim();

	if ( ! productName ) {
		return null;
	}

	// Try to get quantity from the quantity input or display
	const quantityInput = cartItem.querySelector(
		'.wc-block-components-quantity-selector__input'
	);
	const quantity = quantityInput
		? parseInt( quantityInput.value, 10 ) || 1
		: 1;

	// Try to get price - look for the sale price first, then regular price
	const priceElement =
		cartItem.querySelector(
			'.wc-block-components-product-price__value ins .woocommerce-Price-amount'
		) ||
		cartItem.querySelector(
			'.wc-block-components-product-price__value .woocommerce-Price-amount'
		) ||
		cartItem.querySelector( '.wc-block-components-product-price__value' );

	// Get currency minor unit from cart data or currency settings, default to 2
	const cart = getCartData();
	const currency = getCurrencySettings();
	const currencyMinorUnit =
		cart?.totals?.currency_minor_unit ?? currency?.precision ?? 2;

	const price = priceElement
		? parsePriceFromDOM( priceElement.textContent )
		: 0;

	// Build a minimal product object that works with getProductFieldObject
	return {
		name: productName,
		quantity,
		prices: {
			price: Math.round( price * 10 ** currencyMinorUnit ),
			currency_minor_unit: currencyMinorUnit,
		},
	};
};

const STORE_API_ADD_ITEM_PATH = /\/wc\/store(?:\/v\d+)?\/cart\/add-item/;
const STORE_API_UPDATE_ITEM_PATH = /\/wc\/store(?:\/v\d+)?\/cart\/update-item/;
const STORE_API_BATCH_PATH = /\/wc\/store(?:\/v\d+)?\/batch/;

/**
 * Resolve the method and pathname of a same-origin fetch call.
 *
 * @param {Request|string|URL} input Fetch input.
 * @param {Object}             init  Fetch options.
 * @return {{ method: string, pathname: string }|null} Request info, or null when
 *                                                      cross-origin or unparseable.
 */
const getStoreApiRequestInfo = ( input, init = {} ) => {
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
const isStoreApiAddItemRequest = ( input, init = {} ) => {
	const info = getStoreApiRequestInfo( input, init );

	return (
		!! info &&
		info.method === 'POST' &&
		STORE_API_ADD_ITEM_PATH.test( info.pathname )
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
const isStoreApiBatchRequest = ( input, init = {} ) => {
	const info = getStoreApiRequestInfo( input, init );

	return (
		!! info &&
		info.method === 'POST' &&
		STORE_API_BATCH_PATH.test( info.pathname )
	);
};

/**
 * Get the request body without consuming the original Request object.
 *
 * @param {Request|string|URL} input Fetch input.
 * @param {Object}             init  Fetch options.
 * @return {Promise<*>} Request body.
 */
const getRequestBody = async ( input, init = {} ) => {
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
const coerceRequestBodyData = ( body ) => {
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

const toPositiveInteger = ( value, fallback = null ) => {
	const parsed = parseInt( value, 10 );

	return Number.isFinite( parsed ) && parsed > 0 ? parsed : fallback;
};

/**
 * Parse the product and quantity from a Store API add item request body.
 *
 * @param {*} body Request body.
 * @return {{ productId: number|null, quantity: number }} Parsed body data.
 */
const parseAddItemRequestBody = ( body ) => {
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
const parseUpdateItemRequestKey = ( body ) =>
	coerceRequestBodyData( body )?.key ?? null;

/**
 * Get a stable key for comparing Store API cart items.
 *
 * @param {Object} item Store API cart item.
 * @return {string|number|undefined} Cart item key.
 */
const getCartItemKey = ( item ) => item?.key ?? item?.id;

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
const getAddedCartItem = ( cartAfter, cartBefore, requestProductId ) => {
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

/**
 * Scale a cart line item's line total down to a subset of its quantity.
 *
 * A Store API cart line's `line_total` covers its full quantity. When only part
 * of that quantity is reported as an add — a repeated add of a product already
 * in the cart, or a quantity increase — the line total must be scaled to the
 * added units so the reported price stays in step with the reported quantity.
 *
 * @param {Object} item          Store API cart item.
 * @param {number} addedQuantity Quantity being reported as added.
 * @return {Object} The item, with `totals.line_total` scaled to the added units.
 */
const scaleLineTotalToAddedQuantity = ( item, addedQuantity ) => {
	const newQuantity = parseInt( item?.quantity, 10 );
	const lineTotal = parseInt( item?.totals?.line_total, 10 );

	if (
		! Number.isFinite( lineTotal ) ||
		! Number.isFinite( newQuantity ) ||
		! Number.isFinite( addedQuantity ) ||
		! newQuantity ||
		addedQuantity >= newQuantity
	) {
		return item;
	}

	return {
		...item,
		totals: {
			...item.totals,
			line_total: String(
				Math.round( ( lineTotal * addedQuantity ) / newQuantity )
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
const getAddItemFromResponse = ( requestBody, cartAfter, cartBefore ) => {
	const { productId, quantity } = parseAddItemRequestBody( requestBody );
	const product = getAddedCartItem( cartAfter, cartBefore, productId );

	return product
		? {
				product: scaleLineTotalToAddedQuantity( product, quantity ),
				quantity,
		  }
		: null;
};

/**
 * Resolve the added product and quantity for a single Store API update-item
 * call. Updating an item that is already in the cart to a higher quantity (e.g.
 * clicking add-to-cart again, or raising the quantity) is treated as an
 * add_to_cart for the increase, so the quantity reported is the delta between
 * the new and previous quantity — not the new total.
 *
 * @param {*}      requestBody Update-item request body.
 * @param {Object} cartAfter   Cart data returned by the update-item response.
 * @param {Object} cartBefore  Cart data before the update-item call.
 * @return {{ product: Object, quantity: number }|null} Added product, or null when the quantity did not increase.
 */
const getUpdateItemFromResponse = ( requestBody, cartAfter, cartBefore ) => {
	const key = parseUpdateItemRequestKey( requestBody );

	if ( ! key ) {
		return null;
	}

	const updatedItem = Array.isArray( cartAfter?.items )
		? cartAfter.items.find( ( item ) => item.key === key )
		: null;

	if ( ! updatedItem ) {
		return null;
	}

	let previousQuantity = 0;
	if ( lastSeenCartQuantities.has( key ) ) {
		previousQuantity = lastSeenCartQuantities.get( key );
	} else if ( Array.isArray( cartBefore?.items ) ) {
		previousQuantity =
			cartBefore.items.find( ( item ) => item.key === key )?.quantity ??
			0;
	}
	const addedQuantity =
		parseInt( updatedItem.quantity, 10 ) - parseInt( previousQuantity, 10 );

	if ( ! Number.isFinite( addedQuantity ) || addedQuantity <= 0 ) {
		return null;
	}

	return {
		product: scaleLineTotalToAddedQuantity( updatedItem, addedQuantity ),
		quantity: addedQuantity,
	};
};

/**
 * Parse a Store API batch request body into an object.
 *
 * @param {*} body Request body (JSON string or already-parsed object).
 * @return {Object|null} Parsed body, or null when it cannot be parsed.
 */
const parseBatchRequestBody = ( body ) => {
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
 * Resolve every add_to_cart from a Store API batch request/response pair.
 *
 * A batch bundles sub-requests under `requests` and returns their results, in
 * the same order, under `responses`. Each cart sub-response body is the full
 * cart at that point, matched against the previous cart state to pick out the
 * change. Both add-item (new line) and update-item (quantity increase of an
 * existing line) sub-requests are reported as add_to_cart.
 *
 * @param {*}      requestBody  Batch request body.
 * @param {Object} responseJson Batch response JSON.
 * @param {Object} cartBefore   Cart data before the batch request.
 * @return {Array<{ product: Object, quantity: number }>} Added products.
 */
const getBatchAddToCartItems = ( requestBody, responseJson, cartBefore ) => {
	const requests = parseBatchRequestBody( requestBody )?.requests;
	const responses = responseJson?.responses;

	if ( ! Array.isArray( requests ) || ! Array.isArray( responses ) ) {
		return [];
	}

	const adds = [];
	let previousCart = cartBefore;

	requests.forEach( ( request, index ) => {
		const path = request?.path ?? '';
		const isAddItem = STORE_API_ADD_ITEM_PATH.test( path );
		const isUpdateItem = STORE_API_UPDATE_ITEM_PATH.test( path );

		if ( ! isAddItem && ! isUpdateItem ) {
			return;
		}

		const subResponse = responses[ index ];
		const status = subResponse?.status;

		if ( ! subResponse || status < 200 || status >= 300 ) {
			return;
		}

		const cartAfter = subResponse.body;
		const add = isAddItem
			? getAddItemFromResponse( request.body, cartAfter, previousCart )
			: getUpdateItemFromResponse(
					request.body,
					cartAfter,
					previousCart
			  );

		if ( add ) {
			adds.push( add );
		}

		// Record quantities after reading the delta so a later update-item (in
		// this batch or a subsequent request) compares against the new state.
		rememberCartQuantities( cartAfter );
		previousCart = cartAfter;
	} );

	return adds;
};

/**
 * Track successful Store API add item requests.
 *
 * Interactivity API powered add-to-cart blocks do not fire the legacy jQuery
 * event or the WooCommerce Blocks cart-add-item hook in every WooCommerce version.
 *
 * @param {Function} getEventHandler - Function to get the event handler for a given event name.
 */
const trackStoreApiAddToCart = ( getEventHandler ) => {
	addItemEventHandler = ( data ) =>
		safeTrackEvent( getEventHandler, 'add_to_cart', data );

	if (
		addItemStoreApiTrackingStarted ||
		typeof window.fetch !== 'function'
	) {
		return;
	}

	addItemStoreApiTrackingStarted = true;
	const originalFetch = window.fetch;

	window.fetch = async function trackAddItemFetch( ...args ) {
		const [ input, init ] = args;
		const isAddItemRequest = isStoreApiAddItemRequest( input, init );
		const isBatchRequest =
			! isAddItemRequest && isStoreApiBatchRequest( input, init );
		const shouldTrack = isAddItemRequest || isBatchRequest;
		const cartBefore = shouldTrack ? getCartData() : null;
		const bodyPromise = shouldTrack ? getRequestBody( input, init ) : null;
		const response = await originalFetch.apply( this, args );

		if ( ! shouldTrack || ! response?.ok ) {
			return response;
		}

		try {
			const requestBody = await bodyPromise;
			const responseJson = await response.clone().json();
			let adds;
			if ( isBatchRequest ) {
				adds = getBatchAddToCartItems(
					requestBody,
					responseJson,
					cartBefore
				);
			} else {
				adds = [
					getAddItemFromResponse(
						requestBody,
						responseJson,
						cartBefore
					),
				].filter( Boolean );
				// responseJson is the cart for a direct add-item; record it so a
				// later update-item compares against the new state.
				rememberCartQuantities( responseJson );
			}

			// Give the cart-add-item hook a chance to mark these products as
			// already handled first, so blocks that fire both the hook and a
			// Store API request (e.g. the All Products Block) are tracked once,
			// from the hook. A batch add resolves the hook a couple of
			// milliseconds after its response, so it needs a longer wait than a
			// direct add-item (where deferring a single task is enough).
			const trackDelay = isBatchRequest ? BATCH_ADD_TRACK_DELAY : 0;

			adds.forEach( ( { product, quantity } ) => {
				setTimeout( () => {
					if ( wasRecentlyAdded( product ) ) {
						return;
					}

					markRecentlyAdded( product );
					addItemEventHandler( {
						product: { ...product, quantity },
					} );
				}, trackDelay );
			} );
		} catch ( error ) {
			// Tracking must never break the cart. Swallow the error but warn so
			// an unexpected Store API response shape can be diagnosed.
			warnTrackingError(
				'could not parse the Store API add-item response for add_to_cart tracking.',
				error
			);
			return response;
		}

		return response;
	};
};

/**
 * Track when an item is removed from the Interactivity API-powered Mini Cart.
 * The new Mini Cart (WooCommerce 10.4+) doesn't fire the experimental__woocommerce_blocks
 * cart-remove-item action, so we need to listen for clicks on the remove button.
 *
 * This listener only fires if the hook hasn't already handled the removal to prevent
 * duplicate events on older WooCommerce versions.
 *
 * @param {Function} getEventHandler - Function to get the event handler for a given event name.
 */
const trackMiniCartRemoval = ( getEventHandler ) => {
	document.body.addEventListener( 'click', ( event ) => {
		try {
			const removeButton = event.target?.closest?.(
				'.wc-block-cart-item__remove-link'
			);

			if ( ! removeButton ) {
				return;
			}

			// Find the cart item container to get product information
			const cartItem = removeButton.closest(
				'.wc-block-cart-items__row'
			);
			if ( ! cartItem ) {
				return;
			}

			let product = null;

			/*
			 * Try to find product data from available sources:
			 * 1. WooCommerce Blocks store (wc/store/cart) - real-time cart state
			 * 2. Static cart data from server (window.ga4w.data.cart) - initial page load
			 * 3. DOM extraction - last resort when store data is unavailable
			 */
			const productLink = cartItem.querySelector(
				'.wc-block-components-product-name'
			);
			const productHref = productLink?.getAttribute( 'href' );
			const productName = productLink?.textContent?.trim();

			// Extract product ID from URL (e.g., ?p=123)
			let productId = null;
			if ( productHref ) {
				const paramMatch = productHref.match( /[?&]p=(\d+)/ );
				if ( paramMatch ) {
					productId = parseInt( paramMatch[ 1 ], 10 );
				}
			}

			// Try WooCommerce store or static cart data
			const cart = getCartData();
			if ( Array.isArray( cart?.items ) ) {
				if ( productId ) {
					product = getProductFromID( productId, [], cart );
				} else if ( productName ) {
					product = cart.items.find(
						( item ) => item.name === productName
					);
				}
			}

			// Fallback: extract from DOM when cart data lookup fails
			// This can happen if the cart store hasn't synced yet or product matching fails
			if ( ! product ) {
				product = getProductFromDOM( cartItem );
			}

			if ( product ) {
				const dedupKey = getProductDedupKey( product );

				setTimeout( () => {
					if (
						! dedupKey ||
						! recentlyRemovedProducts.has( dedupKey )
					) {
						safeTrackEvent( getEventHandler, 'remove_from_cart', {
							product,
						} );
					}
				}, 0 );
			}
		} catch ( error ) {
			warnTrackingError(
				'could not read mini-cart remove_from_cart data.',
				error
			);
		}
	} );
};

// We add actions asynchronosly, to make sure handlers will have the config available.
export const blocksTracking = ( getEventHandler ) => {
	addUniqueAction( `${ ACTION_PREFIX }-product-render`, NAMESPACE, ( data ) =>
		safeTrackEvent( getEventHandler, 'view_item', data )
	);

	addUniqueAction(
		`${ ACTION_PREFIX }-cart-remove-item`,
		NAMESPACE,
		( data ) => {
			try {
				const dedupKey = getProductDedupKey( data?.product );

				if ( dedupKey ) {
					recentlyRemovedProducts.add( dedupKey );
					setTimeout(
						() => recentlyRemovedProducts.delete( dedupKey ),
						DEDUP_WINDOW
					);
				}

				safeTrackEvent( getEventHandler, 'remove_from_cart', data );
			} catch ( error ) {
				warnTrackingError(
					'could not process Blocks remove_from_cart tracking.',
					error
				);
			}
		}
	);

	// Track Mini Cart removals for Interactivity API-powered Mini Cart (WooCommerce 10.4+)
	trackMiniCartRemoval( getEventHandler );

	addUniqueAction(
		`${ ACTION_PREFIX }-checkout-render-checkout-form`,
		NAMESPACE,
		( data ) => {
			addShippingInfoTracked = false;
			addPaymentInfoTracked = false;
			trackCheckoutEvent( 'begin_checkout', getEventHandler, data );
		}
	);

	addUniqueAction(
		`${ ACTION_PREFIX }-checkout-set-selected-shipping-rate`,
		NAMESPACE,
		( data ) => {
			const storeCart = getCheckoutCartData( data.storeCart );
			addShippingInfoTracked = trackCheckoutEvent(
				'add_shipping_info',
				getEventHandler,
				{
					...data,
					shippingTier:
						findShippingRateName(
							storeCart,
							data.shippingRateId
						) ?? data.shippingRateId,
				}
			);
		}
	);

	addUniqueAction(
		`${ ACTION_PREFIX }-checkout-set-active-payment-method`,
		NAMESPACE,
		( data ) => {
			addPaymentInfoTracked = trackCheckoutEvent(
				'add_payment_info',
				getEventHandler,
				{
					...data,
					paymentType: getPaymentTypeLabel( data.paymentMethodSlug ),
				}
			);
		}
	);

	addUniqueAction(
		`${ ACTION_PREFIX }-checkout-submit`,
		NAMESPACE,
		( data ) => {
			const shippingTier = getSelectedShippingRate( data.storeCart );

			if ( ! addShippingInfoTracked && shippingTier ) {
				addShippingInfoTracked = trackCheckoutEvent(
					'add_shipping_info',
					getEventHandler,
					{
						...data,
						shippingTier,
					}
				);
			}

			if ( addPaymentInfoTracked ) {
				return;
			}

			addPaymentInfoTracked = trackCheckoutEvent(
				'add_payment_info',
				getEventHandler,
				{
					...data,
					paymentType: getPaymentTypeLabel(
						getActivePaymentMethod()
					),
				}
			);
		}
	);

	// These actions only works for All Products Block
	addUniqueAction(
		`${ ACTION_PREFIX }-cart-add-item`,
		NAMESPACE,
		( data ) => {
			try {
				const product = data?.product;

				if ( wasRecentlyAdded( product ) ) {
					return;
				}

				markRecentlyAdded( product );
				safeTrackEvent( getEventHandler, 'add_to_cart', { product } );
			} catch ( error ) {
				warnTrackingError(
					'could not process Blocks add_to_cart tracking.',
					error
				);
			}
		}
	);

	trackStoreApiAddToCart( getEventHandler );

	addUniqueAction(
		`${ ACTION_PREFIX }-product-list-render`,
		NAMESPACE,
		( data ) => {
			// Cache block-rendered products so classic.js click handlers can look
			// them up via getProductFromID when window.ga4w.data.products is empty
			// (e.g. Product Collection block on the empty cart page).
			if ( data?.products ) {
				cacheBlockProducts( data.products );
			}
			safeTrackEvent( getEventHandler, 'view_item_list', data );
		}
	);

	addUniqueAction(
		`${ ACTION_PREFIX }-product-view-link`,
		NAMESPACE,
		( data ) => safeTrackEvent( getEventHandler, 'select_content', data )
	);

	// Listen for the stable WooCommerce 9.4+ DOM event fired when a user clicks a
	// product link inside a Product Collection block. The experimental
	// product-view-link hook only fires for the All Products block; this event
	// covers Product Collection. Product data is resolved via the block products
	// cache populated above by product-list-render.
	if ( ! viewedProductListenerAttached ) {
		viewedProductListenerAttached = true;
		document.body.addEventListener(
			'wc-blocks_viewed_product',
			( event ) => {
				const { productId } = event.detail ?? {};
				const productIdString = String( productId ?? '' );
				if ( /^[1-9]\d*$/.test( productIdString ) ) {
					const normalizedProductId = parseInt(
						productIdString,
						10
					).toString();
					const product = getProductFromID(
						normalizedProductId,
						[],
						null
					) ?? { id: normalizedProductId };
					safeTrackEvent( getEventHandler, 'select_content', {
						product,
					} );
				}
			}
		);
	}
};

/*
 * Remove additional actions added by WooCommerce Core which are either
 * not supported by Google Analytics for WooCommerce or are redundant
 * since Google retired Universal Analytics.
 */
removeAction( `${ ACTION_PREFIX }-checkout-submit`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-set-email-address`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-set-phone-number`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-set-billing-address`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-cart-set-item-quantity`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-product-search`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-store-notice-create`, NAMESPACE );
