const { test, expect } = require( './support/shield-test' );
const {
	openShieldRoute,
} = require( './support/shield-browser' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

const SCAN_RESULTS_TABLE_SELECTOR = '[data-actions-queue-detail="1"] [data-scan-results-table="1"]';
const SCAN_RESULT_IGNORE_SELECTOR = '[data-scan-result-action="ignore"]';

async function openDirectScanResultsTable( page, fixture ) {
	const actionsQueuePage = new ActionsQueuePage( page );
	await openShieldRoute( page, {
		nav: 'scans',
		nav_sub: 'overview',
	} );
	await actionsQueuePage.drillToDetail( fixture );

	const table = page.locator( SCAN_RESULTS_TABLE_SELECTOR ).first();
	await waitForScanResultsData( table );
	return table;
}

async function waitForScanResultsData( table ) {
	await expect( table ).toBeVisible();
	await expect.poll( async () => table.evaluate( ( element ) => {
		const jQuery = globalThis.jQuery;
		if ( !jQuery?.fn?.dataTable?.isDataTable?.( element ) ) {
			return 0;
		}
		return jQuery( element ).DataTable().rows().data().toArray().length;
	} ), { timeout: 20_000 } ).toBeGreaterThan( 0 );
}

async function waitForScanResultsTableIdle( table ) {
	await expect.poll( async () => table.evaluate( ( element ) => {
		const container = element.closest( '[aria-busy]' );
		return container?.getAttribute( 'aria-busy' ) === 'true' ? 'busy' : 'ready';
	} ), { timeout: 20_000 } ).toBe( 'ready' );
}

async function firstRowData( table ) {
	return table.evaluate( ( element ) => globalThis.jQuery( element ).DataTable().rows().data().toArray()[ 0 ] );
}

async function setPageLengthAndPage( table, pageLength, pageIndex ) {
	await table.evaluate( ( element, args ) => new Promise( ( resolve ) => {
		const datatable = globalThis.jQuery( element ).DataTable();
		datatable.one( 'draw', () => {
			datatable.one( 'draw', () => resolve() );
			datatable.page( args.pageIndex ).draw( 'page' );
		} );
		datatable.page.len( args.pageLength ).draw();
	} ), { pageLength, pageIndex } );
}

async function datatablePage( table ) {
	return table.evaluate( ( element ) => globalThis.jQuery( element ).DataTable().page() );
}

function isScanResultActionRequest( request, expectedSubAction ) {
	if ( request.method() !== 'POST' || !request.url().includes( '/admin-ajax.php' ) ) {
		return false;
	}

	const params = new URLSearchParams( request.postData() || '' );
	return params.get( 'sub_action' ) === expectedSubAction;
}

function isScanResultsReloadRequest( request ) {
	if ( request.method() !== 'POST' || !request.url().includes( '/admin-ajax.php' ) ) {
		return false;
	}

	const params = new URLSearchParams( request.postData() || '' );
	return params.get( 'sub_action' ) === 'retrieve_table_data';
}

function isScanResultsReloadResponse( response ) {
	return isScanResultsReloadRequest( response.request() );
}

async function interceptScanResultActionAndPagedTableData( page, subAction, rowTemplate ) {
	const retrieveStarts = [];
	const handler = async ( route ) => {
		if ( isScanResultActionRequest( route.request(), subAction ) ) {
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					success: true,
					data: {
						page_reload: false,
						table_reload: true,
						message: '',
					},
				} ),
			} );
			return;
		}

		if ( isScanResultsReloadRequest( route.request() ) ) {
			const params = new URLSearchParams( route.request().postData() || '' );
			const start = Number( params.get( 'table_data[start]' ) || 0 );
			retrieveStarts.push( start );
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					success: true,
					data: {
						datatable_data: {
							data: [
								{
									...rowTemplate,
									rid: start + 1,
								},
							],
							recordsTotal: 2,
							recordsFiltered: 2,
							searchPanes: {},
						},
					},
				} ),
			} );
			return;
		}

		await route.continue();
	};

	await page.route( '**/admin-ajax.php*', handler );
	return {
		retrieveStarts,
		resetRetrieveStarts: () => {
			retrieveStarts.length = 0;
		},
		cleanup: () => page.unroute( '**/admin-ajax.php*', handler ).catch( () => null ),
	};
}

test( 'scan result row action reload keeps current datatable page', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const table = await openDirectScanResultsTable( page, fixture );
		const rowTemplate = await firstRowData( table );
		const intercepted = await interceptScanResultActionAndPagedTableData( page, 'ignore', rowTemplate );

		try {
			await setPageLengthAndPage( table, 1, 1 );
			await expect.poll( () => datatablePage( table ) ).toBe( 1 );
			intercepted.resetRetrieveStarts();

			const actionRequest = page.waitForRequest(
				( request ) => isScanResultActionRequest( request, 'ignore' ),
				{ timeout: 20_000 }
			);
			const reloadResponse = page.waitForResponse( isScanResultsReloadResponse, { timeout: 20_000 } );

			await table.locator( SCAN_RESULT_IGNORE_SELECTOR ).first().click();
			await actionRequest;
			await reloadResponse;
			await waitForScanResultsTableIdle( table );
			await waitForScanResultsData( table );

			await expect.poll( () => datatablePage( table ) ).toBe( 1 );
			expect( intercepted.retrieveStarts ).toEqual( [ 1 ] );
		}
		finally {
			await intercepted.cleanup();
		}
	} );
} );
