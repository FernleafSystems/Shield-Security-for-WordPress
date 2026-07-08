const { test, expect } = require( './support/shield-test' );
const { openShieldRoute } = require( './support/shield-browser' );
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

function isScanAttemptRecoveryRequest( request ) {
	return request.method() === 'POST'
		&& request.url().includes( '/admin-ajax.php' )
		&& ( request.postData() || '' ).includes( 'ex=scans_attempt_recovery' );
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

async function holdNextScanCheckRequest( page, modalHtml ) {
	let handled = false;
	let routeToFulfill = null;
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
		routeToFulfill = route;
		receivedResolve();
	};

	await page.route( '**/admin-ajax.php', handler );

	return {
		received,
		fulfill: async () => {
			if ( routeToFulfill === null ) {
				throw new Error( 'No held scans_check request to fulfill.' );
			}

			await routeToFulfill.fulfill( actionRouterResponse( {
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
		},
	};
}

async function holdNextScanRecoveryRequest( page, modalHtml ) {
	let requestCount = 0;
	let routeToFulfill = null;
	let receivedResolve;

	const received = new Promise( ( resolve ) => {
		receivedResolve = resolve;
	} );

	const response = actionRouterResponse( {
		success: true,
		running: {
			afs: true,
			wpv: false,
			apc: false,
		},
		failed: false,
		failure_message: '',
		modal_state: 'running',
		modal_html: modalHtml,
	} );

	const handler = async ( route ) => {
		const request = route.request();
		if ( !isScanAttemptRecoveryRequest( request ) ) {
			await route.fallback();
			return;
		}

		requestCount++;
		if ( routeToFulfill !== null ) {
			await route.fulfill( response );
			return;
		}

		routeToFulfill = route;
		receivedResolve();
	};

	await page.route( '**/admin-ajax.php', handler );

	return {
		received,
		requestCount: () => requestCount,
		fulfill: async () => {
			if ( routeToFulfill === null ) {
				throw new Error( 'No held scans_attempt_recovery request to fulfill.' );
			}

			await routeToFulfill.fulfill( response );
			await page.unroute( '**/admin-ajax.php', handler ).catch( () => null );
		},
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

function waitForScanOverviewRedirect( page, timeout = 8000 ) {
	return page.waitForURL( ( url ) => {
		return url.searchParams.get( 'nav' ) === 'scans'
			&& url.searchParams.get( 'nav_sub' ) === 'overview';
	}, { timeout } )
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

	const runningModalHtml = scanProgressHtml( 'running', 37 );
	const completedModalHtml = scanProgressHtml( 'completed', 100 );
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

	await observeModalLiveRegionMutations( sharedModal );
	await withTimeout( delayedRequest.completed, 'Timed out waiting for delayed scans_start response.' );
	await expect( sharedModal ).toBeVisible();
	await assertScanModalState( sharedModal, 'running', 'true' );
	const runningAnnouncement = await assertLiveRegionChangesToCurrentAnnouncement( sharedModal, initiatingAnnouncement );
	await expect.poll( () => modalLiveRegionMutationCount( sharedModal ) ).toBeGreaterThan( 0 );
	await assertProgressbarContract( sharedModal );
	await sharedModal.locator( '.btn-close' ).click( { trial: true } );
	await observeModalLiveRegionMutations( sharedModal );
	await withTimeout( scanCheckRequest.received, 'Timed out waiting for scans_check request.' );
	await assertScanModalState( sharedModal, 'completed', 'false' );
	await assertLiveRegionChangesToCurrentAnnouncement( sharedModal, runningAnnouncement );
	await expect.poll( () => modalLiveRegionMutationCount( sharedModal ) ).toBeGreaterThan( 0 );
	await expect( completionRedirect ).resolves.toBe( 'redirect' );
} );

test( 'scan recovery serializes clicks and ignores stale poll response', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'scans',
		nav_sub: 'run',
	} );
	await page.waitForFunction( () => {
		return Object.keys( window.shieldEventsHandler_Main?.eventHandlers?.submit || {} )
		.includes( 'form#StartScans' );
	}, null, { timeout: 10000 } );
	await ensureStartScansButton( page );

	const runningModalHtml = scanProgressHtml( 'running', 37, { recoveryScanId: 31 } );
	const recoveryModalHtml = scanProgressHtml( 'running', 64 );
	const stalePollModalHtml = scanProgressHtml( 'completed', 100 );
	const delayedRequest = await delayScanStartRequest( page, runningModalHtml, 50 );
	const heldScanCheck = await holdNextScanCheckRequest( page, stalePollModalHtml );
	const heldRecovery = await holdNextScanRecoveryRequest( page, recoveryModalHtml );

	await page.locator( '#StartScansButton' ).first().click();
	await withTimeout( delayedRequest.completed, 'Timed out waiting for delayed scans_start response.' );

	const sharedModal = page.locator( '#ShieldModalContainer.modal.show' );
	await expect( sharedModal ).toBeVisible();
	await assertScanModalState( sharedModal, 'running', 'true' );
	await withTimeout( heldScanCheck.received, 'Timed out waiting for held scans_check request.', 5000 );

	const recoveryButton = sharedModal.locator( '[data-shield-scan-attempt-recovery="1"]' ).first();
	await expect( recoveryButton ).toBeVisible();
	await recoveryButton.evaluate( ( button ) => {
		button.click();
		button.click();
	} );
	await withTimeout( heldRecovery.received, 'Timed out waiting for held scans_attempt_recovery request.' );
	await page.waitForTimeout( 150 );
	expect( heldRecovery.requestCount() ).toBe( 1 );
	await expect( recoveryButton ).toBeDisabled();

	await heldRecovery.fulfill();
	await assertScanModalState( sharedModal, 'running', 'true' );

	await heldScanCheck.fulfill();
	await assertScanModalState( sharedModal, 'running', 'true' );
	await expect( waitForScanOverviewRedirect( page, 1500 ) ).resolves.toBeNull();
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

	const failedModalHtml = scanProgressHtml( 'failed', 100 );
	await failNextScanStartRequest( page, failedModalHtml );

	await page.locator( '#StartScansButton' ).first().click();

	const sharedModal = page.locator( '#ShieldModalContainer.modal.show' );
	await expect( sharedModal ).toBeVisible();
	await expectNamedDialog( page, sharedModal );
	const failedAnnouncement = await assertScanModalState( sharedModal, 'failed', 'false' );
	expect( failedAnnouncement ).not.toHaveLength( 0 );
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

function scanProgressHtml( modalState, progress, options = {} ) {
	const isInitiating = modalState === 'initiating';
	const isFailed = modalState === 'failed';
	const isRunning = isInitiating || modalState === 'running';
	const recoveryScanId = Number( options.recoveryScanId || 0 );
	const remainingScans = 'scan-contract-remaining';
	const announcement = `scan-contract-${modalState}-${progress}`;
	const heading = `scan-contract-heading-${modalState}`;
	const recoveryControl = recoveryScanId > 0
		? `<button type="button" data-shield-scan-attempt-recovery="1" data-scan-id="${recoveryScanId}">Recover</button>`
		: '';

	return `<div class="modal-header">
		<h5 class="modal-title" id="ShieldModalContainerLabel">Scan Progress</h5>
		<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
	</div>
	<div class="modal-body">
		<div data-shield-scan-modal-state="${escapeHtml( modalState )}"
			 aria-busy="${isRunning ? 'true' : 'false'}"
			 data-shield-scan-modal-announcement="${escapeHtml( announcement )}">
			<h6>${escapeHtml( heading )}</h6>
			<p>${escapeHtml( remainingScans )}</p>
			${isFailed ? '' : `<div class="progress">
				<div class="progress-bar" role="progressbar"
					 style="width: ${progress}%"
					 aria-label="Scan progress"
					 aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100"></div>
			</div>`}
			${recoveryControl}
		</div>
	</div>
	<div class="modal-footer"></div>`;
}

function escapeHtml( text = '' ) {
	return String( text )
	.replace( /&/g, '&amp;' )
	.replace( /</g, '&lt;' )
	.replace( />/g, '&gt;' )
	.replace( /"/g, '&quot;' )
	.replace( /'/g, '&#39;' );
}

async function ensureStartScansButton( page ) {
	if ( await page.locator( '#StartScansButton' ).count() === 0 ) {
		await page.locator( '#StartScans' ).evaluate( ( form ) => {
			const button = document.createElement( 'button' );
			button.type = 'submit';
			button.id = 'StartScansButton';
			button.appendChild( document.createTextNode( 'Run' ) );
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

async function assertLiveRegionMatchesCurrentAnnouncement( modal ) {
	const announcement = await currentScanAnnouncement( modal );
	expect( announcement ).not.toHaveLength( 0 );
	await expect( modal.locator( '[data-shield-modal-live-region="1"]' ) ).toBeVisible();
	await expect( modal.locator( '[data-shield-modal-live-region="1"]' ) ).toHaveAttribute( 'aria-live', /^(polite|assertive)$/ );
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

async function observeModalLiveRegionMutations( modal ) {
	await modal.locator( '[data-shield-modal-live-region="1"]' ).evaluate( ( node ) => {
		node.__shieldModalLiveRegionMutationCount = 0;
		node.__shieldModalLiveRegionObserver?.disconnect?.();
		node.__shieldModalLiveRegionObserver = new MutationObserver( () => {
			node.__shieldModalLiveRegionMutationCount++;
		} );
		node.__shieldModalLiveRegionObserver.observe( node, {
			childList: true,
			characterData: true,
			subtree: true,
		} );
	} );
}

const modalLiveRegionMutationCount = ( modal ) => modal.locator( '[data-shield-modal-live-region="1"]' )
.evaluate( ( node ) => Number( node.__shieldModalLiveRegionMutationCount || 0 ) );
