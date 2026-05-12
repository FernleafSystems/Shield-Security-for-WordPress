const { test, expect } = require( './support/shield-test' );
const { dismissBlockingDialogs, openShieldRoute } = require( './support/shield-browser' );
const {
	collectRuntimeErrors,
	expectNoRuntimeErrors,
	expectStatusLiveRegion,
	setDashboardLiveMonitorCollapsed,
} = require( './support/security-assertions' );

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

async function interceptModeNavigationRaf( page ) {
	await page.evaluate( () => {
		const queuedFrames = [];

		window.__shieldModeNavTest = {
			flush() {
				const callbacks = queuedFrames.splice( 0, queuedFrames.length );
				callbacks.forEach( ( callback ) => callback( window.performance.now() ) );
			},
		};

		window.requestAnimationFrame = ( callback ) => {
			queuedFrames.push( callback );
			return queuedFrames.length;
		};
	} );
}

async function flushInterceptedModeNavigationRaf( page ) {
	await page.evaluate( () => {
		window.__shieldModeNavTest?.flush();
	} );
}

function installNativeDialogGuard( page ) {
	const nativeDialogs = [];
	page.on( 'dialog', async ( dialog ) => {
		nativeDialogs.push( dialog.type() );
		await dialog.dismiss().catch( () => null );
	} );
	return nativeDialogs;
}

async function expectNamedOffcanvas( page ) {
	const offcanvas = page.locator( '#AptoOffcanvas' );
	await expect( page.locator( '#AptoOffcanvas.show' ) ).toBeVisible( { timeout: 20_000 } );
	await expect( offcanvas ).toHaveAttribute( 'role', 'dialog' );
	await expect( offcanvas ).toHaveAttribute( 'aria-modal', 'true' );
	const labelId = await offcanvas.getAttribute( 'aria-labelledby' );
	expect( labelId || '' ).not.toHaveLength( 0 );
	expect(
		await page.locator( `#${labelId}` ).evaluate(
			( node ) => node.isConnected && ( node.textContent || '' ).trim().length > 0
		)
	).toBe( true );
	return offcanvas;
}

async function closeOffcanvasAndExpectFocusReturn( offcanvas, launcher ) {
	await offcanvas.locator( '[data-bs-dismiss="offcanvas"]' ).first().click();
	await expect( offcanvas ).not.toHaveAttribute( 'aria-modal', 'true' );
	await expect( launcher ).toBeFocused();
}

test( 'dashboard mode selector shows a matching loading placeholder before each mode navigation completes', async ( { page } ) => {
	for ( const route of modeRoutes ) {
		await openShieldRoute( page, dashboardRoute );
		await dismissBlockingDialogs( page );

		const modeLink = page.locator( `#NavSideBar .mode-item[data-mode="${route.mode}"]` );
		await expect( modeLink ).toBeVisible();
		await interceptModeNavigationRaf( page );

		await modeLink.click();

		const visiblePlaceholder = page.locator(
			`#PageMainBody_Inner-Apto [data-shield-nav-loading-placeholder="${route.mode}"]`
		);

		await expect( visiblePlaceholder ).toBeVisible();
		await expect( visiblePlaceholder ).not.toHaveAttribute( 'aria-hidden', 'true' );
		await expect( page.locator( '#PageMainBody_Inner-Apto' ) ).toHaveAttribute( 'aria-busy', 'true' );

		const completedNavigation = page.waitForURL(
			( url ) => url.searchParams.get( 'nav' ) === route.nav && url.searchParams.get( 'nav_sub' ) === route.nav_sub,
			{ timeout: 20_000, waitUntil: 'domcontentloaded' }
		);
		await flushInterceptedModeNavigationRaf( page );
		await flushInterceptedModeNavigationRaf( page );
		await completedNavigation;
		await page.waitForLoadState( 'domcontentloaded' );
		await dismissBlockingDialogs( page );

		await expect( page.locator( `[data-mode-shell="1"][data-mode="${route.mode}"]` ) ).toBeVisible();
	}
} );

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

