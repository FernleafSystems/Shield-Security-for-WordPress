const { test, expect } = require( './support/shield-test' );
const { expectNoAxeViolations } = require( './support/accessibility' );
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
	isLiveTrafficPollRequest,
	liveTrafficToggleRequest,
	parseShieldAjaxJson,
} = require( './support/security-assertions' );

const panelSelector = '[data-investigate-panel="1"]';

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

const livePanelSelector = '[data-investigate-tabpanel="live"]';

/**
 * The landing opens subjects from the lookup's results. A subject's own panel, with its picker, opens by
 * link (as pivots and older links do).
 */
const openSubject = async ( page, subject ) => {
	await openShieldRoute( page, { nav: 'activity', nav_sub: 'overview', subject } );
};

const expectRoute = async ( page, nav, subNav ) => {
	await expect.poll( () => {
		const url = new URL( page.url() );
		return `${url.searchParams.get( 'nav' )}/${url.searchParams.get( 'nav_sub' )}`;
	} ).toBe( `${nav}/${subNav}` );
};

/**
 * Live traffic has its own tab, with its own link.
 */
const openLive = async ( page ) => {
	await page.locator( '[data-investigate-tab="live"]' ).click();
	await expect( page.locator( '[data-investigate-tab="live"]' ) ).toHaveAttribute( 'aria-current', 'page' );
	await expect( page.locator( `${livePanelSelector} #SectionTrafficLiveLogs` ) ).toBeVisible();
	await expectRoute( page, 'traffic', 'live' );
};

const lookupResult = async ( page, kind, search ) => {
	await page.locator( `[data-lookup-kind="${kind}"]` ).click();
	await page.locator( '[data-lookup-input="1"]' ).fill( search );
	const result = page.locator( `.investigate-lookup__row[data-investigate-subject="${kind}"]` ).first();
	await expect( result ).toBeVisible( { timeout: 20_000 } );
	return result;
};

const expectTrailAtInvestigate = async ( page ) => {
	await expect( page.locator( '[data-operator-step-tab="1"]' ) ).toHaveCount( 2 );
	await expect( page.locator( '[data-step-tab-investigate-reset="1"]' ) ).toHaveCount( 0 );
};

test( 'a subject opened by link offers its picker and the self shortcut, and another is found with a new search', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const panel = page.locator( panelSelector );
	await openSubject( page, 'user' );

	await expectPanelState( page, panel, {
		subject: 'user',
		isLoaded: true,
	} );
	await expect( panel.locator( 'select[name="user_lookup"]' ) ).toBeVisible();

	const selfLookupShortcut = panel.locator( '[data-investigate-panel-content="1"] a[href*="user_lookup="]' ).first();
	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'subject' ) === 'user'
				&& !!url.searchParams.get( 'user_lookup' ),
			{ timeout: 20_000 }
		),
		selfLookupShortcut.click(),
	] );

	await expect( panel.locator( '[data-investigate-panel-header="1"] [data-investigate-subject-header="1"]' ) ).toBeVisible();
	await expectTrailAtInvestigate( page );
	await expectNoAxeViolations( page, '#PageContainer-Apto' );

	// There's no "Change user": another user is found with a new search, which puts this one away.
	await expect( panel.locator( '[data-investigate-change-subject="1"]' ) ).toHaveCount( 0 );
	await page.locator( '[data-lookup-kind="user"]' ).click();
	await expectPanelState( page, panel, {
		subject: '',
		isLoaded: false,
	} );
	await expect( page.locator( '.investigate-lookup__row[data-investigate-subject="user"]' ).first() ).toBeVisible();
} );

