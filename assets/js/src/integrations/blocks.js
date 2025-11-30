import { removeAction } from '@wordpress/hooks';
import { addUniqueAction, getProductFromID } from '../utils';
import { ACTION_PREFIX, NAMESPACE } from '../constants';

/*
 * Track whether the cart-remove-item hook fired recently.
 * Used to prevent duplicate remove_from_cart events.
 */
let hookFiredRecently = false;

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

/**
 * Extract product data from Interactivity API context on a cart item element.
 * WooCommerce 10.4+ stores cart item data in data-wp-context attributes.
 *
 * @param {Element} cartItem - The cart item row element.
 * @return {Object|null} Product object or null if not found.
 */
const getProductFromInteractivityContext = ( cartItem ) => {
	// Find the element with data-wp-context (could be on cartItem or a parent)
	const contextElement =
		cartItem.closest( '[data-wp-context]' ) ||
		cartItem.querySelector( '[data-wp-context]' );

	if ( ! contextElement ) {
		return null;
	}

	try {
		const context = JSON.parse(
			contextElement.getAttribute( 'data-wp-context' )
		);
		// The context structure may vary, look for cart item data
		const item = context?.woocommerce?.cart?.item || context?.item;
		if ( item ) {
			return item;
		}
	} catch ( e ) {
		// Invalid JSON, ignore
	}

	return null;
};

/**
 * Extract product data from DOM elements in a cart item row.
 * This is a fallback when other data sources are unavailable.
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

	let price = 0;
	if ( priceElement ) {
		const priceText = priceElement.textContent
			?.replace( /[^0-9.,]/g, '' )
			.replace( ',', '.' );
		price = parseFloat( priceText ) || 0;
	}

	// Get currency minor unit (decimal places) - default to 2
	const currencyMinorUnit = 2;

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

		// 1. Try Interactivity API context first (WooCommerce 10.4+)
		product = getProductFromInteractivityContext( cartItem );

		// 2. Try WooCommerce store or static cart data
		if ( ! product ) {
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
		}

		// 3. Fallback: extract from DOM elements
		if ( ! product ) {
			product = getProductFromDOM( cartItem );
		}

		if ( product ) {
			/*
			 * Use setTimeout to allow the hook to fire first (if it's going to).
			 * The hook sets hookFiredRecently=true synchronously, so by the time
			 * this callback runs, we'll know if the hook handled it.
			 */
			setTimeout( () => {
				if ( ! hookFiredRecently ) {
					getEventHandler( 'remove_from_cart' )( { product } );
				}
				// Reset the flag for the next removal
				hookFiredRecently = false;
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
			// Mark that the hook fired to prevent duplicate events from click listener
			hookFiredRecently = true;
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
