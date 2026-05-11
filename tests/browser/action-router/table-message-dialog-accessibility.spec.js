const { test, expect } = require( './support/shield-test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const {
	openShieldRoute,
} = require( './support/shield-browser' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );
const {
	expectFocusWithin,
	expectNamedDialog,
	expectOptionalDescription,
} = require( './support/modal-accessibility' );

const SCAN_RESULTS_TABLE_SELECTOR = '[data-actions-queue-detail="1"] [data-scan-results-table="1"]';
const SCAN_RESULT_DELETE_SELECTOR = '[data-scan-result-action="delete"]';
const DIALOG_ACTIVE_SELECTOR = '[data-shield-accessible-dialog="1"][aria-modal="true"]:not([aria-hidden="true"])';
const DIALOG_SELECTOR = '[data-shield-accessible-dialog="1"]';
const DIALOG_TITLE_SELECTOR = '.shield-accessible-dialog__title';
const CONFIRM_BUTTON_SELECTOR = '.shield-accessible-dialog__confirm';

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

async function waitForStableTable( table ) {
	await expect.poll( async () => table.evaluate( ( element ) => {
		const container = element.closest( '[aria-busy]' );
		return container?.getAttribute( 'aria-busy' ) || '';
	} ), { timeout: 20_000 } ).toBe( 'false' );
}

async function triggerTableReload( table ) {
	await table.evaluate( ( element ) => {
		const datatable = globalThis.jQuery( element ).DataTable();
		datatable.ajax.reload( null, false );
	} );
}

async function expectAccessibleMessageDialog( page ) {
	const dialog = page.locator( DIALOG_ACTIVE_SELECTOR );
	await expect( dialog ).toBeVisible();
	await expectNamedDialog( page, dialog );
	await expectOptionalDescription( page, dialog );
	await expectFocusWithin( dialog );
	return dialog;
}

async function expectDialogTitle( dialog, expectedText, { hidden = false } = {} ) {
	const title = dialog.locator( DIALOG_TITLE_SELECTOR );
	await expect( title ).toHaveText( expectedText );
	if ( hidden ) {
		await expect( title ).toHaveClass( /__title--hidden/ );
	}
	else {
		await expect( title ).not.toHaveClass( /__title--hidden/ );
		await expect( title ).toBeVisible();
	}
}

async function expectNoAxeViolations( page, selector ) {
	const results = await new AxeBuilder( { page } )
	.include( selector )
	.analyze();

	expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
}

function isScanResultDeleteRequest( request, expectedRid ) {
	const params = new URLSearchParams( request.postData() || '' );
	const rids = selectedRidsFromParams( params );
	return request.method() === 'POST'
		&& request.url().includes( '/admin-ajax.php' )
		&& params.get( 'sub_action' ) === 'delete'
		&& rids.includes( expectedRid );
}

function isTableDataRequest( request ) {
	const params = new URLSearchParams( request.postData() || '' );
	return request.method() === 'POST'
		&& request.url().includes( '/admin-ajax.php' )
		&& params.get( 'sub_action' ) === 'retrieve_table_data';
}

function selectedRidsFromParams( params ) {
	return Array.from( params.entries() )
	.filter( ( [ key ] ) => key === 'rids' || key === 'rids[]' || key.startsWith( 'rids[' ) )
	.map( ( [ unusedKey, value ] ) => value );
}

function failedActionResponse() {
	return {
		success: false,
		data: {
			message: 'browser-fixture-table-message',
			show_toast: false,
		},
	};
}

async function interceptFailure( page, matcher ) {
	let requestCount = 0;
	const handler = async ( route ) => {
		if ( matcher( route.request() ) ) {
			requestCount++;
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( failedActionResponse() ),
			} );
			return;
		}
		await route.fallback();
	};

	await page.route( '**/admin-ajax.php*', handler );
	return {
		count: () => requestCount,
		cleanup: () => page.unroute( '**/admin-ajax.php*', handler ).catch( () => null ),
	};
}

test( 'scan results row action failure opens accessible message dialog', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const table = await openDirectScanResultsTable( page, fixture );
		const deleteAction = table.locator( SCAN_RESULT_DELETE_SELECTOR ).first();
		await expect( deleteAction ).toBeVisible();
		const targetRid = await deleteAction.getAttribute( 'data-rid' );
		expect( targetRid || '' ).not.toHaveLength( 0 );

		let nativeDialogCount = 0;
		page.on( 'dialog', async ( dialog ) => {
			nativeDialogCount++;
			await dialog.dismiss();
		} );
		const interceptedAction = await interceptFailure(
			page,
			( request ) => isScanResultDeleteRequest( request, targetRid )
		);

		await deleteAction.click();
		const confirmDialog = await expectAccessibleMessageDialog( page );
		await expectDialogTitle( confirmDialog, 'Confirm Action' );
		await confirmDialog.locator( CONFIRM_BUTTON_SELECTOR ).click();

		const messageDialog = await expectAccessibleMessageDialog( page );
		await expectDialogTitle( messageDialog, 'Request Failed' );
		await expectNoAxeViolations( page, DIALOG_SELECTOR );
		await expect.poll( () => nativeDialogCount ).toBe( 0 );
		await expect.poll( () => interceptedAction.count() ).toBe( 1 );

		await messageDialog.locator( CONFIRM_BUTTON_SELECTOR ).click();
		if ( await deleteAction.evaluate( ( action ) => action.isConnected ) ) {
			await expect( deleteAction ).toBeFocused();
		}

		await interceptedAction.cleanup();
	} );
} );

test( 'simple notice dialog hides its redundant title and keeps a named close button', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'dashboard',
		nav_sub: 'overview',
	} );

	await page.evaluate( () => {
		window.shieldServices.dialog().message( {
			message: 'browser-fixture-simple-notice',
		} );
	} );

	const dialog = await expectAccessibleMessageDialog( page );
	await expectDialogTitle( dialog, 'Notice', { hidden: true } );
	await expect( dialog.locator( CONFIRM_BUTTON_SELECTOR ) ).toHaveText( 'Close' );
	await expectNoAxeViolations( page, DIALOG_SELECTOR );
	await dialog.locator( CONFIRM_BUTTON_SELECTOR ).click();
} );

test( 'scan results table data failure clears busy and opens accessible message dialog', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const table = await openDirectScanResultsTable( page, fixture );
		await waitForStableTable( table );

		let nativeDialogCount = 0;
		page.on( 'dialog', async ( dialog ) => {
			nativeDialogCount++;
			await dialog.dismiss();
		} );
		const interceptedAction = await interceptFailure( page, isTableDataRequest );

		await triggerTableReload( table );
		await expect.poll( () => interceptedAction.count() ).toBe( 1 );
		await waitForStableTable( table );

		const dialog = await expectAccessibleMessageDialog( page );
		await expectNoAxeViolations( page, DIALOG_SELECTOR );
		await expect.poll( () => nativeDialogCount ).toBe( 0 );
		await dialog.locator( CONFIRM_BUTTON_SELECTOR ).click();

		await interceptedAction.cleanup();
	} );
} );
