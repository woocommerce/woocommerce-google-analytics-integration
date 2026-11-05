/**
 * Unit tests for the Store API helpers that decide which add_to_cart and
 * remove_from_cart events the block cart, checkout and add-to-cart blocks send.
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

const api = loadSource( 'utils/store-api.js' );
const { parsePriceFromDOM } = loadSource( 'utils/index.js' );

const ORIGIN = 'https://shop.test';

beforeEach( () => {
	globalThis.window = {
		location: { origin: ORIGIN },
		ga4w: { settings: {} },
	};
} );

// The last-seen cart items are shared module state, so each test uses its own line keys.
let keyCount = 0;
const newKey = () => `line-${ ++keyCount }`;

/**
 * A Store API cart line with a line total of 15.00 per unit.
 *
 * @param {string} key       Line item key.
 * @param {number} quantity  Line quantity.
 * @param {Object} overrides Fields to replace.
 * @return {Object} The cart line.
 */
const line = ( key, quantity, overrides = {} ) => ( {
	key,
	id: 12,
	name: 'Hoodie',
	quantity,
	totals: { line_total: String( 1500 * quantity ) },
	...overrides,
} );

const post = { method: 'POST' };

test( 'Store API requests are matched by method and path, with or without a version', () => {
	const cases = [
		[ '/wp-json/wc/store/cart/add-item', post, 'add' ],
		[ '/wp-json/wc/store/v1/cart/add-item?_locale=user', post, 'add' ],
		[ `${ ORIGIN }/wp-json/wc/store/v1/cart/add-item`, post, 'add' ],
		[ '/?rest_route=/wc/store/v1/cart/add-item', post, 'add' ],
		[ '/wp-json/wc/store/v1/cart/add-item', { method: 'post' }, 'add' ],
		[ '/wp-json/wc/store/v1/cart/add-item', {}, null ],
		[ '/wp-json/wc/store/v1/cart/add-item', { method: 'GET' }, null ],
		[ 'https://other.test/wp-json/wc/store/v1/cart/add-item', post, null ],
		[ '/wp-json/wc/store/v1/cart/update-item', post, 'update' ],
		[ '/wp-json/wc/store/update-item', post, null ],
		[ '/wp-json/wc/store/v1/batch?_locale=user', post, 'batch' ],
		[ '/wp-json/wc/store/v1/cart', post, null ],
		[ 'http://[', post, null ],
	];

	for ( const [ url, init, expected ] of cases ) {
		assert.deepStrictEqual(
			[
				api.isStoreApiAddItemRequest( url, init ) && 'add',
				api.isStoreApiUpdateItemRequest( url, init ) && 'update',
				api.isStoreApiBatchRequest( url, init ) && 'batch',
			].filter( Boolean ),
			expected ? [ expected ] : [],
			`${ init.method ?? 'no method' } ${ url }`
		);
	}
} );

test( 'a Request object is matched by its own URL and method', () => {
	const request = new Request( `${ ORIGIN }/wp-json/wc/store/v1/batch`, {
		method: 'POST',
		body: '{}',
	} );

	assert.strictEqual( api.isStoreApiBatchRequest( request ), true );
	assert.strictEqual(
		api.isStoreApiBatchRequest( request, { method: 'GET' } ),
		false,
		'init wins over the Request'
	);
} );

test( 'the request body is read from init, or from a copy of the Request', async () => {
	const request = new Request( `${ ORIGIN }/x`, {
		method: 'POST',
		body: '{"id":1}',
	} );

	assert.strictEqual(
		await api.getRequestBody( '/x', { body: 'a=1' } ),
		'a=1'
	);
	assert.strictEqual( await api.getRequestBody( request ), '{"id":1}' );
	assert.strictEqual( request.bodyUsed, false, 'the page can still send it' );
	assert.strictEqual( await api.getRequestBody( '/x' ), null );
} );

