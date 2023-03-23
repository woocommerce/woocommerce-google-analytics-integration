const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

const webpackConfig = {
	...defaultConfig,
	entry: {
		actions: path.resolve( process.cwd(), 'assets/js/src', 'actions.js' ),
		'admin-ga-settings': path.resolve(
			process.cwd(),
			'assets/js/src',
			'admin-ga-settings.js'
		),
		'ga-integration': path.resolve(
			process.cwd(),
			'assets/js/src',
			'ga-integration.js'
		),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( process.cwd(), 'assets/js/build' ),
	},
};

module.exports = webpackConfig;
