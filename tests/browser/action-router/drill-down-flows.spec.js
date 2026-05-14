const { openShieldRoute, test, expect } = require( './support/shield-test' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );
const {
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
	expectOptionalDescription,
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

async function waitForScanResultsTableEmpty( table ) {
	await expect( table ).toBeVisible();
	await expect.poll( async () => {
		const bodyRows = table.locator( 'tbody tr' );
		if ( await bodyRows.count() !== 1 ) {
			return false;
		}

		const emptyRow = bodyRows.first();
		return await emptyRow.locator( 'td' ).count() === 1
			&& await emptyRow.getAttribute( 'data-scan-result-ignored' ) === null;
	}, { timeout: 20_000 } ).toBe( true );
	await expect( table.locator( 'tbody tr[data-scan-result-ignored]' ) ).toHaveCount( 0 );
}

async function scanResultsDisplayOptions( table ) {
	let options = null;
	await expect.poll( async () => {
		const rawOptions = await table.getAttribute( 'data-results-display-options' );
		if ( !rawOptions ) {
			return false;
		}
		try {
			options = JSON.parse( rawOptions );
			return true;
		}
		catch ( error ) {
			return false;
		}
	}, { timeout: 20_000 } ).toBe( true );
	return options;
}

async function expectScanResultsDisplayOptions( table, expectedOptions ) {
	const options = await scanResultsDisplayOptions( table );
	expect( options ).toMatchObject( expectedOptions );
}

async function delay( milliseconds ) {
	return new Promise( ( resolve ) => setTimeout( resolve, milliseconds ) );
}

function isConfigureSearchRequest( request, searchTerm ) {
	const postData = request.postData() || '';
	const params = new URLSearchParams( postData );
	return params.get( 'render_slug' ) === 'render_configure_search_results'
		&& params.get( 'search' ) === searchTerm;
}

function isConfigureSearchResponse( response, searchTerm ) {
	return isConfigureSearchRequest( response.request(), searchTerm );
}

function isConfigureDiagnosisDirectRequest( request, zoneKey ) {
	const postData = request.postData() || '';
	const params = new URLSearchParams( postData );
	return params.get( 'render_slug' ) === 'configure_drill_down_diagnosis'
		&& params.get( 'zone' ) === zoneKey;
}

function isIgnoreAllRequest( request ) {
	const params = new URLSearchParams( request.postData() || '' );
	return request.method() === 'POST'
		&& request.url().includes( '/admin-ajax.php' )
		&& params.get( 'sub_action' ) === 'ignore_all';
}

function isFileLockerActionRequest( request, expectedPayload ) {
	if ( request.method() !== 'POST' || !request.url().includes( '/admin-ajax.php' ) ) {
		return false;
	}

	const params = new URLSearchParams( request.postData() || '' );
	return params.get( 'action' ) === 'shield_action'
		&& params.get( 'ex' ) === 'filelocker_fileaction'
		&& Object.entries( expectedPayload ).every( ( [ key, value ] ) => params.get( key ) === String( value ) );
}

async function operatorContextAjaxAction( rail, matcher ) {
	const actions = rail.locator( '[data-operator-context-action-ajax="1"]' );
	const count = await actions.count();

	for ( let index = 0; index < count; index++ ) {
		const candidate = actions.nth( index );
		const actionJson = await candidate.getAttribute( 'data-operator-context-action-json' );
		if ( !actionJson ) {
			continue;
		}

		try {
			const action = JSON.parse( actionJson );
			if ( matcher( action ) ) {
				return candidate;
			}
		}
		catch ( error ) {
		}
	}

	return null;
}

async function hasOperatorContextAjaxAction( rail, matcher ) {
	return ( await operatorContextAjaxAction( rail, matcher ) ) !== null;
}

test( 'actions queue drills into groups and back out, opening details when available', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		const bucket = await actionsQueuePage.waitForBucket( fixture.bucket_key );
		await expect( bucket ).toBeVisible();

		await expect( page.locator( '[data-actions-landing="1"] [data-healthy-disclosure-toggle="1"]' ) ).toHaveCount( 0 );
		await expect( page.locator( '[data-actions-landing="1"] [data-healthy-disclosure-body="1"]' ) ).toHaveCount( 0 );

		await actionsQueuePage.clickElement( bucket );
		await expect( page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();
		await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );
		const actionTabs = page.locator( '[data-operator-step-tab="1"]' );
		await expect( actionTabs ).toHaveCount( 3 );
		await expect( actionTabs.first() ).toHaveAttribute( 'data-color-key', 'home' );
		await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__eyebrow' ) ).toHaveCount( 0 );
		await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__title' ) ).toBeVisible();

		const group = await actionsQueuePage.waitForGroupWithRetry( bucket, fixture.group_key );
		if ( group === null ) {
			throw new Error( `Unable to locate Actions Queue group "${fixture.group_key}" in the groups layer.` );
		}
		await actionsQueuePage.clickElement( group );
		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
		await expect( page.locator( '[data-drill-layer="1"]' ) ).toHaveClass( /drill-layer--compact/ );
		await waitForScanResultsTableRows( page.locator( '[data-scan-results-table="1"]' ).first() );

		await page.locator( '[data-step-tab-drill-index="1"]' ).click();
		await expect( page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();
		await expect( page.locator( '[data-drill-layer="2"]' ) ).toHaveClass( /drill-layer--hidden/ );

		await page.locator( '[data-step-tab-drill-index="0"]' ).click();
		await expect( page.locator( '[data-actions-landing="1"] [data-drill-target="groups"]' ).first() ).toBeVisible();
	} );
} );

