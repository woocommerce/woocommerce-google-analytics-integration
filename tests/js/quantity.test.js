/**
 * Unit tests for how quantities are read and compared, including stores that
 * allow decimal quantities (0.5 kg) by filtering woocommerce_stock_amount.
 * Run with `pnpm run test:js`.
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

const { getUnitPrice, parseQuantity, roundQuantity } =
	loadSource( 'utils/index.js' );
const { getCartProductForQuantity, getCartQuantityChange, getCartRowQuantity } =
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

test( 'a cart line unit price divides the line total by a decimal quantity', () => {
	for ( const [ quantity, lineTotal ] of [
		[ 1.5, '1500' ],
		[ 0.5, '500' ],
		[ 2, '2000' ],
	] ) {
		assert.strictEqual(
			getUnitPrice( {
				quantity,
				prices: { price: 999 },
				totals: { line_total: lineTotal },
			} ),
			1000,
			String( quantity )
		);
	}
	assert.strictEqual(
		getUnitPrice( { quantity: 2, prices: { price: '1000' } } ),
		1000
	);
} );

test( 'a classic cart line moved to a new quantity keeps its unit price', () => {
	const line = {
		quantity: 3,
		prices: { price: 1600 },
		totals: { line_total: '4500' },
	};

	assert.deepStrictEqual( getCartProductForQuantity( line, 1 ), {
		quantity: 1,
		prices: { price: 1500 },
		totals: undefined,
	} );

	const odd = { quantity: 1, totals: { line_total: '899' } };
	for ( const quantity of [ 0.5, 0.25, 3 ] ) {
		assert.strictEqual(
			getUnitPrice( getCartProductForQuantity( odd, quantity ) ),
			899,
			String( quantity )
		);
	}
	assert.strictEqual(
		getUnitPrice(
			getCartProductForQuantity(
				{ quantity: 2, totals: { line_total: '0' } },
				1
			)
		),
		0
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
