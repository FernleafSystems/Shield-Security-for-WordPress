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

/**
 * The landing opens subjects from the lookup's results. A subject's own panel, with its picker, opens by
 * link (as pivots and older links do), and Live traffic opens from its tab.
 */
const openSubject = async ( page, subject ) => {
	if ( subject !== 'live_traffic' ) {
		await openShieldRoute( page, { nav: 'activity', nav_sub: 'overview', subject } );
		return;
	}
	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'nav' ) === 'activity'
				&& url.searchParams.get( 'nav_sub' ) === 'overview'
				&& url.searchParams.get( 'subject' ) === subject,
			{ timeout: 20_000 }
		),
		page.locator( '[data-investigate-tab="live"]' ).click(),
	] );
};

const lookupResult = async ( page, kind, search ) => {
	await page.locator( `[data-lookup-kind="${kind}"]` ).click();
	await page.locator( '[data-lookup-input="1"]' ).fill( search );
	const result = page.locator( `.investigate-lookup__row[data-investigate-subject="${kind}"]` ).first();
	await expect( result ).toBeVisible( { timeout: 20_000 } );
	return result;
};

const backToLanding = async ( page ) => {
	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'nav' ) === 'activity'
				&& url.searchParams.get( 'nav_sub' ) === 'overview'
				&& !url.searchParams.get( 'subject' ),
			{ timeout: 20_000 }
		),
		page.locator( '[data-step-tab-drill-index="0"]' ).click(),
	] );
};

test( 'investigate user reset uses the shared generic panel path and self shortcut', async ( { page } ) => {
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
	await expectNoAxeViolations( page, '#PageContainer-Apto' );

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

	// Choosing another user goes back to the lookup, listing users.
	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'nav' ) === 'activity'
				&& url.searchParams.get( 'nav_sub' ) === 'overview'
				&& !url.searchParams.get( 'subject' ),
			{ timeout: 20_000 }
		),
		panel.locator( '[data-investigate-change-subject="1"]' ).click(),
	] );

	await expectPanelState( page, panel, {
		subject: '',
		isLoaded: false,
	} );
	await expect( page.locator( '[data-lookup-kind="user"]' ) ).toHaveAttribute( 'aria-pressed', 'true' );
	await expect( page.locator( '.investigate-lookup__row[data-investigate-subject="user"]' ).first() ).toBeVisible();
} );

test( 'returning to investigate keeps the lookup and focuses the result that was opened', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const panel = page.locator( panelSelector );
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

	await backToLanding( page );
	await expectPanelState( page, panel, {
		subject: '',
		isLoaded: false,
	} );
	await expect( page.locator( '[data-lookup-input="1"]' ) ).toHaveValue( 'admin' );
	await expect( result ).toBeFocused();
} );

test( 'investigate lookup opens each kind of subject into the shared panel', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const panel = page.locator( panelSelector );
	const subjectHeader = '[data-investigate-panel-header="1"] [data-investigate-subject-header="1"]';
	const cases = [
		{ kind: 'user', search: 'admin', content: subjectHeader },
		{ kind: 'plugin', search: 'shield', content: subjectHeader },
		{ kind: 'theme', search: '', content: subjectHeader },
		{ kind: 'core', search: '', content: '#tabInvestigateCoreOverview' },
	];

	for ( const { kind, search, content } of cases ) {
		const result = await lookupResult( page, kind, search );
		await Promise.all( [
			page.waitForURL( ( url ) => url.searchParams.get( 'subject' ) === kind, { timeout: 20_000 } ),
			result.click(),
		] );
		await expectPanelState( page, panel, {
			subject: kind,
			isLoaded: true,
		} );
		await expect( panel.locator( content ).first() ).toBeVisible();
		await backToLanding( page );
		await expectPanelState( page, panel, {
			subject: '',
			isLoaded: false,
		} );
	}

	await openSubject( page, 'live_traffic' );
	await expectPanelState( page, panel, {
		subject: 'live_traffic',
		isLoaded: true,
	} );
	await expect( panel.locator( '#SectionTrafficLiveLogs' ) ).toBeVisible();
	await expect( page.locator( '[data-investigate-tab="live"]' ) ).toHaveAttribute( 'aria-current', 'page' );
	await page.locator( '[data-investigate-tab="lookup"]' ).click();
	await expectPanelState( page, panel, {
		subject: '',
		isLoaded: false,
	} );
	await expect( page.locator( '[data-investigate-tab="lookup"]' ) ).toHaveAttribute( 'aria-current', 'page' );
} );

test( 'investigate tabs lead to the logs, which carry the same tabs', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const logs = [ [ 'activity', 'activity', 'logs' ], [ 'requests', 'traffic', 'logs' ], [ 'sessions', 'activity', 'sessions' ] ];
	for ( const [ tab, nav, subnav ] of logs ) {
		await Promise.all( [
			page.waitForURL( ( url ) => url.searchParams.get( 'nav' ) === nav && url.searchParams.get( 'nav_sub' ) === subnav, { timeout: 20_000 } ),
			page.locator( `[data-investigate-tab="${tab}"]` ).click(),
		] );
		await expect( page.locator( `[data-investigate-tab="${tab}"]` ) ).toHaveAttribute( 'aria-current', 'page' );
	}

	await Promise.all( [
		page.waitForURL( ( url ) => url.searchParams.get( 'nav' ) === 'activity' && url.searchParams.get( 'nav_sub' ) === 'overview', { timeout: 20_000 } ),
		page.locator( '[data-investigate-tab="lookup"]' ).click(),
	] );
	await expect( page.locator( '[data-lookup-input="1"]' ) ).toBeVisible();
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
	await openSubject( page, 'plugin' );
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

	await expect( page.locator( '[data-step-tab-investigate-reset="1"]' ) ).toHaveCount( 1 );
	await expect( panel.locator( '[data-investigate-panel-header="1"] [data-investigate-subject-header="1"]' ) ).toBeVisible();
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

	await backToLanding( page );
	await openSubject( page, 'live_traffic' );
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
	await openSubject( page, 'live_traffic' );

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
		nav: 'activity',
		nav_sub: 'overview',
		subject: 'live_traffic',
	} );

	const panel = page.locator( panelSelector );
	await expectPanelState( page, panel, {
		subject: 'live_traffic',
		isLoaded: true,
	} );
	await expect( panel.locator( '.live-poll-marker' ) ).toHaveCount( 1 );
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

		const panel = page.locator( panelSelector );
		await openSubject( page, 'live_traffic' );
		await expectPanelState( page, panel, {
			subject: 'live_traffic',
			isLoaded: true,
		} );

		const toggle = panel.locator( contract.selectors.toggle );
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

	const panel = page.locator( panelSelector );
	await openSubject( page, 'live_traffic' );

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