test( 'the lookup stays above an open subject, and a new search puts the subject away', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const panel = page.locator( panelSelector );
	const results = page.locator( '[data-lookup-results="1"]' );
	const result = await lookupResult( page, 'user', 'admin' );
	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'subject' ) === 'user' && !!url.searchParams.get( 'user_lookup' ),
			{ timeout: 20_000 }
		),
		result.click(),
	] );
	await expectPanelState( page, panel, {
		subject: 'user',
		isLoaded: true,
	} );
	await expect( panel.locator( '[data-investigate-panel-header="1"] [data-investigate-subject-header="1"]' ) ).toBeVisible();
	await expect( results ).toBeHidden();
	await expect( page.locator( '[data-lookup-input="1"]' ) ).toBeVisible();
	await expectTrailAtInvestigate( page );

	// Searching again puts the subject away for the results, and choosing one opens it.
	const plugin = await lookupResult( page, 'plugin', 'shield' );
	await expect( page.locator( '[data-investigate-landing="1"] [data-drill-layer-key="panel"]' ) ).toHaveAttribute( 'aria-hidden', 'true' );
	await plugin.click();
	await expectPanelState( page, panel, {
		subject: 'plugin',
		isLoaded: true,
	} );
	await expect( results ).toBeHidden();
	await expectTrailAtInvestigate( page );
} );

test( 'investigate lookup opens each kind of subject into the shared panel', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const panel = page.locator( panelSelector );
	const subjectHeader = '[data-investigate-panel-header="1"] [data-investigate-subject-header="1"]';
	const cases = [
		{ kind: 'user', search: 'admin' },
		{ kind: 'plugin', search: 'shield' },
		{ kind: 'theme', search: '' },
	];

	for ( const { kind, search } of cases ) {
		const result = await lookupResult( page, kind, search );
		await Promise.all( [
			page.waitForURL( ( url ) => url.searchParams.get( 'subject' ) === kind, { timeout: 20_000 } ),
			result.click(),
		] );
		await expectPanelState( page, panel, {
			subject: kind,
			isLoaded: true,
		} );
		await expect( panel.locator( subjectHeader ).first() ).toBeVisible();
	}

	// WordPress core is one thing, so choosing it opens it straight away.
	await page.locator( '[data-lookup-kind="core"]' ).click();
	await expectPanelState( page, panel, {
		subject: 'core',
		isLoaded: true,
	} );
	await expect( panel.locator( '#tabInvestigateCoreOverview' ) ).toBeVisible();
	await expectTrailAtInvestigate( page );

	// Choosing another kind puts WordPress core away.
	await page.locator( '[data-lookup-kind="user"]' ).click();
	await expectPanelState( page, panel, {
		subject: '',
		isLoaded: false,
	} );
	await expect( page.locator( '.investigate-lookup__row[data-investigate-subject="user"]' ).first() ).toBeVisible();

	// IP addresses are too many to list, so they wait for a search.
	await page.locator( '[data-lookup-kind="ip"]' ).click();
	await expect( page.locator( '[data-lookup-status="1"]' ) ).toHaveText( /\S/ );
	await expect( page.locator( '.investigate-lookup__row' ) ).toHaveCount( 0 );

	await openLive( page );
	await expect( page.locator( '[data-lookup-input="1"]' ) ).toBeHidden();

	await page.locator( '[data-investigate-tab="lookup"]' ).click();
	await expect( page.locator( '[data-investigate-tab="lookup"]' ) ).toHaveAttribute( 'aria-current', 'page' );
	await expect( page.locator( '[data-lookup-input="1"]' ) ).toBeVisible();
	await expectRoute( page, 'activity', 'overview' );
} );

