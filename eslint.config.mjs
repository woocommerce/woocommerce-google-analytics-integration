import wordpress from '@wordpress/eslint-plugin';

export default [
	{
		ignores: [
			'build/**',
			'node_modules/**',
			'vendor/**',
			'assets/js/build/**',
			'tests/e2e/test-results/**',
			// Written by the CI annotation step before linting.
			'eslintFormatter.cjs',
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