test( 'actions queue warning breadcrumb uses warning palette', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'file_locker_lazy', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );

		const activeBreadcrumb = page.locator( '[data-operator-step-tab="1"][aria-current="step"]' );
		await expect( activeBreadcrumb ).toHaveAttribute( 'data-color-key', 'warning' );
		await expect.poll(
			async () => activeBreadcrumb.evaluate( ( el ) => window.getComputedStyle( el ).backgroundColor )
		).toBe( 'rgb(237, 180, 29)' );
		await expect.poll(
			async () => activeBreadcrumb.evaluate( ( el ) => window.getComputedStyle( el ).color )
		).toBe( 'rgb(29, 35, 39)' );
	} );
} );

test( 'configure renders zones directly, drills into diagnosis, and drills back out', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
	} );

	await expect( page.locator( '[data-healthy-disclosure-toggle="1"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-healthy-disclosure-body="1"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' ).first() ).toBeVisible();

	const zone = page.locator(
		'[data-configure-landing="1"] [data-drill-target="diagnosis"][data-drill-zone-selection*="\\"key\\":\\"secadmin\\""]'
	).first();
	await expect( zone ).toBeVisible();

	await zone.click();
	await expect( page.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();
	await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );
	const configureTabs = page.locator( '[data-operator-step-tab="1"]' );
	await expect( configureTabs ).toHaveCount( 3 );
	await expect( configureTabs.first() ).toHaveAttribute( 'data-color-key', 'home' );
	await expect( page.locator( '[data-step-tab-drill-index="0"]' ) ).toBeVisible();
	await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__title' ) ).toBeVisible();
	await expect( page.locator( '[data-configure-diagnosis="1"] [data-drill-target="editor"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-configure-diagnosis="1"] .zone-summary-header' ) ).toHaveCount( 0 );
	const expandRow = page.locator( '[data-configure-diagnosis="1"] [data-shield-expand-row="1"]' ).first();
	const expandButton = expandRow.locator( '[data-shield-expand-trigger="1"]' );
	await expect( expandRow ).not.toHaveAttribute( 'role', 'button' );
	await expect( expandRow ).not.toHaveAttribute( 'tabindex', '0' );
	await expect( expandButton.locator( '.shield-detail-row__expand-cta' ) ).toBeVisible();
	await expect( expandButton ).toHaveAttribute( 'aria-expanded', 'false' );
	await expandButton.click();
	await expect( expandButton ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( page.locator( '[data-configure-diagnosis="1"] .shield-detail-expansion.show' ).first() ).toBeVisible();
	const visibleExpandedForms = page.locator( '[data-configure-diagnosis="1"] .shield-detail-expansion.show form.options_form_for' );
	await expect( visibleExpandedForms.first() ).toBeVisible();
	await page.locator( '[data-configure-diagnosis="1"] .shield-detail-expansion.show .shield-detail-expansion__btn-save' )
		.first()
		.click();
	await expect( visibleExpandedForms ).toHaveCount( 0, { timeout: 20_000 } );
	await expect( page.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();
	await expect( configureTabs ).toHaveCount( 3 );
	await expect( page.locator( '[data-step-tab-drill-index="0"]' ) ).toBeVisible();
	await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__title' ) ).toBeVisible();
	const refreshedExpandRow = page.locator( '[data-configure-diagnosis="1"] [data-shield-expand-row="1"]' ).first();
	const refreshedExpandButton = refreshedExpandRow.locator( '[data-shield-expand-trigger="1"]' );
	await expect( refreshedExpandButton.locator( '.shield-detail-row__expand-cta' ) ).toBeVisible();
	await refreshedExpandButton.click();
	await expect( refreshedExpandButton ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( visibleExpandedForms.first() ).toBeVisible();
	await refreshedExpandRow.locator( '.shield-detail-row__title' ).click();
	await expect( refreshedExpandButton ).toHaveAttribute( 'aria-expanded', 'false' );
	await expect( visibleExpandedForms ).toHaveCount( 0 );
	await refreshedExpandRow.locator( '.shield-detail-row__title' ).click();
	await expect( refreshedExpandButton ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( visibleExpandedForms.first() ).toBeVisible();
	await refreshedExpandButton.press( 'Enter' );
	await expect( refreshedExpandButton ).toHaveAttribute( 'aria-expanded', 'false' );
	await expect( visibleExpandedForms ).toHaveCount( 0 );
	await refreshedExpandButton.press( 'Space' );
	await expect( refreshedExpandButton ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( visibleExpandedForms.first() ).toBeVisible();
	await expect( page.locator( '[data-configure-diagnosis="1"] [data-healthy-disclosure-toggle="1"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-configure-diagnosis="1"] [data-healthy-disclosure-body="1"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-configure-diagnosis="1"] .shield-detail-row' ).first() ).toBeVisible();

	await page.locator( '[data-step-tab-drill-index="0"]' ).click();
	await expect( page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' ).first() ).toBeVisible();
} );

test( 'configure opens a prefetched diagnosis without a standalone diagnosis request', async ( { page } ) => {
	let directDiagnosisRequestCount = 0;

	await page.route( '**/admin-ajax.php*', async ( route ) => {
		if ( isConfigureDiagnosisDirectRequest( route.request(), 'secadmin' ) ) {
			directDiagnosisRequestCount++;
		}

		await route.continue();
	} );

	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
	} );
	await page.waitForLoadState( 'networkidle' );

	const zone = page.locator(
		'[data-configure-landing="1"] [data-drill-target="diagnosis"][data-drill-zone-selection*="\\"key\\":\\"secadmin\\""]'
	).first();
	await expect( zone ).toBeVisible();

	await zone.click();
	await expect( page.locator( '[data-configure-diagnosis="1"][data-configure-zone="secadmin"]' ) ).toBeVisible();
	expect( directDiagnosisRequestCount ).toBe( 0 );
} );

test( 'configure search keeps the newest results and deep-links into the matching option', async ( { page } ) => {
	await page.route( '**/admin-ajax.php*', async ( route ) => {
		const request = route.request();
		if ( isConfigureSearchRequest( request, 'scan frequency' ) ) {
			await delay( 800 );
		}
		else if ( isConfigureSearchRequest( request, 'comments cooldown' ) ) {
			await delay( 50 );
		}

		await route.continue();
	} );

	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
	} );

	const searchInput = page.locator( '[data-configure-search-input="1"]' );
	const searchDock = page.locator( '[data-configure-search-dock="1"]' );
	const searchBody = page.locator( '[data-configure-search-body="1"]' );
	const firstZoneCard = page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' ).first();
	await expect( searchInput ).toBeVisible();
	await expect( searchDock ).toHaveAttribute( 'data-configure-search-state', 'idle' );
	const zoneTopBefore = ( await firstZoneCard.boundingBox() )?.y || 0;

	const firstSearchRequest = page.waitForRequest( ( request ) => isConfigureSearchRequest( request, 'scan frequency' ) );
	const firstSearchResponse = page.waitForResponse( ( response ) => isConfigureSearchResponse( response, 'scan frequency' ) );
	await searchInput.fill( 'scan frequency' );
	await firstSearchRequest;
	await expect( searchDock ).toHaveAttribute( 'data-configure-search-state', 'loading' );
	await expect( searchBody ).toHaveAttribute( 'aria-busy', 'true' );

	const secondSearchRequest = page.waitForRequest( ( request ) => isConfigureSearchRequest( request, 'comments cooldown' ) );
	const secondSearchResponse = page.waitForResponse( ( response ) => isConfigureSearchResponse( response, 'comments cooldown' ) );
	await searchInput.fill( 'comments cooldown' );
	await secondSearchRequest;
	await Promise.all( [ firstSearchResponse, secondSearchResponse ] );

	const optionResult = page.locator( '[data-configure-search-results="1"] a[href*="config_item=comments_cooldown"]' )
		.first();
	await expect( optionResult ).toBeVisible( { timeout: 20_000 } );
	await expect( optionResult ).toBeVisible();
	await expect( searchDock ).toHaveAttribute( 'data-configure-search-state', 'ready' );
	await expect( searchBody ).toHaveAttribute( 'aria-busy', 'false' );
	await expect(
		page.locator( '[data-configure-search-results="1"] a[href*="config_item=scan_frequency"]' )
	).toHaveCount( 0 );
	await expect( optionResult.locator( '.configure-search-results__icon i' ) ).toHaveClass( /bi/ );
	await expect( optionResult.locator( '.configure-search-results__type' ) ).toHaveClass( /configure-search-results__type--option/ );
	const zoneTopAfter = ( await firstZoneCard.boundingBox() )?.y || 0;
	expect( Math.abs( zoneTopAfter - zoneTopBefore ) ).toBeLessThan( 2 );
	await expect( optionResult ).toHaveAttribute( 'href', /row_key=general_settings/ );
	await expect( optionResult ).toHaveAttribute( 'href', /config_item=comments_cooldown/ );
	const optionHref = await optionResult.getAttribute( 'href' );
	const targetUrl = new URL( optionHref, 'https://example.test' );
	const targetRowKey = targetUrl.searchParams.get( 'row_key' ) || '';
	expect( targetRowKey ).toBe( 'general_settings' );
	await page.evaluate( () => {
		window.__configureSearchSentinel = 'in-place-search';
	} );

	await optionResult.click();

	await expect( page.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();
	const targetExpansion = page.locator( `[data-configure-row-key="${targetRowKey}"] [data-shield-expand-body="1"]` );
	await expect( targetExpansion ).toHaveClass( /show/, { timeout: 20_000 } );
	await expect.poll( () => new URL( page.url() ).searchParams.get( 'zone' ) || '' ).toBe( 'spam' );
	await expect(
		page.locator( `[data-configure-row-key="${targetRowKey}"] form.options_form_for [name="comments_cooldown"]` ).first()
	).toBeVisible( { timeout: 20_000 } );
	expect( await page.evaluate( () => window.__configureSearchSentinel ) ).toBe( 'in-place-search' );
	await expect( searchInput ).toHaveValue( '' );
	await expect( searchDock ).toHaveAttribute( 'data-configure-search-state', 'idle' );
	await expect.poll( () => new URL( page.url() ).searchParams.get( 'row_key' ) || '' ).toBe( '' );
	await expect.poll( () => new URL( page.url() ).searchParams.get( 'config_item' ) || '' ).toBe( '' );

	await page.reload();
	await expect( page.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();
	await expect( page.locator( `[data-configure-row-key="${targetRowKey}"] [data-shield-expand-body="1"].show` ) ).toHaveCount( 0 );
} );

test( 'actions queue keeps the same ignored-plugin direct table after the shared table success event', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'ignored_plugin_direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );
		const rail = page.locator( '[data-operator-context-rail="1"]' );
		const scanResultsTable = page.locator( '[data-scan-results-table="1"]' ).first();
		await expect( rail ).toBeVisible();
		await expect( rail ).toHaveAccessibleName( /\S/ );
		await expect( scanResultsTable ).toBeVisible();
		await waitForScanResultsTableEmpty( scanResultsTable );
		await scanResultsTable.evaluate( ( table ) => {
			table.dispatchEvent( new CustomEvent( 'shield:table-action-success', {
				bubbles: true,
			} ) );
		} );

		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
		await expect( rail ).toBeVisible( { timeout: 20_000 } );
		await expect( page.locator( '[data-mode-shell="1"][data-mode="actions_queue_assets"]' ) ).toHaveCount( 0 );
		await expect( scanResultsTable ).toBeVisible();
	} );
} );

