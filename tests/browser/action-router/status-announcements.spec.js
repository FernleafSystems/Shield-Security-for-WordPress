const { test, expect } = require( './support/shield-test' );
const { dismissBlockingDialogs, openShieldRoute } = require( './support/shield-browser' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

test.setTimeout( 180_000 );

async function failNextMatchingAdminAjaxRequest( page, requestMatcher = () => true, delayMs = 350 ) {
	return fulfillNextMatchingAdminAjaxRequest(
		page,
		requestMatcher,
		{
			success: false,
			data: {
				message: 'status-announcement-test-failure',
			},
		},
		delayMs
	);
}

async function fulfillNextMatchingAdminAjaxRequest( page, requestMatcher = () => true, responseBody = {}, delayMs = 350 ) {
	let handled = false;
	let startedResolve;
	let completedResolve;
	let completedReject;

	const started = new Promise( ( resolve ) => {
		startedResolve = resolve;
	} );
	const completed = new Promise( ( resolve, reject ) => {
		completedResolve = resolve;
		completedReject = reject;
	} );

	const handler = async ( route ) => {
		const request = route.request();
		const shouldFulfill = !handled
			&& request.method() === 'POST'
			&& request.url().includes( '/admin-ajax.php' )
			&& requestMatcher( request );

		if ( !shouldFulfill ) {
			await route.continue();
			return;
		}

		handled = true;
		startedResolve();

		try {
			await new Promise( ( resolve ) => setTimeout( resolve, delayMs ) );
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( responseBody ),
			} );
			completedResolve();
		}
		catch ( error ) {
			completedReject( error );
			throw error;
		}
		finally {
			await page.unroute( '**/admin-ajax.php', handler ).catch( () => null );
		}
	};

	await page.route( '**/admin-ajax.php', handler );

	return {
		started,
		completed,
	};
}

async function waitForScanResultsTableRows( table ) {
	await expect( table ).toBeVisible();
	await expect.poll( async () => {
		if ( await table.locator( 'tbody td.dataTables_empty' ).count() > 0 ) {
			return 0;
		}
		return await table.locator( 'tbody tr' ).count();
	}, { timeout: 20_000 } ).toBeGreaterThan( 0 );
}

function getDatatableContainer( table ) {
	return table.locator( 'xpath=ancestor::div[contains(@class,"dt-container")]' ).first();
}

async function expectStatusAnnouncement( context, politeness ) {
	const region = context.locator( '[data-shield-status-region="1"]' ).first();
	await expect( region ).toHaveAttribute( 'aria-live', politeness );
	await expect.poll( async () => {
		return ( await region.textContent() || '' ).trim().length;
	}, { timeout: 10_000 } ).toBeGreaterThan( 0 );
}

function datatableRequestMatcher( request ) {
	const postData = request.postData() || '';
	return postData.includes( 'sub_action=retrieve_table_data' );
}

function dashboardMonitorRequestMatcher( request ) {
	const postData = request.postData() || '';
	return postData.includes( 'render_dashboard_live_monitor_ticker' )
		|| postData.includes( 'render_traffic_live_logs' );
}

function dashboardMonitorBatchRequestMatcher( request ) {
	const postData = request.postData() || '';
	return postData.includes( 'ajax_batch_requests' )
		&& postData.includes( 'render_dashboard_live_monitor_ticker' )
		&& postData.includes( 'render_traffic_live_logs' );
}

function trafficLiveLogsRequestMatcher( request ) {
	return ( request.postData() || '' ).includes( 'render_traffic_live_logs' );
}

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

