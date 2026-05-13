const { test, expect } = require( './support/shield-test' );
const {
	openShieldRoute,
	selectSelect2Option,
} = require( './support/shield-browser' );
const {
	expectActiveInlineTabState,
	expectInlineTabsContract,
	expectKeyboardTabActivation,
	getInlineTabByIndex,
	getInlineTabByTableType,
} = require( './support/investigate-inline-tabs' );
const {
	collectRuntimeErrors,
	expectInvestigationTableInitialized,
	expectNoRuntimeErrors,
	expectRequestMetaPopover,
	investigationTableResponseMatcher,
	isAdminAjaxRequest,
	requestActionSlug,
	requestPostParam,
} = require( './support/security-assertions' );

const panelSelector = '[data-investigate-panel="1"]';

const isLiveTrafficPollRequest = ( request ) => {
	return isAdminAjaxRequest( request )
		&& requestActionSlug( request ) === 'ajax_render'
		&& requestPostParam( request, 'render_slug' ) === 'render_traffic_live_logs';
};

const parseWrappedAjaxJson = ( raw ) => {
	const openJsonTag = '##APTO_OPEN##';
	const closeJsonTag = '##APTO_CLOSE##';
	if ( raw.includes( openJsonTag ) ) {
		const start = raw.indexOf( openJsonTag ) + openJsonTag.length;
		const end = raw.lastIndexOf( closeJsonTag );
		return JSON.parse( raw.substring( start, end ) );
	}
	return JSON.parse( raw );
};

const expectPanelState = async ( page, panel, { subject, isLoaded, lookupKey = '' } ) => {
	await expect( panel ).toHaveAttribute( 'data-investigate-panel-subject', subject );
	await expect( panel ).toHaveAttribute( 'data-investigate-panel-loaded', isLoaded ? '1' : '0' );
	if ( lookupKey ) {
		await expect.poll( () => {
			const url = new URL( page.url() );
			return url.searchParams.get( lookupKey ) || '';
		} ).toBe( '' );
	}
};

const clickSubjectTile = async ( page, subject ) => {
	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'nav' ) === 'activity'
				&& url.searchParams.get( 'nav_sub' ) === 'overview'
				&& url.searchParams.get( 'subject' ) === subject,
			{ timeout: 20_000 }
		),
		page.locator( `[data-drill-target="panel"][data-investigate-subject="${subject}"]` ).click(),
	] );
};

test( 'investigate user reset uses the shared generic panel path and self shortcut', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const panel = page.locator( panelSelector );
	await clickSubjectTile( page, 'user' );

	await expectPanelState( page, panel, {
		subject: 'user',
		isLoaded: true,
	} );
	await expect( panel.locator( 'select[name="user_lookup"]' ) ).toBeVisible();
	await expect( page.locator( '[data-step-tab-investigate-reset="1"]' ) ).toHaveCount( 0 );

	const selfLookupShortcut = panel.locator( '[data-investigate-panel-content="1"] a[href*="user_lookup="]' ).first();

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'subject' ) === 'user'
				&& !!url.searchParams.get( 'user_lookup' ),
			{ timeout: 20_000 }
		),
		selfLookupShortcut.click(),
	] );

	await expect( page.locator( '[data-step-tab-investigate-reset="1"]' ) ).toHaveCount( 1 );
	await expect( panel.locator( '[data-investigate-panel-header="1"] [data-investigate-subject-header="1"]' ) ).toBeVisible();

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'subject' ) === 'user'
				&& !url.searchParams.get( 'user_lookup' ),
			{ timeout: 20_000 }
		),
		page.locator( '[data-step-tab-investigate-reset="1"]' ).click(),
	] );

	await expectPanelState( page, panel, {
		subject: 'user',
		isLoaded: true,
		lookupKey: 'user_lookup',
	} );
	await expect( page.locator( '[data-step-tab-investigate-reset="1"]' ) ).toHaveCount( 0 );
	await expect( panel.locator( '[data-investigate-panel-header="1"] [data-investigate-subject-header="1"]' ) ).toHaveCount( 0 );

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'subject' ) === 'user'
				&& !!url.searchParams.get( 'user_lookup' ),
			{ timeout: 20_000 }
		),
		selfLookupShortcut.click(),
	] );

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'subject' ) === 'user'
				&& !url.searchParams.get( 'user_lookup' ),
			{ timeout: 20_000 }
		),
		panel.locator( '[data-investigate-change-subject="1"]' ).click(),
	] );

	await expectPanelState( page, panel, {
		subject: 'user',
		isLoaded: true,
		lookupKey: 'user_lookup',
	} );
	await expect( page.locator( '[data-step-tab-investigate-reset="1"]' ) ).toHaveCount( 0 );

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'nav' ) === 'activity'
				&& url.searchParams.get( 'nav_sub' ) === 'overview'
				&& !url.searchParams.get( 'subject' ),
			{ timeout: 20_000 }
		),
		page.locator( '[data-step-tab-drill-index="0"]' ).click(),
	] );

	await expectPanelState( page, panel, {
		subject: '',
		isLoaded: false,
	} );
} );

