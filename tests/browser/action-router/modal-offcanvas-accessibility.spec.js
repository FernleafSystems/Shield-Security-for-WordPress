const { test, expect } = require( './support/shield-test' );
const { expectNoAxeViolations } = require( './support/accessibility' );
const {
	openShieldRoute,
} = require( './support/shield-browser' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );
const {
	expectAccessibleMessageDialog,
	expectFocusWithin,
	expectLabelledControl,
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
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
	let completion = Promise.resolve();
	let continuationError;
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
		completion = released.then( () => route.continue() ).catch( ( error ) => {
			continuationError = error;
		} );
		await completion;
	};

	await page.route( '**/admin-ajax.php*', handler );
	return {
		started,
		release: () => releaseResolve(),
		completed: async () => {
			await started;
			await completion;
			if ( continuationError ) {
				throw continuationError;
			}
		},
		remove: async ( primaryError = null ) => {
			releaseResolve();
			await completion;
			try {
				await page.unroute( '**/admin-ajax.php*', handler );
				if ( continuationError ) {
					throw continuationError;
				}
			}
			catch ( error ) {
				if ( !primaryError ) {
					throw error;
				}
				await test.info().attach( 'request-cleanup-error', { body: String( error ), contentType: 'text/plain' } );
			}
		},
	};
}

async function fulfillNextMatchingRequest( page, matcher, body ) {
	let matched = false;
	let completion = Promise.resolve();
	let fulfillError;
	const handler = async ( route ) => {
		if ( matched || !matcher( route.request() ) ) {
			await route.fallback();
			return;
		}

		matched = true;
		completion = route.fulfill( {
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify( body ),
		} ).catch( ( error ) => {
			fulfillError = error;
		} );
		await completion;
	};

	await page.route( '**/admin-ajax.php*', handler );
	return {
		seen: () => matched,
		remove: async ( primaryError = null ) => {
			await completion;
			try {
				await page.unroute( '**/admin-ajax.php*', handler );
				if ( fulfillError ) {
					throw fulfillError;
				}
			}
			catch ( error ) {
				if ( !primaryError ) {
					throw error;
				}
				await test.info().attach( 'request-cleanup-error', { body: String( error ), contentType: 'text/plain' } );
			}
		},
	};
}

async function expectNoNativeDialogDuring( page, action ) {
	let nativeDialogCount = 0;
	page.on( 'dialog', async ( dialog ) => {
		nativeDialogCount++;
		await dialog.dismiss();
	} );

	await action();
	await expect.poll( () => nativeDialogCount ).toBe( 0 );
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
	await expectNamedDialog( page, offcanvas, 'AptoOffcanvasLabel' );

	return { launcher, offcanvas };
}

test( 'paused request cleanup releases early exits and removes unmatched handlers', async ( { page, fixtureApi } ) => {
	await fixtureApi.withIpAnalysisActivityMetaFixture( async ( fixture ) => {
		await openShieldRoute( page, { nav: 'activity', nav_sub: 'logs' } );
		let unmatchedCalls = 0;
		const unused = await pauseNextMatchingRequest( page, () => {
			unmatchedCalls++;
			return true;
		} );
		await unused.remove();
		const paused = await pauseNextMatchingRequest( page, isIpAnalysisOffcanvasRequest );
		const launcher = page.locator( `.offcanvas_ip_analysis[data-ip="${fixture.ip}"]` ).first();
		const interruption = new Error( 'Intentional loading-check interruption' );
		let caught;
		try {
			await launcher.click();
			await paused.started;
			throw interruption;
		}
		catch ( error ) {
			caught = error;
		}
		finally {
			// Simulate exiting before the normal release/completed path.
			await paused.remove( caught );
		}
		expect( caught ).toBe( interruption );
		const offcanvas = page.locator( '#AptoOffcanvas' );
		await expect( offcanvas.locator( '[data-investigate-panel-tabs="1"]' ) ).toBeVisible();
		await offcanvas.press( 'Escape' );
		await expect( launcher ).toBeFocused();
		await launcher.click();
		await expect( offcanvas.locator( '[data-investigate-panel-tabs="1"]' ) ).toBeVisible();
		expect( unmatchedCalls ).toBe( 0 );
		await offcanvas.press( 'Escape' );
	} );
} );

