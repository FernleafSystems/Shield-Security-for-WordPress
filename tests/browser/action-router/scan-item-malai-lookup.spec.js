const { openShieldRoute, test, expect } = require( './support/shield-test' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

const SCAN_ITEM_ANALYSIS_RENDER_SLUG = 'scanitemanalysis_container';
const MALAI_QUERY_ACTION_SLUG = 'scans_malai_file_query';

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

function paramsForRequest( request ) {
	return new URLSearchParams( request.postData() || '' );
}

function isScanItemAnalysisRequest( request ) {
	return paramsForRequest( request ).get( 'render_slug' ) === SCAN_ITEM_ANALYSIS_RENDER_SLUG;
}

function isMalaiQueryRequest( request ) {
	return paramsForRequest( request ).get( 'ex' ) === MALAI_QUERY_ACTION_SLUG;
}

async function openMalaiTabForRid( page, table, rid ) {
	await waitForScanResultsTableRows( table );

	const viewAction = table.locator(
		`button[data-scan-result-action="view"][data-rid="${rid}"]`
	).first();
	await expect( viewAction ).toBeVisible();
	await expect( viewAction ).toHaveAttribute( 'type', 'button' );

	const analysisResponse = page.waitForResponse(
		( response ) => isScanItemAnalysisRequest( response.request() ),
		{ timeout: 20_000 }
	);
	await viewAction.evaluate( ( element ) => {
		element.click();
	} );
	await analysisResponse;

	const modal = page.locator( '#ShieldModalContainer.modal.show' );
	await expect( modal ).toBeVisible();
	await expect( modal.locator( '#tabMalai-tab' ) ).toBeVisible( { timeout: 20_000 } );
	await modal.locator( '#tabMalai-tab' ).click();
	await expect( modal.locator( '#tabMalai' ) ).toBeVisible();

	const form = modal.locator( 'form#FileScanMalaiQuery' );
	await expect( form ).toBeVisible();
	const actionData = JSON.parse( await form.getAttribute( 'data-scan-item-malai-query-action' ) || '{}' );
	expect( actionData.ex ).toBe( MALAI_QUERY_ACTION_SLUG );

	return {
		form,
		modal,
	};
}

async function submitMalaiAndAssertNoNavigation( page, modal, form, rid, activeTab = null ) {
	const urlBeforeSubmit = page.url();
	const sentinel = await page.evaluate( () => {
		window.__shieldMalaiLookupSubmitSentinel = `alive-${Date.now()}`;
		return window.__shieldMalaiLookupSubmitSentinel;
	} );

	let capturedParams = null;
	const malaiHandler = async ( route ) => {
		if ( isMalaiQueryRequest( route.request() ) ) {
			capturedParams = paramsForRequest( route.request() );
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					success: true,
					data: {
						page_reload: false,
						show_toast: false,
					},
				} ),
			} );
			return;
		}

		await route.continue();
	};

	await page.route( '**/admin-ajax.php*', malaiHandler );
	try {
		await form.locator( 'input[name="confirm"]' ).check();
		const malaiResponse = page.waitForResponse(
			( response ) => isMalaiQueryRequest( response.request() ),
			{ timeout: 20_000 }
		);
		await form.evaluate( ( element ) => {
			element.requestSubmit();
		} );
		await malaiResponse;

		expect( capturedParams?.get( 'confirm' ) ).toBe( 'Y' );
		expect( capturedParams?.get( 'rid' ) ).toBe( String( rid ) );
		expect( page.url() ).toBe( urlBeforeSubmit );
		expect( await page.evaluate( () => window.__shieldMalaiLookupSubmitSentinel ) ).toBe( sentinel );
		await expect( modal ).toBeVisible();
		await expect( modal.locator( '#tabMalai' ) ).toBeVisible();
		if ( activeTab !== null ) {
			await expect( activeTab ).toHaveAttribute( 'aria-selected', 'true' );
		}
	}
	finally {
		await page.unroute( '**/admin-ajax.php*', malaiHandler ).catch( () => {} );
	}
}

async function exerciseMalaiLookupSubmit( page, table, rid, activeTab = null ) {
	const { modal, form } = await openMalaiTabForRid( page, table, rid );
	await submitMalaiAndAssertNoNavigation( page, modal, form, rid, activeTab );
	await modal.locator( '.btn-close' ).click();
	await expect( modal ).toBeHidden();
}

async function activateInvestigateFileStatusTab( page, subjectKey ) {
	const tab = page.locator(
		`[data-investigate-panel-tab="1"][data-source-tab-id="tab-navlink-${subjectKey}-file-status"]`
	);
	await expect( tab ).toBeVisible();
	await tab.click();
	await expect( tab ).toHaveAttribute( 'aria-selected', 'true' );
	return tab;
}

test( 'scan item MAL{ai} lookup submits without native navigation across shared table contexts', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'malai_lookup_contexts', async ( fixture ) => {
		const context = fixture.context || {};
		expect( String( context.plugin_slug || '' ) ).not.toHaveLength( 0 );
		expect( String( context.theme_slug || '' ) ).not.toHaveLength( 0 );
		expect( Number( context.plugin_rid ) ).toBeGreaterThan( 0 );
		expect( Number( context.theme_rid ) ).toBeGreaterThan( 0 );
		expect( Number( context.core_rid ) ).toBeGreaterThan( 0 );
		const actionsQueuePage = new ActionsQueuePage( page );

		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );
		await actionsQueuePage.drillToDetail( fixture );
		await exerciseMalaiLookupSubmit(
			page,
			page.locator( '[data-actions-queue-detail="1"] [data-scan-results-table="1"]' ).first(),
			context.plugin_rid
		);

		const investigateScenarios = [
			{
				subjectKey: 'plugin',
				rid: context.plugin_rid,
				route: {
					nav: 'activity',
					nav_sub: 'by_plugin',
					plugin_slug: context.plugin_slug,
				},
			},
			{
				subjectKey: 'theme',
				rid: context.theme_rid,
				route: {
					nav: 'activity',
					nav_sub: 'by_theme',
					theme_slug: context.theme_slug,
				},
			},
			{
				subjectKey: 'core',
				rid: context.core_rid,
				route: {
					nav: 'activity',
					nav_sub: 'by_core',
				},
			},
		];

		for ( const scenario of investigateScenarios ) {
			await openShieldRoute( page, scenario.route );
			const activeTab = await activateInvestigateFileStatusTab( page, scenario.subjectKey );
			await exerciseMalaiLookupSubmit(
				page,
				page.locator( '[data-scan-results-table="1"]' ).first(),
				scenario.rid,
				activeTab
			);
		}
	} );
} );
