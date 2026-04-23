const { test, expect } = require( '@playwright/test' );
const { openShieldRoute } = require( './support/shield-browser' );

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
		const shouldDelay = !handled
			&& request.method() === 'POST'
			&& request.url().includes( '/admin-ajax.php' )
			&& ( request.postData() || '' ).includes( 'ex=scans_start' );

		if ( !shouldDelay ) {
			await route.continue();
			return;
		}

		handled = true;
		startedResolve();
		await new Promise( ( resolve ) => setTimeout( resolve, delayMs ) );
		const response = await route.fetch();
		const body = await response.body();
		await route.fulfill( { response, body } );
		completedResolve();
		await page.unroute( '**/admin-ajax.php', handler ).catch( () => null );
	};

	await page.route( '**/admin-ajax.php', handler );

	return {
		started,
		completed,
	};
}

test( 'manual scan start shows the existing overlay immediately while the start request is in flight', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'scans',
		nav_sub: 'run',
	} );

	const delayedRequest = await delayScanStartRequest( page );

	await page.locator( '#StartScansButton' ).click();
	await delayedRequest.started;

	await expect( page.locator( '#ShieldOverlay' ) ).toBeVisible();

	await delayedRequest.completed;
	await expect( page.locator( '#ScanProgressModal' ) ).toBeVisible();
} );
