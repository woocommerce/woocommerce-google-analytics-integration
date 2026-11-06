/**
 * Unit tests for the event data the tracker sends to Google Analytics:
 * prices, discounts, order value, product IDs, variations and categories.
 * Run with `npm run test:js`.
 */

/**
 * External dependencies
 */
const { test, beforeEach } = require( 'node:test' );
const assert = require( 'node:assert' );

/**
 * Internal dependencies
 */
const loadSource = require( './helpers/load-source' );

const events = loadSource( 'tracker/data-formatting.js' );
const {
	cacheBlockProducts,
	formatPrice,
	getProductFieldObject,
	getProductFromID,
	getProductId,
} = loadSource( 'utils/index.js' );

beforeEach( () => {
	globalThis.window = { ga4w: { settings: {} } };
} );

/**
 * A Store API cart line item, in minor units.
 *
 * @param {Object} overrides Fields to replace.
 * @return {Object} The cart item.
 */
const cartItem = ( overrides = {} ) => ( {
	id: 10,
	name: 'Hoodie',
	quantity: 2,
	prices: { price: '1500', currency_minor_unit: 2 },
	totals: { line_total: '3000' },
	...overrides,
} );

/**
 * A Store API cart.
 *
 * @param {Object[]} items     Cart items.
 * @param {Object}   overrides Fields to replace.
 * @return {Object} The cart.
 */
const cart = ( items, overrides = {} ) => ( {
	items,
	coupons: [],
	totals: {
		currency_code: 'USD',
		currency_minor_unit: 2,
		total_price: '99999',
	},
	...overrides,
} );

test( 'formatPrice turns minor units into a number', () => {
	assert.strictEqual( formatPrice( '1234' ), 12.34 );
	assert.strictEqual( formatPrice( 1234, 0 ), 1234 );
	assert.strictEqual( formatPrice( '1234', 3 ), 1.234 );
} );

test( 'checkout events send nothing for an empty cart', () => {
	for ( const storeCart of [ undefined, cart( [] ) ] ) {
		assert.strictEqual( events.begin_checkout( { storeCart } ), false );
		assert.strictEqual(
			events.add_shipping_info( { storeCart, shippingTier: 'Flat' } ),
			false
		);
		assert.strictEqual(
			events.add_payment_info( { storeCart, paymentType: 'Card' } ),
			false
		);
	}
} );

test( 'checkout value is the sum of line totals, without shipping or tax', () => {
	const data = events.begin_checkout( {
		storeCart: cart( [
			cartItem(),
			cartItem( { id: 11, totals: { line_total: '999' } } ),
		] ),
	} );

	assert.strictEqual( data.value, 39.99 );
	assert.strictEqual( data.currency, 'USD' );
} );

test( 'checkout value falls back to the price of a static server cart line', () => {
	// The server cart has no totals and stores the line total in prices.price.
	const data = events.begin_checkout( {
		storeCart: cart( [
			cartItem( { totals: undefined, prices: { price: '4500' } } ),
		] ),
	} );

	assert.strictEqual( data.value, 45 );
} );

test( 'checkout value follows the currency minor unit', () => {
	for ( const [ minorUnit, lineTotal, expected ] of [
		[ 0, '1200', 1200 ], // JPY
		[ 2, '1200', 12 ], // USD
		[ 3, '1200', 1.2 ], // KWD
	] ) {
		const data = events.begin_checkout( {
			storeCart: cart(
				[ cartItem( { totals: { line_total: lineTotal } } ) ],
				{
					totals: {
						currency_code: 'X',
						currency_minor_unit: minorUnit,
					},
				}
			),
		} );

		assert.strictEqual( data.value, expected, `minor unit ${ minorUnit }` );
	}
} );

test( 'checkout sends the first coupon only, and no coupon key without one', () => {
	const withCoupons = events.begin_checkout( {
		storeCart: cart( [ cartItem() ], {
			coupons: [ { code: 'first' }, { code: 'second' } ],
		} ),
	} );
	const withoutCoupon = events.begin_checkout( {
		storeCart: cart( [ cartItem() ] ),
	} );

	assert.strictEqual( withCoupons.coupon, 'first' );
	assert.ok( ! ( 'coupon' in withoutCoupon ) );
} );

