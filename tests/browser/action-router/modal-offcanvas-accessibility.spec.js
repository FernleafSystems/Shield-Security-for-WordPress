const { test, expect } = require( './support/shield-test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const {
	openShieldRoute,
} = require( './support/shield-browser' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );
const {
	expectFocusWithin,
	expectLabelledControl,
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
	expectNamedOffcanvas,
} = require( './support/modal-accessibility' );

function requestRenderSlug( request ) {
	const params = new URLSearchParams( request.postData() || '' );
	return params.get( 'render_slug' ) || '';
}

function isScanItemAnalysisRequest( request ) {
	return request.method() === 'POST'
		&& request.url().includes( '/admin-ajax.php' )
		&& requestRenderSlug( request ) === 'scanitemanalysis_container';
}

function isIpAnalysisOffcanvasRequest( request ) {
	return request.method() === 'POST'
		&& request.url().includes( '/admin-ajax.php' )
		&& requestRenderSlug( request ) === 'offcanvas_ipanalysis';
}

async function pauseNextMatchingRequest( page, matcher ) {
	let matched = false;
	let startedResolve;
	const started = new Promise( ( resolve ) => {
		startedResolve = resolve;
	} );
	let releaseResolve;
	const released = new Promise( ( resolve ) => {
		releaseResolve = resolve;
	} );

	const handler = async ( route ) => {
		if ( matched || !matcher( route.request() ) ) {
			await route.fallback();
			return;
		}

		matched = true;
		startedResolve();
		await released;
		await route.continue();
		await page.unroute( '**/admin-ajax.php*', handler ).catch( () => null );
	};

	await page.route( '**/admin-ajax.php*', handler );
	return {
		started,
		release: () => releaseResolve(),
		remove: () => page.unroute( '**/admin-ajax.php*', handler ).catch( () => null ),
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

async function expectNoAxeViolations( page, selector ) {
	const results = await new AxeBuilder( { page } )
	.include( selector )
	.analyze();

	expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
}

async function openIpAnalysisOffcanvasFromLauncher( page, ip ) {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'logs',
	} );

	const launcher = page.locator( `.offcanvas_ip_analysis[data-ip="${ip}"]` ).first();
	await expect( launcher ).toBeVisible();

	const response = page.waitForResponse(
		( resp ) => isIpAnalysisOffcanvasRequest( resp.request() ),
		{ timeout: 20_000 }
	);
	await launcher.click();
	await response;

	const offcanvas = page.locator( '#AptoOffcanvas' );
	await expect( offcanvas ).toBeVisible();
	await expectNamedOffcanvas( page, offcanvas, 'AptoOffcanvasLabel' );

	return { launcher, offcanvas };
}

test( 'shared dynamic modal shell starts inert without a stale accessible name', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'dashboard',
		nav_sub: 'overview',
	} );

	const modal = page.locator( '#ShieldModalContainer' );
	await expect( modal ).toBeHidden();
	await expect( modal ).toHaveAttribute( 'tabindex', '-1' );
	await expect( modal ).toHaveAttribute( 'aria-hidden', 'true' );
	expect( await modal.getAttribute( 'aria-labelledby' ) ).toBeNull();
	await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
	await expect( modal.locator( '.modal-content' ) ).toHaveCount( 1 );
	await expect( modal.locator( '[data-shield-modal-live-region="1"]' ) ).toHaveCount( 1 );
} );

