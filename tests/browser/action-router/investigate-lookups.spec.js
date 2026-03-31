const { test, expect } = require( '@playwright/test' );
const {
	openShieldRoute,
	selectSelect2Option,
} = require( './support/shield-browser' );

const panelSelector = '[data-investigate-panel="1"]';

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
		/Shield/i,
		( url ) => url.searchParams.get( 'subject' ) === 'plugin'
			&& !!url.searchParams.get( 'plugin_slug' )
	);

	const stepTabs = page.locator( '[data-operator-step-tab="1"]' );
	await expect( stepTabs ).toHaveCount( 4 );
	await expect( page.locator( '[data-step-tab-investigate-reset="1"]' ) ).toHaveCount( 1 );
	const resolvedLabel = ( await stepTabs.last().textContent() || '' ).trim();
	expect( resolvedLabel.length ).toBeGreaterThan( 0 );

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
	await expect( stepTabs.filter( { hasText: resolvedLabel } ) ).toHaveCount( 0 );
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
		/admin/i,
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
	const isAdminAjaxRequest = ( request ) => request.url().includes( '/admin-ajax.php' );

	await page.route( '**/admin-ajax.php**', async ( route ) => {
		const request = route.request();
		const postData = request.postData() || '';
		if ( isAdminAjaxRequest( request ) && postData.includes( 'ex=ajax_batch_requests' ) ) {
			batchRequestCount++;
		}
		if ( isAdminAjaxRequest( request )
			&& postData.includes( 'ex=render_investigate_by_user_panel_body' )
			&& !postData.includes( 'ex=ajax_batch_requests' ) ) {
			directUserPanelRequestCount++;
		}
		if ( isAdminAjaxRequest( request ) && postData.includes( 'render_traffic_live_logs' ) ) {
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
	const isLiveTrafficPollRequest = ( request ) => {
		const postData = request.postData() || '';
		return request.url().includes( '/admin-ajax.php' )
			&& postData.includes( 'render_traffic_live_logs' );
	};

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
	await expect( panel.locator( '.live-poll-marker' ) ).toHaveText( 'poll-1' );

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
