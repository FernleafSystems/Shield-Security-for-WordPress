const { test, expect } = require( './support/shield-test' );
const { expectNoAxeViolations } = require( './support/accessibility' );
const { openShieldRoute } = require( './support/shield-browser' );

function isScanRequest( request, action ) {
	const params = new URLSearchParams( request.postData() || '' );
	return request.method() === 'POST'
		&& request.url().includes( '/admin-ajax.php' )
		&& params.get( 'ex' ) === action;
}

// The envelope the AJAX adapter wraps every action's payload in.
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

/**
 * The scan actions' reply: the progress, as ScanProgressBuilder reports it, and for a start that set scans running,
 * the scans to follow.
 */
function progressResponse( state, html, { detail = '', scanIds = null, success = true } = {} ) {
	return actionRouterResponse( {
		success,
		progress_state: state,
		progress_html: html,
		progress_detail: detail,
		...( scanIds === null ? {} : { scan_ids: scanIds } ),
	} );
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

// The test site has no running scans, so pages that should poll are told to. Shield starts once the page is parsed,
// and this is listening before Shield's script is, so it's told first.
async function pollOnLoad( page ) {
	await page.addInitScript( () => {
		document.addEventListener( 'DOMContentLoaded', () => {
			const flags = window.shield_vars_main?.comps?.scans?.flags;
			if ( flags ) {
				flags.initial_check = true;
			}
		}, { once: true } );
	} );
}

/**
 * Holds the poll the page starts on load, answers a start with the given reply, and answers later polls as running.
 */
async function coordinateInitialScanCheckRace( page, initialState, initialHtml, runningHtml, startReply = null ) {
	await pollOnLoad( page );

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
			await route.fulfill( startReply || progressResponse( 'running', runningHtml, { scanIds: [ 31, 32 ] } ) );
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
		await route.fulfill( progressResponse( 'running', runningHtml ) );
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
			await heldInitialCheck.fulfill( progressResponse( initialState, initialHtml ) );
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

async function delayScanStartRequest( page, runningHtml, delayMs = 1200, scanIds = [ 31 ] ) {
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
		await route.fulfill( progressResponse( 'running', runningHtml, { scanIds } ) );
		completedResolve();
		await page.unroute( '**/admin-ajax.php', handler ).catch( () => null );
	};

	await page.route( '**/admin-ajax.php', handler );

	return {
		started,
		completed,
	};
}

async function holdNextScanCheckRequest( page, completedHtml ) {
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

			await routeToFulfill.fulfill( progressResponse( 'completed', completedHtml ) );
			await page.unroute( '**/admin-ajax.php', handler ).catch( () => null );
		},
	};
}

