const { test, expect } = require( '@playwright/test' );
const { openShieldRoute } = require( './support/shield-browser' );

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

function scanModalHtml( state ) {
	return `<div class="modal-header">
		<h5 class="modal-title">Scan Progress</h5>
		<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
	</div>
	<div class="modal-body">
		<div data-shield-scan-modal-state="${state}" aria-busy="${state === 'running' ? 'true' : 'false'}"></div>
	</div>
	<div class="modal-footer"></div>`;
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

async function delayScanStartRequest( page, delayMs = 1200 ) {
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
			modal_html: scanModalHtml( 'running' ),
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

async function completeNextScanCheckRequest( page ) {
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
			modal_html: scanModalHtml( 'completed' ),
		} ) );
		await page.unroute( '**/admin-ajax.php', handler ).catch( () => null );
	};

	await page.route( '**/admin-ajax.php', handler );
	return {
		received,
	};
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

	const delayedRequest = await delayScanStartRequest( page );
	const scanCheckRequest = await completeNextScanCheckRequest( page );
	const completionRedirect = waitForScanOverviewRedirect( page );

	if ( await page.locator( '#StartScansButton' ).count() === 0 ) {
		await page.locator( '#StartScans' ).evaluate( ( form ) => {
			const button = document.createElement( 'button' );
			button.type = 'submit';
			button.id = 'StartScansButton';
			button.textContent = 'Run';
			form.appendChild( button );
		} );
	}
	await page.locator( '#StartScansButton' ).first().click();
	await withTimeout( delayedRequest.started, 'Timed out waiting for scans_start request.' );

	const sharedModal = page.locator( '#ShieldModalContainer.modal.show' );
	await expect( sharedModal ).toBeVisible();
	await expect( page.locator( '#ShieldOverlay' ) ).toBeHidden();

	await withTimeout( delayedRequest.completed, 'Timed out waiting for delayed scans_start response.' );
	await expect( sharedModal ).toBeVisible();
	await sharedModal.locator( '.btn-close' ).click( { trial: true } );
	await withTimeout( scanCheckRequest.received, 'Timed out waiting for scans_check request.' );
	await expect( completionRedirect ).resolves.toBe( 'redirect' );
} );
