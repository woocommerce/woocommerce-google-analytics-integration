const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

const webpackConfig = {
	...defaultConfig,
	entry: {
		main: path.resolve( process.cwd(), 'assets/js/src', 'index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( process.cwd(), 'assets/js/build' ),
	},
};

module.exports = webpackConfig;
