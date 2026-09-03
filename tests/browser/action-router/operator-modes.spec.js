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
	await expect( page.locator( `#${labelId}` ) ).toHaveAccessibleName( /\S/ );
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

test( 'dashboard overview exposes status summaries and destination cards as accessible routes', async ( { page } ) => {
	await openShieldRoute( page, dashboardRoute );
	await dismissBlockingDialogs( page );

	const overview = page.locator( '.operator-mode-landing__overview' );
	const status = overview.locator( '.operator-mode-overview__status' );
	await expect( overview ).toBeVisible();
	await expect( status ).toHaveRole( 'region' );
	await expect( status ).toHaveAccessibleName( /\S/ );
	await expect( status ).not.toHaveAttribute( 'href' );
	await expect( status.locator( 'a' ) ).toHaveCount( 2 );

	for ( const summary of await status.locator( '[data-summary-id]' ).all() ) {
		await expect( summary ).toHaveRole( 'link' );
		await expect( summary ).toHaveAccessibleName( /\S/ );
		await expect( summary ).toHaveAttribute( 'href', /nav=scans/ );
	}

	const destinations = overview.locator( '.operator-mode-overview__destination' );
	await expect( destinations ).toHaveCount( 3 );
	for ( const destination of await destinations.all() ) {
		await expect( destination ).toHaveRole( 'link' );
		await expect( destination ).toHaveAccessibleName( /\S/ );
		await expect( destination ).toHaveAttribute( 'href', /nav=/ );
	}
} );

test( 'dashboard overview respects reduced motion for summary links', async ( { page } ) => {
	await page.emulateMedia( { reducedMotion: 'reduce' } );
	await openShieldRoute( page, dashboardRoute );

	const arrow = page.locator( '.operator-mode-overview__summary-value > i' ).first();
	await expect( arrow ).toBeVisible();
	expect( await arrow.evaluate( ( element ) => getComputedStyle( element ).transitionProperty ) ).toBe( 'none' );
} );

test( 'dashboard sidebar keeps dashboard first, separates actions, and preserves the dashboard geometry', async ( { page } ) => {
	await page.setViewportSize( { width: 2400, height: 1100 } );
	await openShieldRoute( page, dashboardRoute );
	await dismissBlockingDialogs( page );

	const navigation = page.locator( '#NavSideBar' );
	const links = navigation.locator( '.shield-mode-selector .mode-item' );
	await expect( links ).toHaveCount( 5 );
	expect( await links.evaluateAll( ( nodes ) => nodes.map( ( node ) => node.getAttribute( 'data-kind' ) === 'dashboard' ? 'dashboard' : node.getAttribute( 'data-mode' ) ) ) )
		.toEqual( [ 'dashboard', 'actions', 'investigate', 'configure', 'reports' ] );
	await expect( navigation.locator( '.shield-mode-selector .sidebar-sep' ) ).toHaveCount( 1 );
	await expect( links.nth( 1 ).locator( 'xpath=following-sibling::*[1]' ) ).toHaveClass( /sidebar-sep/ );

	const overview = page.locator( '.operator-mode-landing__overview' );
	const status = overview.locator( '.operator-mode-overview__status' );
	const monitorPlacement = overview.locator(
		'xpath=following-sibling::*[1][contains(concat(" ", normalize-space(@class), " "), " operator-mode-landing__live-monitor-placement ")]'
	);
	const monitor = monitorPlacement.locator( '[data-dashboard-live-monitor="1"]' );
	const contentPane = overview.locator( 'xpath=ancestor::*[contains(@class, "shield-rail-layout__content")][1]' );
	const [ contentBox, overviewBox, monitorPlacementBox ] = await Promise.all( [
		contentPane.boundingBox(),
		overview.boundingBox(),
		monitorPlacement.boundingBox(),
	] );
	expect( contentBox ).not.toBeNull();
	expect( overviewBox ).not.toBeNull();
	expect( monitorPlacementBox ).not.toBeNull();
	expect( Math.abs( overviewBox.width - 1080 ) ).toBeLessThan( 2 );
	expect( Math.abs( monitorPlacementBox.width - 1080 ) ).toBeLessThan( 2 );
	expect( Math.abs( ( overviewBox.x + overviewBox.width / 2 ) - ( contentBox.x + contentBox.width / 2 ) ) ).toBeLessThan( 2 );
	expect( Math.abs( ( monitorPlacementBox.x + monitorPlacementBox.width / 2 ) - ( contentBox.x + contentBox.width / 2 ) ) ).toBeLessThan( 2 );
	await expect( overview.locator( '[data-dashboard-live-monitor="1"]' ) ).toHaveCount( 0 );
	await expect( monitorPlacement ).toHaveCount( 1 );
	await expect( monitor ).toHaveCount( 1 );

	const cards = [ status, ...( await overview.locator( '.operator-mode-overview__destination' ).all() ) ];
	for ( const [ index, card ] of cards.entries() ) {
		const accent = index === 0
			? card.locator( '.operator-mode-overview__overall > .shield-card-accent' )
			: card.locator( ':scope > .shield-card-accent' );
		const accentContainer = index === 0
			? card.locator( '.operator-mode-overview__overall' )
			: card;
		const [ accentContainerBox, accentBox ] = await Promise.all( [ accentContainer.boundingBox(), accent.boundingBox() ] );
		expect( accentContainerBox ).not.toBeNull();
		expect( accentBox ).not.toBeNull();
		expect( Math.abs( accentBox.x - accentContainerBox.x ) ).toBeLessThan( 2 );
		expect( Math.abs( accentBox.y - accentContainerBox.y ) ).toBeLessThan( 2 );
		if ( index === 0 ) {
			expect( accentBox.width ).toBeLessThanOrEqual( 4 );
			expect( Math.abs( accentBox.height - accentContainerBox.height ) ).toBeLessThan( 2 );
		}
		else {
			expect( Math.abs( accentBox.width - accentContainerBox.width ) ).toBeLessThan( 2 );
		}
	}
} );

