module.exports = {
	extends: [
		'plugin:@wordpress/eslint-plugin/recommended',
		'plugin:import/recommended',
	],
	settings: {
		jsdoc: {
			mode: 'typescript',
		},
		'import/core-modules': [ 'webpack' ],
	},
	globals: {
		jQuery: 'readonly',
	},
};