test( 'request bodies are read as JSON, form data or an object', () => {
	const form = new FormData();
	form.append( 'id', '5' );

	assert.deepStrictEqual( api.coerceRequestBodyData( '{"id":5}' ), {
		id: 5,
	} );
	assert.deepStrictEqual( api.coerceRequestBodyData( 'id=5&quantity=2' ), {
		id: '5',
		quantity: '2',
	} );
	assert.deepStrictEqual( api.coerceRequestBodyData( form ), { id: '5' } );
	assert.deepStrictEqual(
		api.coerceRequestBodyData( new URLSearchParams( 'id=7' ) ),
		{ id: '7' }
	);
	assert.deepStrictEqual( api.coerceRequestBodyData( { id: 9 } ), { id: 9 } );
	assert.deepStrictEqual( api.coerceRequestBodyData( null ), {} );
} );

test( 'only whole numbers above zero count as an ID or quantity', () => {
	for ( const [ value, expected ] of [
		[ '3', 3 ],
		[ 3, 3 ],
		[ '2.7', 2 ],
		[ 0, null ],
		[ -2, null ],
		[ 'abc', null ],
		[ undefined, null ],
	] ) {
		assert.strictEqual(
			api.toPositiveInteger( value ),
			expected,
			String( value )
		);
	}
	assert.strictEqual( api.toPositiveInteger( 'abc', 1 ), 1 );
} );

test( 'an add-item body gives the product and a quantity of at least 1', () => {
	assert.deepStrictEqual(
		api.parseAddItemRequestBody( '{"id":"12","quantity":"3"}' ),
		{ productId: 12, quantity: 3 }
	);
	assert.deepStrictEqual( api.parseAddItemRequestBody( 'id=12' ), {
		productId: 12,
		quantity: 1,
	} );
	assert.deepStrictEqual(
		api.parseAddItemRequestBody( { id: 'x', quantity: 0 } ),
		{ productId: null, quantity: 1 }
	);
} );

test( 'an add of a new line reports that line and its quantity', () => {
	const key = newKey();
	const change = api.getAddItemFromResponse(
		{ id: 12, quantity: 2 },
		{ items: [ line( key, 2 ) ] },
		{ items: [] }
	);

	assert.deepStrictEqual( change, {
		eventName: 'add_to_cart',
		product: line( key, 2 ),
		quantity: 2,
		isAddItem: true,
	} );
} );

test( 'an add to a line already in the cart reports only the added units', () => {
	const key = newKey();
	const change = api.getAddItemFromResponse(
		{ id: 12, quantity: 2 },
		{ items: [ line( key, 3 ) ] },
		{ items: [ line( key, 1 ) ] }
	);

	assert.strictEqual( change.quantity, 2 );
	assert.strictEqual( change.product.totals.line_total, '3000' );
} );

test( 'an add of another variation of the same product reports the new line', () => {
	const red = newKey();
	const blue = newKey();
	const change = api.getAddItemFromResponse(
		{ id: 12, quantity: 1 },
		{ items: [ line( red, 1 ), line( blue, 1 ) ] },
		{ items: [ line( red, 1 ) ] }
	);

	assert.strictEqual( change.product.key, blue );
} );

test( 'an add capped by stock reports what the cart actually got', () => {
	const key = newKey();
	const change = api.getAddItemFromResponse(
		{ id: 12, quantity: 5 },
		{ items: [ line( key, 2 ) ] },
		{ items: [ line( key, 1 ) ] }
	);

	assert.strictEqual( change.quantity, 1 );
	assert.strictEqual( change.product.totals.line_total, '1500' );
} );

test( 'without the cart before, an add is matched by product ID and capped at the line', () => {
	const key = newKey();
	const change = api.getAddItemFromResponse(
		{ id: 12, quantity: 5 },
		{ items: [ line( newKey(), 1, { id: 99 } ), line( key, 3 ) ] },
		null
	);

	assert.strictEqual( change.product.key, key );
	assert.strictEqual( change.quantity, 3 );
} );