test( 'actions queue ignores all results from the context rail and refreshes the direct table in place', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );
		const rail = page.locator( '[data-operator-context-rail="1"]' );
		const ignoreAllAction = await operatorContextAjaxAction(
			rail,
			( action ) => action?.sub_action === 'ignore_all'
		);
		const scanResultsTable = page.locator( '[data-scan-results-table="1"]' ).first();
		const displayCollection = page.locator( '[data-scan-results-display-collection="1"]' ).first();
		const ignoredFilter = page.locator( '[data-scan-results-display-filter="1"][data-scan-results-display-option="include_ignored"]' ).first();
		await expect( rail ).toBeVisible();
		await expect( rail ).toHaveAccessibleName( /\S/ );

		await waitForScanResultsTableRows( scanResultsTable );
		expect( ignoreAllAction ).not.toBeNull();
		await expect( displayCollection ).toBeVisible();
		await displayCollection.click();
		await ignoredFilter.click();
		await waitForScanResultsTableRows( scanResultsTable );

		let nativeDialogCount = 0;
		let ignoreAllRequestCount = 0;
		page.on( 'dialog', async ( dialog ) => {
			nativeDialogCount++;
			await dialog.dismiss();
		} );
		page.on( 'request', ( request ) => {
			if ( isIgnoreAllRequest( request ) ) {
				ignoreAllRequestCount++;
			}
		} );

		await ignoreAllAction.evaluate( ( action ) => {
			action.click();
			action.click();
		} );
		const confirmModal = page.locator( '[data-shield-accessible-dialog="1"][aria-modal="true"]:not([aria-hidden="true"])' );
		await expect( confirmModal ).toBeVisible();
		await expectNamedDialog( page, confirmModal );
		await expectOptionalDescription( page, confirmModal );
		await expect( confirmModal.locator( '.shield-accessible-dialog__cancel' ) ).toBeFocused();

		await confirmModal.locator( '.shield-accessible-dialog__cancel' ).click();
		await expectModalHiddenWithoutAriaModal( page, '[data-shield-accessible-dialog="1"]' );
		await expect( ignoreAllAction ).toBeFocused();
		await expect.poll( () => nativeDialogCount ).toBe( 0 );
		await expect.poll( () => ignoreAllRequestCount, { timeout: 1000 } ).toBe( 0 );
		await expect.poll(
			async () => await hasOperatorContextAjaxAction(
				rail,
				( action ) => action?.sub_action === 'ignore_all'
			),
			{ timeout: 1000 }
		).toBe( true );

		const ignoreAllRequest = page.waitForRequest( isIgnoreAllRequest, { timeout: 20_000 } );
		const requestsBeforeConfirm = ignoreAllRequestCount;
		await ignoreAllAction.evaluate( ( action ) => {
			action.click();
			action.click();
		} );
		await expect( confirmModal ).toBeVisible();
		await expectNamedDialog( page, confirmModal );
		await confirmModal.locator( '.shield-accessible-dialog__confirm' ).click();
		await ignoreAllRequest;

		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
		await expect.poll( () => nativeDialogCount ).toBe( 0 );
		await expect.poll( () => ignoreAllRequestCount ).toBe( requestsBeforeConfirm + 1 );
		await expect.poll(
			async () => await hasOperatorContextAjaxAction(
				rail,
				( action ) => action?.sub_action === 'ignore_all'
			),
			{ timeout: 20_000 }
		).toBe( false );
		await expect( rail ).toBeVisible( { timeout: 20_000 } );
		await expect( displayCollection ).toBeVisible();
		await expect( scanResultsTable ).toHaveAttribute( 'data-results-display-options', /"include_ignored":true/, { timeout: 20_000 } );
		await expect( page.locator( '[data-mode-shell="1"][data-mode="actions_queue_assets"]' ) ).toHaveCount( 0 );
		await waitForScanResultsTableRows( scanResultsTable );
		await expect( scanResultsTable.locator( '[data-scan-result-ignored-badge="1"]' ) ).toHaveCount( 1 );
		await expect( scanResultsTable.locator( 'tbody tr[data-scan-result-ignored="1"]' ) ).toHaveCount( 1 );
	} );
} );

