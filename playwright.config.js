const { defineConfig } = require( '@playwright/test' );

const laneMap = process.env.SHIELD_BROWSER_LANE_MAP;

if ( !laneMap ) {
	throw new Error( 'SHIELD_BROWSER_LANE_MAP must be set. Use composer test:browser or php bin/shield test:browser.' );
}

module.exports = defineConfig( {
	testDir: './tests/browser',
	timeout: 60_000,
	outputDir: './test-results/playwright',
	expect: {
		timeout: 10_000,
	},
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: process.env.CI ? 1 : 2,
	reporter: process.env.CI ? [ [ 'github' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		headless: true,
		trace: process.env.CI ? 'retain-on-failure' : 'on-first-retry',
		screenshot: 'only-on-failure',
		video: process.env.CI ? 'retain-on-failure' : 'off',
	},
} );
