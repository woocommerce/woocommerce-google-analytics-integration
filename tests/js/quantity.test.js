/**
 * Unit tests for how quantities are read and compared, including stores that
 * allow decimal quantities (0.5 kg) by filtering woocommerce_stock_amount.
 * Run with `npm run test:js`.
 */

/**
 * External dependencies
 */
const { test } = require( 'node:test' );
const assert = require( 'node:assert' );

/**
 * Internal dependencies
 */
const loadSource = require( './helpers/load-source' );

const { parseQuantity, roundQuantity } = loadSource( 'utils/index.js' );
const { getCartItemUnitPrice, getCartQuantityChange, getCartRowQuantity } =
	loadSource( 'integrations/classic.js' );

/**
 * A stand-in for a classic cart row whose `input.qty` holds the given value.
 *
 * @param {string|undefined} value Input value, or undefined for a row with no input.
 * @return {Object} The row.
 */
const cartRow = ( value ) => ( {
	querySelector: ( selector ) =>
		selector === 'input.qty' && value !== undefined ? { value } : null,
} );

test( 'a quantity keeps its decimals and falls back when it is not above zero', () => {
	for ( const [ value, expected ] of [
		[ '3', 3 ],
		[ 3, 3 ],
		[ '1.5', 1.5 ],
		[ 0.25, 0.25 ],
		[ 0, null ],
		[ -2, null ],
		[ '', null ],
		[ 'abc', null ],
		[ undefined, null ],
		[ NaN, null ],
	] ) {
		assert.strictEqual( parseQuantity( value ), expected, String( value ) );
	}
	assert.strictEqual( parseQuantity( 'abc', 1 ), 1 );
} );

test( 'the classic cart unit price divides the line price by a decimal quantity', () => {
	assert.strictEqual(
		getCartItemUnitPrice( { quantity: 1.5, prices: { price: 1500 } } ),
		1000
	);
	assert.strictEqual(
		getCartItemUnitPrice( { quantity: 0.5, prices: { price: 500 } } ),
		1000
	);
	assert.strictEqual(
		getCartItemUnitPrice( { quantity: 2, prices: { price: 2000 } } ),
		1000
	);
	assert.strictEqual(
		getCartItemUnitPrice( { quantity: 0, prices: { price: 0 } } ),
		null
	);
} );

test( 'a classic cart quantity change reports the decimal difference', () => {
	assert.deepStrictEqual( getCartQuantityChange( 2.5, 2 ), {
		eventName: 'remove_from_cart',
		quantity: 0.5,
	} );
	assert.deepStrictEqual( getCartQuantityChange( 1.5, 2.5 ), {
		eventName: 'add_to_cart',
		quantity: 1,
	} );
	assert.deepStrictEqual( getCartQuantityChange( 3, 2 ), {
		eventName: 'remove_from_cart',
		quantity: 1,
	} );
	assert.strictEqual( getCartQuantityChange( 2, 2 ), null );
	assert.strictEqual( getCartQuantityChange( 'abc', 2 ), null );
} );

test( 'a classic cart row quantity keeps its decimals and allows 0', () => {
	assert.strictEqual( getCartRowQuantity( cartRow( '1.5' ) ), 1.5 );
	assert.strictEqual( getCartRowQuantity( cartRow( '3' ) ), 3 );
	assert.strictEqual( getCartRowQuantity( cartRow( '0' ) ), 0 );
	assert.ok( Number.isNaN( getCartRowQuantity( cartRow( undefined ) ) ) );
} );

test( 'quantity maths is rounded to six decimal places', () => {
	assert.strictEqual( roundQuantity( 0.1 + 0.2 ), 0.3 );
	assert.strictEqual( roundQuantity( 2 ), 2 );
	assert.strictEqual( roundQuantity( 0.000001 ), 0.000001 );
	assert.strictEqual( roundQuantity( 0.0000001 ), 0 );
} );
