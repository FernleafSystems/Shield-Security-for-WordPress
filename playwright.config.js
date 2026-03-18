const { defineConfig } = require( '@playwright/test' );

const baseUrl = process.env.SHIELD_BROWSER_BASE_URL;

if ( !baseUrl ) {
	throw new Error( 'SHIELD_BROWSER_BASE_URL must be set. Use composer test:browser or php bin/shield test:browser.' );
}

module.exports = defineConfig( {
	testDir: './tests/browser',
	timeout: 60_000,
	outputDir: './test-results/playwright',
	expect: {
		timeout: 10_000,
	},
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: process.env.CI ? 1 : undefined,
	reporter: process.env.CI ? [ [ 'github' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		baseURL: baseUrl,
		headless: true,
		trace: process.env.CI ? 'retain-on-failure' : 'on-first-retry',
		screenshot: 'only-on-failure',
		video: process.env.CI ? 'retain-on-failure' : 'off',
	},
} );
