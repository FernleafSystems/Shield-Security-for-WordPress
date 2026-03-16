const { test, expect } = require( '@playwright/test' );
const { openShieldRoute } = require( './support/shield-browser' );

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
	for ( const route of modeRoutes ) {
		await openShieldRoute( page, dashboardRoute );

		const modeLink = page.locator( `#NavSideBar .mode-item[data-mode="${route.mode}"]` );
		await expect( modeLink ).toBeVisible();

		await Promise.all( [
			page.waitForURL(
				( url ) => url.searchParams.get( 'nav' ) === route.nav && url.searchParams.get( 'nav_sub' ) === route.nav_sub,
				{ timeout: 20_000 }
			),
			modeLink.click(),
		] );

		await expect( page.locator( `[data-mode-shell="1"][data-mode="${route.mode}"]` ) ).toBeVisible();
	}
} );
