const { test } = require( './support/shield-test' );
const {
	expectResponseHeaders,
} = require( './support/security-assertions' );

test( 'security headers fixture exposes expected Shield-owned response headers', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withSecurityHeadersFixture( async ( fixture ) => {
		const context = await browser.newContext( {
			baseURL: lane.baseUrl,
		} );
		const page = await context.newPage();
		try {
			const response = await page.goto( fixture.path, { waitUntil: 'domcontentloaded' } );
			await expectResponseHeaders( response, fixture.expected_headers );
		}
		finally {
			await context.close();
		}
	} );
} );