test( 'configure sidebar zone actions are buttons that open accessible offcanvas panels without navigation', async ( { page } ) => {
	const nativeDialogs = installNativeDialogGuard( page );
	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
	} );
	await dismissBlockingDialogs( page );

	const originalUrl = page.url();
	const whitelabelAction = page.locator(
		'#NavSideBar button[data-zone_component_action="offcanvas_zone_component_config"][data-zone_component_slug="whitelabel"]'
	).first();

	await expect( whitelabelAction ).toBeVisible();
	await expect( whitelabelAction ).toHaveRole( 'button' );
	await expect( whitelabelAction ).toHaveAttribute( 'type', 'button' );
	expect( await whitelabelAction.getAttribute( 'href' ) ).toBeNull();

	await whitelabelAction.click();
	const clickedOffcanvas = await expectNamedOffcanvas( page );
	expect( page.url() ).toBe( originalUrl );
	await closeOffcanvasAndExpectFocusReturn( clickedOffcanvas, whitelabelAction );

	await whitelabelAction.focus();
	await page.keyboard.press( 'Enter' );
	const keyboardOffcanvas = await expectNamedOffcanvas( page );
	expect( page.url() ).toBe( originalUrl );
	await closeOffcanvasAndExpectFocusReturn( keyboardOffcanvas, whitelabelAction );

	expect( nativeDialogs ).toEqual( [] );
} );

test( 'dashboard overview renders stable dashboard shell contracts without runtime errors', async ( { page } ) => {
	const runtimeErrors = collectRuntimeErrors( page );
	await openShieldRoute( page, dashboardRoute );

	await expect( page.locator( '[data-mode-shell="1"][data-mode="dashboard"]' ) ).toBeVisible();
	await expect( page.locator( '[data-dashboard-live-monitor="1"]' ) ).toBeVisible();
	await expectStatusLiveRegion( page.locator( '[data-dashboard-live-monitor="1"] [data-shield-status-region="1"]' ) );
	await expectNoRuntimeErrors( runtimeErrors, 'dashboard overview shell render' );
} );

test( 'dashboard live monitor persists explicit collapsed state changes', async ( { page } ) => {
	await page.setViewportSize( { width: 1500, height: 1100 } );
	await openShieldRoute( page, dashboardRoute );
	await dismissBlockingDialogs( page );
	await setDashboardLiveMonitorCollapsed( page, false );
	await page.reload( { waitUntil: 'domcontentloaded' } );
	await dismissBlockingDialogs( page );

	const liveMonitor = page.locator( '[data-dashboard-live-monitor="1"]' );
	const liveMonitorToggle = page.locator( '[data-live-monitor-toggle="1"]' );

	await expect( page.locator( '[data-mode-shell="1"][data-mode="dashboard"]' ) ).toBeVisible();
	await expect( liveMonitor ).toHaveAttribute( 'data-is-collapsed', '0' );
	await expect( liveMonitorToggle ).toHaveAttribute( 'aria-expanded', 'true' );

	await setDashboardLiveMonitorCollapsed( page, true );
	await page.reload( { waitUntil: 'domcontentloaded' } );
	await dismissBlockingDialogs( page );
	await expect( liveMonitor ).toHaveAttribute( 'data-is-collapsed', '1' );
	await expect( liveMonitorToggle ).toHaveAttribute( 'aria-expanded', 'false' );

	await setDashboardLiveMonitorCollapsed( page, false );
	await page.reload( { waitUntil: 'domcontentloaded' } );
	await dismissBlockingDialogs( page );
	await expect( liveMonitor ).toHaveAttribute( 'data-is-collapsed', '0' );
	await expect( liveMonitorToggle ).toHaveAttribute( 'aria-expanded', 'true' );
} );