test( 'investigate landing loads each enabled subject tile into the shared panel', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const panel = page.locator( panelSelector );
	const subjectExpectations = [
		{ subject: 'user', content: 'select[name="user_lookup"]' },
		{ subject: 'ip', content: 'select[name="analyse_ip"]' },
		{ subject: 'plugin', content: 'select[name="plugin_slug"]' },
		{ subject: 'theme', content: 'select[name="theme_slug"]' },
		{ subject: 'core', content: '#tabInvestigateCoreOverview' },
		{ subject: 'live_traffic', content: '#SectionTrafficLiveLogs' },
	];

	for ( const { subject, content } of subjectExpectations ) {
		await clickSubjectTile( page, subject );

		await expectPanelState( page, panel, {
			subject,
			isLoaded: true,
		} );
		await expect( panel.locator( '[data-investigate-panel-content="1"]' ).locator( content ) ).toBeVisible();

		await Promise.all( [
			page.waitForURL(
				( url ) => url.searchParams.get( 'nav' ) === 'activity'
					&& url.searchParams.get( 'nav_sub' ) === 'overview'
					&& !url.searchParams.get( 'subject' ),
				{ timeout: 20_000 }
			),
			page.locator( '[data-step-tab-drill-index="0"]' ).click(),
		] );

		await expectPanelState( page, panel, {
			subject: '',
			isLoaded: false,
		} );
	}
} );

test( 'investigate landing deep link opens the IP panel, resets generically, and supports self lookup', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
		subject: 'ip',
		analyse_ip: '203.0.113.88',
	} );

	const panel = page.locator( '[data-drill-layer="1"] [data-investigate-panel="1"]' );
	await expectPanelState( page, panel, {
		subject: 'ip',
		isLoaded: true,
	} );
	await expect( page.locator( '[data-step-tab-investigate-reset="1"]' ) ).toHaveCount( 1 );
	await expect( panel.locator( '[data-investigate-panel-header="1"] [data-investigate-subject-header="1"]' ) ).toBeVisible();

	const rail = page.locator( '[data-operator-context-rail="1"]' );
	await expect( rail ).toBeVisible();
	await expect( rail.locator( '[data-operator-context-rail-body="1"]' ) ).toBeVisible();

	const subjectHeader = panel.locator( '[data-investigate-subject-header="1"]' );
	const contextStepJson = await subjectHeader.getAttribute( 'data-investigate-context-step' );
	expect( contextStepJson ).not.toBeNull();
	const contextStep = JSON.parse( contextStepJson );
	expect( contextStep ).toMatchObject( {
		breadcrumb_label: '203.0.113.88',
		title: '203.0.113.88',
		color_key: 'investigate',
	} );
	expect( typeof contextStep.summary ).toBe( 'string' );
	expect( contextStep.summary.trim().length ).toBeGreaterThan( 0 );
	expect( typeof contextStep.badge_status ).toBe( 'string' );
	expect( contextStep.badge_status.trim().length ).toBeGreaterThan( 0 );

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'subject' ) === 'ip'
				&& !url.searchParams.get( 'analyse_ip' ),
			{ timeout: 20_000 }
		),
		page.locator( '[data-step-tab-investigate-reset="1"]' ).click(),
	] );

	await expectPanelState( page, panel, {
		subject: 'ip',
		isLoaded: true,
		lookupKey: 'analyse_ip',
	} );
	await expect( page.locator( '[data-step-tab-investigate-reset="1"]' ) ).toHaveCount( 0 );

	const selfLookupShortcut = panel.locator( '[data-investigate-panel-content="1"] a[href*="analyse_ip="]' ).first();

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'subject' ) === 'ip'
				&& !!url.searchParams.get( 'analyse_ip' ),
			{ timeout: 20_000 }
		),
		selfLookupShortcut.click(),
	] );

	await expect( page.locator( '[data-step-tab-investigate-reset="1"]' ) ).toHaveCount( 1 );

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'nav' ) === 'activity'
				&& url.searchParams.get( 'nav_sub' ) === 'overview'
				&& !url.searchParams.get( 'subject' )
				&& !url.searchParams.get( 'analyse_ip' ),
			{ timeout: 20_000 }
		),
		page.locator( '[data-step-tab-drill-index="0"]' ).click(),
	] );

	await expectPanelState( page, panel, {
		subject: '',
		isLoaded: false,
	} );
} );