async function holdNextScanRecoveryRequest( page, runningHtml ) {
	let requestCount = 0;
	let routeToFulfill = null;
	let receivedResolve;

	const received = new Promise( ( resolve ) => {
		receivedResolve = resolve;
	} );

	const response = progressResponse( 'running', runningHtml );

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
		receivedResolve( request.postData() || '' );
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

async function answerNextScanStart( page, reply ) {
	let handled = false;
	const handler = async ( route ) => {
		const request = route.request();
		if ( handled || !isScanRequest( request, 'scans_start' ) ) {
			await route.fallback();
			return;
		}

		handled = true;
		await route.fulfill( reply );
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

test( 'manual scan start shows progress and then the result in the page, with no dialog or redirect', async ( { page, fixtureApi } ) => {
	await openScanRunPage( page );

	const { progress_html: runningHtml } = await fixtureApi.renderScanProgressFixture( 'running' );
	const { progress_html: completedHtml } = await fixtureApi.renderScanProgressFixture( 'completed' );
	const delayedRequest = await delayScanStartRequest( page, runningHtml );
	const scanCheckRequest = await holdNextScanCheckRequest( page, completedHtml );

	await submitStartScansForm( page );
	await withTimeout( delayedRequest.started, 'Timed out waiting for scans_start request.' );

	const scanRun = scanRunPage( page );
	await expect( scanRun.locator( '[data-scan-step-panel="progress"]' ) ).toBeVisible();
	await expect( scanRun.locator( '[data-scan-step-panel="choose"]' ) ).toBeHidden();
	await assertProgressState( scanRun, 'initiating', 'true' );
	const initiatingAnnouncement = await assertLiveRegionMatchesCurrentAnnouncement( scanRun );
	await expect( page.locator( '#ShieldOverlay' ) ).toBeHidden();
	await expect( page.locator( '#ShieldModalContainer' ) ).toBeHidden();
	await observeLiveRegionMutations( scanRun );
	await expectNoAxeViolations( page, '[data-shield-scan-run="1"]' );

	await withTimeout( delayedRequest.completed, 'Timed out waiting for delayed scans_start response.' );
	await assertProgressState( scanRun, 'running', 'true' );
	await expect( scanRun.locator( '[data-scan-step-tab="scanning"]' ) ).toHaveAttribute( 'aria-current', 'step' );
	const runningAnnouncement = await assertLiveRegionChangesToCurrentAnnouncement( scanRun, initiatingAnnouncement );
	await expect.poll( () => liveRegionMutationCount( scanRun ) ).toBeGreaterThan( 0 );
	await assertProgressbarContract( scanRun );
	await expectNoAxeViolations( page, '[data-shield-scan-run="1"]' );
	await observeLiveRegionMutations( scanRun );
	await withTimeout( scanCheckRequest.received, 'Timed out waiting for scans_check request.' );
	const redirect = waitForScanOverviewRedirect( page, 2500 );
	await scanCheckRequest.fulfill();
	await assertProgressState( scanRun, 'completed', 'false' );
	await expect( scanRun.locator( '[data-scan-step-tab="results"]' ) ).toHaveAttribute( 'aria-current', 'step' );
	await expect( scanRun.locator( '[data-scan-step-tab="choose"]' ) ).toHaveClass( /is-done/ );
	await assertLiveRegionChangesToCurrentAnnouncement( scanRun, runningAnnouncement );
	await expect.poll( () => liveRegionMutationCount( scanRun ) ).toBeGreaterThan( 0 );
	await expect( scanRun.locator( 'a[href*="nav_sub=overview"]' ).first() ).toBeVisible();
	await expect( scanRun.locator( 'a[href*="nav_sub=run"]' ) ).toBeVisible();
	await expectNoAxeViolations( page, '[data-shield-scan-run="1"]' );
	await expect( redirect ).resolves.toBeNull();
	await expect( page.locator( '#ShieldModalContainer' ) ).toBeHidden();
} );

test( 'scan progress on the Actions Queue shows in its top bar, not in a dialog', async ( { page } ) => {
	await pollOnLoad( page );
	const handler = async ( route ) => {
		if ( !isScanRequest( route.request(), 'scans_check' ) ) {
			await route.fallback();
			return;
		}
		await route.fulfill( progressResponse( 'running', scanProgressHtml( 'running', 37 ), { detail: 'WordPress Filesystem Scan 37%' } ) );
	};
	await page.route( '**/admin-ajax.php', handler );

	try {
		await openShieldRoute( page, { nav: 'scans', nav_sub: 'overview' } );

		// The queue stays usable: a link to the scans' progress says they're running.
		const indicator = page.locator( '[data-shield-scan-indicator="1"]' );
		await expect( indicator ).toBeVisible();
		await expect( indicator ).toHaveAttribute( 'data-state', 'running' );
		await expect( indicator ).toContainText( 'WordPress Filesystem Scan 37%' );
		await expect( indicator ).toHaveAttribute( 'href', /nav_sub=run/ );
		await expect( page.locator( '#ShieldModalContainer' ) ).toBeHidden();
		await expect( page.locator( '[data-shield-scan-run="1"]' ) ).toHaveCount( 0 );
	}
	finally {
		await page.unroute( '**/admin-ajax.php', handler );
	}
} );

test( 'manual start invalidates late completed initial check and follows the started scans', async ( { page } ) => {
	const race = await coordinateInitialScanCheckRace(
		page,
		'completed',
		scanProgressHtml( 'completed', 100 ),
		scanProgressHtml( 'running', 37 )
	);

	try {
		await openScanRunPage( page );
		await withTimeout( race.initialCheckReceived, 'Timed out waiting for initial scans_check request.' );
		await submitStartScansForm( page );
		await withTimeout( race.manualStartCompleted, 'Timed out waiting for scans_start response.' );

		const scanRun = scanRunPage( page );
		await assertProgressState( scanRun, 'running', 'true' );
		const runningAnnouncement = await currentAnnouncement( scanRun );
		const redirect = waitForScanOverviewRedirect( page, 1800 );

		await race.fulfillInitialCheck();
		await assertProgressState( scanRun, 'running', 'true' );
		expect( await currentAnnouncement( scanRun ) ).toBe( runningAnnouncement );

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
	const race = await coordinateInitialScanCheckRace(
		page,
		'running',
		scanProgressHtml( 'running', 12 ),
		scanProgressHtml( 'running', 37 )
	);

	try {
		await openScanRunPage( page );
		await withTimeout( race.initialCheckReceived, 'Timed out waiting for initial scans_check request.' );
		await submitStartScansForm( page );
		await withTimeout( race.manualStartCompleted, 'Timed out waiting for scans_start response.' );

		const scanRun = scanRunPage( page );
		const runningAnnouncement = await assertProgressState( scanRun, 'running', 'true' );
		await race.fulfillInitialCheck();
		await assertProgressState( scanRun, 'running', 'true' );
		expect( await currentAnnouncement( scanRun ) ).toBe( runningAnnouncement );

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

test( 'a failed start is not replaced by a progress update already on its way, and polling stops', async ( { page, fixtureApi } ) => {
	const { progress_html: failedHtml } = await fixtureApi.renderScanProgressFixture( 'failed' );
	const race = await coordinateInitialScanCheckRace(
		page,
		'running',
		scanProgressHtml( 'running', 12 ),
		scanProgressHtml( 'running', 37 ),
		progressResponse( 'failed', failedHtml, { success: false } )
	);

	try {
		await openScanRunPage( page );
		await withTimeout( race.initialCheckReceived, 'Timed out waiting for initial scans_check request.' );
		await submitStartScansForm( page );
		await withTimeout( race.manualStartCompleted, 'Timed out waiting for scans_start response.' );

		const scanRun = scanRunPage( page );
		await assertProgressState( scanRun, 'failed', 'false' );
		await race.fulfillInitialCheck();
		await page.waitForTimeout( 3500 );
		await assertProgressState( scanRun, 'failed', 'false' );
		await expect( scanRun ).toContainText( 'Browser fixture failure' );
		expect( race.checkRequestCount() ).toBe( 1 );
	}
	finally {
		await race.cleanup();
	}
} );

test( 'scan recovery serializes clicks, reports the followed scans and ignores a stale poll response', async ( { page } ) => {
	await openScanRunPage( page );

	const delayedRequest = await delayScanStartRequest( page, scanProgressHtml( 'running', 37, { recoveryScanId: 31 } ), 50 );
	const heldScanCheck = await holdNextScanCheckRequest( page, scanProgressHtml( 'completed', 100 ) );
	const heldRecovery = await holdNextScanRecoveryRequest( page, scanProgressHtml( 'running', 64 ) );

	await submitStartScansForm( page );
	await withTimeout( delayedRequest.completed, 'Timed out waiting for delayed scans_start response.' );

	const scanRun = scanRunPage( page );
	await assertProgressState( scanRun, 'running', 'true' );
	await withTimeout( heldScanCheck.received, 'Timed out waiting for held scans_check request.', 5000 );

	const recoveryButton = scanRecoveryButton( scanRun );
	await expect( recoveryButton ).toBeVisible();
	await recoveryButton.evaluate( ( button ) => {
		button.click();
		button.click();
	} );
	const recoveryPostData = await withTimeout( heldRecovery.received, 'Timed out waiting for held scans_attempt_recovery request.' );
	expect( new URLSearchParams( recoveryPostData ).get( 'scan_id' ) ).toBe( '31' );
	expect( scanIdsFromPostData( recoveryPostData ) ).toEqual( [ 31 ] );
	await page.waitForTimeout( 150 );
	expect( heldRecovery.requestCount() ).toBe( 1 );
	await expectRecoveryControlBusy( recoveryButton, true );

	await heldRecovery.fulfill();
	await expect.poll( () => currentAnnouncement( scanRun ) ).toBe( 'scan-contract-running-64' );
	await assertProgressState( scanRun, 'running', 'true' );

	// The poll sent before the recovery answers with a finished scan: it was replaced, so the page ignores it.
	await heldScanCheck.fulfill();
	await page.waitForTimeout( 500 );
	await assertProgressState( scanRun, 'running', 'true' );
	expect( await currentAnnouncement( scanRun ) ).toBe( 'scan-contract-running-64' );
} );

test( 'a scan recovered on a page opened while scans run is followed by later progress updates', async ( { page } ) => {
	await pollOnLoad( page );
	const checkScanIds = [];
	const handler = async ( route ) => {
		const request = route.request();
		if ( isScanRequest( request, 'scans_check' ) ) {
			checkScanIds.push( scanIdsFromPostData( request.postData() || '' ) );
			await route.fulfill( progressResponse( 'running', scanProgressHtml( 'running', 37, { recoveryScanId: 41 } ) ) );
		}
		else if ( isScanRequest( request, 'scans_attempt_recovery' ) ) {
			await route.fulfill( progressResponse( 'running', scanProgressHtml( 'running', 38 ) ) );
		}
		else {
			await route.fallback();
		}
	};
	await page.route( '**/admin-ajax.php', handler );

	try {
		await openShieldRoute( page, { nav: 'scans', nav_sub: 'run' } );
		const scanRun = scanRunPage( page );
		await scanRecoveryButton( scanRun ).click();

		await expect.poll( () => checkScanIds.at( -1 ), { timeout: 10000 } ).toEqual( [ 41 ] );
		expect( checkScanIds[ 0 ] ).toEqual( [] );
	}
	finally {
		await page.unroute( '**/admin-ajax.php', handler );
	}
} );

test( 'manual scan failure shows in the page with a way to try again', async ( { page, fixtureApi } ) => {
	await openScanRunPage( page );

	const { progress_html: failedHtml } = await fixtureApi.renderScanProgressFixture( 'failed' );
	await answerNextScanStart( page, progressResponse( 'failed', failedHtml, { success: false } ) );

	await submitStartScansForm( page );

	const scanRun = scanRunPage( page );
	await assertProgressState( scanRun, 'failed', 'false' );
	await assertLiveRegionMatchesCurrentAnnouncement( scanRun );
	await expect( scanRun ).toContainText( 'Browser fixture failure' );
	await expect( scanRun.getByRole( 'link', { name: 'Try again' } ) ).toHaveAttribute( 'href', /step=choose/ );
	await expect( page.locator( '#ShieldModalContainer' ) ).toBeHidden();
	await expectNoAxeViolations( page, '[data-shield-scan-run="1"]' );
} );

test( 'a refused start shows the server reason in the page as plain text', async ( { page } ) => {
	await openScanRunPage( page );
	await answerNextScanStart( page, actionRouterResponse( { success: false, message: '<b>scan-start-refused</b>' } ) );

	await submitStartScansForm( page );

	const scanRun = scanRunPage( page );
	await assertProgressState( scanRun, 'failed', 'false' );
	await expect( scanRun.locator( '[data-scan-progress-message="1"]' ) ).toHaveText( '<b>scan-start-refused</b>' );
	await expect( scanRun.locator( '[data-scan-progress-message="1"] b' ) ).toHaveCount( 0 );
	expect( await currentAnnouncement( scanRun ) ).toBe( '<b>scan-start-refused</b>' );
	await assertLiveRegionMatchesCurrentAnnouncement( scanRun );
	await expect( scanRun.getByRole( 'link', { name: 'Try again' } ) ).toBeVisible();
	await expectNoAxeViolations( page, '[data-shield-scan-run="1"]' );
} );

/**
 * Answers the scan polling in the order given: 'running' as the server would, 'lost' as a request that never
 * gets an answer, and 'rejected' as the server refusing with its reason. The last answer repeats.
 */
async function scriptScanChecks( page, outcomes ) {
	await pollOnLoad( page );

	let polls = 0;
	await page.route( '**/admin-ajax.php', async ( route ) => {
		if ( !isScanRequest( route.request(), 'scans_check' ) ) {
			await route.fallback();
			return;
		}
		const outcome = outcomes[ Math.min( polls, outcomes.length - 1 ) ];
		polls++;
		if ( outcome === 'lost' ) {
			await route.abort( 'failed' );
		}
		else if ( outcome === 'rejected' ) {
			await route.fulfill( actionRouterResponse( { success: false, message: 'scan-contract-refused' } ) );
		}
		else {
			await route.fulfill( progressResponse( 'running', scanProgressHtml( 'running', 37 ), { detail: 'WordPress Filesystem Scan 37%' } ) );
		}
	} );
	return () => polls;
}

test( 'one unanswered progress update leaves the scans running and the polling going', async ( { page } ) => {
	const polls = await scriptScanChecks( page, [ 'running', 'lost', 'running' ] );
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'run' } );

	await expect.poll( polls, { timeout: 15000 } ).toBeGreaterThanOrEqual( 3 );
	await assertProgressState( scanRunPage( page ), 'running', 'true' );
} );

test( 'progress updates that keep going unanswered say so without calling the scan failed, then recover', async ( { page } ) => {
	const polls = await scriptScanChecks( page, [ 'running', 'lost', 'lost', 'lost', 'running' ] );
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'run' } );

	const scanRun = scanRunPage( page );
	await expect( scanRun.locator( '[data-scan-progress-state]' ) )
	.toHaveAttribute( 'data-scan-progress-state', 'stale', { timeout: 15000 } );
	await expect( scanRun ).toContainText( 'may still be running' );
	await expect( scanRun.locator( '[data-scan-progress-state]' ) ).not.toContainText( 'failed' );
	await expect( scanRun.getByRole( 'link', { name: 'Refresh' } ) ).toHaveAttribute( 'href', /nav_sub=run/ );
	await expectNoAxeViolations( page, '[data-shield-scan-run="1"]' );

	await expect.poll( polls, { timeout: 15000 } ).toBeGreaterThanOrEqual( 5 );
	await assertProgressState( scanRun, 'running', 'true' );
} );

test( 'the actions queue keeps showing scans as running while progress updates go unanswered', async ( { page } ) => {
	await scriptScanChecks( page, [ 'running', 'lost', 'lost', 'lost', 'running' ] );
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'overview' } );

	const indicator = page.locator( '[data-shield-scan-indicator="1"]' );
	await expect( indicator ).toContainText( "couldn't be updated", { timeout: 15000 } );
	await expect( indicator ).toHaveAttribute( 'data-state', 'running' );
	await expect( indicator ).toContainText( 'WordPress Filesystem Scan 37%', { timeout: 15000 } );
} );

test( 'a progress update the server refuses still shows as a failure, with its reason', async ( { page } ) => {
	await scriptScanChecks( page, [ 'running', 'rejected' ] );
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'run' } );

	const scanRun = scanRunPage( page );
	await expect( scanRun.locator( '[data-scan-progress-state]' ) )
	.toHaveAttribute( 'data-scan-progress-state', 'failed', { timeout: 15000 } );
	await expect( scanRun ).toContainText( 'scan-contract-refused' );
} );

