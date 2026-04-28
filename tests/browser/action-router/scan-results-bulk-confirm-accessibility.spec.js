const { test, expect } = require( './support/shield-test' );
const {
	openShieldRoute,
} = require( './support/shield-browser' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );
const {
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
	expectOptionalDescription,
} = require( './support/modal-accessibility' );

const SCAN_RESULTS_TABLE_SELECTOR = '[data-actions-queue-detail="1"] [data-scan-results-table="1"]';
const CONFIRM_DIALOG_SELECTOR = '#AptoGeneralPurposeDialog';
const CONFIRM_DIALOG_ACTIVE_SELECTOR = `${CONFIRM_DIALOG_SELECTOR}[aria-modal="true"]`;
const CONFIRM_BUTTON_SELECTOR = '[data-shield-dialog-confirm="1"]';
const CANCEL_BUTTON_SELECTOR = '[data-shield-dialog-cancel="1"]';

async function openDirectScanResultsTable( page, fixture, { includeIgnored = false } = {} ) {
	const actionsQueuePage = new ActionsQueuePage( page );
	await openShieldRoute( page, {
		nav: 'scans',
		nav_sub: 'overview',
	} );
	await actionsQueuePage.drillToDetail( fixture );

	const table = page.locator( SCAN_RESULTS_TABLE_SELECTOR ).first();
	if ( includeIgnored ) {
		await showIgnoredResults( page );
	}
	await waitForScanResultsData( table );
	return table;
}

async function showIgnoredResults( page ) {
	const displayCollection = page.locator( '[data-scan-results-display-collection="1"]' ).first();
	const ignoredToggle = page.locator( '[data-scan-results-display-filter="1"][data-scan-results-display-option="include_ignored"]' ).first();
	const tableReload = page.waitForRequest(
		( request ) => ( request.postData() || '' ).includes( 'sub_action=retrieve_table_data' ),
		{ timeout: 20_000 }
	);

	await expect( displayCollection ).toBeVisible();
	await displayCollection.click();
	await expect( ignoredToggle ).toBeVisible();
	await ignoredToggle.click();
	await tableReload;
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

async function selectScanResultsRows( table, rowState ) {
	return table.evaluate( ( element, state ) => {
		const datatable = globalThis.jQuery( element ).DataTable();
		datatable.rows().deselect();
		datatable.rows( ( unusedIndex, data ) => {
			if ( state === 'ignored' ) {
				return Boolean( data?.is_ignored );
			}
			return !Boolean( data?.is_ignored );
		} ).select();

		return datatable.rows( { selected: true } ).data().toArray()
		.map( ( data ) => String( data?.rid || '' ) )
		.filter( ( rid ) => rid.length > 0 );
	}, rowState );
}

async function clickDatatableButton( table, buttonName, clickCount = 1 ) {
	return table.evaluate( ( element, args ) => {
		const datatable = globalThis.jQuery( element ).DataTable();
		const buttonNode = datatable.button( args.buttonName ).node().get( 0 );
		if ( !( buttonNode instanceof HTMLElement ) ) {
			throw new Error( `Missing DataTables button: ${args.buttonName}` );
		}
		for ( let i = 0; i < args.clickCount; i++ ) {
			buttonNode.click();
		}
	}, { buttonName, clickCount } );
}

async function focusIsOnDatatableButton( table, buttonName ) {
	return table.evaluate( ( element, name ) => {
		const datatable = globalThis.jQuery( element ).DataTable();
		const buttonNode = datatable.button( name ).node().get( 0 );
		return buttonNode instanceof HTMLElement && document.activeElement === buttonNode;
	}, buttonName );
}

function scanResultsBulkActionMatcher( expectedSubAction, expectedRids = [] ) {
	return ( request ) => {
		if ( request.method() !== 'POST' || !request.url().includes( '/admin-ajax.php' ) ) {
			return false;
		}

		const params = new URLSearchParams( request.postData() || '' );
		const rids = selectedRidsFromParams( params );
		return params.get( 'sub_action' ) === expectedSubAction
			&& expectedRids.every( ( rid ) => rids.includes( String( rid ) ) );
	};
}

function selectedRidsFromParams( params ) {
	return Array.from( params.entries() )
	.filter( ( [ key ] ) => key === 'rids' || key === 'rids[]' || key.startsWith( 'rids[' ) )
	.map( ( [ unusedKey, value ] ) => value );
}

async function interceptBulkAction( page, expectedSubAction, expectedRids ) {
	let requestCount = 0;
	const matcher = scanResultsBulkActionMatcher( expectedSubAction, expectedRids );
	const handler = async ( route ) => {
		if ( matcher( route.request() ) ) {
			requestCount++;
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					success: true,
					data: {
						page_reload: false,
						table_reload: false,
						message: '',
					},
				} ),
			} );
			return;
		}
		await route.continue();
	};

	await page.route( '**/admin-ajax.php*', handler );

	return {
		requestCount: () => requestCount,
		waitForRequest: () => page.waitForRequest( matcher, { timeout: 20_000 } ),
		cleanup: () => page.unroute( '**/admin-ajax.php*', handler ).catch( () => null ),
	};
}