test( 'scan item analysis shared modal stays named through async replacement', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		const paused = await pauseNextMatchingRequest( page, isScanItemAnalysisRequest );

		try {
			await openShieldRoute( page, {
				nav: 'scans',
				nav_sub: 'overview',
			} );

			await actionsQueuePage.drillToDetail( fixture );
			const table = page.locator( '[data-scan-results-table="1"]' ).first();
			await waitForScanResultsTableRows( table );

			const viewAction = table.locator( '[data-scan-result-action="view"]' ).first();
			await expect( viewAction ).toBeVisible();
			const response = page.waitForResponse(
				( resp ) => isScanItemAnalysisRequest( resp.request() ),
				{ timeout: 20_000 }
			);
			await viewAction.click();
			await paused.started;

			const modal = page.locator( '#ShieldModalContainer' );
			await expect( modal ).toBeVisible();
			await expectNamedDialog( page, modal, 'ShieldModalContainerLabel' );
			await expectFocusWithin( modal );
			await expectNoAxeViolations( page, '#ShieldModalContainer' );

			paused.release();
			await response;
			await expect( modal.locator( '#tabInfo[role="tabpanel"]' ) ).toBeVisible( { timeout: 20_000 } );
			await expectNamedDialog( page, modal );

			await modal.locator( '[data-bs-dismiss="modal"]' ).first().click();
			await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
			await expect( viewAction ).toBeFocused();
		}
		finally {
			paused.release();
			await paused.remove();
		}
	} );
} );

test( 'IP analysis offcanvas is named while loading and after async replacement', async ( { page, fixtureApi } ) => {
	await fixtureApi.withIpAnalysisActivityMetaFixture( async ( fixture ) => {
		const paused = await pauseNextMatchingRequest( page, isIpAnalysisOffcanvasRequest );
		try {
			await openShieldRoute( page, {
				nav: 'activity',
				nav_sub: 'logs',
			} );

			const launcher = page.locator( `.offcanvas_ip_analysis[data-ip="${fixture.ip}"]` ).first();
			await expect( launcher ).toBeVisible();
			const response = page.waitForResponse(
				( resp ) => isIpAnalysisOffcanvasRequest( resp.request() ),
				{ timeout: 20_000 }
			);
			await launcher.click();
			await paused.started;

			const offcanvas = page.locator( '#AptoOffcanvas' );
			await expect( offcanvas ).toBeVisible();
			await expectNamedOffcanvas( page, offcanvas, 'AptoOffcanvasLabel' );
			await expectFocusWithin( offcanvas );
			await expectLabelledControl( offcanvas.locator( '[data-bs-dismiss="offcanvas"]' ).first() );
			await expectNoAxeViolations( page, '#AptoOffcanvas' );

			paused.release();
			await response;
			await expect( offcanvas.locator( '[data-investigate-panel-tabs="1"]' ) ).toBeVisible( { timeout: 20_000 } );
			await expectNamedOffcanvas( page, offcanvas, 'AptoOffcanvasLabel' );

			await offcanvas.locator( '[data-bs-dismiss="offcanvas"]' ).first().click();
			await expectModalHiddenWithoutAriaModal( page, '#AptoOffcanvas' );
			await expect( launcher ).toBeFocused();
		}
		finally {
			paused.release();
			await paused.remove();
		}
	} );
} );

test( 'IP analysis offcanvas returns focus to root opener after replace navigation', async ( { page, fixtureApi } ) => {
	await fixtureApi.withIpAnalysisActivityMetaFixture( async ( fixture ) => {
		const { launcher, offcanvas } = await openIpAnalysisOffcanvasFromLauncher( page, fixture.ip );
		const form = offcanvas.locator( 'form[data-investigate-panel-form="1"]' ).first();
		await expect( form ).toBeAttached();

		const response = page.waitForResponse(
			( resp ) => isIpAnalysisOffcanvasRequest( resp.request() ),
			{ timeout: 20_000 }
		);
		await form.evaluate( ( formEl ) => {
			formEl.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );
		} );
		await response;

		await expect( offcanvas.locator( '[data-investigate-panel-tabs="1"]' ) ).toBeVisible( { timeout: 20_000 } );
		await expectNamedOffcanvas( page, offcanvas, 'AptoOffcanvasLabel' );

		await offcanvas.locator( '[data-bs-dismiss="offcanvas"]' ).first().click();
		await expectModalHiddenWithoutAriaModal( page, '#AptoOffcanvas' );
		await expect( launcher ).toBeFocused();
	} );
} );
