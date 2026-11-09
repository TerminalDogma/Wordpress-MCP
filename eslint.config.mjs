import js from '@eslint/js';
import globals from 'globals';

export default [
	{ ignores: [ 'vendor/**', 'node_modules/**', 'dist/**' ] },
	js.configs.recommended,
	{
		files: [ '**/*.js' ],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'script',
			globals: {
				...globals.browser,
				// WordPress admin and the objects the plugin prints with wp_localize_script().
				wp: 'readonly',
				ajaxurl: 'readonly',
				mscScoreImport: 'readonly',
				mscTrailers: 'readonly',
				acf: 'readonly',
			},
		},
		rules: {
			// Empty catch blocks and unused catch variables are deliberate "ignore this error" cases.
			'no-empty': [ 'error', { allowEmptyCatch: true } ],
			'no-unused-vars': [ 'error', { caughtErrors: 'none' } ],
		},
	},
	{
		files: [ 'eslint.config.mjs' ],
		languageOptions: { sourceType: 'module' },
	},
];