for ( const scenario of [
	{ name: 'report creation', route: { nav: 'reports', nav_sub: 'overview' }, launcher: '.offcanvas_report_create_form', body: '.form_create_report', contextual: true, licensed: true },
	{ name: 'IP rule creation', route: { nav: 'ips', nav_sub: 'rules' }, launcher: '.offcanvas_form_create_ip_rule', body: '#IpRuleAddForm', contextual: true },
	{ name: 'table search help', route: { nav: 'activity', nav_sub: 'logs' }, launcher: 'button.search-help', body: '.offcanvas-body table' },
] ) {
	test( `${scenario.name} uses the named shared offcanvas and accessible loaded body`, async ( { page, fixtureApi } ) => {
		const verify = async () => {
			await openShieldRoute( page, scenario.route );
			if ( scenario.contextual ) {
				await page.locator( '.page-action-menu-toggle' ).click();
			}
			const launcher = page.locator( scenario.launcher ).first();
			await launcher.click();
			const offcanvas = page.locator( '#AptoOffcanvas' );
			await expect( offcanvas.locator( scenario.body ) ).toBeVisible();
			await expectNamedDialog( page, offcanvas, 'AptoOffcanvasLabel' );
			await expectNoAxeViolations( page, '#AptoOffcanvas' );
			await offcanvas.press( 'Escape' );
			await expect( offcanvas ).toBeHidden();
			await expect( scenario.contextual ? page.locator( '.page-action-menu-toggle' ) : launcher ).toBeFocused();
		};
		if ( scenario.licensed ) {
			await fixtureApi.withLicenseClearFixture( verify );
		}
		else {
			await verify();
		}
	} );
}

test( 'site authorization uses an accessible loaded offcanvas without submitting', async ( { page, fixtureApi } ) => {
	await fixtureApi.withImportExportNetworkFixture( async () => {
		await openShieldRoute( page, { nav: 'tools', nav_sub: 'importexport' } );
		await page.locator( '[data-import-export-task="clients"]' ).click();
		await expectNoAxeViolations( page, '#PageContainer-Apto' );
		const launcher = page.locator( '[data-import-export-add-clients="1"]' );
		await launcher.click();
		const offcanvas = page.locator( '#AptoOffcanvas' );
		await expect( offcanvas.locator( '#ImportExportSitesAuthoriseUrlsForm' ) ).toBeVisible();
		await expectNamedDialog( page, offcanvas, 'AptoOffcanvasLabel' );
		await expect( offcanvas.locator( 'textarea' ) ).toHaveAccessibleName( /\S/ );
		await expect( offcanvas.locator( 'textarea' ) ).toHaveAccessibleDescription( /\S/ );
		await expectNoAxeViolations( page, '#AptoOffcanvas' );
		await offcanvas.press( 'Escape' );
		await expect( launcher ).toBeFocused();
	} );
} );

test( 'shared dynamic modal shell starts inert without a stale accessible name', async ( { page } ) => {
	// Dashboard onboarding legitimately populates this shared shell before dismissing it.
	await openShieldRoute( page, {
		nav: 'reports',
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
		let testError;

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
			await viewAction.click();
			await paused.started;

			const modal = page.locator( '#ShieldModalContainer' );
			await expect( modal ).toBeVisible();
			await expectNamedDialog( page, modal, 'ShieldModalContainerLabel' );
			await expectFocusWithin( modal );
			await expectNoAxeViolations( page, '#ShieldModalContainer' );

			paused.release();
			await paused.completed();
			await expect( modal.locator( '#tabInfo[role="tabpanel"]' ) ).toBeVisible( { timeout: 20_000 } );
			await expectNamedDialog( page, modal );

			await modal.locator( '[data-bs-dismiss="modal"]' ).first().click();
			await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
			await expect( viewAction ).toBeFocused();
		}
		catch ( error ) {
			testError = error;
			throw error;
		}
		finally {
			await paused.remove( testError );
		}
	} );
} );

