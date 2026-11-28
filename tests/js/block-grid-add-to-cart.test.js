/**
 * Unit tests for add_to_cart from the "Add to cart" button of a block product
 * grid. The button adds through the Store API, so the add is reported by the
 * Store API interceptor once the server answers, not by the click listener on
 * the product card. Run with `pnpm run test:js`.
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
let subResponseStatus = 201;

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
				responses: [
					{ status: subResponseStatus, body: cartAfterAdd },
				],
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
 * @param {Object[]} products               The products of the grid.
 * @param {Object[]} [productData=products] The products the page has data for.
 * @return {function(Object): void} Clicks the button of a product.
 */
const showGrid = ( products, productData = products ) => {
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
	// The cart from page load, which the page never updates: a later add
	// compares against the last cart the Store API returned instead.
	window.ga4w.data = { products: productData, cart: { items: [] } };
	classicTracking( getEventHandler, { products: productData } );

	return ( product ) => clicks.get( product.id )();
};

/**
 * The server answers the Store API batch that a button sent for its product.
 *
 * @param {Object} product  The added product.
 * @param {number} quantity The quantity of the product in the cart after the add.
 */
const serverAnswers = async ( product, quantity = 1 ) => {
	subResponseStatus = 201;
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

/**
 * The server refuses the add (for example a second add of a "Sold individually"
 * product): the batch succeeds, its add-item sub-request does not.
 *
 * @param {Object} product The product the button tried to add.
 */
const serverRejects = async ( product ) => {
	subResponseStatus = 400;
	cartAfterAdd = { code: 'woocommerce_rest_cart_product_sold_individually' };

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

// The item of each add_to_cart event, or null for an event with no item.
const addToCartItems = () =>
	sent
		.filter( ( [ , eventName ] ) => eventName === 'add_to_cart' )
		.map( ( [ , , data ] ) => data.items[ 0 ] ?? null );

// The event of the interceptor, from the cart line in the server's answer,
// with the category from the product data of the page.
const fromStoreApi = ( product ) => ( {
	item_id: product.id,
	item_name: product.name,
	item_category: 'Clothing',
	quantity: 1,
	price: 18,
	item_variant: '',
} );

// The same event when the page has no data for the product: no category.
const fromStoreApiWithoutPageData = ( product ) => {
	const { item_category: itemCategory, ...item } = fromStoreApi( product );

	return item;
};

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
	test( `one click on the button sends add_to_cart once, when a ${ server } server answers`, async ( t ) => {
		mockTime( t );
		const product = newProduct();
		const click = showGrid( [ product ] );

		click( product );
		assert.deepStrictEqual( addToCartItems(), [] );

		wait( t, responseTime );
		await serverAnswers( product );
		wait( t, 1000 );

		assert.deepStrictEqual( addToCartItems(), [ fromStoreApi( product ) ] );
	} );
}

test( 'two adds of the same product send add_to_cart twice, each for one unit at the unit price', async ( t ) => {
	mockTime( t );
	const product = newProduct();
	const click = showGrid( [ product ] );

	click( product );
	await serverAnswers( product, 1 );
	wait( t, 1000 );
	click( product );
	await serverAnswers( product, 2 );
	wait( t, 1000 );

	assert.deepStrictEqual( addToCartItems(), [
		fromStoreApi( product ),
		fromStoreApi( product ),
	] );
} );

test( 'adds of two products each send their own add_to_cart', async ( t ) => {
	mockTime( t );
	const [ first, second ] = [ newProduct(), newProduct() ];
	const click = showGrid( [ first, second ] );

	click( first );
	click( second );
	await serverAnswers( first );
	await serverAnswers( second );
	wait( t, 1000 );

	assert.deepStrictEqual( addToCartItems(), [
		fromStoreApi( first ),
		fromStoreApi( second ),
	] );
} );

test( 'an add the server rejects sends no add_to_cart', async ( t ) => {
	mockTime( t );
	const product = newProduct();
	const click = showGrid( [ product ] );

	click( product );
	await serverRejects( product );
	wait( t, 1000 );

	assert.deepStrictEqual( addToCartItems(), [] );
} );

test( 'an add of a product the page has no data for is sent with no category', async ( t ) => {
	mockTime( t );
	const product = newProduct();
	const click = showGrid( [ product ], [] );

	click( product );
	await serverAnswers( product );
	wait( t, 1000 );

	assert.deepStrictEqual( addToCartItems(), [
		fromStoreApiWithoutPageData( product ),
	] );
} );
