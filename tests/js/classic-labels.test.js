/**
 * Unit tests for the labels the classic checkout sends as shipping_tier and
 * payment_type, read from the checkout form.
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

const {
	classicTracking,
	getCheckoutOptionLabel,
	getSelectedCheckoutOption,
	getShippingTierLabel,
} = loadSource( 'integrations/classic.js' );

/**
 * A stand-in for a <label>: text parts, where a price part sits in a
 * `.woocommerce-Price-amount` element and a tax part in a `.tax_label` one.
 *
 * @param {...(string|{price: string}|{taxLabel: string})} parts Label text parts.
 * @return {Object} The label.
 */
const label = ( ...parts ) => {
	const nodes = parts.map( ( part ) => ( {
		text: part.price ?? part.taxLabel ?? part,
		className:
			( part.price !== undefined && '.woocommerce-Price-amount' ) ||
			( part.taxLabel !== undefined && '.tax_label' ),
		removed: false,
		remove() {
			this.removed = true;
		},
	} ) );

	return {
		get textContent() {
			return nodes
				.filter( ( { removed } ) => ! removed )
				.map( ( { text } ) => text )
				.join( '' );
		},
		querySelectorAll( selector ) {
			return nodes.filter(
				( { className } ) =>
					className && selector.split( ', ' ).includes( className )
			);
		},
		cloneNode: () => label( ...parts ),
	};
};

// Labels on the page that point at an input with `for`.
let labelsFor = {};

/**
 * A stand-in for a checkout <input>.
 *
 * @param {Object} fields Input fields: type, value, checked, id, labels, closest.
 * @return {Object} The input.
 */
const input = ( fields ) => ( {
	tagName: 'INPUT',
	type: 'radio',
	checked: false,
	labels: [],
	closest: () => null,
	...fields,
} );

beforeEach( () => {
	labelsFor = {};
	globalThis.window = { CSS: { escape: ( value ) => value } };
	globalThis.document = {
		inputs: [],
		querySelectorAll() {
			return this.inputs;
		},
		querySelector( selector ) {
			const id = selector.match( /^label\[for="(.+)"\]$/ )?.[ 1 ];
			return labelsFor[ id ] ?? null;
		},
	};
} );

test( 'a radio is labelled by its label, trimmed, or by its value without one', () => {
	assert.strictEqual(
		getCheckoutOptionLabel(
			input( {
				value: 'cod',
				labels: [ label( '  Cash on delivery ' ) ],
			} )
		),
		'Cash on delivery'
	);
	assert.strictEqual(
		getCheckoutOptionLabel(
			input( { value: 'cod', closest: () => label( 'Wrapping label' ) } )
		),
		'Wrapping label'
	);
	assert.strictEqual(
		getCheckoutOptionLabel( input( { value: 'cod', labels: null } ) ),
		'cod'
	);
	assert.strictEqual( getCheckoutOptionLabel( undefined ), undefined );
} );

test( 'a select is labelled by its selected option, or by its value', () => {
	const select = ( text ) => ( {
		tagName: 'SELECT',
		value: 'flat_rate:1',
		selectedIndex: 0,
		options: [ { text } ],
	} );

	assert.strictEqual(
		getCheckoutOptionLabel( select( ' Flat rate ' ) ),
		'Flat rate'
	);
	assert.strictEqual( getCheckoutOptionLabel( select( '' ) ), 'flat_rate:1' );
} );

test( 'the shipping tier is the label without its price', () => {
	const flatRate = label( 'Flat rate: ', { price: '$4.00' } );

	assert.strictEqual(
		getShippingTierLabel( input( { labels: [ flatRate ] } ) ),
		'Flat rate'
	);
	assert.strictEqual(
		flatRate.textContent,
		'Flat rate: $4.00',
		'the label on the page keeps its price'
	);
	assert.strictEqual(
		getShippingTierLabel(
			input( { labels: [ label( 'Free shipping' ) ] } )
		),
		'Free shipping'
	);
	assert.strictEqual(
		getShippingTierLabel(
			input( {
				value: 'flat_rate:1',
				labels: [ label( { price: '' } ) ],
			} )
		),
		'flat_rate:1'
	);
	assert.strictEqual( getShippingTierLabel( undefined ), undefined );
} );

test( 'the shipping tier leaves out the tax label after the price', () => {
	assert.strictEqual(
		getShippingTierLabel(
			input( {
				labels: [
					label( 'Flat rate: ', { price: '$4.00' }, ' ', {
						taxLabel: '(incl. VAT)',
					} ),
				],
			} )
		),
		'Flat rate'
	);
} );

test( 'placing an order with no payment method, a free order, sends no add_payment_info', () => {
	let submit;
	const sent = [];
	document.body = { addEventListener() {} };
	document.addEventListener = () => {};
	document.querySelector = ( selector ) =>
		selector === 'form.checkout'
			? {
					addEventListener: ( name, callback ) => {
						submit = callback;
					},
				}
			: null;
	window.document = document;

	classicTracking(
		( eventName ) => ( data ) => sent.push( [ eventName, data ] ),
		{ events: [], cart: { items: [ {} ] } }
	);
	submit();

	assert.deepStrictEqual(
		sent.map( ( [ eventName ] ) => eventName ),
		[]
	);
} );

test( 'the only shipping method, a hidden input, is labelled by its label for it', () => {
	labelsFor.shipping_method_0_flat_rate1 = label( 'Flat rate: ', {
		price: '$10.00',
	} );
	const hidden = input( {
		type: 'hidden',
		id: 'shipping_method_0_flat_rate1',
		value: 'flat_rate:1',
		labels: null,
	} );

	assert.strictEqual( getShippingTierLabel( hidden ), 'Flat rate' );
	assert.strictEqual( getCheckoutOptionLabel( hidden ), 'Flat rate: $10.00' );
} );

test( 'the selected option is the checked radio, a select, or the only hidden input', () => {
	const checked = input( { value: 'b', checked: true } );
	document.inputs = [ input( { value: 'a' } ), checked ];
	assert.strictEqual( getSelectedCheckoutOption( 'any' ), checked );

	const select = { tagName: 'SELECT' };
	document.inputs = [ select ];
	assert.strictEqual( getSelectedCheckoutOption( 'any' ), select );

	const hidden = input( { type: 'hidden', value: 'flat_rate:1' } );
	document.inputs = [ hidden ];
	assert.strictEqual( getSelectedCheckoutOption( 'any' ), hidden );

	document.inputs = [ input( { value: 'a' } ) ];
	assert.strictEqual( getSelectedCheckoutOption( 'any' ), undefined );
} );