test( 'actions queue ignores all malware results from the context rail without replacing the direct table', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'malware_direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );
		const rail = page.locator( '[data-operator-context-rail="1"]' );
		const ignoreAllAction = await operatorContextAjaxAction(
			rail,
			( action ) => action?.sub_action === 'ignore_all'
		);
		const scanResultsTable = page.locator( '[data-actions-queue-detail="1"] [data-scan-results-table="1"]' ).first();

		await waitForScanResultsTableRows( scanResultsTable );
		expect( ignoreAllAction ).not.toBeNull();

		const ignoreAllRequest = page.waitForRequest( isIgnoreAllRequest, { timeout: 20_000 } );
		await ignoreAllAction.click();
		const confirmModal = page.locator( '[data-shield-accessible-dialog="1"][aria-modal="true"]:not([aria-hidden="true"])' );
		await expect( confirmModal ).toBeVisible();
		await confirmModal.locator( '.shield-accessible-dialog__confirm' ).click();
		await ignoreAllRequest;

		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
		await expect( page.locator( '[data-actions-queue-detail="1"] .shield-scan-pane-empty' ) ).toHaveCount( 0 );
		await expect( scanResultsTable ).toBeVisible();
		await expect( scanResultsTable ).toHaveAttribute( 'data-results-display-options', /"ignored_only":false/, { timeout: 20_000 } );
		await waitForScanResultsTableEmpty( scanResultsTable );
		await expect.poll(
			async () => await hasOperatorContextAjaxAction(
				rail,
				( action ) => action?.sub_action === 'ignore_all'
			),
			{ timeout: 20_000 }
		).toBe( false );
	} );
} );