test( 'investigate tabs open the logs and IP rules in place, each loading when first opened', async ( { page } ) => {
	const tableRequests = new Map();
	await page.route( '**/admin-ajax.php**', async ( route ) => {
		const body = route.request().postData() || '';
		for ( const slug of [ 'logtable_action', 'traffictable_action', 'iprulestable_action', 'sessionstable_action' ] ) {
			if ( body.includes( slug ) ) {
				tableRequests.set( slug, ( tableRequests.get( slug ) || 0 ) + 1 );
			}
		}
		await route.continue();
	} );

	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );
	const pageUrl = page.url();
	await page.waitForTimeout( 1_000 );
	expect( [ ...tableRequests.values() ].reduce( ( a, b ) => a + b, 0 ) ).toBe( 0 );

	// Each tab's link is the one the menu has always used.
	const tabs = [
		[ 'activity', '#ShieldTable-ActivityLog', 'activity/logs' ],
		[ 'requests', '#ShieldTable-TrafficViewer', 'traffic/logs' ],
		[ 'ip_rules', '#ShieldTable-IpRules', 'ips/rules' ],
		[ 'sessions', '#ShieldTable-SessionsViewer', 'activity/sessions' ],
	];
	for ( const [ tab, table, route ] of tabs ) {
		await page.locator( `[data-investigate-tab="${tab}"]` ).click();
		await expect( page.locator( `[data-investigate-tab="${tab}"]` ) ).toHaveAttribute( 'aria-current', 'page' );
		await expect( page.locator( `[data-investigate-tabpanel="${tab}"]` ) ).toBeVisible();
		await expect.poll( () => page.locator( table ).evaluate(
			( element ) => Boolean( globalThis.jQuery?.fn?.dataTable?.isDataTable?.( element ) )
		), { timeout: 20_000 } ).toBe( true );
		await expectRoute( page, ...route.split( '/' ) );
	}

	// Tabs switch in place, and a table already loaded isn't loaded again.
	expect( page.url().split( '?' )[ 0 ] ).toBe( pageUrl.split( '?' )[ 0 ] );
	const activityRequests = tableRequests.get( 'logtable_action' ) || 0;
	await page.locator( '[data-investigate-tab="lookup"]' ).click();
	await expect( page.locator( '[data-lookup-input="1"]' ) ).toBeVisible();
	await page.locator( '[data-investigate-tab="activity"]' ).click();
	await expect( page.locator( '#ShieldTable-ActivityLog' ) ).toBeVisible();
	expect( tableRequests.get( 'logtable_action' ) || 0 ).toBe( activityRequests );

	// A link to a tab opens it directly, and the menu's link to a tab opens it in place.
	await openShieldRoute( page, { nav: 'activity', nav_sub: 'sessions' } );
	await expect( page.locator( '[data-investigate-tab="sessions"]' ) ).toHaveAttribute( 'aria-current', 'page' );
	await expect( page.locator( '[data-investigate-tabpanel="sessions"]' ) ).toBeVisible();
	const menuLink = page.locator( '#NavSideBar .sidebar-tools a.tool-item[href*="nav=ips"][href*="nav_sub=rules"]' ).first();
	if ( await menuLink.count() > 0 ) {
		await menuLink.click();
		await expect( page.locator( '[data-investigate-tab="ip_rules"]' ) ).toHaveAttribute( 'aria-current', 'page' );
		await expect( menuLink ).toHaveClass( /active/ );
		await expectRoute( page, 'ips', 'rules' );
	}
} );

