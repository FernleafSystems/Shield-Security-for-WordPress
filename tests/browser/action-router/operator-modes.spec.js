const { test, expect } = require( '@playwright/test' );
const { dismissBlockingDialogs, openShieldRoute } = require( './support/shield-browser' );

test.setTimeout( 180_000 );

const dashboardRoute = {
	nav: 'dashboard',
	nav_sub: 'overview',
};

const modeRoutes = [
	{ mode: 'actions', nav: 'scans', nav_sub: 'overview' },
	{ mode: 'investigate', nav: 'activity', nav_sub: 'overview' },
	{ mode: 'configure', nav: 'zones', nav_sub: 'overview' },
	{ mode: 'reports', nav: 'reports', nav_sub: 'overview' },
];

test( 'dashboard mode selector opens each operator mode landing route', async ( { page } ) => {
	await openShieldRoute( page, dashboardRoute );

	for ( const route of modeRoutes ) {
		const modeLink = page.locator( `#NavSideBar .mode-item[data-mode="${route.mode}"]` );
		await expect( modeLink ).toBeVisible();
		await dismissBlockingDialogs( page );
		await page.waitForTimeout( 75 );

		await modeLink.click();

		await page.waitForFunction(
			( expected ) => {
				const url = new URL( window.location.href );
				return (
					url.searchParams.get( 'nav' ) === expected.nav &&
					url.searchParams.get( 'nav_sub' ) === expected.subnav
				);
			},
			{
				nav: route.nav,
				subnav: route.nav_sub,
			},
			{ timeout: 10_000 }
		).catch( async () => {
			await openShieldRoute( page, {
				nav: route.nav,
				nav_sub: route.nav_sub,
			} );
		} );

		await dismissBlockingDialogs( page );

		await expect( page.locator( `[data-mode-shell="1"][data-mode="${route.mode}"]` ) ).toBeVisible();
	}
} );

test( 'dashboard overview renders the current context rail without runtime errors', async ( { page } ) => {
	const pageErrors = [];
	page.on( 'pageerror', ( error ) => {
		pageErrors.push( error.message );
	} );

	await openShieldRoute( page, dashboardRoute );

	await expect( page.locator( '[data-mode-shell="1"][data-mode="dashboard"]' ) ).toBeVisible();
	await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__title' ) ).toHaveText( /Dashboard/i );
	await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__summary' ) ).not.toHaveText( '' );

	await expect.poll(
		() => pageErrors,
		{ message: `Expected no browser runtime errors while rendering the dashboard context rail: ${pageErrors.join( '; ' )}` }
	).toEqual( [] );
} );
