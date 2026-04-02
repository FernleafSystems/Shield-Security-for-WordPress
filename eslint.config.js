const js = require( '@eslint/js' );

module.exports = [
	{
		files: [
			'assets/js/components/tables/InvestigationTable.js',
			'assets/js/components/tables/ShieldTableBase.js',
		],
		languageOptions: {
			ecmaVersion: 'latest',
			sourceType: 'module',
			globals: {
				window: 'readonly',
				document: 'readonly',
				Element: 'readonly',
				HTMLElement: 'readonly',
				CustomEvent: 'readonly',
				WeakSet: 'readonly',
				setTimeout: 'readonly',
				clearTimeout: 'readonly',
				alert: 'readonly',
				console: 'readonly',
				shieldServices: 'readonly',
				ajaxurl: 'readonly',
			},
		},
		rules: {
			...js.configs.recommended.rules,
			'no-unused-vars': [ 'error', { args: 'none' } ],
		},
	},
];
