const { test, expect } = require( './support/shield-test' );
const { openShieldRoute, dismissBlockingDialogs } = require( './support/shield-browser' );
const { collectRuntimeErrors, expectNoRuntimeErrors } = require( './support/security-assertions' );

test.setTimeout( 180_000 );

for ( const workspace of [ 'list', 'settings', 'charts' ] ) {
	test( `legacy reports ${workspace} opens the requested workspace and survives refresh`, async ( { page } ) => {
		const errors = collectRuntimeErrors( page );
		await openShieldRoute( page, { nav: 'reports', nav_sub: workspace } );
		await dismissBlockingDialogs( page );
		const url = new URL( page.url() );
		expect( url.searchParams.get( 'nav_sub' ) ).toBe( 'overview' );
		expect( url.searchParams.get( 'workspace' ) ).toBe( workspace );
		await expect( page.locator( `[data-reports-workspace="${workspace}"]` ) ).toBeVisible();
		await page.reload();
		await expect( page.locator( `[data-reports-workspace="${workspace}"]` ) ).toBeVisible();
		expectNoRuntimeErrors( errors );
	} );
}

for ( const component of [ 'whitelabel', 'module_integrations', 'two_factor_auth' ] ) {
	test( `legacy configuration ${component} opens the existing settings form`, async ( { page } ) => {
		const errors = collectRuntimeErrors( page );
		await openShieldRoute( page, { nav: 'zone_components', nav_sub: component } );
		const url = new URL( page.url() );
		expect( url.searchParams.get( 'nav' ) ).toBe( 'zones' );
		expect( url.searchParams.get( 'component' ) ).toBe( component );
		const dialog = page.locator( '.offcanvas.show' );
		await expect( dialog ).toBeVisible();
		await expect( dialog.locator( 'form' ) ).toBeVisible();
		await expect( dialog.locator( 'button[type="submit"]' ) ).toBeVisible();
		expectNoRuntimeErrors( errors );
	} );
}
