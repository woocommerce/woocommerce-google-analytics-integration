import wordpress from '@wordpress/eslint-plugin';

export default [
	{
		ignores: [
			'build/**',
			'node_modules/**',
			'vendor/**',
			'assets/js/build/**',
			'tests/e2e/test-results/**',
		],
	},
	...wordpress.configs.recommended,
	{
		settings: {
			jsdoc: {
				mode: 'typescript',
			},
			'import/core-modules': [ 'webpack' ],
		},
		languageOptions: {
			globals: {
				jQuery: 'readonly',
			},
		},
	},
];