[
	{ name: 'plugin', fixture: 'ignored_plugin_direct_table' },
	{ name: 'theme', fixture: 'ignored_theme_direct_table' },
	{ name: 'WordPress', fixture: 'ignored_wordpress_direct_table' },
	{ name: 'malware', fixture: 'ignored_malware_direct_table' },
].forEach( ( scenario ) => {
	test( `actions queue ${ scenario.name } direct table starts active-only when all scoped results are ignored`, async ( { page, fixtureApi } ) => {
		await fixtureApi.withActionsQueueFixture( scenario.fixture, async ( fixture ) => {
			const actionsQueuePage = new ActionsQueuePage( page );
			await openShieldRoute( page, {
				nav: 'scans',
				nav_sub: 'overview',
			} );

			await actionsQueuePage.drillToDetail( fixture );
			const scanResultsTable = page.locator( '[data-scan-results-table="1"]' ).first();
			const displayCollection = page.locator( '[data-scan-results-display-collection="1"]' ).first();
			const ignoredToggle = page.locator( '[data-scan-results-display-filter="1"][data-scan-results-display-option="include_ignored"]' ).first();

			await waitForScanResultsTableEmpty( scanResultsTable );
			await expect( displayCollection ).toBeVisible();
			await expectScanResultsDisplayOptions( scanResultsTable, {
				ignored_only: false,
				include_ignored: false,
				include_repaired: false,
				include_deleted: false,
			} );
			await expect( scanResultsTable.locator( '[data-scan-result-ignored-badge="1"]' ) ).toHaveCount( 0 );

			const showIgnoredReload = page.waitForRequest(
				( request ) => ( request.postData() || '' ).includes( 'sub_action=retrieve_table_data' ),
				{ timeout: 20_000 }
			);
			await displayCollection.click();
			await expect( ignoredToggle ).toBeVisible();
			await ignoredToggle.click();
			await showIgnoredReload;
			await waitForScanResultsTableRows( scanResultsTable );
			await expectScanResultsDisplayOptions( scanResultsTable, {
				include_ignored: true,
				ignored_only: false,
			} );
			await expect( scanResultsTable.locator( 'tbody tr[data-scan-result-ignored="1"]' ) ).toHaveCount( 2 );

			const hideIgnoredReload = page.waitForRequest(
				( request ) => ( request.postData() || '' ).includes( 'sub_action=retrieve_table_data' ),
				{ timeout: 20_000 }
			);
			await ignoredToggle.click();
			await hideIgnoredReload;

			await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
			await expect( page.locator( '[data-mode-shell="1"][data-mode="actions_queue_assets"]' ) ).toHaveCount( 0 );
			await waitForScanResultsTableEmpty( scanResultsTable );
			await expect( scanResultsTable.locator( '[data-scan-result-ignored-badge="1"]' ) ).toHaveCount( 0 );
			await expectScanResultsDisplayOptions( scanResultsTable, {
				include_ignored: false,
				ignored_only: false,
			} );
			await expect( displayCollection ).toBeVisible();
			await expect( page.locator( '[data-actions-queue-retry]' ) ).toHaveCount( 0 );
			await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
		} );
	} );
} );