test( 'investigate landing IP analysis loads investigation tables without runtime errors', async ( { page } ) => {
	const runtimeErrors = collectRuntimeErrors( page );

	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
		subject: 'ip',
		analyse_ip: '203.0.113.88',
	} );

	const panel = page.locator( '[data-drill-layer="1"] [data-investigate-panel="1"]' );
	await expectPanelState( page, panel, {
		subject: 'ip',
		isLoaded: true,
	} );

	await expectInlineTabsContract( panel );

	for ( const tableType of [ 'sessions', 'activity', 'traffic' ] ) {
		const targetTab = await getInlineTabByTableType( panel, tableType );

		await Promise.all( [
			page.waitForResponse( investigationTableResponseMatcher( tableType ) ),
			targetTab.click(),
		] );

		await expectActiveInlineTabState( panel, targetTab );
		await expectInvestigationTableInitialized( panel, tableType );
	}
	const activityTab = await getInlineTabByTableType( panel, 'activity' );
	const trafficTab = await getInlineTabByTableType( panel, 'traffic' );
	const firstTab = await getInlineTabByIndex( panel, 0 );
	const lastTab = await getInlineTabByIndex( panel, 3 );

	await expectKeyboardTabActivation( page, panel, trafficTab, 'ArrowLeft', activityTab );
	await expectInvestigationTableInitialized( panel, 'activity' );
	await expectKeyboardTabActivation( page, panel, activityTab, 'ArrowRight', trafficTab );
	await expectInvestigationTableInitialized( panel, 'traffic' );
	await expectKeyboardTabActivation( page, panel, trafficTab, 'ArrowUp', activityTab );
	await expectInvestigationTableInitialized( panel, 'activity' );
	await expectKeyboardTabActivation( page, panel, activityTab, 'ArrowDown', trafficTab );
	await expectInvestigationTableInitialized( panel, 'traffic' );
	await expectKeyboardTabActivation( page, panel, trafficTab, 'Home', firstTab );
	await expectKeyboardTabActivation( page, panel, firstTab, 'End', lastTab );
	await expectInvestigationTableInitialized( panel, 'traffic' );

	await expectNoRuntimeErrors( runtimeErrors, 'Expected no browser runtime errors while loading drill-down IP analysis investigation tables' );
} );

test( 'investigate landing IP activity meta button loads request meta popover', async ( { page, fixtureApi } ) => {
	const runtimeErrors = collectRuntimeErrors( page );

	await fixtureApi.withIpAnalysisActivityMetaFixture( async ( fixture ) => {
		await openShieldRoute( page, {
			nav: 'activity',
			nav_sub: 'overview',
			subject: 'ip',
			analyse_ip: fixture.ip,
		} );

		const panel = page.locator( '[data-drill-layer="1"] [data-investigate-panel="1"]' );
		await expectPanelState( page, panel, {
			subject: 'ip',
			isLoaded: true,
		} );

		await expectInlineTabsContract( panel );
		const targetTab = await getInlineTabByTableType( panel, 'activity' );

		await Promise.all( [
			page.waitForResponse( investigationTableResponseMatcher( 'activity' ) ),
			targetTab.click(),
		] );

		await expectActiveInlineTabState( panel, targetTab );
		await expectInvestigationTableInitialized( panel, 'activity' );
		await expectRequestMetaPopover( page, panel, fixture.rid, fixture.expected_meta );
	} );

	await expectNoRuntimeErrors( runtimeErrors, 'Expected no browser runtime errors while opening the IP investigation request-meta popover' );
} );

