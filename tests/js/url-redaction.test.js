/**
 * Unit tests for the URL redaction helper the gtag snippet installs.
 *
 * The helper ships as JavaScript embedded in a PHP string, so the test reads it
 * straight out of the source file and runs it against a stubbed `document`.
 * Run with `npm run test:js`.
 */

/**
 * External dependencies
 */
const { test } = require( 'node:test' );
const assert = require( 'node:assert' );
const fs = require( 'fs' );
const path = require( 'path' );

const SOURCE = path.join(
	__dirname,
	'..',
	'..',
	'includes',
	'class-wc-google-gtag-js.php'
);
const FACTORY_START = 'window.wcGoogleAnalyticsIntegration.redactGtagConfig = ';
const FACTORY_END = '( %1$s );';

/**
 * The `(function ( redaction ) { … })` factory, read from the PHP source.
 *
 * @return {string} The factory source.
 */
function readFactorySource() {
	const php = fs.readFileSync( SOURCE, 'utf8' );
	const start = php.indexOf( FACTORY_START );
	const end = php.indexOf( FACTORY_END, start );

	if ( start === -1 || end === -1 ) {
		throw new Error(
			`Could not find the redaction helper in ${ SOURCE }. If it was renamed or moved, update FACTORY_START/FACTORY_END here.`
		);
	}

	return php.slice( start + FACTORY_START.length, end );
}

const factorySource = readFactorySource();

const DEFAULT_REDACTION = {
	params: [ 'key', 'login', 'session', 'redirect' ],
	order_endpoints: [ 'order-received', 'order-pay' ],
	order_params: [
		'order-received',
		'order-pay',
		'page_id',
		'pay_for_order',
		'utm_source',
		'_gl',
	],
};

/**
 * Build the helper with a stubbed browser environment.
 *
 * @param {Object}   options           Test options.
 * @param {string}   options.href      The page URL the stubbed document reports.
 * @param {string}   options.referrer  The referrer the stubbed document reports.
 * @param {Object}   options.redaction The redaction lists passed to the factory.
 * @param {Function} options.urlImpl   The `URL` implementation the helper sees.
 *
 * @return {Function} The `redactGtagConfig( config )` helper.
 */
function createHelper( {
	href = 'http://example.test/shop/',
	referrer = '',
	redaction = DEFAULT_REDACTION,
	urlImpl = URL,
} = {} ) {
	const documentStub = { location: { href }, referrer };

	const factory = new Function(
		'document',
		'URL',
		`return ${ factorySource };`
	);

	return factory( documentStub, urlImpl )( redaction );
}

/**
 * Run the helper against a URL and return the resulting page_location.
 *
 * @param {string} href      The page URL.
 * @param {Object} redaction Optional redaction lists.
 *
 * @return {string|undefined} The page_location the helper set, if any.
 */
function locationFor( href, redaction ) {
	return createHelper( { href, redaction } )( {} ).page_location;
}

test( 'strips denylisted parameters and keeps the rest', () => {
	assert.strictEqual(
		locationFor(
			'http://example.test/shop/?utm_source=e2e&key=wc_order_abc&login=bob&foo=bar'
		),
		'http://example.test/shop/?utm_source=e2e&foo=bar'
	);
} );

test( 'strips an order key by value under any parameter name', () => {
	assert.strictEqual(
		locationFor( 'http://example.test/shop/?order=wc_order_abc&foo=1' ),
		'http://example.test/shop/?foo=1'
	);
} );

test( 'strips an order key whose format was filtered away from wc_order_', () => {
	assert.strictEqual(
		locationFor( 'http://example.test/shop/?order=wc_abc123&foo=1' ),
		'http://example.test/shop/?foo=1'
	);
} );

test( 'strips an order key nested in another URL', () => {
	const nested = encodeURIComponent(
		'http://example.test/checkout/order-received/5/?key=wc_order_abc'
	);

	assert.strictEqual(
		locationFor( `http://example.test/shop/?return_url=${ nested }&foo=1` ),
		'http://example.test/shop/?foo=1'
	);
} );