test( 'datatable status region announces busy and failed refresh states', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );

		const table = page.locator( '[data-actions-queue-detail="1"] [data-scan-results-table="1"]' ).first();
		await waitForScanResultsTableRows( table );

		const container = getDatatableContainer( table );
		const failedRequest = await failNextMatchingAdminAjaxRequest( page, datatableRequestMatcher );

		await page.locator( '[data-actions-queue-detail="1"] .dt-buttons button.table-refresh' ).first().click();
		await failedRequest.started;
		await expectStatusAnnouncement( container, 'polite' );
		await failedRequest.completed;
		await expectStatusAnnouncement( container, 'assertive' );
	} );
} );

test( 'dashboard live monitor announces failed poll through stable status region', async ( { page } ) => {
	await page.setViewportSize( { width: 1500, height: 1000 } );
	await openShieldRoute( page, {
		nav: 'dashboard',
		nav_sub: 'overview',
	} );
	await dismissBlockingDialogs( page );
	await setDashboardLiveMonitorCollapsed( page, false );

	const failedRequest = await failNextMatchingAdminAjaxRequest( page, dashboardMonitorRequestMatcher );
	await page.reload( { waitUntil: 'domcontentloaded' } );
	await dismissBlockingDialogs( page );

	const liveMonitor = page.locator( '[data-dashboard-live-monitor="1"]' );
	await expect( liveMonitor ).toBeVisible();
	await failedRequest.completed;
	await expectStatusAnnouncement( liveMonitor, 'assertive' );
} );

test( 'dashboard live monitor announces partial batch failures assertively', async ( { page } ) => {
	await page.setViewportSize( { width: 1500, height: 1000 } );
	await openShieldRoute( page, {
		nav: 'dashboard',
		nav_sub: 'overview',
	} );
	await dismissBlockingDialogs( page );
	await setDashboardLiveMonitorCollapsed( page, false );

	const partialFailureRequest = await fulfillNextMatchingAdminAjaxRequest(
		page,
		dashboardMonitorBatchRequestMatcher,
		{
			success: true,
			data: {
				message: '',
				results: {
					ticker: {
						success: false,
						status_code: 500,
						error: 'status-announcement-test-failure',
						data: {
							success: false,
							message: 'status-announcement-test-failure',
							error: 'status-announcement-test-failure',
							html: '',
						},
					},
					traffic: {
						success: true,
						status_code: 200,
						data: {
							success: true,
							html: '',
						},
					},
				},
			},
		}
	);
	await page.reload( { waitUntil: 'domcontentloaded' } );
	await dismissBlockingDialogs( page );

	const liveMonitor = page.locator( '[data-dashboard-live-monitor="1"]' );
	await expect( liveMonitor ).toBeVisible();
	await partialFailureRequest.completed;
	await expectStatusAnnouncement( liveMonitor, 'assertive' );
} );

test( 'traffic live logs page announces failed poll through stable status region', async ( { page } ) => {
	const failedRequest = await failNextMatchingAdminAjaxRequest( page, trafficLiveLogsRequestMatcher );
	await openShieldRoute( page, {
		nav: 'traffic',
		nav_sub: 'live',
	} );

	const section = page.locator( '#SectionTrafficLiveLogs' );
	await expect( section ).toBeVisible();
	await failedRequest.completed;
	await expectStatusAnnouncement( section, 'assertive' );
} );

test( 'actions queue lazy asset panel announces quiet loading failure', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'file_locker_lazy', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );
		const panel = actionsQueuePage.assetPanel( fixture.panel_target );
		await expect( panel ).toHaveAttribute( 'data-actions-queue-asset-panel-lazy', '1' );
		await expect( panel ).toHaveAttribute( 'data-actions-queue-asset-panel-loaded', '0' );

		const failedRequest = await failNextMatchingAdminAjaxRequest( page );
		await actionsQueuePage.openAssetPanel( fixture.panel_target );
		await failedRequest.started;
		await expectStatusAnnouncement( panel, 'polite' );
		await failedRequest.completed;
		await expectStatusAnnouncement( panel, 'assertive' );
		await expect( panel ).toHaveAttribute( 'data-actions-queue-asset-panel-loaded', '0' );
	} );
} );