test( 'investigate landing drill-back clears stale plugin breadcrumbs after a resolved selection', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const panel = page.locator( panelSelector );
	await clickSubjectTile( page, 'plugin' );
	await expectPanelState( page, panel, {
		subject: 'plugin',
		isLoaded: true,
	} );

	await selectSelect2Option(
		page,
		'plugin_slug',
		'shield',
		( url ) => url.searchParams.get( 'subject' ) === 'plugin'
			&& !!url.searchParams.get( 'plugin_slug' )
	);

	const stepTabs = page.locator( '[data-operator-step-tab="1"]' );
	await expect( stepTabs ).toHaveCount( 4 );
	await expect( page.locator( '[data-step-tab-investigate-reset="1"]' ) ).toHaveCount( 1 );

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'nav' ) === 'activity'
				&& url.searchParams.get( 'nav_sub' ) === 'overview'
				&& !url.searchParams.get( 'subject' )
				&& !url.searchParams.get( 'plugin_slug' ),
			{ timeout: 20_000 }
		),
		page.locator( '[data-step-tab-drill-index="0"]' ).click(),
	] );

	await expectPanelState( page, panel, {
		subject: '',
		isLoaded: false,
	} );
	await expect( stepTabs ).toHaveCount( 2 );
	await expect( page.locator( '[data-step-tab-investigate-reset="1"]' ) ).toHaveCount( 0 );
} );

test( 'investigate landing reopens a cached generic user panel with a live select2 lookup', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
		subject: 'user',
	} );

	const panel = page.locator( '[data-drill-layer="1"] [data-investigate-panel="1"]' );
	await expectPanelState( page, panel, {
		subject: 'user',
		isLoaded: true,
	} );
	await expect( panel.locator( 'select[name="user_lookup"]' ) ).toBeVisible();

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'nav' ) === 'activity'
				&& url.searchParams.get( 'nav_sub' ) === 'overview'
				&& !url.searchParams.get( 'subject' ),
			{ timeout: 20_000 }
		),
		page.locator( '[data-step-tab-drill-index="0"]' ).click(),
	] );

	await expectPanelState( page, panel, {
		subject: '',
		isLoaded: false,
	} );

	await clickSubjectTile( page, 'user' );
	await expectPanelState( page, panel, {
		subject: 'user',
		isLoaded: true,
	} );

	await selectSelect2Option(
		page,
		'user_lookup',
		'admin',
		( url ) => url.searchParams.get( 'subject' ) === 'user'
			&& !!url.searchParams.get( 'user_lookup' )
	);

	await expect( page.locator( '[data-step-tab-investigate-reset="1"]' ) ).toHaveCount( 1 );
	await expect( panel.locator( '[data-investigate-panel-header="1"] [data-investigate-subject-header="1"]' ) ).toBeVisible();
} );

test( 'investigate landing preloads generic subjects and keeps live traffic lazy', async ( { page } ) => {
	let batchRequestCount = 0;
	let directUserPanelRequestCount = 0;
	let liveTrafficRequestCount = 0;

	await page.route( '**/admin-ajax.php**', async ( route ) => {
		const request = route.request();
		const actionSlug = requestActionSlug( request );
		if ( isAdminAjaxRequest( request ) && actionSlug === 'ajax_batch_requests' ) {
			batchRequestCount++;
		}
		if ( isAdminAjaxRequest( request ) && actionSlug === 'render_investigate_by_user_panel_body' ) {
			directUserPanelRequestCount++;
		}
		if ( isLiveTrafficPollRequest( request ) ) {
			liveTrafficRequestCount++;
		}
		await route.continue();
	} );

	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	await expect.poll( () => batchRequestCount ).toBeGreaterThan( 0 );
	expect( liveTrafficRequestCount ).toBe( 0 );

	const panel = page.locator( panelSelector );
	await clickSubjectTile( page, 'user' );
	await expectPanelState( page, panel, {
		subject: 'user',
		isLoaded: true,
	} );
	expect( directUserPanelRequestCount ).toBe( 0 );

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'nav' ) === 'activity'
				&& url.searchParams.get( 'nav_sub' ) === 'overview'
				&& !url.searchParams.get( 'subject' ),
			{ timeout: 20_000 }
		),
		page.locator( '[data-step-tab-drill-index="0"]' ).click(),
	] );

	await clickSubjectTile( page, 'live_traffic' );
	await expectPanelState( page, panel, {
		subject: 'live_traffic',
		isLoaded: true,
	} );
	await expect.poll( () => liveTrafficRequestCount ).toBeGreaterThan( 0 );
} );