test( 'matches parameter names case-insensitively and keeps the original case of the rest', () => {
	assert.strictEqual(
		locationFor( 'http://example.test/shop/?KEY=1&Login=2&Foo=Bar' ),
		'http://example.test/shop/?Foo=Bar'
	);
} );

test( 'keeps only allowlisted parameters on an order-received path', () => {
	assert.strictEqual(
		locationFor(
			'http://example.test/checkout/order-received/5/?key=wc_order_a&utm_source=e2e&_gl=1x&pay_for_order=true&foo=bar'
		),
		'http://example.test/checkout/order-received/5/?utm_source=e2e&_gl=1x&pay_for_order=true'
	);
} );

test( 'detects an order page from the query string on plain permalinks', () => {
	assert.strictEqual(
		locationFor(
			'http://example.test/?page_id=8&order-received=5&key=wc_order_a&foo=bar'
		),
		'http://example.test/?page_id=8&order-received=5'
	);
} );

test( 'follows a custom endpoint slug', () => {
	const redaction = {
		...DEFAULT_REDACTION,
		order_endpoints: [ 'thank-you', 'order-pay' ],
		order_params: [ 'thank-you', 'utm_source' ],
	};

	assert.strictEqual(
		locationFor(
			'http://example.test/checkout/thank-you/5/?key=wc_order_a&foo=bar&utm_source=e2e',
			redaction
		),
		'http://example.test/checkout/thank-you/5/?utm_source=e2e'
	);
} );

test( 'keeps the encoding of the parameters it does not strip', () => {
	assert.strictEqual(
		locationFor( 'http://example.test/shop/?color=red%20blue&q=a~b&key=1' ),
		'http://example.test/shop/?color=red%20blue&q=a~b'
	);
} );

test( 'keeps repeated parameters', () => {
	assert.strictEqual(
		locationFor( 'http://example.test/shop/?a=1&a=2&key=1' ),
		'http://example.test/shop/?a=1&a=2'
	);
} );

test( 'preserves the fragment', () => {
	assert.strictEqual(
		locationFor( 'http://example.test/shop/?key=1&a=b#reviews' ),
		'http://example.test/shop/?a=b#reviews'
	);
} );

test( 'leaves no empty query string behind', () => {
	assert.strictEqual(
		locationFor( 'http://example.test/shop/?key=wc_order_a' ),
		'http://example.test/shop/'
	);
} );

test( 'sets nothing when there is nothing to strip', () => {
	const config = createHelper( {
		href: 'http://example.test/shop/?color=red%20blue&utm_source=e2e',
	} )( {} );

	assert.deepStrictEqual( config, {} );
} );

test( 'redacts the referrer independently of the page URL', () => {
	const config = createHelper( {
		href: 'http://example.test/shop/',
		referrer:
			'http://example.test/checkout/order-received/5/?key=wc_order_a&utm_source=e2e',
	} )( {} );

	assert.deepStrictEqual( config, {
		page_referrer:
			'http://example.test/checkout/order-received/5/?utm_source=e2e',
	} );
} );

test( 'leaves a page_location supplied by the merchant untouched', () => {
	const config = createHelper( {
		href: 'http://example.test/shop/?key=wc_order_a',
	} )( { page_location: 'http://example.test/custom/' } );

	assert.strictEqual( config.page_location, 'http://example.test/custom/' );
} );

test( 'does nothing when redaction is disabled', () => {
	const config = createHelper( {
		href: 'http://example.test/shop/?key=wc_order_a',
		redaction: [],
	} )( { track_404: true } );

	assert.deepStrictEqual( config, { track_404: true } );
} );

test( 'returns a config it cannot inspect unchanged', () => {
	assert.strictEqual(
		createHelper( { href: 'http://example.test/shop/?key=1' } )( null ),
		null
	);
} );

test( 'falls back to the path when the URL cannot be parsed', () => {
	const config = createHelper( {
		href: 'http://example.test/shop/?key=wc_order_a#x',
		urlImpl: function BrokenUrl() {
			throw new Error( 'unparsable' );
		},
	} )( {} );

	assert.strictEqual( config.page_location, 'http://example.test/shop/' );
} );