test( 'an add with no matching line in the response reports nothing', () => {
	const key = newKey();
	const cartItems = { items: [ line( key, 1 ) ] };

	assert.strictEqual(
		api.getAddItemFromResponse( { id: 99 }, cartItems, cartItems ),
		null
	);
	assert.strictEqual(
		api.getAddItemFromResponse( { id: 12 }, { items: [] }, null ),
		null
	);
} );

test( 'a quantity increase is an add_to_cart of the difference', () => {
	const key = newKey();
	const change = api.getUpdateItemChangeFromResponse(
		{ key, quantity: 3 },
		{ items: [ line( key, 3 ) ] },
		{ items: [ line( key, 1 ) ] }
	);

	assert.strictEqual( change.eventName, 'add_to_cart' );
	assert.strictEqual( change.quantity, 2 );
	assert.strictEqual( change.product.totals.line_total, '3000' );
} );

test( 'a quantity decrease is a remove_from_cart valued from the line before', () => {
	const key = newKey();
	const change = api.getUpdateItemChangeFromResponse(
		{ key, quantity: 1 },
		{ items: [ line( key, 1 ) ] },
		{ items: [ line( key, 3 ) ] }
	);

	assert.strictEqual( change.eventName, 'remove_from_cart' );
	assert.strictEqual( change.quantity, 2 );
	assert.strictEqual( change.product.totals.line_total, '3000' );
} );

test( 'a quantity set to 0 is a remove_from_cart of the whole line', () => {
	const key = newKey();
	const change = api.getUpdateItemChangeFromResponse(
		{ key, quantity: 0 },
		{ items: [] },
		{ items: [ line( key, 3 ) ] }
	);

	assert.strictEqual( change.eventName, 'remove_from_cart' );
	assert.strictEqual( change.quantity, 3 );
	assert.strictEqual( change.product.totals.line_total, '4500' );
} );

test( 'an update compares with the last cart the Store API returned, not stale page data', () => {
	const key = newKey();
	api.rememberCartItems( { items: [ line( key, 4 ) ] } );

	const change = api.getUpdateItemChangeFromResponse(
		{ key, quantity: 5 },
		{ items: [ line( key, 5 ) ] },
		{ items: [ line( key, 1 ) ] }
	);

	assert.strictEqual( change.quantity, 1 );
} );

test( 'an update that changes nothing, or names no line, reports nothing', () => {
	const key = newKey();
	const items = { items: [ line( key, 2 ) ] };

	assert.strictEqual(
		api.getUpdateItemChangeFromResponse(
			{ key, quantity: 2 },
			items,
			items
		),
		null
	);
	assert.strictEqual(
		api.getUpdateItemChangeFromResponse( { quantity: 2 }, items, items ),
		null
	);
	assert.strictEqual(
		api.getUpdateItemChangeFromResponse(
			{ key: newKey(), quantity: 2 },
			items,
			items
		),
		null
	);
} );

test( 'a batch reports each successful cart change in order', () => {
	const added = newKey();
	const updated = newKey();
	const before = { items: [ line( updated, 1, { id: 20 } ) ] };
	const afterAdd = {
		items: [ line( updated, 1, { id: 20 } ), line( added, 1 ) ],
	};
	const afterUpdate = {
		items: [ line( updated, 3, { id: 20 } ), line( added, 1 ) ],
	};

	const changes = api.getBatchCartChanges(
		JSON.stringify( {
			requests: [
				{ path: '/wc/store/v1/cart/add-item', body: { id: 12 } },
				{ path: '/wc/store/v1/cart/select-shipping-rate', body: {} },
				{
					path: '/wc/store/v1/cart/update-item',
					body: { key: updated, quantity: 3 },
				},
				{ path: '/wc/store/v1/cart/add-item', body: { id: 30 } },
			],
		} ),
		{
			responses: [
				{ status: 201, body: afterAdd },
				{ status: 200, body: afterAdd },
				{ status: 200, body: afterUpdate },
				// A failed sub-request is skipped even when its body holds a cart.
				{
					status: 400,
					body: {
						items: [ ...afterUpdate.items, line( newKey(), 1 ) ],
					},
				},
			],
		},
		before
	);

	assert.deepStrictEqual(
		changes.map( ( { eventName, product, quantity } ) => [
			eventName,
			product.key,
			quantity,
		] ),
		[
			[ 'add_to_cart', added, 1 ],
			[ 'add_to_cart', updated, 2 ],
		]
	);
} );