test( 'investigate landing starts and stops live traffic polling with the live panel', async ( { page } ) => {
	const livePollWindowMs = 5_500;
	let livePollCount = 0;

	await page.route( '**/admin-ajax.php**', async ( route ) => {
		if ( isLiveTrafficPollRequest( route.request() ) ) {
			livePollCount++;
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					success: true,
					data: {
						message: '',
						page_reload: false,
						html: `<div class="live-poll-marker">poll-${livePollCount}</div>`,
					},
				} ),
			} );
			return;
		}
		await route.continue();
	} );

	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const panel = page.locator( panelSelector );
	await clickSubjectTile( page, 'live_traffic' );

	await expectPanelState( page, panel, {
		subject: 'live_traffic',
		isLoaded: true,
	} );
	await expect( panel.locator( '.live-poll-marker' ) ).toHaveCount( 1 );
	expect( livePollCount ).toBe( 1 );

	const maxPollCountAfterExit = livePollCount + 1;

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'nav' ) === 'activity'
				&& url.searchParams.get( 'nav_sub' ) === 'overview'
				&& !url.searchParams.get( 'subject' ),
			{ timeout: 20_000 }
		),
		page.locator( '[data-step-tab-drill-index="0"]' ).click(),
	] );

	await expectPanelState( page, panel, {
		subject: '',
		isLoaded: false,
	} );
	await page.waitForTimeout( livePollWindowMs + 500 );
	expect( livePollCount ).toBeLessThanOrEqual( maxPollCountAfterExit );
	await page.waitForTimeout( livePollWindowMs + 500 );
	expect( livePollCount ).toBeLessThanOrEqual( maxPollCountAfterExit );
} );

test( 'investigate live traffic auth-refresh poll reloads the page from an authenticated admin request', async ( { page } ) => {
	let dialogCount = 0;
	let livePollCount = 0;
	let captureNextLivePoll = false;

	page.on( 'dialog', async ( dialog ) => {
		dialogCount++;
		await dialog.dismiss();
	} );

	const authRefreshContractPromise = new Promise( ( resolve, reject ) => {
		const timeoutId = setTimeout( () => {
			reject( new Error( 'Timed out waiting for the real auth-refresh live traffic response.' ) );
		}, 15_000 );

		page.route( '**/admin-ajax.php**', async ( route ) => {
			if ( !isLiveTrafficPollRequest( route.request() ) ) {
				await route.continue();
				return;
			}

			livePollCount++;
			const response = await route.fetch();
			const body = await response.text();
			const payload = parseWrappedAjaxJson( body );

			if ( captureNextLivePoll && payload?.data?.auth_refresh_required === true ) {
				captureNextLivePoll = false;
				clearTimeout( timeoutId );
				resolve( {
					headers: route.request().headers(),
					payload,
				} );
			}

			await route.fulfill( {
				response,
				body,
			} );
		} ).catch( reject );
	} );

	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const panel = page.locator( panelSelector );
	await clickSubjectTile( page, 'live_traffic' );

	await expectPanelState( page, panel, {
		subject: 'live_traffic',
		isLoaded: true,
	} );
	await expect.poll( () => livePollCount ).toBeGreaterThan( 0 );

	captureNextLivePoll = true;
	await page.context().clearCookies();

	const authRefreshContract = await authRefreshContractPromise;

	expect( authRefreshContract.headers[ 'x-shield-auth-refresh' ] || '' ).toBe( '1' );
	expect( authRefreshContract.payload.success ).toBe( false );
	expect( authRefreshContract.payload.data.auth_refresh_required ).toBe( true );
	expect( authRefreshContract.payload.data.page_reload ).toBe( true );
	expect( authRefreshContract.payload.data.show_toast ).toBe( false );
	expect( authRefreshContract.payload.data.error_code ).toBe( 'user_auth_required' );
	expect( typeof authRefreshContract.payload.data.message ).toBe( 'string' );
	expect( authRefreshContract.payload.data.message.length ).toBeGreaterThan( 0 );
	expect( authRefreshContract.payload.data.error ).toBe( authRefreshContract.payload.data.message );

	await expect.poll( () => page.url() ).toContain( '/wp-login.php' );
	await expect.poll( () => dialogCount ).toBe( 0 );
} );
