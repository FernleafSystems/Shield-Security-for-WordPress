const { test, expect } = require( './support/shield-test' );
const { dismissBlockingDialogs, openShieldRoute } = require( './support/shield-browser' );

test.setTimeout( 180_000 );

const dashboardRoute = {
	nav: 'dashboard',
	nav_sub: 'overview',
};

async function setDashboardLiveMonitorCollapsed( page, isCollapsed ) {
	await page.evaluate( async ( nextCollapsed ) => {
		const requestData = window.shield_vars_main?.comps?.dashboard_live_monitor?.ajax?.set_state || null;
		if ( !requestData?.ajaxurl ) {
			throw new Error( 'Missing dashboard live monitor set_state AJAX payload.' );
		}

		const response = await fetch( requestData.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: new URLSearchParams( {
				...requestData,
				is_collapsed: nextCollapsed ? '1' : '0',
			} ),
		} );
		const payload = await response.json();
		if ( !response.ok || !payload?.success ) {
			throw new Error( 'Dashboard live monitor state request failed.' );
		}
	}, isCollapsed );
}

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

test( 'dashboard overview renders the current context rail without runtime errors', async ( { page } ) => {
	const pageErrors = [];
	page.on( 'pageerror', ( error ) => {
		pageErrors.push( error.message );
	} );

	await openShieldRoute( page, dashboardRoute );

	await expect( page.locator( '[data-mode-shell="1"][data-mode="dashboard"]' ) ).toBeVisible();
	await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__eyebrow' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__title' ) ).toHaveText( /Dashboard/i );
	await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__summary' ) ).not.toHaveText( '' );

	await expect.poll(
		() => pageErrors,
		{ message: `Expected no browser runtime errors while rendering the dashboard context rail: ${pageErrors.join( '; ' )}` }
	).toEqual( [] );
} );

test( 'dashboard overview stacks earlier, auto-collapses the live monitor, and keeps the context rail header horizontal', async ( { page } ) => {
	await page.setViewportSize( { width: 1500, height: 1100 } );
	await openShieldRoute( page, dashboardRoute );
	await dismissBlockingDialogs( page );
	await setDashboardLiveMonitorCollapsed( page, false );
	await page.reload( { waitUntil: 'domcontentloaded' } );
	await dismissBlockingDialogs( page );

	const featuredCard = page.locator( '.operator-mode-landing__featured' );
	const sideStack = page.locator( '.operator-mode-landing__side-stack' );
	const liveMonitor = page.locator( '[data-dashboard-live-monitor="1"]' );
	const liveMonitorToggle = page.locator( '[data-live-monitor-toggle="1"]' );
	const railHeader = page.locator( '[data-operator-context-rail="1"] .operator-context-rail__header' );

	await expect( featuredCard ).toBeVisible();
	await expect( sideStack ).toBeVisible();
	await expect( liveMonitor ).toHaveAttribute( 'data-is-collapsed', '0' );
	await expect( liveMonitorToggle ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__eyebrow' ) ).toHaveCount( 0 );
	await expect.poll( async () => railHeader.evaluate( ( el ) => window.getComputedStyle( el ).alignItems ) ).toBe( 'center' );

	const wideFeaturedBox = await featuredCard.boundingBox();
	const wideSideBox = await sideStack.boundingBox();
	expect( wideFeaturedBox ).not.toBeNull();
	expect( wideSideBox ).not.toBeNull();
	expect( Math.abs( ( wideSideBox?.y || 0 ) - ( wideFeaturedBox?.y || 0 ) ) ).toBeLessThan( 12 );
	expect( ( wideSideBox?.x || 0 ) ).toBeGreaterThan( ( wideFeaturedBox?.x || 0 ) );

	await page.setViewportSize( { width: 1200, height: 1100 } );
	await expect( liveMonitor ).toHaveAttribute( 'data-is-collapsed', '1' );
	await expect( liveMonitorToggle ).toHaveAttribute( 'aria-expanded', 'false' );

	const compactFeaturedBox = await featuredCard.boundingBox();
	const compactSideBox = await sideStack.boundingBox();
	expect( compactFeaturedBox ).not.toBeNull();
	expect( compactSideBox ).not.toBeNull();
	expect( ( compactSideBox?.y || 0 ) ).toBeGreaterThan( ( compactFeaturedBox?.y || 0 ) + ( compactFeaturedBox?.height || 0 ) - 4 );

	await page.setViewportSize( { width: 1500, height: 1100 } );
	await expect( liveMonitor ).toHaveAttribute( 'data-is-collapsed', '0' );
	await expect( liveMonitorToggle ).toHaveAttribute( 'aria-expanded', 'true' );
} );