// Markup with the progress root's contract, for tests about how the page handles replies rather than what they show.
function scanProgressHtml( state, progress, options = {} ) {
	const isRunning = state === 'initiating' || state === 'running';
	const recoveryScanId = Number( options.recoveryScanId || 0 );
	const announcement = `scan-contract-${state}-${progress}`;
	const recoveryControl = recoveryScanId > 0
		? `<button type="button" data-scan-attempt-recovery="1" data-scan-id="${recoveryScanId}">Recover</button>`
		: '';
	// A finished scan's heading takes focus when it arrives, as the real markup's does.
	const headingAttrs = state === 'completed' ? ' id="ScanFinishedTitle" tabindex="-1"' : '';

	return `<div class="shield-scan-progress"
		 data-scan-progress-state="${escapeHtml( state )}"
		 aria-busy="${isRunning ? 'true' : 'false'}"
		 data-scan-progress-announcement="${escapeHtml( announcement )}">
		<h2${headingAttrs}>${escapeHtml( `scan-contract-heading-${state}` )}</h2>
		<div role="progressbar" aria-label="Scan progress"
			 aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100"></div>
		${recoveryControl}
	</div>`;
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

// Run Manual Scan holds both the progress and the live region that announces it.
function scanRunPage( page ) {
	return page.locator( '[data-shield-scan-run="1"]' );
}

function scanRecoveryButton( scanRun ) {
	return scanRun.locator( '[data-scan-attempt-recovery="1"]' ).first();
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
	await expect( button ).toHaveAttribute( 'aria-busy', isBusy ? 'true' : 'false' );
}

async function assertProgressState( scanRun, state, busy ) {
	const stateEl = scanRun.locator( '[data-scan-progress-state]' );
	await expect( stateEl ).toHaveAttribute( 'data-scan-progress-state', state );
	await expect( stateEl ).toHaveAttribute( 'aria-busy', busy );
	const announcement = await stateEl.getAttribute( 'data-scan-progress-announcement' );
	expect( announcement || '' ).not.toHaveLength( 0 );
	return announcement;
}

async function currentAnnouncement( scanRun ) {
	return scanRun.locator( '[data-scan-progress-announcement]' )
	.evaluate( ( node ) => ( node.dataset.scanProgressAnnouncement || '' ).trim() );
}

function liveRegionFor( scanRun ) {
	return scanRun.locator( '[data-shield-status-region="1"]' ).first();
}

async function assertLiveRegionMatchesCurrentAnnouncement( scanRun ) {
	const announcement = await currentAnnouncement( scanRun );
	expect( announcement ).not.toHaveLength( 0 );
	const liveRegion = liveRegionFor( scanRun );
	await expect( liveRegion ).toBeVisible();
	await expect( liveRegion ).toHaveAttribute( 'aria-live', /^(polite|assertive)$/ );
	await expect( liveRegion ).toHaveText( announcement );
	return announcement;
}

async function assertLiveRegionChangesToCurrentAnnouncement( scanRun, previousAnnouncement ) {
	const announcement = await assertLiveRegionMatchesCurrentAnnouncement( scanRun );
	expect( announcement ).not.toBe( previousAnnouncement );
	return announcement;
}

async function assertProgressbarContract( scanRun ) {
	const progressbar = scanRun.locator( '[role="progressbar"]' ).first();
	await expect( progressbar ).toHaveAttribute( 'aria-valuemin', '0' );
	await expect( progressbar ).toHaveAttribute( 'aria-valuemax', '100' );
	expect( await progressbar.getAttribute( 'aria-valuenow' ) ).not.toHaveLength( 0 );
	expect( await progressbar.getAttribute( 'aria-label' ) ).not.toHaveLength( 0 );
}

async function observeLiveRegionMutations( scanRun ) {
	await liveRegionFor( scanRun ).evaluate( ( node ) => {
		node.__shieldLiveRegionMutationCount = 0;
		node.__shieldLiveRegionObserver?.disconnect?.();
		node.__shieldLiveRegionObserver = new MutationObserver( () => {
			node.__shieldLiveRegionMutationCount++;
		} );
		node.__shieldLiveRegionObserver.observe( node, {
			childList: true,
			characterData: true,
			subtree: true,
		} );
	} );
}

const liveRegionMutationCount = ( scanRun ) => liveRegionFor( scanRun )
.evaluate( ( node ) => Number( node.__shieldLiveRegionMutationCount || 0 ) );

test( 'run manual scan needs at least one scan ticked before it starts', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'run', step: 'choose' } );
	// The test site can't start scans, so the page leaves the button out: put one back to check how it follows the choices.
	await page.locator( '#StartScans .shield-scan-run__foot' ).evaluate( ( foot ) => {
		if ( !foot.querySelector( '#StartScansButton' ) ) {
			foot.innerHTML = '<span data-scan-none-note="1" hidden>none</span><button type="submit" id="StartScansButton">Run</button>';
		}
	} );
	const button = page.locator( '#StartScansButton' );

	const choices = page.locator( '#StartScans input[data-scan-choice]:enabled' );
	for ( const choice of await choices.all() ) {
		await choice.uncheck();
	}
	await expect( button ).toBeDisabled();
	await expect( page.locator( '[data-scan-none-note="1"]' ) ).toBeVisible();

	await choices.first().check();
	await expect( button ).toBeEnabled();
	await expect( page.locator( '[data-scan-none-note="1"]' ) ).toBeHidden();
} );

test( 'the actions queue tabs end with a way to run scans', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'overview' } );
	const link = page.locator( '.actions-queue-sections__tab--run' );
	await expect( link ).toHaveCount( 1 );
	expect( await link.getAttribute( 'href' ) ).toContain( 'nav_sub=run' );
} );