test( 'actions queue lazy-loads the file locker asset panel to a terminal state on demand', async ( { page, fixtureApi } ) => {
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

		await actionsQueuePage.openAssetPanel( fixture.panel_target );
		await expect.poll( async () => {
			return await panel.getAttribute( 'data-actions-queue-asset-panel-loading' ) || '';
		}, { timeout: 20_000 } ).toBe( '' );

		await expect( panel.locator( '.alert.alert-warning' ) ).toHaveCount( 0 );
		await expect( panel ).toHaveAttribute( 'data-actions-queue-asset-panel-loaded', '1', { timeout: 20_000 } );
		const fileActionForms = panel.locator( 'form.filelocker_fileaction' );
		await expect( fileActionForms ).toHaveCount( 2 );
		await expect( fileActionForms.first() ).toBeVisible();

		const fileActionSubmits = fileActionForms.locator( 'input[type=submit][data-action][data-rid]' );
		await expect( fileActionSubmits ).toHaveCount( 2 );
		for ( let index = 0; index < await fileActionSubmits.count(); index++ ) {
			const submit = fileActionSubmits.nth( index );
			await expect( submit ).toBeVisible();
			await expect( submit ).toBeEnabled();
			expect( await submit.getAttribute( 'href' ) ).toBeNull();
		}

		const fileActionSubmit = fileActionSubmits.first();
		const fileAction = await fileActionSubmit.getAttribute( 'data-action' );
		const fileRID = await fileActionSubmit.getAttribute( 'data-rid' );
		expect( fileAction || '' ).not.toHaveLength( 0 );
		expect( fileRID || '' ).not.toHaveLength( 0 );

		const fileActionRequest = page.waitForRequest(
			( request ) => isFileLockerActionRequest( request, {
				confirmed: '0',
				file_action: fileAction,
				rid: fileRID,
			} ),
			{ timeout: 20_000 }
		);
		await fileActionSubmit.click();
		await fileActionRequest;
		await expect( fileActionSubmit ).toBeEnabled( { timeout: 20_000 } );
	} );
} );
