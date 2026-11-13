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
		await expect( page.locator( `[data-reports-tabpanel="${workspace}"]` ) ).toBeVisible();
		await page.reload();
		await expect( page.locator( `[data-reports-tabpanel="${workspace}"]` ) ).toBeVisible();
		expectNoRuntimeErrors( errors );
	} );
}

// Old links that named a component open its settings where they live now: a Configure topic's panel,
// or the page that manages them.
for ( const [ component, nav, navSub, rowKey ] of [
	[ 'whitelabel', 'tools', 'whitelabel', null ],
	[ 'module_integrations', 'zones', 'overview', 'integrations' ],
	[ 'two_factor_auth', 'zones', 'overview', 'two_factor' ],
] ) {
	for ( const route of [
		{ nav: 'zone_components', nav_sub: component },
		{ nav: 'zones', nav_sub: 'overview', component },
	] ) {
		test( `legacy configuration ${component} via ${route.nav} opens its settings panel`, async ( { page } ) => {
			const errors = collectRuntimeErrors( page );
			await openShieldRoute( page, route );
			await dismissBlockingDialogs( page );
			const url = new URL( page.url() );
			expect( url.searchParams.get( 'nav' ) ).toBe( nav );
			expect( url.searchParams.get( 'nav_sub' ) ).toBe( navSub );
			expect( url.searchParams.get( 'component' ) ).toBeNull();
			expect( url.searchParams.get( 'row_key' ) ).toBe( rowKey );
			await expect( page.locator( 'form.options_form_for--panel:visible' ).first() ).toBeVisible();
			await expect( page.locator( '.offcanvas.show' ) ).toHaveCount( 0 );
			expectNoRuntimeErrors( errors );
		} );
	}
}
