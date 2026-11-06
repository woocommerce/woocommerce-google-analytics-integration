/**
 * Lets the Node test runner load the plugin's JS sources from assets/js/src.
 *
 * The sources are ES modules written for webpack, which resolves imports
 * without a file extension (`../utils`). Node does not, so this resolve hook
 * tries `<specifier>.js` and `<specifier>/index.js` for relative imports.
 */

/**
 * External dependencies
 */
const { registerHooks } = require( 'node:module' );
const path = require( 'path' );

registerHooks( {
	resolve( specifier, context, nextResolve ) {
		try {
			return nextResolve( specifier, context );
		} catch ( error ) {
			if ( ! specifier.startsWith( '.' ) ) {
				throw error;
			}
			for ( const candidate of [ '.js', '/index.js' ] ) {
				try {
					return nextResolve( specifier + candidate, context );
				} catch {}
			}
			throw error;
		}
	},
	// Tell Node the sources are ES modules, which webpack knows from their syntax.
	load( url, context, nextLoad ) {
		return nextLoad(
			url,
			url.includes( '/assets/js/src/' )
				? { ...context, format: 'module' }
				: context
		);
	},
} );

const SRC = path.join( __dirname, '..', '..', '..', 'assets', 'js', 'src' );

/**
 * Loads a module from assets/js/src.
 *
 * @param {string} file Path relative to assets/js/src, such as `utils/index.js`.
 * @return {Object} The module's exports.
 */
module.exports = ( file ) => require( path.join( SRC, file ) );
