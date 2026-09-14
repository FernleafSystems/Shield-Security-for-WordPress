const { openShieldRoute, test, expect } = require( './support/shield-test' );
const { expectNoAxeViolations } = require( './support/accessibility' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );
const {
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
} = require( './support/modal-accessibility' );

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

function isScanItemAnalysisRequest( request ) {
	const postData = request.postData() || '';
	const params = new URLSearchParams( postData );
	return params.get( 'render_slug' ) === 'scanitemanalysis_container';
}

test( 'unavailable scan file download is disabled and skipped by keyboard navigation', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'unavailable_file_direct_table', async ( fixture ) => {
		await openShieldRoute( page, { nav: 'scans', nav_sub: 'overview' } );
		await new ActionsQueuePage( page ).drillToDetail( fixture );
		const table = page.locator( '[data-scan-results-table="1"]' ).first();
		await waitForScanResultsTableRows( table );
		const viewAction = table.locator( 'button[data-scan-result-action="view"]' ).first();
		await viewAction.click();
		const modal = page.locator( '#ShieldModalContainer.modal.show' );
		await expect( modal.locator( '#tabInfo' ) ).toBeVisible();
		const download = modal.locator( '.href-download' );
		await expect( download ).toHaveAccessibleName( /\S/ );
		await expect( download ).toHaveAttribute( 'aria-disabled', 'true' );
		await expect( download ).toHaveAttribute( 'tabindex', '-1' );
		await expect( download ).not.toHaveAttribute( 'disabled' );
		await expectNoAxeViolations( page, '#ShieldModalContainer' );
		for ( const width of [ 1280, 768 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			await modal.locator( '.btn-close' ).focus();
			await page.keyboard.press( 'Shift+Tab' );
			await expect( download ).not.toBeFocused();
			await expect( modal.getByRole( 'tab', { selected: true } ) ).toBeFocused();
			await modal.locator( '.btn-close' ).focus();
			await page.keyboard.press( 'Tab' );
			await expect( modal.getByRole( 'tab', { selected: true } ) ).toBeFocused();
			await test.info().attach( `unavailable-modal-${width}`, {
				body: await page.screenshot( { animations: 'disabled', path: test.info().outputPath( `unavailable-modal-${width}.png` ) } ),
				contentType: 'image/png',
			} );
		}
		await page.keyboard.press( 'Escape' );
		await expect( viewAction ).toBeFocused();
		await viewAction.click();
		await expect( modal.locator( '#tabInfo' ) ).toBeVisible();
		await expect( download ).toHaveAttribute( 'aria-disabled', 'true' );
		await page.keyboard.press( 'Escape' );
		await expect( viewAction ).toBeFocused();
	} );
} );