test( 'dashboard overview wraps cards at narrow widths without horizontal overflow', async ( { page } ) => {
	await page.setViewportSize( { width: 2400, height: 1000 } );
	await openShieldRoute( page, dashboardRoute );
	await dismissBlockingDialogs( page );

	const overview = page.locator( '.operator-mode-landing__overview' );
	const destinations = page.locator( '.operator-mode-overview__destination' );
	await expect( destinations ).toHaveCount( 3 );
	let boxes = await destinations.evaluateAll( ( nodes ) => nodes.map( ( node ) => {
		const box = node.getBoundingClientRect();
		return { height: box.height, top: box.top, width: box.width };
	} ) );
	const wideOverview = await overview.boundingBox();
	expect( wideOverview ).not.toBeNull();
	expect( boxes.every( ( box ) => box.width >= 280 ) ).toBe( true );
	expect( boxes.every( ( box ) => box.height <= 96 ) ).toBe( true );
	expect( boxes[ 1 ].top ).toBe( boxes[ 0 ].top );
	expect( boxes[ 2 ].top ).toBe( boxes[ 0 ].top );

	await page.setViewportSize( { width: 1300, height: 1000 } );
	const observedOverview = await overview.boundingBox();
	boxes = await destinations.evaluateAll( ( nodes ) => nodes.map( ( node ) => {
		const box = node.getBoundingClientRect();
		return { top: box.top };
	} ) );
	expect( observedOverview ).not.toBeNull();
	expect( boxes[ 1 ].top ).toBeGreaterThan( boxes[ 0 ].top );
	expect( boxes[ 2 ].top ).toBeGreaterThan( boxes[ 1 ].top );
	expect( await overview.evaluate( ( element ) => element.scrollWidth <= element.clientWidth ) ).toBe( true );

	await page.setViewportSize( { width: 375, height: 1000 } );
	const narrowOverview = await overview.boundingBox();
	const narrowBoxes = await destinations.evaluateAll( ( nodes ) => nodes.map( ( node ) => {
		const box = node.getBoundingClientRect();
		return { left: box.left, right: box.right, top: box.top, bottom: box.bottom };
	} ) );
	expect( narrowOverview ).not.toBeNull();
	expect( narrowOverview.width ).toBeLessThan( 280 );
	expect( narrowBoxes.every( ( box ) =>
		box.left >= narrowOverview.x && box.right <= narrowOverview.x + narrowOverview.width &&
		box.top >= narrowOverview.y && box.bottom <= narrowOverview.y + narrowOverview.height
	) ).toBe( true );
	expect( await overview.evaluate( ( element ) => element.scrollWidth <= element.clientWidth ) ).toBe( true );
} );

test( 'operator context rails stay hidden without actions and release the content column', async ( { page } ) => {
	await page.setViewportSize( { width: 2400, height: 1000 } );

	for ( const route of [ dashboardRoute, ...modeRoutes ] ) {
		await openShieldRoute( page, route );
		await dismissBlockingDialogs( page );

		const shell = page.locator( '[data-inner-page-body-shell="1"][data-operator-chrome="1"]' );
		const layout = shell.locator( ':scope > .shield-rail-layout--context-end' );
		const content = layout.locator( '.shield-rail-layout__content' );
		const rail = layout.locator( '[data-operator-context-rail="1"]' );
		await expect( rail ).toBeHidden();

		const [ layoutBox, contentBox ] = await Promise.all( [
			layout.boundingBox(),
			content.boundingBox(),
		] );
		expect( layoutBox ).not.toBeNull();
		expect( contentBox ).not.toBeNull();
		expect( Math.abs( contentBox.x - layoutBox.x ) ).toBeLessThan( 2 );
		expect( Math.abs( contentBox.width - layoutBox.width ) ).toBeLessThan( 2 );
		expect( await layout.evaluate( ( element ) => element.scrollWidth <= element.clientWidth ) ).toBe( true );
	}
} );