test( 'scan item analysis render failure opens accessible message dialog', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		const failed = await fulfillNextMatchingRequest( page, isScanItemAnalysisRequest, {
			success: false,
			data: {
				message: 'scan-item-analysis-failed',
				page_reload: false,
			},
		} );
		let testError;

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

			await expectNoNativeDialogDuring( page, async () => {
				await viewAction.click();
				await expect.poll( failed.seen ).toBe( true );
				const dialog = await expectAccessibleMessageDialog( page );
				await expectNoAxeViolations( page, '[data-shield-accessible-dialog="1"]' );
				await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
				await dialog.locator( '.shield-accessible-dialog__confirm' ).click();
				await expectModalHiddenWithoutAriaModal( page, '[data-shield-accessible-dialog="1"]' );
				await expect( viewAction ).toBeFocused();
			} );
		}
		catch ( error ) {
			testError = error;
			throw error;
		}
		finally {
			await failed.remove( testError );
		}
	} );
} );

test( 'scan item analysis unnamed replacement opens accessible message dialog', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		const malformed = await fulfillNextMatchingRequest( page, isScanItemAnalysisRequest, {
			success: true,
			data: {
				html: '<div class="modal-body"><button type="button" data-bs-dismiss="modal" aria-label="Dismiss"></button></div>',
				page_reload: false,
			},
		} );
		let testError;

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

			await expectNoNativeDialogDuring( page, async () => {
				await viewAction.click();
				await expect.poll( malformed.seen ).toBe( true );
				const dialog = await expectAccessibleMessageDialog( page );
				await expectNoAxeViolations( page, '[data-shield-accessible-dialog="1"]' );
				await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
				await dialog.locator( '.shield-accessible-dialog__confirm' ).click();
				await expectModalHiddenWithoutAriaModal( page, '[data-shield-accessible-dialog="1"]' );
				await expect( viewAction ).toBeFocused();
			} );
		}
		catch ( error ) {
			testError = error;
			throw error;
		}
		finally {
			await malformed.remove( testError );
		}
	} );
} );

test( 'IP analysis offcanvas is named while loading and after async replacement', async ( { page, fixtureApi } ) => {
	await fixtureApi.withIpAnalysisActivityMetaFixture( async ( fixture ) => {
		const paused = await pauseNextMatchingRequest( page, isIpAnalysisOffcanvasRequest );
		let testError;
		try {
			await openShieldRoute( page, {
				nav: 'activity',
				nav_sub: 'logs',
			} );

			const launcher = page.locator( `.offcanvas_ip_analysis[data-ip="${fixture.ip}"]` ).first();
			await expect( launcher ).toBeVisible();
			await launcher.click();
			await paused.started;

			const offcanvas = page.locator( '#AptoOffcanvas' );
			await expect( offcanvas ).toBeVisible();
			await expectNamedDialog( page, offcanvas, 'AptoOffcanvasLabel' );
			await expectFocusWithin( offcanvas );
			await expectLabelledControl( offcanvas.locator( '[data-bs-dismiss="offcanvas"]' ).first() );
			await expectNoAxeViolations( page, '#AptoOffcanvas' );

			paused.release();
			await paused.completed();
			await expect( offcanvas.locator( '[data-investigate-panel-tabs="1"]' ) ).toBeVisible( { timeout: 20_000 } );
			await expectNamedDialog( page, offcanvas, 'AptoOffcanvasLabel' );
			await expectNoAxeViolations( page, '#AptoOffcanvas' );

			await offcanvas.locator( '[data-bs-dismiss="offcanvas"]' ).first().click();
			await expectModalHiddenWithoutAriaModal( page, '#AptoOffcanvas' );
			await expect( launcher ).toBeFocused();
		}
		catch ( error ) {
			testError = error;
			throw error;
		}
		finally {
			await paused.remove( testError );
		}
	} );
} );