test( 'scan item analysis keeps shared modal semantics after async content replacement', async ( { page, fixtureApi } ) => {
	await fixtureApi.withLicenseClearFixture( () => fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );
		const table = page.locator( '[data-scan-results-table="1"]' ).first();
		await waitForScanResultsTableRows( table );

		const viewAction = table.locator( 'button[data-scan-result-action="view"]' ).first();
		await expect( viewAction ).toBeVisible();
		await expect( viewAction ).toHaveAttribute( 'type', 'button' );
		expect( await viewAction.getAttribute( 'href' ) ).toBeNull();
		const analysisRequest = page.waitForRequest( isScanItemAnalysisRequest, { timeout: 20_000 } );
		await viewAction.click();
		await analysisRequest;

		const modal = page.locator( '#ShieldModalContainer.modal.show' );
		await expect( modal ).toBeVisible();
		await expectNamedDialog( page, modal );

		await expect( modal.locator( '#tabInfo[role="tabpanel"]' ) ).toBeVisible( { timeout: 20_000 } );
		await expectNamedDialog( page, modal );
		await expectNoAxeViolations( page, '#ShieldModalContainer' );
		const tabs = modal.getByRole( 'tablist' ).getByRole( 'tab' );
		await expect( modal.locator( '#tabMalai-tab' ) ).toBeVisible();
		await tabs.first().focus();
		// Keyboard must work immediately after async insertion, before any tab is clicked.
		await page.keyboard.press( 'ArrowRight' );
		await expect( tabs.nth( 1 ) ).toBeFocused();
		await expect( tabs.nth( 1 ) ).toHaveAttribute( 'aria-selected', 'true' );
		await expect( modal.locator( '#tabContents' ) ).toBeVisible();
		await expectNoAxeViolations( page, '#ShieldModalContainer' );
		await page.keyboard.press( 'ArrowRight' );
		await expect( tabs.nth( 2 ) ).toBeFocused();
		await expect( tabs.nth( 2 ) ).toHaveAttribute( 'aria-selected', 'true' );
		await expect( modal.locator( '#tabDiff' ) ).toBeVisible();
		await expectNoAxeViolations( page, '#ShieldModalContainer' );
		await page.keyboard.press( 'End' );
		await expect( tabs.last() ).toBeFocused();
		await expect( tabs.last() ).toHaveAttribute( 'aria-selected', 'true' );
		await page.keyboard.press( 'Home' );
		await expect( tabs.first() ).toBeFocused();
		await expect( modal.locator( '#tabInfo' ) ).toBeVisible();

		const downloadLink = modal.locator( '.href-download' );
		await expect( downloadLink ).toHaveAccessibleName( /\S/ );
		await expect( downloadLink ).toBeEnabled();
		await modal.locator( '.btn-close' ).focus();
		await page.keyboard.press( 'Shift+Tab' );
		await expect( downloadLink ).toBeFocused();
		const downloadedFile = page.waitForEvent( 'download' );
		await page.keyboard.press( 'Enter' );
		expect( await ( await downloadedFile ).failure() ).toBeNull();
		for ( const width of [ 1280, 768 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			await tabs.first().focus();
			await page.keyboard.press( 'Home' );
			await expect( tabs.first() ).toHaveAttribute( 'aria-selected', 'true' );
			await expect( modal.locator( '#tabInfo' ) ).toBeVisible();
			await downloadLink.focus();
			await expect( downloadLink ).toBeFocused();
			await test.info().attach( `available-info-${width}`, {
				body: await page.screenshot( { animations: 'disabled', path: test.info().outputPath( `available-info-${width}.png` ) } ),
				contentType: 'image/png',
			} );
			await tabs.first().focus();
			await page.keyboard.press( 'ArrowRight' );
			await page.keyboard.press( 'Tab' );
			const code = modal.locator( '#tabContents pre' );
			await expect( code ).toBeFocused();
			if ( await code.evaluate( ( node ) => node.scrollWidth > node.clientWidth ) ) {
				await code.evaluate( ( node ) => { node.scrollLeft = 0; } );
				await page.keyboard.press( 'ArrowRight' );
				await expect.poll( () => code.evaluate( ( node ) => node.scrollLeft ) ).toBeGreaterThan( 0 );
			}
			await test.info().attach( `available-contents-${width}`, {
				body: await page.screenshot( { animations: 'disabled', path: test.info().outputPath( `available-contents-${width}.png` ) } ),
				contentType: 'image/png',
			} );
			await page.keyboard.press( 'Shift+Tab' );
			await expect( tabs.nth( 1 ) ).toBeFocused();
			await page.keyboard.press( 'ArrowRight' );
			await expect( tabs.nth( 2 ) ).toBeFocused();
			await expect( tabs.nth( 2 ) ).toHaveAttribute( 'aria-selected', 'true' );
			await expect( modal.locator( '#tabDiff' ) ).toBeVisible();
			await test.info().attach( `available-diff-${width}`, {
				body: await page.screenshot( { animations: 'disabled', path: test.info().outputPath( `available-diff-${width}.png` ) } ),
				contentType: 'image/png',
			} );
		}

		await modal.locator( '.btn-close' ).click();
		await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
		await expect( viewAction ).toBeFocused();

		await viewAction.click();
		await expect( modal.locator( '#tabInfo' ) ).toBeVisible();
		await tabs.first().focus();
		await page.keyboard.press( 'ArrowRight' );
		await expect( tabs.nth( 1 ) ).toBeFocused();
		await expect( modal.locator( '#tabContents' ) ).toBeVisible();
		await page.keyboard.press( 'Escape' );
		await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
		await expect( viewAction ).toBeFocused();
	} ) );
} );
