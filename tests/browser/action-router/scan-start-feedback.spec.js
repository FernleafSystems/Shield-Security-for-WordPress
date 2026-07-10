const { test, expect } = require( './support/shield-test' );
const { openShieldRoute } = require( './support/shield-browser' );
const {
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
} = require( './support/modal-accessibility' );

function isScanRequest( request, action ) {
	const params = new URLSearchParams( request.postData() || '' );
	return request.method() === 'POST'
		&& request.url().includes( '/admin-ajax.php' )
		&& params.get( 'ex' ) === action;
}

function actionRouterResponse( data ) {
	const { success = true, ...payload } = data;
	return {
		status: 200,
		contentType: 'application/json',
		body: JSON.stringify( {
			success,
			data: {
				page_reload: false,
				message: '',
				html: '',
				...payload,
			},
		} ),
	};
}

async function withTimeout( promise, label, timeout = 10000 ) {
	let timer;
	try {
		return await Promise.race( [
			promise,
			new Promise( ( unusedResolve, reject ) => {
				timer = setTimeout( () => reject( new Error( label ) ), timeout );
			} ),
		] );
	}
	finally {
		clearTimeout( timer );
	}
}

async function startWithEmptyTrackedScanIds( page ) {
	await page.addInitScript( () => {
		window.addEventListener( 'load', () => {
			const scans = window.shield_vars_main?.comps?.scans;
			if ( scans ) {
				scans.started_scan_ids = [];
			}
		}, { once: true } );
	} );
}

async function coordinateInitialScanCheckRace( page, staleModalState, staleModalHtml, runningModalHtml ) {
	await page.addInitScript( () => {
		window.addEventListener( 'load', () => {
			const flags = window.shield_vars_main?.comps?.scans?.flags;
			if ( flags ) {
				flags.initial_check = true;
			}
		}, { once: true } );
	} );

	let heldInitialCheck = null;
	let checkRequestCount = 0;
	let initialCheckResolve;
	let manualStartResolve;
	let nextCheckResolve;

	const initialCheckReceived = new Promise( ( resolve ) => {
		initialCheckResolve = resolve;
	} );
	const manualStartCompleted = new Promise( ( resolve ) => {
		manualStartResolve = resolve;
	} );
	const nextCheckReceived = new Promise( ( resolve ) => {
		nextCheckResolve = resolve;
	} );

	const handler = async ( route ) => {
		const request = route.request();
		if ( isScanRequest( request, 'scans_start' ) ) {
			await route.fulfill( actionRouterResponse( {
				success: true,
				scans_running: true,
				scan_ids: [ 31, 32 ],
				modal_state: 'running',
				modal_html: runningModalHtml,
			} ) );
			manualStartResolve();
			return;
		}

		if ( !isScanRequest( request, 'scans_check' ) ) {
			await route.fallback();
			return;
		}

		checkRequestCount++;
		if ( heldInitialCheck === null ) {
			heldInitialCheck = route;
			initialCheckResolve();
			return;
		}

		if ( checkRequestCount === 2 ) {
			nextCheckResolve( request.postData() || '' );
		}
		await route.fulfill( actionRouterResponse( {
			success: true,
			running: {
				afs: true,
				wpv: false,
				apc: false,
			},
			failed: false,
			failure_message: '',
			modal_state: 'running',
			modal_html: runningModalHtml,
		} ) );
	};

	await page.route( '**/admin-ajax.php', handler );

	return {
		initialCheckReceived,
		manualStartCompleted,
		nextCheckReceived,
		checkRequestCount: () => checkRequestCount,
		fulfillInitialCheck: async () => {
			if ( heldInitialCheck === null ) {
				throw new Error( 'No held initial scans_check request to fulfill.' );
			}

			await heldInitialCheck.fulfill( actionRouterResponse( {
				success: true,
				running: {
					afs: staleModalState === 'running',
					wpv: false,
					apc: false,
				},
				failed: false,
				failure_message: '',
				modal_state: staleModalState,
				modal_html: staleModalHtml,
			} ) );
		},
		cleanup: () => page.unroute( '**/admin-ajax.php', handler ),
	};
}

