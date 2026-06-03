import { getProductFromID } from '../utils';

const checkoutPaymentMethodSelector = 'input[name="payment_method"]';
const checkoutShippingMethodSelector =
	'input[name^="shipping_method"], select[name^="shipping_method"]';

const getSelectedCheckoutOption = ( selector ) =>
	Array.from( document.querySelectorAll( selector ) ).find(
		( element ) => element.checked || element.tagName === 'SELECT'
	);

// Return the user-facing label for a shipping or payment input. For radios,
// WooCommerce renders the human label as the text content of the associated
// <label for="..."> element. For selects we fall back to the selected option's
// text. The raw `value` (e.g. "free_shipping:1") is kept as a last resort so we
// never emit an empty `shipping_tier`/`payment_type`.
const getCheckoutOptionLabel = ( element ) => {
	if ( ! element ) {
		return undefined;
	}

	if ( element.tagName === 'SELECT' ) {
		const selectedOption = element.options[ element.selectedIndex ];
		return selectedOption?.text?.trim() || element.value;
	}

	const label = element.labels?.[ 0 ] || element.closest( 'label' );

	return label?.textContent?.trim() || element.value;
};

/**
 * The Google Analytics integration for classic WooCommerce pages
 * triggers events using three different methods.
 *
 * 1. Instantly handle events listed in the `events` object.
 * 2. Listen for custom events from WooCommerce core.
 * 3. Listen for various actions (i.e clicks) on specific elements.
 *
 * To be executed once data set is complete, and `document` is ready.
 *
 * It also handles some Block events that are not fired reliably for `woocommerce/all-products` block.
 *
 * @param {Function} getEventHandler
 * @param {Object}   data               - The tracking data from the current page load, containing the following properties:
 * @param {Object}   data.events        - An object containing the events to be instantly tracked.
 * @param {Object}   data.cart          - The cart object.
 * @param {Object[]} data.products      - An array of all product from the current page.
 * @param {Object}   data.product       - The single product object.
 * @param {Object}   data.added_to_cart - The product added to cart.
 * @param {Object}   data.order         - The order object.
 * @param {string}   data.list_name     - The name of the product list for the current page context.
 */
