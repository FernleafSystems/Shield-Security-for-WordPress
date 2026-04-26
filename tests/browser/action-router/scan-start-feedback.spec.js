const { test, expect } = require( '@playwright/test' );
const { fetchShieldRenderedHtml, openShieldRoute } = require( './support/shield-browser' );
const {
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
} = require( './support/modal-accessibility' );

function isScanStartRequest( request ) {
	return request.method() === 'POST'
		&& request.url().includes( '/admin-ajax.php' )
		&& ( request.postData() || '' ).includes( 'ex=scans_start' );
}

function isScanCheckRequest( request ) {
	return request.method() === 'POST'
		&& request.url().includes( '/admin-ajax.php' )
		&& ( request.postData() || '' ).includes( 'ex=scans_check' );
}

function actionRouterResponse( data ) {
	return {
		status: 200,
		contentType: 'application/json',
		body: JSON.stringify( {
			success: data.success !== false,
			data: {
				page_reload: false,
				message: '',
				html: '',
				...data,
			},
		} ),
	};
}

function withTimeout( promise, label, timeout = 10000 ) {
	return Promise.race( [
		promise,
		new Promise( ( unusedResolve, reject ) => {
			setTimeout( () => reject( new Error( label ) ), timeout );
		} ),
	] );
}

async function delayScanStartRequest( page, modalHtml, delayMs = 1200 ) {
	let handled = false;
	let startedResolve;
	let completedResolve;

	const started = new Promise( ( resolve ) => {
		startedResolve = resolve;
	} );
	const completed = new Promise( ( resolve ) => {
		completedResolve = resolve;
	} );

	const handler = async ( route ) => {
		const request = route.request();
		const shouldDelay = !handled && isScanStartRequest( request );

		if ( !shouldDelay ) {
			await route.continue();
			return;
		}

		handled = true;
		startedResolve();
		await new Promise( ( resolve ) => setTimeout( resolve, delayMs ) );
		await route.fulfill( actionRouterResponse( {
			success: true,
			scans_running: true,
			scan_ids: [ 31 ],
			modal_state: 'running',
			modal_html: modalHtml,
		} ) );
		completedResolve();
		await page.unroute( '**/admin-ajax.php', handler ).catch( () => null );
	};

	await page.route( '**/admin-ajax.php', handler );

	return {
		started,
		completed,
	};
}

async function completeNextScanCheckRequest( page, modalHtml ) {
	let handled = false;
	let receivedResolve;

	const received = new Promise( ( resolve ) => {
		receivedResolve = resolve;
	} );

	const handler = async ( route ) => {
		const request = route.request();
		if ( handled || !isScanCheckRequest( request ) ) {
			await route.fallback();
			return;
		}

		handled = true;
		receivedResolve();
		await route.fulfill( actionRouterResponse( {
			success: true,
			running: {
				afs: false,
				wpv: false,
				apc: false,
			},
			failed: false,
			failure_message: '',
			modal_state: 'completed',
			modal_html: modalHtml,
		} ) );
		await page.unroute( '**/admin-ajax.php', handler ).catch( () => null );
	};

	await page.route( '**/admin-ajax.php', handler );
	return {
		received,
	};
}

async function failNextScanStartRequest( page, modalHtml ) {
	let handled = false;
	const handler = async ( route ) => {
		const request = route.request();
		if ( handled || !isScanStartRequest( request ) ) {
			await route.fallback();
			return;
		}

		handled = true;
		await route.fulfill( actionRouterResponse( {
			success: true,
			scans_running: false,
			modal_state: 'failed',
			modal_html: modalHtml,
		} ) );
		await page.unroute( '**/admin-ajax.php', handler ).catch( () => null );
	};

	await page.route( '**/admin-ajax.php', handler );
}

async function respondToNextScanStartWithoutModal( page ) {
	let handled = false;
	const handler = async ( route ) => {
		const request = route.request();
		if ( handled || !isScanStartRequest( request ) ) {
			await route.fallback();
			return;
		}

		handled = true;
		await route.fulfill( actionRouterResponse( {
			success: false,
			message: 'scan-start-feedback-fallback',
		} ) );
		await page.unroute( '**/admin-ajax.php', handler ).catch( () => null );
	};

	await page.route( '**/admin-ajax.php', handler );
}

function waitForScanOverviewRedirect( page ) {
	return page.waitForURL( ( url ) => {
		return url.searchParams.get( 'nav' ) === 'scans'
			&& url.searchParams.get( 'nav_sub' ) === 'overview';
	}, { timeout: 8000 } )
	.then( () => 'redirect' )
	.catch( () => null );
}

test( 'manual scan start uses the shared modal while start and completion progress', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'scans',
		nav_sub: 'run',
	} );
	await page.waitForFunction( () => {
		return Object.keys( window.shieldEventsHandler_Main?.eventHandlers?.submit || {} )
		.includes( 'form#StartScans' );
	}, null, { timeout: 10000 } );

	const runningModalHtml = await scanProgressHtml( page, 'running', 37 );
	const completedModalHtml = await scanProgressHtml( page, 'completed', 100 );
	const delayedRequest = await delayScanStartRequest( page, runningModalHtml );
	const scanCheckRequest = await completeNextScanCheckRequest( page, completedModalHtml );
	const completionRedirect = waitForScanOverviewRedirect( page );

	await ensureStartScansButton( page );
	await page.locator( '#StartScansButton' ).first().click();
	await withTimeout( delayedRequest.started, 'Timed out waiting for scans_start request.' );

	const sharedModal = page.locator( '#ShieldModalContainer.modal.show' );
	await expect( sharedModal ).toBeVisible();
	await expectNamedDialog( page, sharedModal );
	await assertScanModalState( sharedModal, 'initiating', 'true' );
	const initiatingAnnouncement = await assertLiveRegionMatchesCurrentAnnouncement( sharedModal );
	await expect( page.locator( '#ShieldOverlay' ) ).toBeHidden();

	await withTimeout( delayedRequest.completed, 'Timed out waiting for delayed scans_start response.' );
	await expect( sharedModal ).toBeVisible();
	await assertScanModalState( sharedModal, 'running', 'true' );
	const runningAnnouncement = await assertLiveRegionChangesToCurrentAnnouncement( sharedModal, initiatingAnnouncement );
	await assertProgressbarContract( sharedModal );
	await sharedModal.locator( '.btn-close' ).click( { trial: true } );
	await withTimeout( scanCheckRequest.received, 'Timed out waiting for scans_check request.' );
	await assertScanModalState( sharedModal, 'completed', 'false' );
	await assertLiveRegionChangesToCurrentAnnouncement( sharedModal, runningAnnouncement );
	await expect( completionRedirect ).resolves.toBe( 'redirect' );
} );