function scanIdsFromPostData( postData ) {
	const params = new URLSearchParams( postData );
	return [ ...params.entries() ]
	.filter( ( [ key ] ) => /^scan_ids\[\d+]$/.test( key ) )
	.map( ( [ , value ] ) => Number( value ) );
}

async function delayScanStartRequest( page, modalHtml, delayMs = 1200, scanIds = [ 31 ] ) {
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
		const shouldDelay = !handled && isScanRequest( request, 'scans_start' );

		if ( !shouldDelay ) {
			await route.fallback();
			return;
		}

		handled = true;
		startedResolve();
		await new Promise( ( resolve ) => setTimeout( resolve, delayMs ) );
		await route.fulfill( actionRouterResponse( {
			success: true,
			scans_running: true,
			scan_ids: scanIds,
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

async function respondToNextScanCheckRequest( page, modalHtml, modalState = 'completed' ) {
	let handled = false;
	let receivedResolve;

	const received = new Promise( ( resolve ) => {
		receivedResolve = resolve;
	} );

	const handler = async ( route ) => {
		const request = route.request();
		if ( handled || !isScanRequest( request, 'scans_check' ) ) {
			await route.fallback();
			return;
		}

		handled = true;
		receivedResolve( request.postData() || '' );
		await route.fulfill( actionRouterResponse( {
			success: true,
			running: {
				afs: modalState === 'running',
				wpv: false,
				apc: false,
			},
			failed: false,
			failure_message: '',
			modal_state: modalState,
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
		if ( handled || !isScanRequest( request, 'scans_check' ) ) {
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
		if ( !isScanRequest( request, 'scans_attempt_recovery' ) ) {
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
		if ( handled || !isScanRequest( request, 'scans_start' ) ) {
			await route.fallback();
			return;
		}

		handled = true;
		await route.fulfill( actionRouterResponse( {
			success: false,
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
		if ( handled || !isScanRequest( request, 'scans_start' ) ) {
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
	await openScanRunPage( page );

	const runningModalHtml = scanProgressHtml( 'running', 37 );
	const completedModalHtml = scanProgressHtml( 'completed', 100 );
	const delayedRequest = await delayScanStartRequest( page, runningModalHtml );
	const scanCheckRequest = await respondToNextScanCheckRequest( page, completedModalHtml );
	const completionRedirect = waitForScanOverviewRedirect( page );

	await submitStartScansForm( page );
	await withTimeout( delayedRequest.started, 'Timed out waiting for scans_start request.' );

	const sharedModal = scanModal( page );
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
	await sharedModal.locator( '[data-bs-dismiss="modal"]' ).click( { trial: true } );
	await observeModalLiveRegionMutations( sharedModal );
	await withTimeout( scanCheckRequest.received, 'Timed out waiting for scans_check request.' );
	await assertScanModalState( sharedModal, 'completed', 'false' );
	await assertLiveRegionChangesToCurrentAnnouncement( sharedModal, runningAnnouncement );
	await expect.poll( () => modalLiveRegionMutationCount( sharedModal ) ).toBeGreaterThan( 0 );
	await expect( completionRedirect ).resolves.toBe( 'redirect' );
} );

test( 'manual start normalizes tracked scan IDs and preserves them for empty or invalid replacements', async ( { page } ) => {
	await startWithEmptyTrackedScanIds( page );
	await openScanRunPage( page );

	const replacements = [
		{
			scanIds: [],
			expected: [],
		},
		{
			scanIds: [ '31', 31, 0, -4, 'bad', 32, 1.5, Number.MAX_SAFE_INTEGER + 1, null ],
			expected: [ 31, 32 ],
		},
		{
			scanIds: { unexpected: 44 },
			expected: [ 31, 32 ],
		},
		{
			scanIds: [ 0, -4, 'bad', 1.5, Number.MAX_SAFE_INTEGER + 1, null ],
			expected: [ 31, 32 ],
		},
	];

	for ( const [ index, replacement ] of replacements.entries() ) {
		const startModalHtml = scanProgressHtml( 'running', 20 + index );
		const checkModalHtml = scanProgressHtml( 'running', 70 + index );
		const scanCheckRequest = await respondToNextScanCheckRequest( page, checkModalHtml, 'running' );
		const scanStartRequest = await delayScanStartRequest( page, startModalHtml, 50, replacement.scanIds );

		await submitStartScansForm( page );
		await withTimeout( scanStartRequest.completed, 'Timed out waiting for scans_start response.' );
		const postData = await withTimeout(
			scanCheckRequest.received,
			'Timed out waiting for normalized scans_check request.'
		);
		expect( scanIdsFromPostData( postData ) ).toEqual( replacement.expected );
	}
} );

test( 'manual start invalidates late completed initial check and preserves started scan IDs', async ( { page } ) => {
	const staleModalHtml = scanProgressHtml( 'completed', 100 );
	const runningModalHtml = scanProgressHtml( 'running', 37 );
	const race = await coordinateInitialScanCheckRace(
		page,
		'completed',
		staleModalHtml,
		runningModalHtml
	);

	try {
		await openScanRunPage( page );
		await withTimeout( race.initialCheckReceived, 'Timed out waiting for initial scans_check request.' );
		await submitStartScansForm( page );
		await withTimeout( race.manualStartCompleted, 'Timed out waiting for scans_start response.' );

		const sharedModal = scanModal( page );
		await assertScanModalState( sharedModal, 'running', 'true' );
		const runningAnnouncement = await currentScanAnnouncement( sharedModal );
		const redirect = waitForScanOverviewRedirect( page, 1800 );

		await race.fulfillInitialCheck();
		await assertScanModalState( sharedModal, 'running', 'true' );
		expect( await currentScanAnnouncement( sharedModal ) ).toBe( runningAnnouncement );

		const nextCheckPostData = await withTimeout(
			race.nextCheckReceived,
			'Timed out waiting for replacement scans_check request.'
		);
		expect( scanIdsFromPostData( nextCheckPostData ) ).toEqual( [ 31, 32 ] );
		await expect( redirect ).resolves.toBeNull();
	}
	finally {
		await race.cleanup();
	}
} );

test( 'manual start invalidates late running initial check without creating another poll loop', async ( { page } ) => {
	const staleModalHtml = scanProgressHtml( 'running', 12 );
	const runningModalHtml = scanProgressHtml( 'running', 37 );
	const race = await coordinateInitialScanCheckRace(
		page,
		'running',
		staleModalHtml,
		runningModalHtml
	);

	try {
		await openScanRunPage( page );
		await withTimeout( race.initialCheckReceived, 'Timed out waiting for initial scans_check request.' );
		await submitStartScansForm( page );
		await withTimeout( race.manualStartCompleted, 'Timed out waiting for scans_start response.' );

		const sharedModal = scanModal( page );
		const runningAnnouncement = await assertScanModalState( sharedModal, 'running', 'true' );
		await race.fulfillInitialCheck();
		await assertScanModalState( sharedModal, 'running', 'true' );
		expect( await currentScanAnnouncement( sharedModal ) ).toBe( runningAnnouncement );

		const nextCheckPostData = await withTimeout(
			race.nextCheckReceived,
			'Timed out waiting for replacement scans_check request.'
		);
		expect( scanIdsFromPostData( nextCheckPostData ) ).toEqual( [ 31, 32 ] );
		const requestCountAfterReplacement = race.checkRequestCount();
		await page.waitForTimeout( 2300 );
		expect( race.checkRequestCount() ).toBe( requestCountAfterReplacement );
	}
	finally {
		await race.cleanup();
	}
} );

test( 'scan recovery serializes clicks and ignores stale poll response', async ( { page } ) => {
	await openScanRunPage( page );

	const runningModalHtml = scanProgressHtml( 'running', 37, { recoveryScanId: 31 } );
	const recoveryModalHtml = scanProgressHtml( 'running', 64 );
	const stalePollModalHtml = scanProgressHtml( 'completed', 100 );
	const delayedRequest = await delayScanStartRequest( page, runningModalHtml, 50 );
	const heldScanCheck = await holdNextScanCheckRequest( page, stalePollModalHtml );
	const heldRecovery = await holdNextScanRecoveryRequest( page, recoveryModalHtml );

	await submitStartScansForm( page );
	await withTimeout( delayedRequest.completed, 'Timed out waiting for delayed scans_start response.' );

	const sharedModal = scanModal( page );
	await expect( sharedModal ).toBeVisible();
	await assertScanModalState( sharedModal, 'running', 'true' );
	await withTimeout( heldScanCheck.received, 'Timed out waiting for held scans_check request.', 5000 );

	const recoveryButton = scanRecoveryButton( sharedModal );
	await expect( recoveryButton ).toBeVisible();
	await recoveryButton.evaluate( ( button ) => {
		button.click();
		button.click();
	} );
	await withTimeout( heldRecovery.received, 'Timed out waiting for held scans_attempt_recovery request.' );
	await page.waitForTimeout( 150 );
	expect( heldRecovery.requestCount() ).toBe( 1 );
	await expectRecoveryControlBusy( recoveryButton, true );

	await heldRecovery.fulfill();
	await assertScanModalState( sharedModal, 'running', 'true' );

	await heldScanCheck.fulfill();
	await assertScanModalState( sharedModal, 'running', 'true' );
	await expect( waitForScanOverviewRedirect( page, 1500 ) ).resolves.toBeNull();
} );

test( 'manual start supersedes recovery while preserving its lock cleanup and polling', async ( { page } ) => {
	await openScanRunPage( page );

	const firstRunningModalHtml = scanProgressHtml( 'running', 37, { recoveryScanId: 31 } );
	const replacementModalHtml = scanProgressHtml( 'running', 64, { recoveryScanId: 32 } );
	const staleRecoveryModalHtml = scanProgressHtml( 'running', 81 );
	const completedModalHtml = scanProgressHtml( 'completed', 100 );
	const firstStart = await delayScanStartRequest( page, firstRunningModalHtml, 50 );
	const heldRecovery = await holdNextScanRecoveryRequest( page, staleRecoveryModalHtml );

	await submitStartScansForm( page );
	await withTimeout( firstStart.completed, 'Timed out waiting for first scans_start response.' );
	const sharedModal = scanModal( page );
	const firstRecoveryButton = scanRecoveryButton( sharedModal );
	await firstRecoveryButton.click();
	await withTimeout( heldRecovery.received, 'Timed out waiting for held scans_attempt_recovery request.' );

	const secondStart = await delayScanStartRequest( page, replacementModalHtml, 50, [ 31, 32 ] );
	const resumedCheck = await respondToNextScanCheckRequest( page, completedModalHtml );
	await submitStartScansForm( page );
	await withTimeout( secondStart.completed, 'Timed out waiting for replacement scans_start response.' );
	const replacementAnnouncement = await assertScanModalState( sharedModal, 'running', 'true' );
	const replacementRecoveryButton = scanRecoveryButton( sharedModal );
	await expectRecoveryControlBusy( replacementRecoveryButton, true );

	await page.waitForTimeout( 1200 );
	await expectRecoveryControlBusy( replacementRecoveryButton, true );
	await replacementRecoveryButton.evaluate( ( button ) => {
		button.click();
		button.click();
	} );
	await page.waitForTimeout( 150 );
	expect( heldRecovery.requestCount() ).toBe( 1 );

	await heldRecovery.fulfill();
	await assertScanModalState( sharedModal, 'running', 'true' );
	expect( await currentScanAnnouncement( sharedModal ) ).toBe( replacementAnnouncement );
	await expectRecoveryControlBusy( replacementRecoveryButton, false );

	const resumedCheckPostData = await withTimeout(
		resumedCheck.received,
		'Timed out waiting for polling to resume after stale recovery.',
		5000
	);
	expect( scanIdsFromPostData( resumedCheckPostData ) ).toEqual( [ 31, 32 ] );
} );

test( 'manual scan failure modal returns focus to the previous scan control when closed', async ( { page } ) => {
	const previousControl = await openScanRunPage( page );
	await previousControl.focus();

	const failedModalHtml = scanProgressHtml( 'failed', 100 );
	await failNextScanStartRequest( page, failedModalHtml );

	await submitStartScansForm( page );

	const sharedModal = scanModal( page );
	await expect( sharedModal ).toBeVisible();
	await expectNamedDialog( page, sharedModal );
	const failedAnnouncement = await assertScanModalState( sharedModal, 'failed', 'false' );
	expect( failedAnnouncement ).not.toHaveLength( 0 );
	await assertLiveRegionMatchesCurrentAnnouncement( sharedModal );

	await sharedModal.locator( '[data-bs-dismiss="modal"]' ).click();
	await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
	await expect( previousControl ).toBeFocused();
} );

test( 'manual scan start shows local error modal when response lacks modal contract', async ( { page } ) => {
	const previousControl = await openScanRunPage( page );
	await previousControl.focus();
	await respondToNextScanStartWithoutModal( page );

	await submitStartScansForm( page );

	const sharedModal = scanModal( page );
	await expect( sharedModal ).toBeVisible();
	await expectNamedDialog( page, sharedModal );
	await assertScanModalState( sharedModal, 'failed', 'false' );
	await assertLiveRegionMatchesCurrentAnnouncement( sharedModal );

	await sharedModal.locator( '[data-bs-dismiss="modal"]' ).click();
	await expect( previousControl ).toBeFocused();
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

async function openScanRunPage( page ) {
	await openShieldRoute( page, {
		nav: 'scans',
		nav_sub: 'run',
	} );
	await page.waitForFunction( () => {
		return Object.keys( window.shieldEventsHandler_Main?.eventHandlers?.submit || {} )
		.includes( 'form#StartScans' );
	}, null, { timeout: 10000 } );

	await expect( page.locator( '#StartScans' ) ).toBeVisible();
	const firstEnabledScanControl = page.locator( '#StartScans input[type="checkbox"]:enabled' ).first();
	await expect( firstEnabledScanControl ).toBeVisible();
	return firstEnabledScanControl;
}

function scanModal( page ) {
	return page.locator( '#ShieldModalContainer' );
}

function scanRecoveryButton( modal ) {
	return modal.locator( '[data-shield-scan-attempt-recovery="1"]' ).first();
}

async function submitStartScansForm( page ) {
	await page.locator( '#StartScans' ).evaluate( ( form ) => {
		form.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );
	} );
}

async function expectRecoveryControlBusy( button, isBusy ) {
	if ( isBusy ) {
		await expect( button ).toBeDisabled();
	}
	else {
		await expect( button ).toBeEnabled();
	}
	await expect( button ).toHaveAttribute( 'aria-disabled', isBusy ? 'true' : 'false' );
	await expect( button ).toHaveAttribute( 'aria-busy', isBusy ? 'true' : 'false' );
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
	const liveRegion = modal.locator( '[data-shield-modal-live-region="1"]' );
	await expect( liveRegion ).toBeVisible();
	await expect( liveRegion ).toHaveAttribute( 'aria-live', /^(polite|assertive)$/ );
	await expect( liveRegion ).toHaveText( announcement );
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
