const { AxeBuilder, openShieldRoute, test, expect } = require( './support/shield-test' );
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

function formatAxeViolations( violations ) {
	return violations.map( ( violation ) => {
		const targets = violation.nodes
		.flatMap( ( node ) => node.target || [] )
		.slice( 0, 5 )
		.join( ', ' );

		return `${ violation.id }: ${ targets }`;
	} ).join( '\n' );
}

async function expectNoAxeViolations( page, selector, excludes = [], disabledRules = [] ) {
	let builder = new AxeBuilder( { page } )
	.include( selector );
	for ( const exclude of excludes ) {
		builder = builder.exclude( exclude );
	}
	if ( disabledRules.length > 0 ) {
		builder = builder.disableRules( disabledRules );
	}
	const results = await builder.analyze();

	expect( results.violations, formatAxeViolations( results.violations ) ).toEqual( [] );
}

test( 'scan item analysis keeps shared modal semantics after async content replacement', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await page.route( '**/admin-ajax.php*', async ( route ) => {
			if ( isScanItemAnalysisRequest( route.request() ) ) {
				await new Promise( ( resolve ) => setTimeout( resolve, 600 ) );
			}
			await route.continue();
		} );

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
		await expectNoAxeViolations(
			page,
			'#ShieldModalContainer',
			[ '#ShieldModalContainer [role="tab"]' ],
			[ 'heading-order' ]
		);

		await modal.locator( '.btn-close' ).click();
		await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
		await expect( viewAction ).toBeFocused();
	} );
} );
