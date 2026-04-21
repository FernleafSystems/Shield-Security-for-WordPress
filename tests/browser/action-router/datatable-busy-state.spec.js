const { test, expect } = require( '@playwright/test' );
const {
	openShieldRoute,
	withActionsQueueFixture,
} = require( './support/shield-browser' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

async function delayNextMatchingAdminAjaxRequest( page, requestMatcher, delayMs = 1200 ) {
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
		const shouldDelay = !handled
			&& request.method() === 'POST'
			&& request.url().includes( '/admin-ajax.php' )
			&& requestMatcher( request );

		if ( !shouldDelay ) {
			await route.continue();
			return;
		}

		handled = true;
		startedResolve();

		try {
			await new Promise( ( resolve ) => setTimeout( resolve, delayMs ) );
			const response = await route.fetch();
			const body = await response.body();
			await route.fulfill( { response, body } );
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
	await expect( table.locator( 'tbody td.dataTables_empty' ) ).toHaveCount( 0 );
}

function getDatatableContainer( table ) {
	return table.locator( 'xpath=ancestor::div[contains(@class,"dt-container")]' ).first();
}

async function expectBusyState( container ) {
	await expect( container ).toHaveClass( /shield-table-is-busy/ );
	await expect( container ).toHaveAttribute( 'aria-busy', 'true' );
	await expect( container.locator( '.dt-processing' ) ).toBeVisible();
}

async function expectNotBusyState( container ) {
	await expect( container ).not.toHaveClass( /shield-table-is-busy/ );
	await expect( container ).toHaveAttribute( 'aria-busy', 'false' );
	await expect( container.locator( '.dt-processing' ) ).toBeHidden();
}

function investigationTableRequestMatcher( tableType ) {
	return ( request ) => {
		const postData = request.postData() || '';
		return postData.includes( 'sub_action=retrieve_table_data' )
			&& postData.includes( `table_type=${tableType}` );
	};
}

async function openIpAnalysisOffcanvas( page, ip ) {
	await openShieldRoute( page, {
		nav: 'ips',
		nav_sub: 'rules',
		analyse_ip: ip,
	} );

	const offcanvas = page.locator( '#AptoOffcanvas.show' );
	await expect( offcanvas ).toBeVisible();
	return offcanvas;
}

async function expectInvestigationTableInitialized( table ) {
	await expect( table ).toBeVisible();
	await expect.poll(
		async () => table.evaluate( ( el ) => {
			return !!globalThis.jQuery?.fn?.dataTable?.isDataTable?.( el );
		} ),
		{ timeout: 20_000 }
	).toBe( true );
}

test( 'datatable busy: actions queue reload button marks the direct table busy while the reload request is in flight', async ( { page } ) => {
	await withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );

		const table = page.locator( '[data-actions-queue-detail="1"] [data-scan-results-table="1"]' ).first();
		await waitForScanResultsTableRows( table );

		const container = getDatatableContainer( table );
		await expectNotBusyState( container );

		const delayedRequest = await delayNextMatchingAdminAjaxRequest(
			page,
			( request ) => ( request.postData() || '' ).includes( 'sub_action=retrieve_table_data' )
		);

		await page.locator( '[data-actions-queue-detail="1"] .dt-buttons button' )
			.filter( { hasText: /^Reload Table$/ } )
			.first()
			.click();

		await delayedRequest.started;
		await expectBusyState( container );
		await delayedRequest.completed;

		await waitForScanResultsTableRows( table );
		await expectNotBusyState( container );
	} );
} );

test( 'datatable busy: IP analysis tab loads show busy state from the first investigation-table request', async ( { page } ) => {
	const offcanvas = await openIpAnalysisOffcanvas( page, '198.51.100.20' );
	const activityTab = offcanvas.locator( '[data-investigate-panel-tabs="1"] [data-investigate-panel-tab="1"]' )
		.filter( { hasText: /Activity Log/i } )
		.first();

	const delayedRequest = await delayNextMatchingAdminAjaxRequest(
		page,
		investigationTableRequestMatcher( 'activity' )
	);

	await activityTab.click();
	await delayedRequest.started;

	const table = offcanvas.locator( '.tab-pane.active.show table[data-investigation-table="1"][data-table-type="activity"]' ).first();
	const container = offcanvas.locator( '.tab-pane.active.show div.dt-container' ).first();

	await expectBusyState( container );
	await delayedRequest.completed;

	await expectInvestigationTableInitialized( table );
	await expectNotBusyState( container );
} );

test( 'datatable busy: actions queue display-filter reload marks the visible direct table busy while the datatable request is in flight', async ( { page } ) => {
	await withActionsQueueFixture( 'ignored_plugin_direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );

		const table = page.locator( '[data-actions-queue-detail="1"] [data-scan-results-table="1"]' ).first();
		const container = getDatatableContainer( table );
		const displayCollection = page.locator( '[data-scan-results-display-collection="1"]' ).first();
		const repairedToggle = page.locator( '[data-scan-results-display-filter="1"][data-scan-results-display-option="include_repaired"]' ).first();

		await waitForScanResultsTableRows( table );
		await expect( displayCollection ).toBeVisible();
		await expectNotBusyState( container );

		const delayedRequest = await delayNextMatchingAdminAjaxRequest(
			page,
			( request ) => ( request.postData() || '' ).includes( 'sub_action=retrieve_table_data' )
		);

		await displayCollection.click();
		await repairedToggle.click();
		await delayedRequest.started;
		await expectBusyState( container );
		await delayedRequest.completed;

		const refreshedTable = page.locator( '[data-actions-queue-detail="1"] [data-scan-results-table="1"]' ).first();
		await waitForScanResultsTableRows( refreshedTable );
		await expect( page.locator( '[data-scan-results-display-collection="1"]' ).first() ).toBeVisible();
		await expectNotBusyState( getDatatableContainer( refreshedTable ) );
	} );
} );
