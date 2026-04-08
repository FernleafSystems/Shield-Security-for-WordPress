const js = require( '@eslint/js' );

module.exports = [
	{
		files: [
			'assets/js/components/tables/ActivityLogMetaPopover.js',
			'assets/js/components/tables/InvestigationTable.js',
			'assets/js/components/tables/ShieldTableActivityLog.js',
			'assets/js/components/tables/ShieldTableBase.js',
			'assets/js/components/mode/StepTabsController.js',
			'assets/js/components/mode/ActionsQueueLandingController.js',
		],
		languageOptions: {
			ecmaVersion: 'latest',
			sourceType: 'module',
			globals: {
				window: 'readonly',
				document: 'readonly',
				Element: 'readonly',
				HTMLElement: 'readonly',
				HTMLButtonElement: 'readonly',
				HTMLInputElement: 'readonly',
				HTMLFormElement: 'readonly',
				CustomEvent: 'readonly',
				WeakSet: 'readonly',
				setTimeout: 'readonly',
				clearTimeout: 'readonly',
				alert: 'readonly',
				console: 'readonly',
				shieldAppMain: 'readonly',
				shieldEventsHandler_Main: 'readonly',
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
