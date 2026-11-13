/**
 * Unit tests for add_to_cart from the "Add to cart" button of a block product
 * grid. Two parts of the plugin see that add: the click listener on the
 * product card, and the Store API interceptor once the server answers.
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

const ORIGIN = 'https://shop.test';
const sent = [];
let cartAfterAdd;

// blocks.js wraps window.fetch once, the first time blocksTracking() runs, so
// the page and the server behind it are set up once for the whole file.
globalThis.window = {
	location: { origin: ORIGIN },
	ga4w: { settings: {} },
	gtag: ( ...args ) => sent.push( args ),
	// The server: answers a Store API batch with the cart after its add-item.
	fetch: async () => ( {
		ok: true,
		clone: () => ( {
			json: async () => ( {
				responses: [ { status: 201, body: cartAfterAdd } ],
			} ),
		} ),
	} ),
};
globalThis.document = {
	body: { addEventListener() {}, removeEventListener() {} },
	querySelector: () => null,
	querySelectorAll: () => [],
};

const { forgetCartItems } = loadSource( 'utils/store-api.js' );
const { setupEventHandlers } = loadSource( 'tracker/index.js' );
const { classicTracking } = loadSource( 'integrations/classic.js' );
const { blocksTracking } = loadSource( 'integrations/blocks.js' );

const getEventHandler = setupEventHandlers( {
	events: [ 'add_to_cart' ],
	tracker_function_name: 'gtag',
} );
blocksTracking( getEventHandler );

beforeEach( () => {
	sent.length = 0;
	forgetCartItems();
} );

// What the plugin remembers about an add lasts for the page, so each test adds its own product.
let productCount = 0;
const newProduct = () => {
	const id = 100 + ++productCount;

	return {
		id,
		name: `Product ${ id }`,
		categories: [ { name: 'Clothing' } ],
		prices: { price: 1800, currency_minor_unit: 2 },
	};
};

/**
 * Shows products in a block product grid, each with an "Add to cart" button.
 *
 * @param {Object[]} products The products of the page.
 * @return {function(Object): void} Clicks the button of a product.
 */
const showGrid = ( products ) => {
	const clicks = new Map();
	const cards = products.map( ( product ) => {
		const button = {
			getAttribute: () => String( product.id ),
			classList: { contains: ( name ) => name === 'add_to_cart_button' },
		};
		const target = {
			closest: ( selector ) =>
				selector.includes( 'product-button' ) ? button : null,
		};

		return {
			querySelector: () => button,
			closest: () => null,
			addEventListener: ( name, listener ) =>
				clicks.set( product.id, () => listener( { target } ) ),
		};
	} );

	document.querySelectorAll = ( selector ) =>
		selector.includes( '.wc-block-product-template .product' ) ? cards : [];
	classicTracking( getEventHandler, { products } );

	return ( product ) => clicks.get( product.id )();
};

/**
 * The server answers the Store API batch that a button sent for its product.
 *
 * @param {Object} product  The added product.
 * @param {number} quantity The quantity of the product in the cart after the add.
 */
const serverAnswers = async ( product, quantity = 1 ) => {
	cartAfterAdd = {
		items: [
			{
				key: `line-${ product.id }`,
				id: product.id,
				name: product.name,
				quantity,
				variation: [],
				prices: product.prices,
				totals: { line_total: String( 1800 * quantity ) },
			},
		],
	};

	await window.fetch( `${ ORIGIN }/wp-json/wc/store/v1/batch`, {
		method: 'POST',
		body: JSON.stringify( {
			requests: [
				{
					path: '/wc/store/v1/cart/add-item',
					method: 'POST',
					body: { id: product.id, quantity: 1 },
				},
			],
		} ),
	} );
};

const addToCartItems = () =>
	sent
		.filter( ( [ , eventName ] ) => eventName === 'add_to_cart' )
		.map( ( [ , , data ] ) => data.items[ 0 ] );

// The event of the click listener, from the product data of the page.
const fromClick = ( product ) => ( {
	item_id: product.id,
	item_name: product.name,
	item_category: 'Clothing',
	quantity: 1,
	price: 18,
} );

// The event of the interceptor, from the cart line in the server's answer.
const fromStoreApi = ( product ) => ( {
	item_id: product.id,
	item_name: product.name,
	quantity: 1,
	price: 18,
	item_variant: '',
} );

const mockTime = ( t ) =>
	t.mock.timers.enable( { apis: [ 'setTimeout', 'Date' ] } );

// Moves the clock in small steps, so a timer that is set during the wait fires at its own time.
const wait = ( t, milliseconds ) => {
	for ( let elapsed = 0; elapsed < milliseconds; elapsed += 5 ) {
		t.mock.timers.tick( 5 );
	}
};

for ( const [ server, responseTime ] of [
	[ 'fast', 50 ],
	[ 'slow', 5000 ],
] ) {
	test( `one click on the button sends add_to_cart once, with a ${ server } server`, async ( t ) => {
		mockTime( t );
		const product = newProduct();
		const click = showGrid( [ product ] );

		click( product );
		assert.deepStrictEqual( addToCartItems(), [ fromClick( product ) ] );

		wait( t, responseTime );
		await serverAnswers( product );
		wait( t, 1000 );

		assert.deepStrictEqual( addToCartItems(), [ fromClick( product ) ] );
	} );
}

test( 'two clicks on the button send add_to_cart twice', async ( t ) => {
	mockTime( t );
	const product = newProduct();
	const click = showGrid( [ product ] );

	click( product );
	click( product );
	await serverAnswers( product, 1 );
	await serverAnswers( product, 2 );
	wait( t, 1000 );

	assert.deepStrictEqual( addToCartItems(), [
		fromClick( product ),
		fromClick( product ),
	] );
} );

test( 'a click on one button does not hide the add of another product', async ( t ) => {
	mockTime( t );
	const [ clicked, other ] = [ newProduct(), newProduct() ];
	const click = showGrid( [ clicked, other ] );

	click( clicked );
	await serverAnswers( other );
	wait( t, 1000 );

	assert.deepStrictEqual( addToCartItems(), [
		fromClick( clicked ),
		fromStoreApi( other ),
	] );
} );

test( 'an add with no click is sent when the server answers', async ( t ) => {
	mockTime( t );
	const product = newProduct();
	showGrid( [ product ] );

	await serverAnswers( product );
	wait( t, 1000 );

	assert.deepStrictEqual( addToCartItems(), [ fromStoreApi( product ) ] );
} );

test( 'a click covers one add: a later add of the product with no click is sent', async ( t ) => {
	mockTime( t );
	const product = newProduct();
	const click = showGrid( [ product ] );

	click( product );
	await serverAnswers( product, 1 );
	wait( t, 1000 );
	await serverAnswers( product, 2 );
	wait( t, 1000 );

	assert.deepStrictEqual( addToCartItems(), [
		fromClick( product ),
		fromStoreApi( product ),
	] );
} );

test( 'a click that the server never answered stops covering adds after a minute', async ( t ) => {
	mockTime( t );
	const product = newProduct();
	const click = showGrid( [ product ] );

	click( product );
	wait( t, 60000 );
	await serverAnswers( product );
	wait( t, 1000 );

	assert.deepStrictEqual( addToCartItems(), [
		fromClick( product ),
		fromStoreApi( product ),
	] );
} );