test( 'shipping tier and payment type are added only when set', () => {
	const storeCart = cart( [ cartItem() ] );

	assert.strictEqual(
		events.add_shipping_info( { storeCart, shippingTier: 'Flat rate' } )
			.shipping_tier,
		'Flat rate'
	);
	assert.ok(
		! ( 'shipping_tier' in events.add_shipping_info( { storeCart } ) )
	);
	assert.strictEqual(
		events.add_payment_info( { storeCart, paymentType: 'Card' } )
			.payment_type,
		'Card'
	);
	assert.ok(
		! ( 'payment_type' in events.add_payment_info( { storeCart } ) )
	);
} );

test( 'an item is priced from its line total, or its unit price without one', () => {
	assert.strictEqual( getProductFieldObject( cartItem() ).price, 30 );
	assert.strictEqual(
		getProductFieldObject( cartItem( { totals: undefined } ) ).price,
		15
	);
	// The Store API sends a free line as the string '0', which is kept.
	assert.strictEqual(
		getProductFieldObject( cartItem( { totals: { line_total: '0' } } ) )
			.price,
		0
	);
} );

test( 'a lower price after coupons sets the discount and lowers the price', () => {
	const item = getProductFieldObject( {
		...cartItem( { totals: undefined } ),
		price_after_coupon_discount: 1200,
	} );

	assert.strictEqual( item.price, 12 );
	assert.strictEqual( item.discount, 3 );
} );

test( 'an equal or missing price after coupons leaves the price alone', () => {
	for ( const discounted of [ 1500, undefined ] ) {
		const item = getProductFieldObject( {
			...cartItem( { totals: undefined } ),
			price_after_coupon_discount: discounted,
		} );

		assert.strictEqual( item.price, 15 );
		assert.ok( ! ( 'discount' in item ) );
	}
} );

test( 'the product quantity wins over the quantity passed in', () => {
	assert.strictEqual( getProductFieldObject( cartItem(), 5 ).quantity, 2 );
	assert.strictEqual(
		getProductFieldObject( cartItem( { quantity: undefined } ), 5 )
			.quantity,
		5
	);
} );

test( 'variation attributes become one lower-case string', () => {
	const item = getProductFieldObject(
		cartItem( {
			variation: [
				{ attribute: 'Color', value: 'Blue' },
				{ attribute: '', value: 'skipped' },
				{ attribute: 'Size', value: '' },
				{ attribute: 'SIZE', value: 'L' },
			],
		} )
	);

	assert.strictEqual( item.item_variant, 'color: Blue, size: L' );
	assert.strictEqual(
		getProductFieldObject( cartItem( { variation: 'Blue, L' } ) )
			.item_variant,
		'Blue, L'
	);
	assert.ok( ! ( 'item_variant' in getProductFieldObject( cartItem() ) ) );
} );

test( 'up to five categories are sent, under GA4’s numbered keys', () => {
	const categories = [ 'A', 'B', 'C', 'D', 'E', 'F' ].map( ( name ) => ( {
		name,
	} ) );
	const item = getProductFieldObject( cartItem( { categories } ) );

	assert.deepStrictEqual(
		Object.keys( item ).filter( ( key ) =>
			key.startsWith( 'item_category' )
		),
		[
			'item_category',
			'item_category2',
			'item_category3',
			'item_category4',
			'item_category5',
		]
	);
	assert.strictEqual( item.item_category5, 'E' );
	assert.ok(
		! ( 'item_category' in getProductFieldObject( cartItem() ) ),
		'no categories key'
	);
	assert.ok(
		! (
			'item_category' in
			getProductFieldObject( cartItem( { categories: [] } ) )
		)
	);
} );

test( 'the item ID follows the identifier setting', () => {
	assert.strictEqual(
		getProductId( {
			id: 10,
			sku: 'HD-1',
			extensions: {
				woocommerce_google_analytics_integration: {
					identifier: 'from-extension',
				},
			},
		} ),
		'from-extension'
	);
	assert.strictEqual( getProductId( { id: 10, sku: 'HD-1' } ), 10 );

	window.ga4w.settings.identifier = 'product_sku';
	assert.strictEqual( getProductId( { id: 10, sku: 'HD-1' } ), 'HD-1' );
	assert.strictEqual( getProductId( { id: 10, sku: '' } ), '#10' );
} );