test( 'IP analysis offcanvas render failure opens accessible message dialog', async ( { page, fixtureApi } ) => {
	await fixtureApi.withIpAnalysisActivityMetaFixture( async ( fixture ) => {
		const failed = await fulfillNextMatchingRequest( page, isIpAnalysisOffcanvasRequest, {
			success: false,
			data: {
				error: 'offcanvas-render-failed',
				page_reload: false,
			},
		} );
		let testError;

		try {
			await openShieldRoute( page, {
				nav: 'activity',
				nav_sub: 'logs',
			} );

			const launcher = page.locator( `.offcanvas_ip_analysis[data-ip="${fixture.ip}"]` ).first();
			await expect( launcher ).toBeVisible();

			await expectNoNativeDialogDuring( page, async () => {
				await launcher.click();
				await expect.poll( failed.seen ).toBe( true );
				const dialog = await expectAccessibleMessageDialog( page );
				await expectNoAxeViolations( page, '[data-shield-accessible-dialog="1"]' );
				await expectModalHiddenWithoutAriaModal( page, '#AptoOffcanvas' );
				await dialog.locator( '.shield-accessible-dialog__confirm' ).click();
				await expectModalHiddenWithoutAriaModal( page, '[data-shield-accessible-dialog="1"]' );
				await expect( launcher ).toBeFocused();
			} );
		}
		catch ( error ) {
			testError = error;
			throw error;
		}
		finally {
			await failed.remove( testError );
		}
	} );
} );

test( 'IP analysis offcanvas unnamed replacement opens accessible message dialog', async ( { page, fixtureApi } ) => {
	await fixtureApi.withIpAnalysisActivityMetaFixture( async ( fixture ) => {
		const malformed = await fulfillNextMatchingRequest( page, isIpAnalysisOffcanvasRequest, {
			success: true,
			data: {
				html: '<div class="offcanvas-body"><button type="button" data-bs-dismiss="offcanvas" aria-label="Dismiss"></button></div>',
				page_reload: false,
			},
		} );
		let testError;

		try {
			await openShieldRoute( page, {
				nav: 'activity',
				nav_sub: 'logs',
			} );

			const launcher = page.locator( `.offcanvas_ip_analysis[data-ip="${fixture.ip}"]` ).first();
			await expect( launcher ).toBeVisible();

			await expectNoNativeDialogDuring( page, async () => {
				await launcher.click();
				await expect.poll( malformed.seen ).toBe( true );
				const dialog = await expectAccessibleMessageDialog( page );
				await expectNoAxeViolations( page, '[data-shield-accessible-dialog="1"]' );
				await expectModalHiddenWithoutAriaModal( page, '#AptoOffcanvas' );
				await dialog.locator( '.shield-accessible-dialog__confirm' ).click();
				await expectModalHiddenWithoutAriaModal( page, '[data-shield-accessible-dialog="1"]' );
				await expect( launcher ).toBeFocused();
			} );
		}
		catch ( error ) {
			testError = error;
			throw error;
		}
		finally {
			await malformed.remove( testError );
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
		await expectNamedDialog( page, offcanvas, 'AptoOffcanvasLabel' );
		await expectNoAxeViolations( page, '#AptoOffcanvas' );

		await offcanvas.locator( '[data-bs-dismiss="offcanvas"]' ).first().click();
		await expectModalHiddenWithoutAriaModal( page, '#AptoOffcanvas' );
		await expect( launcher ).toBeFocused();
	} );
} );