export function classicTracking(
	getEventHandler,
	{
		events,
		cart,
		products,
		product,
		added_to_cart: addedToCart,
		order,
		list_name: listName,
	}
) {
	let shippingInfoTracked = false;
	let paymentInfoTracked = false;

	const trackShippingInfo = ( shippingTier ) => {
		shippingInfoTracked = true;
		getEventHandler( 'add_shipping_info' )( {
			storeCart: cart,
			shippingTier,
		} );
	};

	const trackPaymentInfo = ( paymentType ) => {
		paymentInfoTracked = true;
		getEventHandler( 'add_payment_info' )( {
			storeCart: cart,
			paymentType,
		} );
	};

	// Instantly track the events listed in the `events` object.
	Object.values( events ?? {} ).forEach( ( eventName ) => {
		if ( eventName === 'add_to_cart' ) {
			getEventHandler( eventName )( { product: addedToCart } );
		} else {
			getEventHandler( eventName )( {
				storeCart: cart,
				products,
				product,
				order,
				listName,
			} );
		}
	} );

	document.body.addEventListener( 'change', ( event ) => {
		if ( ! cart?.items?.length ) {
			return;
		}

		// Only react to changes inside the classic checkout form. The cart page
		// shipping calculator uses the same `shipping_method[*]` input names, so
		// without this scope a shipping change on the cart page would emit a
		// spurious add_shipping_info event. This mirrors the submit handler,
		// which also only operates on `form.checkout`.
		if ( ! event.target.closest?.( 'form.checkout' ) ) {
			return;
		}

		if ( event.target.matches( checkoutShippingMethodSelector ) ) {
			trackShippingInfo( getCheckoutOptionLabel( event.target ) );
		}

		if ( event.target.matches( checkoutPaymentMethodSelector ) ) {
			trackPaymentInfo( getCheckoutOptionLabel( event.target ) );
		}
	} );

	document
		.querySelector( 'form.checkout' )
		?.addEventListener( 'submit', () => {
			if ( ! cart?.items?.length ) {
				return;
			}

			if ( ! shippingInfoTracked ) {
				const shippingElement = getSelectedCheckoutOption(
					checkoutShippingMethodSelector
				);

				if ( shippingElement ) {
					trackShippingInfo(
						getCheckoutOptionLabel( shippingElement )
					);
				}
			}

			if ( ! paymentInfoTracked ) {
				trackPaymentInfo(
					getCheckoutOptionLabel(
						getSelectedCheckoutOption(
							checkoutPaymentMethodSelector
						)
					)
				);
			}
		} );

	/**
	 * Track the custom add to cart event dispatched by WooCommerce Core
	 *
	 * @param {Event}         e         - The event object
	 * @param {Object}        fragments - An object containing fragments of the updated cart.
	 * @param {string}        cartHash  - A string representing the hash of the cart after the update.
	 * @param {HTMLElement[]} button    - An array of HTML elements representing the add to cart button.
	 */
	function handleAddedToCart( e, fragments, cartHash, button ) {
		const buttonElement = button?.[ 0 ] ?? button;

		// Get product ID from data attribute (archive pages) or value (single product pages).
		const productID = parseInt(
			buttonElement?.dataset.product_id || buttonElement?.value
		);

		if ( Number.isNaN( productID ) ) {
			// eslint-disable-next-line no-console
			console.error(
				'Google Analytics for WooCommerce: Could not read product ID from the button given in `added_to_cart` event. Check whether WooCommerce Core events or elements are malformed by other extensions.'
			);
			return;
		}

		// If the current product doesn't match search by ID.
		const productToHandle =
			product?.id === productID
				? product
				: getProductFromID( productID, products, cart );

		// Confirm we found a product to handle.
		if ( ! productToHandle ) {
			return;
		}

		getEventHandler( 'add_to_cart' )( { product: productToHandle } );
	}

	// Behavior change vs. older versions: we no longer assign `document.body.onadded_to_cart`
	// because that override pattern stomps on any handler set by another plugin (and is
	// stomped by anything that runs after us). Sites that previously hooked in via
	// `document.body.onadded_to_cart = ...` should migrate to `addEventListener` or jQuery
	// `.on('added_to_cart', ...)` to coexist with this plugin.
	document.body.addEventListener( 'added_to_cart', ( event ) => {
		const detail = Array.isArray( event.detail )
			? event.detail
			: [
					event.detail?.fragments,
					event.detail?.cartHash,
					event.detail?.button,
			  ];

		handleAddedToCart( event, ...detail );
	} );
	window.jQuery?.( document.body )?.on( 'added_to_cart', handleAddedToCart );

	/**
	 * Attaches click event listeners to all remove from cart links
	 */
	const removeFromCartListener = () => {
		document
			.querySelectorAll(
				'.woocommerce-cart-form .woocommerce-cart-form__cart-item .remove[data-product_id]'
			)
			.forEach( ( item ) =>
				item.addEventListener( 'click', removeFromCartHandler )
			);
	};

	/**
	 * Handle remove from cart events
	 *
	 * @param {HTMLElement|Object} element - The HTML element clicked on to trigger this event
	 */
	function removeFromCartHandler( element ) {
		const productID = parseInt( element.target?.dataset.product_id );

		if ( Number.isNaN( productID ) ) {
			// eslint-disable-next-line no-console
			console.error(
				'Google Analytics for WooCommerce: Could not read product ID from the target element given to remove from cart event. Check whether WooCommerce Core events or elements are malformed by other extensions.'
			);
			return;
		}
		getEventHandler( 'remove_from_cart' )( {
			product: getProductFromID( productID, products, cart ),
		} );
	}

	// Attach event listeners on initial page load and when the cart div is updated
	removeFromCartListener();
	const oldOnupdatedWcDiv = document.body.onupdated_wc_div;
	document.body.onupdated_wc_div = function () {
		if ( typeof oldOnupdatedWcDiv === 'function' ) {
			oldOnupdatedWcDiv.apply( this, arguments );
		}
		removeFromCartListener();
	};

	// Trigger the handler when an item is removed from the mini-cart and WooCommerce dispatches the `removed_from_cart` event.
	const oldOnRemovedFromCart = document.body.onremoved_from_cart;
	/**
	 * Track the custom removed from cart event dispatched by WooCommerce Core
	 *
	 * @param {Event}         e         - The event object
	 * @param {Object}        fragments - An object containing fragments of the updated cart.
	 * @param {string}        cartHash  - A string representing the hash of the cart after the update.
	 * @param {HTMLElement[]} button    - An array of HTML elements representing the remove from cart button.
	 */
	document.body.onremoved_from_cart = function (
		e,
		fragments,
		cartHash,
		button
	) {
		if ( typeof oldOnRemovedFromCart === 'function' ) {
			oldOnRemovedFromCart.apply( this, arguments );
		}
		removeFromCartHandler( { target: button?.[ 0 ] } );
	};

	// Handle product selection events.
	// Attach click event listeners to non-block product listings
	// to send a `select_content` event if the target link takes the user to the product page.
	document
		.querySelectorAll( '.products .product:not(.wp-block-post)' )
		?.forEach( ( productCard ) => {
			// Get the Product ID from a child node containing the relevant attribute
			const productId = productCard
				.querySelector( 'a[data-product_id]' )
				?.getAttribute( 'data-product_id' );

			if ( ! productId ) {
				return;
			}

			productCard.addEventListener( 'click', ( event ) => {
				// Return early if the user has clicked on an
				// "Add to cart" button or anything other than a product link
				const targetLink = event.target.closest(
					'.woocommerce-loop-product__link'
				);

				const isProductButton =
					event.target.classList.contains( 'button' ) &&
					event.target.hasAttribute( 'data-product_id' );

				const isAddToCartButton =
					event.target.classList.contains( 'add_to_cart_button' ) &&
					! event.target.classList.contains(
						'product_type_variable'
					);

				if (
					! targetLink &&
					( ! isProductButton || isAddToCartButton )
				) {
					return;
				}

				getEventHandler( 'select_content' )( {
					product: getProductFromID(
						parseInt( productId ),
						products,
						cart
					),
				} );
			} );
		} );

	// Handle select_content and add_to_cart in Products (Beta) block, Product Collection (Beta) block.
	// Attach click event listeners to a whole product card, as some links may not have the product_id data attribute.
	document
		.querySelectorAll(
			'.products-block-post-template .product, .wc-block-product-template .product'
		)
		?.forEach( ( productCard ) => {
			// Get the Product ID from a child node containing the relevant attribute
			const productId = productCard
				.querySelector( '[data-product_id]' )
				?.getAttribute( 'data-product_id' );

			if ( ! productId ) {
				return;
			}

			productCard.addEventListener( 'click', ( event ) => {
				const target = event.target;
				// `product-view-link` has no serilized HTML identifier/selector, so we look for the parent block element.
				const viewLink = target.closest(
					'.wc-block-components-product-image a'
				);

				// Catch name click
				const nameLink = target.closest( '.wp-block-post-title a' );

				// Catch the enclosing product button.
				const button = target.closest(
					'.wc-block-components-product-button [data-product_id]'
				);

				const isAddToCartButton =
					button &&
					button.classList.contains( 'add_to_cart_button' ) &&
					! button.classList.contains( 'product_type_variable' );

				if ( isAddToCartButton ) {
					// Add to cart.
					getEventHandler( 'add_to_cart' )( {
						product: getProductFromID(
							parseInt( productId ),
							products,
							cart
						),
					} );
				} else if ( viewLink || button || nameLink ) {
					// Product image or add-to-cart-like button.
					getEventHandler( 'select_content' )( {
						product: getProductFromID(
							parseInt( productId ),
							products,
							cart
						),
					} );
				}
			} );
		} );
}