test( 'manual scan failure modal returns focus to scan launcher when closed', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'scans',
		nav_sub: 'run',
	} );
	await page.waitForFunction( () => {
		return Object.keys( window.shieldEventsHandler_Main?.eventHandlers?.submit || {} )
		.includes( 'form#StartScans' );
	}, null, { timeout: 10000 } );
	await ensureStartScansButton( page );

	const failedModalHtml = await scanProgressHtml( page, 'failed', 100 );
	await failNextScanStartRequest( page, failedModalHtml );

	await page.locator( '#StartScansButton' ).first().click();

	const sharedModal = page.locator( '#ShieldModalContainer.modal.show' );
	await expect( sharedModal ).toBeVisible();
	await expectNamedDialog( page, sharedModal );
	const failedAnnouncement = await assertScanModalState( sharedModal, 'failed', 'false' );
	expect( failedAnnouncement ).not.toContain( '100%' );
	await assertLiveRegionMatchesCurrentAnnouncement( sharedModal );

	await sharedModal.locator( '.btn-close' ).click();
	await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
	await expect( page.locator( '#StartScansButton' ).first() ).toBeFocused();
} );

test( 'manual scan start shows local error modal when response lacks modal contract', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'scans',
		nav_sub: 'run',
	} );
	await page.waitForFunction( () => {
		return Object.keys( window.shieldEventsHandler_Main?.eventHandlers?.submit || {} )
		.includes( 'form#StartScans' );
	}, null, { timeout: 10000 } );
	await ensureStartScansButton( page );
	await respondToNextScanStartWithoutModal( page );

	await page.locator( '#StartScansButton' ).first().click();

	const sharedModal = page.locator( '#ShieldModalContainer.modal.show' );
	await expect( sharedModal ).toBeVisible();
	await expectNamedDialog( page, sharedModal );
	await assertScanModalState( sharedModal, 'failed', 'false' );
	await assertLiveRegionMatchesCurrentAnnouncement( sharedModal );

	await sharedModal.locator( '.btn-close' ).click();
	await expect( page.locator( '#StartScansButton' ).first() ).toBeFocused();
} );

async function scanProgressHtml( page, modalState, progress ) {
	return fetchShieldRenderedHtml( page, 'render_scans_progress', {
		modal_state: modalState,
		current_scan: 'scan-contract-current',
		remaining_scans: 'scan-contract-remaining',
		progress,
	} );
}

async function ensureStartScansButton( page ) {
	if ( await page.locator( '#StartScansButton' ).count() === 0 ) {
		await page.locator( '#StartScans' ).evaluate( ( form ) => {
			const button = document.createElement( 'button' );
			button.type = 'submit';
			button.id = 'StartScansButton';
			button.textContent = 'Run';
			form.appendChild( button );
		} );
	}
}

async function assertScanModalState( modal, state, busy ) {
	const stateEl = modal.locator( '[data-shield-scan-modal-state]' );
	await expect( stateEl ).toHaveAttribute( 'data-shield-scan-modal-state', state );
	await expect( stateEl ).toHaveAttribute( 'aria-busy', busy );
	const announcement = await stateEl.getAttribute( 'data-shield-scan-modal-announcement' );
	expect( announcement || '' ).not.toHaveLength( 0 );
	return announcement;
}

async function currentScanAnnouncement( modal ) {
	return modal.locator( '[data-shield-scan-modal-announcement]' )
	.evaluate( ( node ) => ( node.dataset.shieldScanModalAnnouncement || '' ).trim() );
}

async function liveRegionText( modal ) {
	return modal.locator( '[data-shield-modal-live-region="1"]' )
	.evaluate( ( node ) => ( node.textContent || '' ).trim() );
}

async function assertLiveRegionMatchesCurrentAnnouncement( modal ) {
	const announcement = await currentScanAnnouncement( modal );
	expect( announcement ).not.toHaveLength( 0 );
	await expect.poll( async () => liveRegionText( modal ) ).toBe( announcement );
	return announcement;
}

async function assertLiveRegionChangesToCurrentAnnouncement( modal, previousAnnouncement ) {
	const announcement = await assertLiveRegionMatchesCurrentAnnouncement( modal );
	expect( announcement ).not.toBe( previousAnnouncement );
	return announcement;
}

async function assertProgressbarContract( modal ) {
	const progressbar = modal.locator( '[role="progressbar"]' );
	await expect( progressbar ).toHaveAttribute( 'aria-valuemin', '0' );
	await expect( progressbar ).toHaveAttribute( 'aria-valuemax', '100' );
	expect( await progressbar.getAttribute( 'aria-valuenow' ) ).not.toHaveLength( 0 );
	expect( await progressbar.getAttribute( 'aria-label' ) ).not.toHaveLength( 0 );
}