test( 'investigate landing deep link opens the IP panel with its context', async ( { page } ) => {
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
	await expect( panel.locator( '[data-investigate-panel-header="1"] [data-investigate-subject-header="1"]' ) ).toBeVisible();
	await expect( page.locator( '[data-lookup-input="1"]' ) ).toBeVisible();
	await expectTrailAtInvestigate( page );

	const rail = page.locator( '[data-operator-context-rail="1"]' );
	await expect( rail ).toBeHidden();

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

test( 'a subject picked in its own panel keeps the trail at Investigate', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const panel = page.locator( panelSelector );
	await openSubject( page, 'user' );
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

	await expect( panel.locator( '[data-investigate-panel-header="1"] [data-investigate-subject-header="1"]' ) ).toBeVisible();
	await expectTrailAtInvestigate( page );
} );

test( 'investigate landing loads a subject only when it is opened, and live traffic only from its tab', async ( { page } ) => {
	let userPanelRequestCount = 0;
	let liveTrafficRequestCount = 0;

	await page.route( '**/admin-ajax.php**', async ( route ) => {
		const request = route.request();
		if ( isAdminAjaxRequest( request ) && ( request.postData() || '' ).includes( 'render_investigate_by_user_panel_body' ) ) {
			userPanelRequestCount++;
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
	await page.waitForTimeout( 1_500 );
	expect( userPanelRequestCount ).toBe( 0 );
	expect( liveTrafficRequestCount ).toBe( 0 );

	const panel = page.locator( panelSelector );
	const result = await lookupResult( page, 'user', 'admin' );
	await result.click();
	await expectPanelState( page, panel, {
		subject: 'user',
		isLoaded: true,
	} );
	expect( userPanelRequestCount ).toBeGreaterThan( 0 );
	expect( liveTrafficRequestCount ).toBe( 0 );

	await openLive( page );
	await expect.poll( () => liveTrafficRequestCount ).toBeGreaterThan( 0 );
} );

test( 'investigate live traffic polls only while its tab is open, keeping what it showed', async ( { page } ) => {
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

	const live = page.locator( livePanelSelector );
	await openLive( page );
	await expect( live.locator( '.live-poll-marker' ) ).toHaveCount( 1 );
	expect( livePollCount ).toBe( 1 );

	const maxPollCountAfterExit = livePollCount + 1;
	await page.locator( '[data-investigate-tab="lookup"]' ).click();
	await expectRoute( page, 'activity', 'overview' );
	await page.waitForTimeout( livePollWindowMs + 500 );
	expect( livePollCount ).toBeLessThanOrEqual( maxPollCountAfterExit );
	await page.waitForTimeout( livePollWindowMs + 500 );
	expect( livePollCount ).toBeLessThanOrEqual( maxPollCountAfterExit );

	// Back on the tab, what it last showed is still there while it picks up again.
	const pollsBeforeReturn = livePollCount;
	await page.locator( '[data-investigate-tab="live"]' ).click();
	await expect( live.locator( '.live-poll-marker' ) ).toHaveCount( 1 );
	await expect.poll( () => livePollCount ).toBeGreaterThan( pollsBeforeReturn );
} );

test( 'investigate landing direct live traffic route starts one live poller', async ( { page } ) => {
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
		nav: 'traffic',
		nav_sub: 'live',
	} );

	const live = page.locator( livePanelSelector );
	await expect( page.locator( '[data-investigate-tab="live"]' ) ).toHaveAttribute( 'aria-current', 'page' );
	await expect( live.locator( '.live-poll-marker' ) ).toHaveCount( 1 );
	expect( livePollCount ).toBe( 1 );
	await page.waitForTimeout( 750 );
	expect( livePollCount ).toBe( 1 );
} );

test( 'investigate live traffic panel toggle updates without page reload', async ( { page, fixtureApi } ) => {
	await fixtureApi.withLiveTrafficToggleFixture( async ( contract ) => {
		await page.route( '**/admin-ajax.php**', async ( route ) => {
			if ( isLiveTrafficPollRequest( route.request() ) ) {
				await route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( {
						success: true,
						data: {
							message: '',
							page_reload: false,
							html: '<div class="live-poll-marker">poll</div>',
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

		await openLive( page );
		const toggle = page.locator( livePanelSelector ).locator( contract.selectors.toggle );
		await expect( toggle ).toBeEnabled();
		await expect( toggle ).not.toBeChecked();
		const livePanelUrl = page.url();

		const enableResponse = page.waitForResponse(
			( response ) => liveTrafficToggleRequest( contract.action_slug, 'Y' )( response.request() )
		);
		await toggle.check();
		const enablePayload = parseShieldAjaxJson( await ( await enableResponse ).text() );
		expect( enablePayload ).toHaveProperty( 'success', true );
		expect( enablePayload.data.page_reload ).toBe( false );
		await page.waitForTimeout( 2_300 );
		expect( page.url() ).toBe( livePanelUrl );
		await expect( toggle ).toBeChecked();

		const inspected = await fixtureApi.inspectLiveTrafficToggleFixture();
		expect( inspected.state.enable_live_log ).toBe( 'Y' );
		expect( inspected.state.live_log_started_at ).toBeGreaterThan( 0 );
	} );
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

	await openLive( page );
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