test( 'purchase sends nothing without an order', () => {
	assert.strictEqual( events.purchase( { order: undefined } ), false );
} );

test( 'purchase value is the sum of price × quantity, rounded per line', () => {
	const order = {
		id: 123,
		affiliation: 'Store',
		items: [
			// 10.01 × 1.25 = 12.5125, rounded to 12.51.
			cartItem( {
				quantity: 1.25,
				totals: undefined,
				prices: { price: 1001, currency_minor_unit: 2 },
			} ),
			cartItem( {
				quantity: 0.5,
				totals: undefined,
				prices: { price: 333, currency_minor_unit: 2 },
			} ),
		],
		totals: {
			currency_code: 'EUR',
			currency_minor_unit: 2,
			tax_total: '250',
			shipping_total: '500',
		},
	};

	const data = events.purchase( { order } );

	// 12.51 + 1.67 (3.33 × 0.5 = 1.665, rounded up).
	assert.strictEqual( data.value, 14.18 );
	assert.strictEqual( data.tax, 2.5 );
	assert.strictEqual( data.shipping, 5 );
	assert.strictEqual( data.transaction_id, 123 );
	assert.strictEqual( data.affiliation, 'Store' );
	assert.strictEqual( data.currency, 'EUR' );
} );

test( 'purchase value has no decimals in a zero-decimal currency', () => {
	const data = events.purchase( {
		order: {
			id: 1,
			items: [
				cartItem( {
					quantity: 3,
					totals: undefined,
					prices: { price: 1200, currency_minor_unit: 0 },
				} ),
			],
			totals: {
				currency_code: 'JPY',
				currency_minor_unit: 0,
				tax_total: '0',
				shipping_total: '300',
			},
		},
	} );

	assert.strictEqual( data.value, 3600 );
	assert.strictEqual( data.shipping, 300 );
} );

test( 'view_item_list builds the list ID from the name and counts from 1', () => {
	const data = events.view_item_list( {
		products: [ cartItem(), cartItem( { id: 11 } ) ],
		listName: '  Best Sellers & More! ',
	} );

	assert.strictEqual( data.item_list_id, 'best_sellers_more' );
	assert.strictEqual( data.item_list_name, '  Best Sellers & More! ' );
	assert.deepStrictEqual(
		data.items.map( ( { index } ) => index ),
		[ 1, 2 ]
	);
	// A list is priced per unit, not per line.
	assert.strictEqual( data.items[ 0 ].price, 15 );
} );

test( 'view_item_list sends nothing for an empty list, and has a default name', () => {
	assert.strictEqual( events.view_item_list( { products: [] } ), false );
	assert.strictEqual(
		events.view_item_list( { products: [ cartItem() ] } ).item_list_name,
		'Product List'
	);
} );

test( 'getProductFromID searches products, then the cart, then cached block products', () => {
	const listed = { id: 1, name: 'listed' };
	const inCart = { id: 2, name: 'in cart' };
	const cached = { id: 3, name: 'cached' };
	cacheBlockProducts( [
		cached,
		{ id: 3, name: 'duplicate' },
		{ name: 'no id' },
	] );
	cacheBlockProducts( 'not a list' );

	const sources = [
		[ listed, { id: 2, name: 'listed 2' } ],
		{ items: [ inCart ] },
	];

	assert.strictEqual( getProductFromID( '1', ...sources ), listed );
	assert.strictEqual( getProductFromID( 2, ...sources ).name, 'listed 2' );
	assert.strictEqual( getProductFromID( 2, [], sources[ 1 ] ), inCart );
	assert.strictEqual( getProductFromID( '3', undefined, undefined ), cached );
	assert.strictEqual( getProductFromID( null, ...sources ), undefined );
	assert.strictEqual( getProductFromID( 99, ...sources ), undefined );
} );

test( 'add_to_cart and remove_from_cart send no items without a product', () => {
	assert.deepStrictEqual( events.add_to_cart( {} ), { items: [] } );
	assert.deepStrictEqual( events.remove_from_cart( {} ), { items: [] } );
	assert.strictEqual(
		events.add_to_cart( { product: cartItem( { quantity: undefined } ) } )
			.items[ 0 ].quantity,
		1
	);
} );