test( 'two updates of one line in a batch each compare with the line before them', () => {
	const key = newKey();
	// An earlier Store API response saw the line with 1 unit.
	api.rememberCartItems( { items: [ line( key, 1 ) ] } );
	const update = ( quantity ) => ( {
		path: '/wc/store/v1/cart/update-item',
		body: { key, quantity },
	} );

	const changes = api.getBatchCartChanges(
		{ requests: [ update( 3 ), update( 2 ) ] },
		{
			responses: [
				{ status: 200, body: { items: [ line( key, 3 ) ] } },
				{ status: 200, body: { items: [ line( key, 2 ) ] } },
			],
		},
		{ items: [ line( key, 1 ) ] }
	);

	assert.deepStrictEqual(
		changes.map( ( { eventName, quantity } ) => [ eventName, quantity ] ),
		[
			[ 'add_to_cart', 2 ],
			[ 'remove_from_cart', 1 ],
		]
	);
} );

test( 'a batch that cannot be read reports nothing', () => {
	assert.deepStrictEqual(
		api.getBatchCartChanges( 'not json', { responses: [] }, null ),
		[]
	);
	assert.deepStrictEqual(
		api.getBatchCartChanges( { requests: [] }, {}, null ),
		[]
	);
} );

test( 'a line total is scaled to the reported units and rounded', () => {
	const scaled = ( lineTotal, lineQuantity, quantity ) =>
		api.scaleLineTotalToQuantity(
			{ quantity: lineQuantity, totals: { line_total: lineTotal } },
			quantity
		).totals.line_total;

	assert.strictEqual( scaled( '1000', 3, 1 ), '333' );
	assert.strictEqual( scaled( '1000', 3, 2 ), '667' );
	assert.strictEqual( scaled( '1000', 1, 3 ), '3000' );
	assert.strictEqual( scaled( '1000', 3, 0 ), '0' );

	const emptyLine = { quantity: 0, totals: { line_total: '0' } };
	assert.strictEqual(
		api.scaleLineTotalToQuantity( emptyLine, 1 ),
		emptyLine
	);
} );

test( 'prices read from the page follow the store’s separators', () => {
	const cases = [
		[ '$1,234.56', {}, 1234.56 ],
		[
			'1.234,56 €',
			{ decimalSeparator: ',', thousandSeparator: '.' },
			1234.56,
		],
		[
			'1.234.567,89 €',
			{ decimalSeparator: ',', thousandSeparator: '.' },
			1234567.89,
		],
		[
			'1 234,56 €',
			{ decimalSeparator: ',', thousandSeparator: ' ' },
			1234.56,
		],
		[ '¥1,200', { decimalSeparator: '.', thousandSeparator: ',' }, 1200 ],
		// Currency symbols with a dot, before or after the amount.
		[ 'Bs.1,234.56', {}, 1234.56 ],
		[ 'د.إ 1,234.56', {}, 1234.56 ],
		[ '1,234.56 лв.', {}, 1234.56 ],
		[ '', {}, 0 ],
		[ 'Free', {}, 0 ],
	];

	for ( const [ text, currency, expected ] of cases ) {
		window.ga4w.settings.currency = currency;
		assert.strictEqual( parsePriceFromDOM( text ), expected, text );
	}
} );

test( 'prices read from the page use accounting.js when the page has it', () => {
	window.ga4w.settings.currency = { decimalSeparator: ',' };
	window.accounting = {
		unformat: ( text, decimal ) => `${ text } with ${ decimal }`,
	};

	assert.strictEqual( parsePriceFromDOM( 'Bs.9,99' ), '9,99 with ,' );
} );

test( 'prices read from the page are 0 without currency settings', () => {
	assert.strictEqual( parsePriceFromDOM( '$5.00' ), 0 );
} );
