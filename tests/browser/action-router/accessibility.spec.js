const { test, expect } = require( './support/shield-test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { openShieldRoute } = require( './support/shield-browser' );

async function expectNoAxeViolations( page, selector ) {
	const results = await new AxeBuilder( { page } )
		.include( selector )
		.analyze();

	expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
}

test( 'dashboard overview passes axe smoke', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'dashboard',
		nav_sub: 'overview',
	} );

	await expect( page.locator( '#PageContainer-Apto' ) ).toBeVisible();
	await expectNoAxeViolations( page, '#PageContainer-Apto' );
} );

test( 'plugin investigate page passes axe smoke for a loaded subject state', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'by_plugin',
		plugin_slug: 'wp-simple-firewall/icwp-wpsf.php',
	} );

	await expect( page.locator( '[data-investigate-subject-header="1"]' ) ).toBeVisible();
	await expectNoAxeViolations( page, '#PageContainer-Apto' );
} );