async function assertAccessibleConfirmVisible( page ) {
	const confirmModal = page.locator( CONFIRM_DIALOG_ACTIVE_SELECTOR );
	await expect( confirmModal ).toBeVisible();
	await expectNamedDialog( page, confirmModal, 'AptoGeneralPurposeDialogTitle' );
	await expectOptionalDescription( page, confirmModal );
	return confirmModal;
}

test( 'scan results bulk ignore cancel uses accessible confirm without native dialog', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const table = await openDirectScanResultsTable( page, fixture );
		const selectedRids = await selectScanResultsRows( table, 'active' );
		expect( selectedRids.length ).toBeGreaterThan( 0 );

		let nativeDialogCount = 0;
		page.on( 'dialog', async ( dialog ) => {
			nativeDialogCount++;
			await dialog.dismiss();
		} );
		const interceptedAction = await interceptBulkAction( page, 'ignore', selectedRids );

		await clickDatatableButton( table, 'selected-ignore:name' );

		const confirmModal = await assertAccessibleConfirmVisible( page );
		await confirmModal.locator( CANCEL_BUTTON_SELECTOR ).click();
		await expectModalHiddenWithoutAriaModal( page, CONFIRM_DIALOG_SELECTOR );
		await expect.poll( () => nativeDialogCount ).toBe( 0 );
		await expect.poll( () => interceptedAction.requestCount(), { timeout: 1000 } ).toBe( 0 );
		await expect.poll( () => focusIsOnDatatableButton( table, 'selected-ignore:name' ) ).toBe( true );

		await interceptedAction.cleanup();
	} );
} );

test( 'scan results bulk ignore confirm sends selected active row ids', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const table = await openDirectScanResultsTable( page, fixture );
		const selectedRids = await selectScanResultsRows( table, 'active' );
		expect( selectedRids.length ).toBeGreaterThan( 0 );

		let nativeDialogCount = 0;
		page.on( 'dialog', async ( dialog ) => {
			nativeDialogCount++;
			await dialog.dismiss();
		} );
		const interceptedAction = await interceptBulkAction( page, 'ignore', selectedRids );
		const actionRequest = interceptedAction.waitForRequest();

		await clickDatatableButton( table, 'selected-ignore:name' );
		const confirmModal = await assertAccessibleConfirmVisible( page );
		await confirmModal.locator( CONFIRM_BUTTON_SELECTOR ).click();
		await actionRequest;

		await expect.poll( () => nativeDialogCount ).toBe( 0 );
		await expect.poll( () => interceptedAction.requestCount() ).toBe( 1 );

		await interceptedAction.cleanup();
	} );
} );

test( 'scan results bulk unignore confirm handles selected ignored row ids', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'ignored_plugin_direct_table', async ( fixture ) => {
		const table = await openDirectScanResultsTable( page, fixture, { includeIgnored: true } );
		const selectedRids = await selectScanResultsRows( table, 'ignored' );
		expect( selectedRids.length ).toBeGreaterThan( 0 );

		let nativeDialogCount = 0;
		page.on( 'dialog', async ( dialog ) => {
			nativeDialogCount++;
			await dialog.dismiss();
		} );
		const interceptedAction = await interceptBulkAction( page, 'unignore', selectedRids );

		await clickDatatableButton( table, 'selected-unignore:name' );
		let confirmModal = await assertAccessibleConfirmVisible( page );
		await confirmModal.locator( CANCEL_BUTTON_SELECTOR ).click();
		await expectModalHiddenWithoutAriaModal( page, CONFIRM_DIALOG_SELECTOR );
		await expect.poll( () => interceptedAction.requestCount(), { timeout: 1000 } ).toBe( 0 );
		await expect.poll( () => focusIsOnDatatableButton( table, 'selected-unignore:name' ) ).toBe( true );

		const actionRequest = interceptedAction.waitForRequest();
		await clickDatatableButton( table, 'selected-unignore:name' );
		confirmModal = await assertAccessibleConfirmVisible( page );
		await confirmModal.locator( CONFIRM_BUTTON_SELECTOR ).click();
		await actionRequest;

		await expect.poll( () => nativeDialogCount ).toBe( 0 );
		await expect.poll( () => interceptedAction.requestCount() ).toBe( 1 );

		await interceptedAction.cleanup();
	} );
} );

test( 'scan results bulk repair-delete uses one accessible danger confirm for duplicate clicks', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const table = await openDirectScanResultsTable( page, fixture );
		const selectedRids = await selectScanResultsRows( table, 'active' );
		expect( selectedRids.length ).toBeGreaterThan( 0 );

		let nativeDialogCount = 0;
		page.on( 'dialog', async ( dialog ) => {
			nativeDialogCount++;
			await dialog.dismiss();
		} );
		const interceptedAction = await interceptBulkAction( page, 'repair-delete', selectedRids );
		const actionRequest = interceptedAction.waitForRequest();

		await clickDatatableButton( table, 'selected-repair:name', 2 );
		const confirmModal = await assertAccessibleConfirmVisible( page );
		await expect( confirmModal ).toHaveCount( 1 );
		await expect( confirmModal.locator( CANCEL_BUTTON_SELECTOR ) ).toBeFocused();
		await confirmModal.locator( CONFIRM_BUTTON_SELECTOR ).click();
		await actionRequest;

		await expect.poll( () => nativeDialogCount ).toBe( 0 );
		await expect.poll( () => interceptedAction.requestCount() ).toBe( 1 );

		await interceptedAction.cleanup();
	} );
} );
