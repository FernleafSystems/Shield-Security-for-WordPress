const { test, expect } = require( '@playwright/test' );
const {
	openShieldRoute,
	withActionsQueueFixture,
} = require( './support/shield-browser' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

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

test( 'actions queue drills into groups and back out, opening details when available', async ( { page } ) => {
	await withActionsQueueFixture( 'direct_table', async ( fixture ) => {
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
		await expect( page.locator( '[data-step-tab-drill-index="0"]' ) ).toHaveText( /Actions Queue/i );
		await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__eyebrow' ) ).toHaveCount( 0 );
		await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__title' ) ).not.toHaveText( '' );

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

test( 'actions queue warning breadcrumb uses warning palette', async ( { page } ) => {
	await withActionsQueueFixture( 'file_locker_lazy', async ( fixture ) => {
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

	const zone = page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' )
		.filter( { hasText: /Security Admin/i } )
		.first();
	await expect( zone ).toBeVisible();

	await zone.click();
	await expect( page.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();
	await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );
	const configureTabs = page.locator( '[data-operator-step-tab="1"]' );
	await expect( configureTabs ).toHaveCount( 3 );
	await expect( configureTabs.first() ).toHaveAttribute( 'data-color-key', 'home' );
	await expect( page.locator( '[data-step-tab-drill-index="0"]' ) ).toHaveText( /Configure/i );
	await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__title' ) ).toHaveText( /Security Admin/i );
	await expect( page.locator( '[data-configure-diagnosis="1"] [data-drill-target="editor"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-configure-diagnosis="1"] .zone-summary-header' ) ).toHaveCount( 0 );
	const expandRow = page.locator( '[data-configure-diagnosis="1"] [data-shield-expand-trigger="1"]' ).first();
	await expect( expandRow.locator( '.shield-detail-row__expand-cta' ) ).toBeVisible();
	await expandRow.click();
	await expect( page.locator( '[data-configure-diagnosis="1"] .shield-detail-expansion.show' ).first() ).toBeVisible();
	const visibleExpandedForms = page.locator( '[data-configure-diagnosis="1"] .shield-detail-expansion.show form.options_form_for' );
	await expect( visibleExpandedForms.first() ).toBeVisible();
	await page.locator( '[data-configure-diagnosis="1"] .shield-detail-expansion.show .shield-detail-expansion__btn-save' )
		.first()
		.click();
	await expect( visibleExpandedForms ).toHaveCount( 0, { timeout: 20_000 } );
	await expect( page.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();
	await expect( configureTabs ).toHaveCount( 3 );
	await expect( page.locator( '[data-step-tab-drill-index="0"]' ) ).toHaveText( /Configure/i );
	await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__title' ) ).toHaveText( /Security Admin/i );
	const refreshedExpandRow = page.locator( '[data-configure-diagnosis="1"] [data-shield-expand-trigger="1"]' ).first();
	await expect( refreshedExpandRow.locator( '.shield-detail-row__expand-cta' ) ).toBeVisible();
	await refreshedExpandRow.click();
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

	const zone = page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' )
		.filter( { hasText: /Security Admin/i } )
		.first();
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

	const optionResult = page.locator( '[data-configure-search-results="1"] a' )
		.filter( { hasText: /Comments Cooldown/i } )
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

test( 'actions queue keeps the same ignored-plugin direct table after the shared table success event', async ( { page } ) => {
	await withActionsQueueFixture( 'ignored_plugin_direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );
		const rail = page.locator( '[data-operator-context-rail="1"]' );
		const detailTitle = rail.locator( '.operator-context-rail__title' );
		const detailBadge = rail.locator( '.shield-badge' );
		const titleText = ( await detailTitle.textContent() || '' ).trim();
		const badgeText = ( await detailBadge.textContent() || '' ).trim();
		const scanResultsTable = page.locator( '[data-scan-results-table="1"]' ).first();
		await expect( scanResultsTable ).toBeVisible();
		await waitForScanResultsTableRows( scanResultsTable );
		await scanResultsTable.evaluate( ( table ) => {
			table.dispatchEvent( new CustomEvent( 'shield:table-action-success', {
				bubbles: true,
			} ) );
		} );

		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
		await expect( detailTitle ).toHaveText( titleText, { timeout: 20_000 } );
		await expect( detailBadge ).toHaveText( badgeText, { timeout: 20_000 } );
		await expect( page.locator( '[data-mode-shell="1"][data-mode="actions_queue_assets"]' ) ).toHaveCount( 0 );
		await expect( scanResultsTable ).toBeVisible();
	} );
} );

test( 'actions queue ignores all results from the context rail and refreshes the direct table in place', async ( { page } ) => {
	await withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );
		const rail = page.locator( '[data-operator-context-rail="1"]' );
		const detailTitle = rail.locator( '.operator-context-rail__title' );
		const detailBadge = rail.locator( '.shield-badge' );
		const displayForm = rail.locator( '[data-operator-context-display-form="1"]' );
		const ignoreAllActions = rail.locator( '[data-operator-context-action-ajax="1"]' )
			.filter( { hasText: /^Ignore All Results$/ } );
		const scanResultsTable = page.locator( '[data-scan-results-table="1"]' ).first();
		const titleText = ( await detailTitle.textContent() || '' ).trim();

		await waitForScanResultsTableRows( scanResultsTable );
		await expect( ignoreAllActions ).toHaveCount( 1 );
		await expect( displayForm ).toBeVisible();

		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await actionsQueuePage.clickElement( ignoreAllActions.first() );

		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
		await expect( ignoreAllActions ).toHaveCount( 0, { timeout: 20_000 } );
		await expect( detailTitle ).toHaveText( titleText, { timeout: 20_000 } );
		await expect( detailBadge ).toHaveText( '1 item', { timeout: 20_000 } );
		await expect( displayForm ).toBeVisible();
		await expect( displayForm.locator( 'input[name="include_ignored"]' ) ).toBeChecked();
		await expect( displayForm.locator( 'input[name="include_ignored"]' ) ).toBeDisabled();
		await expect( page.locator( '[data-mode-shell="1"][data-mode="actions_queue_assets"]' ) ).toHaveCount( 0 );
		await waitForScanResultsTableRows( scanResultsTable );
	} );
} );

test( 'actions queue saves context-box display toggles and keeps the ignored-plugin direct table visible', async ( { page } ) => {
	await withActionsQueueFixture( 'ignored_plugin_direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );
		const rail = page.locator( '[data-operator-context-rail="1"]' );
		const displayForm = rail.locator( '[data-operator-context-display-form="1"]' );
		const ignoredToggle = displayForm.locator( 'input[name="include_ignored"]' );
		const repairedToggle = displayForm.locator( 'input[name="include_repaired"]' );
		const deletedToggle = displayForm.locator( 'input[name="include_deleted"]' );

		await expect( displayForm ).toBeVisible();
		await expect( ignoredToggle ).toBeChecked();
		await expect( ignoredToggle ).toBeDisabled();
		await expect( repairedToggle ).not.toBeChecked();
		await expect( deletedToggle ).not.toBeChecked();

		const scanResultsTable = page.locator( '[data-scan-results-table="1"]' ).first();
		await waitForScanResultsTableRows( scanResultsTable );
		await page.evaluate( () => {
			window.__actionsQueueDisplayToggleSentinel = 'detail-still-live';
		} );
		await repairedToggle.check();

		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
		await expect( repairedToggle ).toBeChecked( { timeout: 20_000 } );
		await expect( ignoredToggle ).toBeChecked();
		await expect( ignoredToggle ).toBeDisabled();
		await expect( page.locator( '[data-mode-shell="1"][data-mode="actions_queue_assets"]' ) ).toHaveCount( 0 );
		await expect( scanResultsTable ).toBeVisible();
		await page.waitForTimeout( 2500 );
		expect( await page.evaluate( () => window.__actionsQueueDisplayToggleSentinel || '' ) ).toBe( 'detail-still-live' );
		await expect( page.locator( '[data-actions-queue-retry]' ) ).toHaveCount( 0 );
		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
		await expect( repairedToggle ).toBeChecked();
		await expect( scanResultsTable ).toBeVisible();
	} );
} );

test( 'actions queue lazy-loads the file locker asset panel to a terminal state on demand', async ( { page } ) => {
	await withActionsQueueFixture( 'file_locker_lazy', async ( fixture ) => {
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

		const failureAlert = panel.locator( '.alert.alert-warning' );
		if ( await failureAlert.count() > 0 ) {
			await expect( failureAlert ).toContainText( /Unable to load these scan details/i );
		}
		else {
			await expect( panel ).toHaveAttribute( 'data-actions-queue-asset-panel-loaded', '1', { timeout: 20_000 } );
			await expect( panel.locator( 'form.filelocker_fileaction' ) ).toBeVisible();
		}
	} );
} );
