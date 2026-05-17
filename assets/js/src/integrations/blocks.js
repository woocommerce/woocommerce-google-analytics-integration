import { removeAction } from '@wordpress/hooks';
import { addUniqueAction, getProductFromID } from '../utils';
import { ACTION_PREFIX, NAMESPACE } from '../constants';

const recentlyRemovedProducts = new Set();

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
	// Try to get fresh cart data from WooCommerce Blocks store
	if ( window.wp?.data?.select?.( 'wc/store/cart' ) ) {
		const storeCart = window.wp.data
			.select( 'wc/store/cart' )
			.getCartData();
		if ( storeCart?.items?.length > 0 ) {
			return storeCart;
		}
	}

	// Fallback to static cart data from page load
	return window.ga4w?.data?.cart;
};

const getProductDedupKey = ( product ) =>
	product?.key ?? product?.id ?? product?.name;

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
	if ( window.accounting?.unformat ) {
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
		const removeButton = event.target.closest(
			'.wc-block-cart-item__remove-link'
		);

		if ( ! removeButton ) {
			return;
		}

		// Find the cart item container to get product information
		const cartItem = removeButton.closest( '.wc-block-cart-items__row' );
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
		if ( cart?.items ) {
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
				if ( ! dedupKey || ! recentlyRemovedProducts.has( dedupKey ) ) {
					getEventHandler( 'remove_from_cart' )( { product } );
				}
			}, 0 );
		}
	} );
};

// We add actions asynchronosly, to make sure handlers will have the config available.
export const blocksTracking = ( getEventHandler ) => {
	addUniqueAction(
		`${ ACTION_PREFIX }-product-render`,
		NAMESPACE,
		getEventHandler( 'view_item' )
	);

	addUniqueAction(
		`${ ACTION_PREFIX }-cart-remove-item`,
		NAMESPACE,
		( data ) => {
			const dedupKey = getProductDedupKey( data?.product );

			if ( dedupKey ) {
				recentlyRemovedProducts.add( dedupKey );
				setTimeout(
					() => recentlyRemovedProducts.delete( dedupKey ),
					50
				);
			}

			getEventHandler( 'remove_from_cart' )( data );
		}
	);

	// Track Mini Cart removals for Interactivity API-powered Mini Cart (WooCommerce 10.4+)
	trackMiniCartRemoval( getEventHandler );

	addUniqueAction(
		`${ ACTION_PREFIX }-checkout-render-checkout-form`,
		NAMESPACE,
		( data ) => {
			/*
			 * WooCommerce 10.4+ may fire this event before cart data is fully loaded.
			 * If storeCart is empty or missing items, fall back to the cart data
			 * provided by the server via window.ga4w.data.cart.
			 */
			const storeCart = data?.storeCart;
			const cartData =
				storeCart?.items?.length > 0
					? storeCart
					: window.ga4w?.data?.cart;

			if ( cartData ) {
				getEventHandler( 'begin_checkout' )( { storeCart: cartData } );
			}
		}
	);

	// These actions only works for All Products Block
	addUniqueAction(
		`${ ACTION_PREFIX }-cart-add-item`,
		NAMESPACE,
		( { product } ) => {
			getEventHandler( 'add_to_cart' )( { product } );
		}
	);

	addUniqueAction(
		`${ ACTION_PREFIX }-product-list-render`,
		NAMESPACE,
		getEventHandler( 'view_item_list' )
	);

	addUniqueAction(
		`${ ACTION_PREFIX }-product-view-link`,
		NAMESPACE,
		getEventHandler( 'select_content' )
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