test( 'dashboard priority strip reflows its contiguous regions with the content pane', async ( { page } ) => {
	await page.setViewportSize( { width: 2400, height: 1000 } );
	await openShieldRoute( page, dashboardRoute );
	await dismissBlockingDialogs( page );

	const status = page.locator( '.operator-mode-overview__status' );
	const overall = status.locator( '.operator-mode-overview__overall' );
	const summaries = status.locator( '.operator-mode-overview__summaries' );
	const summaryCells = summaries.locator( '[data-summary-id]' );
	const wideOverview = page.locator( '.operator-mode-landing__overview' );
	const [ wideOverviewBox, wideOverall, wideSummaries, wideCells ] = await Promise.all( [
		wideOverview.boundingBox(),
		overall.boundingBox(),
		summaries.boundingBox(),
		summaryCells.evaluateAll( ( nodes ) => nodes.map( ( node ) => {
			const box = node.getBoundingClientRect();
			return { x: box.x, y: box.y, right: box.right, bottom: box.bottom };
		} ) ),
	] );
	expect( wideOverviewBox ).not.toBeNull();
	expect( wideOverall ).not.toBeNull();
	expect( wideSummaries ).not.toBeNull();
	expect( wideOverviewBox.width ).toBeGreaterThan( 920 );
	expect( wideSummaries.x ).toBeGreaterThan( wideOverall.x );
	expect( wideCells[ 1 ].x ).toBeGreaterThan( wideCells[ 0 ].x );
	expect( Math.abs( wideCells[ 1 ].x - wideCells[ 0 ].right ) ).toBeLessThan( 2 );
	expect( wideOverall.width ).toBeLessThan( ( wideCells[ 0 ].right - wideCells[ 0 ].x ) * 2 );

	await page.setViewportSize( { width: 1300, height: 1000 } );
	const [ compactOverview, compactOverall, compactSummaries, compactCells ] = await Promise.all( [
		wideOverview.boundingBox(),
		overall.boundingBox(),
		summaries.boundingBox(),
		summaryCells.evaluateAll( ( nodes ) => nodes.map( ( node ) => {
			const box = node.getBoundingClientRect();
			return { x: box.x, y: box.y, right: box.right, bottom: box.bottom };
		} ) ),
	] );
	expect( compactOverview ).not.toBeNull();
	expect( compactOverall ).not.toBeNull();
	expect( compactSummaries ).not.toBeNull();
	expect( compactSummaries.y ).toBeGreaterThan( compactOverall.y );
	expect( Math.abs( compactSummaries.y - ( compactOverall.y + compactOverall.height ) ) ).toBeLessThan( 2 );
	expect( compactCells[ 1 ].x ).toBeGreaterThan( compactCells[ 0 ].x );
	expect( Math.abs( compactCells[ 1 ].y - compactCells[ 0 ].y ) ).toBeLessThan( 2 );

	await page.setViewportSize( { width: 500, height: 1000 } );
	const [ narrowOverall, narrowSummaries, narrowCells ] = await Promise.all( [
		overall.boundingBox(),
		summaries.boundingBox(),
		summaryCells.evaluateAll( ( nodes ) => nodes.map( ( node ) => {
			const box = node.getBoundingClientRect();
			return { x: box.x, y: box.y, right: box.right, bottom: box.bottom };
		} ) ),
	] );
	expect( narrowOverall ).not.toBeNull();
	expect( narrowSummaries ).not.toBeNull();
	expect( narrowSummaries.y ).toBeGreaterThan( narrowOverall.y );
	expect( Math.abs( narrowSummaries.y - ( narrowOverall.y + narrowOverall.height ) ) ).toBeLessThan( 2 );
	expect( narrowCells[ 1 ].y ).toBeGreaterThan( narrowCells[ 0 ].y );
	expect( Math.abs( narrowCells[ 1 ].y - narrowCells[ 0 ].bottom ) ).toBeLessThan( 2 );
	expect( Math.abs( narrowCells[ 1 ].x - narrowCells[ 0 ].x ) ).toBeLessThan( 2 );
	expect( Math.abs( narrowCells[ 1 ].right - narrowCells[ 0 ].right ) ).toBeLessThan( 2 );
} );

test( 'compact sidebar expands equally on hover and keyboard focus', async ( { page } ) => {
	await page.setViewportSize( { width: 900, height: 900 } );
	await openShieldRoute( page, dashboardRoute );
	await dismissBlockingDialogs( page );

	const sidebar = page.locator( '#PageMainSide-Apto' );
	const viewport = page.viewportSize();
	if ( !viewport ) {
		throw new Error( 'Expected a configured browser viewport.' );
	}
	await expect.poll( async () => sidebar.evaluate( ( element ) => element.getBoundingClientRect().bottom ) )
		.toBeGreaterThanOrEqual( viewport.height - 1 );

	const label = page.locator( '#NavSideBar .mode-label' ).first();
	await expect( label ).not.toBeVisible();
	await sidebar.hover();
	await expect( label ).toBeVisible();

	await page.mouse.move( 500, 500 );
	await expect( label ).not.toBeVisible();
	await page.locator( '#NavSideBar .mode-item' ).first().focus();
	await expect( label ).toBeVisible();
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
