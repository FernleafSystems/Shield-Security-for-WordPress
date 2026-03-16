const { defineConfig } = require( '@playwright/test' );

const port = Number.parseInt( process.env.SHIELD_PLAYGROUND_PORT || '9400', 10 );

module.exports = defineConfig( {
	testDir: './tests/browser',
	timeout: 60_000,
	expect: {
		timeout: 10_000,
	},
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: process.env.CI ? 1 : undefined,
	reporter: process.env.CI ? [ [ 'github' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		baseURL: `http://127.0.0.1:${port}`,
		headless: true,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
} );
