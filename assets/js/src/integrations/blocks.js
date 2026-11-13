import { removeAction } from '@wordpress/hooks';
import {
	addUniqueAction,
	getProductFromID,
	cacheBlockProducts,
	forgetClickedAdd,
	getCurrencySettings,
	parsePriceFromDOM,
} from '../utils';
import {
	rememberCartItems,
	isStoreApiAddItemRequest,
	isStoreApiUpdateItemRequest,
	isStoreApiBatchRequest,
	getRequestBody,
	getBatchCartChanges,
	getUpdateItemChangeFromResponse,
	getAddItemFromResponse,
	rememberCartResponse,
} from '../utils/store-api';
import { ACTION_PREFIX, NAMESPACE } from '../constants';

const recentlyRemovedProducts = new Set();
let addShippingInfoTracked = false;
let addPaymentInfoTracked = false;

/*
 * Track recently dispatched add_to_cart events so the cart-add-item hook and
 * the fetch interceptor don't both fire for the same Store API add. Entries
 * auto-expire after 200ms, which is long enough for the second code path to
 * short-circuit but short enough that a deliberate second add of the same
 * product is not swallowed.
 */
const recentlyAdded = new Set();
const DEDUP_WINDOW = 200;
let addItemStoreApiTrackingStarted = false;
let storeApiEventHandler = null;

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
 * the interceptor must give the hook time to claim the add
 * before falling back to firing the event itself. Comfortably larger than the
 * hook latency yet well within the 200ms de-dup window above.
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

let viewedProductListener = null;

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
	try {
		const storeCart = getCheckoutCartData( data.storeCart );

		if ( ! storeCart?.items?.length ) {
			return false;
		}

		safeTrackEvent( getEventHandler, eventName, { ...data, storeCart } );
		return true;
	} catch ( error ) {
		warnTrackingError(
			`could not prepare the ${ eventName } event.`,
			error
		);
		return false;
	}
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

/**
 * Track successful Store API add item requests.
 *
 * Interactivity API powered add-to-cart blocks do not fire the legacy jQuery
 * event or the WooCommerce Blocks cart-add-item hook in every WooCommerce version.
 *
 * @param {Function} getEventHandler - Function to get the event handler for a given event name.
 */
const trackStoreApiAddToCart = ( getEventHandler ) => {
	storeApiEventHandler = ( eventName, data ) =>
		safeTrackEvent( getEventHandler, eventName, data );

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
		const isUpdateItemRequest =
			! isAddItemRequest && isStoreApiUpdateItemRequest( input, init );
		const isBatchRequest =
			! isAddItemRequest &&
			! isUpdateItemRequest &&
			isStoreApiBatchRequest( input, init );
		const shouldTrack =
			isAddItemRequest || isUpdateItemRequest || isBatchRequest;
		const cartBefore = shouldTrack ? getCartData() : null;
		const bodyPromise = shouldTrack ? getRequestBody( input, init ) : null;
		const response = await originalFetch.apply( this, args );

		if ( ! response?.ok ) {
			return response;
		}

		if ( ! shouldTrack ) {
			try {
				await rememberCartResponse( input, init, response );
			} catch ( error ) {
				warnTrackingError(
					'could not parse the Store API cart response.',
					error
				);
			}

			return response;
		}

		try {
			const requestBody = await bodyPromise;
			const responseJson = await response.clone().json();
			let changes;
			if ( isBatchRequest ) {
				changes = getBatchCartChanges(
					requestBody,
					responseJson,
					cartBefore
				);
			} else if ( isUpdateItemRequest ) {
				changes = [
					getUpdateItemChangeFromResponse(
						requestBody,
						responseJson,
						cartBefore
					),
				].filter( Boolean );
				rememberCartItems( responseJson );
			} else {
				changes = [
					getAddItemFromResponse(
						requestBody,
						responseJson,
						cartBefore
					),
				].filter( Boolean );
				// responseJson is the cart for a direct add-item; record it so a
				// later update-item compares against the new state.
				rememberCartItems( responseJson );
			}

			// Give the cart-add-item hook a chance to mark these products as
			// already handled first, so blocks that fire both the hook and a
			// Store API request (e.g. the All Products Block) are tracked once,
			// from the hook. A batch add resolves the hook a couple of
			// milliseconds after its response, so it needs a longer wait than a
			// direct add-item (where deferring a single task is enough).
			const trackDelay = isBatchRequest ? BATCH_ADD_TRACK_DELAY : 0;

			changes.forEach(
				( { eventName, product, quantity, isAddItem } ) => {
					if ( eventName === 'remove_from_cart' ) {
						storeApiEventHandler( eventName, {
							product: { ...product, quantity },
						} );
						return;
					}

					// An add_to_cart from a quantity increase (update-item) has no
					// competing hook event to dedupe against — the cart-add-item
					// hook only fires for adds — so track it directly. The add-dedup
					// gate below is only for add-item, where a block can fire both
					// the hook and a Store API request for the same add.
					if ( ! isAddItem ) {
						storeApiEventHandler( eventName, {
							product: { ...product, quantity },
						} );
						return;
					}

					// The click listener of a block product grid (classic.js)
					// sends add_to_cart on the click, before the request. The
					// response can come any time later, so the window that
					// matches the hook below cannot match a click: each click
					// accounts for one add-item of its product.
					if ( forgetClickedAdd( product.id ) ) {
						return;
					}

					setTimeout( () => {
						if ( wasRecentlyAdded( product ) ) {
							return;
						}

						markRecentlyAdded( product );
						storeApiEventHandler( eventName, {
							product: { ...product, quantity },
						} );
					}, trackDelay );
				}
			);
		} catch ( error ) {
			// Tracking must never break the cart. Swallow the error but warn so
			// an unexpected Store API response shape can be diagnosed.
			warnTrackingError(
				'could not parse the Store API cart response for cart change tracking.',
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
			try {
				const storeCart = getCheckoutCartData( data.storeCart );
				const shippingRateId = data.shippingRateId;

				addShippingInfoTracked = trackCheckoutEvent(
					'add_shipping_info',
					getEventHandler,
					{
						...data,
						shippingTier:
							findShippingRateName( storeCart, shippingRateId ) ??
							shippingRateId,
					}
				);
			} catch ( error ) {
				addShippingInfoTracked = false;
				warnTrackingError(
					'could not prepare the add_shipping_info event.',
					error
				);
			}
		}
	);

	addUniqueAction(
		`${ ACTION_PREFIX }-checkout-set-active-payment-method`,
		NAMESPACE,
		( data ) => {
			try {
				addPaymentInfoTracked = trackCheckoutEvent(
					'add_payment_info',
					getEventHandler,
					{
						...data,
						paymentType: getPaymentTypeLabel(
							data.paymentMethodSlug
						),
					}
				);
			} catch ( error ) {
				addPaymentInfoTracked = false;
				warnTrackingError(
					'could not prepare the add_payment_info event.',
					error
				);
			}
		}
	);

	addUniqueAction(
		`${ ACTION_PREFIX }-checkout-submit`,
		NAMESPACE,
		( data ) => {
			try {
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
			} catch ( error ) {
				warnTrackingError(
					'could not prepare checkout submit tracking.',
					error
				);
			}
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
	if ( viewedProductListener ) {
		document.body.removeEventListener(
			'wc-blocks_viewed_product',
			viewedProductListener
		);
	}

	viewedProductListener = ( event ) => {
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
	};

	document.body.addEventListener(
		'wc-blocks_viewed_product',
		viewedProductListener
	);
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
