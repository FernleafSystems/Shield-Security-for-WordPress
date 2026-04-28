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
const SCAN_RESULT_DELETE_SELECTOR = '[data-scan-result-action="delete"]';
const CONFIRM_DIALOG_SELECTOR = '#AptoGeneralPurposeDialog';
const CONFIRM_DIALOG_ACTIVE_SELECTOR = `${CONFIRM_DIALOG_SELECTOR}[aria-modal="true"]`;
const CONFIRM_BUTTON_SELECTOR = '[data-shield-dialog-confirm="1"]';
const CANCEL_BUTTON_SELECTOR = '[data-shield-dialog-cancel="1"]';

async function openDirectScanResultsTable( page, fixture ) {
	const actionsQueuePage = new ActionsQueuePage( page );
	await openShieldRoute( page, {
		nav: 'scans',
		nav_sub: 'overview',
	} );
	await actionsQueuePage.drillToDetail( fixture );

	const table = page.locator( SCAN_RESULTS_TABLE_SELECTOR ).first();
	await waitForScanResultsTableRows( table );
	return table;
}

async function waitForScanResultsTableRows( table ) {
	await expect( table ).toBeVisible();
	await expect.poll(
		async () => await table.locator( SCAN_RESULT_DELETE_SELECTOR ).count(),
		{ timeout: 20_000 }
	).toBeGreaterThan( 0 );
}

async function waitForStableScanResultsTable( table ) {
	await expect( table ).toBeVisible();
	await expect.poll( async () => {
		const isProcessing = await table.evaluate( ( element ) => {
			const container = element.closest( '[aria-busy]' );
			return container?.getAttribute( 'aria-busy' ) === 'true';
		} );
		return isProcessing ? 'busy' : 'ready';
	}, { timeout: 20_000 } ).toBe( 'ready' );
}

function isScanResultDeleteRequest( request, expectedRid = null ) {
	const params = new URLSearchParams( request.postData() || '' );
	const rids = Array.from( params.entries() )
		.filter( ( [ key ] ) => key === 'rids' || key === 'rids[]' || key.startsWith( 'rids[' ) )
		.map( ( [ , value ] ) => value );
	return request.method() === 'POST'
		&& request.url().includes( '/admin-ajax.php' )
		&& params.get( 'sub_action' ) === 'delete'
		&& ( expectedRid === null || rids.includes( expectedRid ) );
}

test( 'scan results row delete cancel uses accessible confirm without native dialog', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const table = await openDirectScanResultsTable( page, fixture );
		const deleteAction = table.locator( SCAN_RESULT_DELETE_SELECTOR ).first();
		await expect( deleteAction ).toBeVisible();

		let nativeDialogCount = 0;
		let deleteRequestCount = 0;
		page.on( 'dialog', async ( dialog ) => {
			nativeDialogCount++;
			await dialog.dismiss();
		} );
		page.on( 'request', ( request ) => {
			if ( isScanResultDeleteRequest( request ) ) {
				deleteRequestCount++;
			}
		} );

		await deleteAction.click();

		const confirmModal = page.locator( CONFIRM_DIALOG_ACTIVE_SELECTOR );
		await expect( confirmModal ).toBeVisible();
		await expectNamedDialog( page, confirmModal, 'AptoGeneralPurposeDialogTitle' );
		await expectOptionalDescription( page, confirmModal );
		await expect( confirmModal.locator( CANCEL_BUTTON_SELECTOR ) ).toBeFocused();

		await confirmModal.locator( CANCEL_BUTTON_SELECTOR ).click();
		await expectModalHiddenWithoutAriaModal( page, CONFIRM_DIALOG_SELECTOR );
		if ( await deleteAction.evaluate( ( action ) => action.isConnected ) ) {
			await expect( deleteAction ).toBeFocused();
		}
		await expect.poll( () => nativeDialogCount ).toBe( 0 );
		await page.waitForTimeout( 500 );
		expect( deleteRequestCount ).toBe( 0 );
		await expect( table.locator( SCAN_RESULT_DELETE_SELECTOR ).first() ).toBeVisible();
	} );
} );

test( 'scan results row delete confirm uses one accessible confirm for rapid duplicate clicks', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const table = await openDirectScanResultsTable( page, fixture );
		const deleteAction = table.locator( SCAN_RESULT_DELETE_SELECTOR ).first();
		await expect( deleteAction ).toBeVisible();
		const targetRid = await deleteAction.getAttribute( 'data-rid' );
		expect( targetRid || '' ).not.toHaveLength( 0 );

		let nativeDialogCount = 0;
		let deleteRequestCount = 0;
		page.on( 'dialog', async ( dialog ) => {
			nativeDialogCount++;
			await dialog.dismiss();
		} );
		page.on( 'request', ( request ) => {
			if ( isScanResultDeleteRequest( request, targetRid ) ) {
				deleteRequestCount++;
			}
		} );

		const deleteRequest = page.waitForRequest(
			( request ) => isScanResultDeleteRequest( request, targetRid ),
			{ timeout: 20_000 }
		);
		await deleteAction.evaluate( ( action ) => {
			action.click();
			action.click();
		} );

		const confirmModal = page.locator( CONFIRM_DIALOG_ACTIVE_SELECTOR );
		await expect( confirmModal ).toHaveCount( 1 );
		await expect( confirmModal ).toBeVisible();
		await expectNamedDialog( page, confirmModal, 'AptoGeneralPurposeDialogTitle' );

		await confirmModal.locator( CONFIRM_BUTTON_SELECTOR ).click();
		await deleteRequest;

		await expect.poll( () => nativeDialogCount ).toBe( 0 );
		await page.waitForTimeout( 500 );
		expect( deleteRequestCount ).toBe( 1 );
		await waitForStableScanResultsTable( table );
	} );
} );
