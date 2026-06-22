const { test, expect } = require( './support/shield-test' );
const { openShieldRoute } = require( './support/shield-browser' );
const {
	isAdminAjaxRequest,
	parseShieldAjaxJson,
	requestActionSlug,
	requestPostParam,
} = require( './support/security-assertions' );

test.setTimeout( 180_000 );

function liveTrafficToggleRequest( actionSlug, enabled ) {
	return ( request ) => isAdminAjaxRequest( request )
		&& requestActionSlug( request ) === actionSlug
		&& requestPostParam( request, 'enabled' ) === enabled;
}

async function fulfillNextToggleRequest( page, matcher, payload, delayMs = 250 ) {
	let handled = false;
	let startedResolve;
	let completedResolve;
	let completedReject;

	const started = new Promise( ( resolve ) => {
		startedResolve = resolve;
	} );
	const completed = new Promise( ( resolve, reject ) => {
		completedResolve = resolve;
		completedReject = reject;
	} );

	const handler = async ( route ) => {
		const request = route.request();
		if ( handled || !matcher( request ) ) {
			await route.continue();
			return;
		}

		handled = true;
		startedResolve();
		try {
			await new Promise( ( resolve ) => setTimeout( resolve, delayMs ) );
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( payload ),
			} );
			completedResolve();
		}
		catch ( error ) {
			completedReject( error );
			throw error;
		}
		finally {
			await page.unroute( '**/admin-ajax.php', handler ).catch( () => null );
		}
	};

	await page.route( '**/admin-ajax.php', handler );

	return {
		completed,
		started,
	};
}

function waitForMainFrameNavigation( page ) {
	return page.waitForEvent( 'framenavigated', {
		predicate: ( frame ) => frame === page.mainFrame(),
		timeout: 20_000,
	} );
}

test( 'live traffic toggle restores failed enable request without changing server state', async ( { page, fixtureApi } ) => {
	await fixtureApi.withLiveTrafficToggleFixture( async ( contract ) => {
		await openShieldRoute( page, contract.route );

		const toggle = page.locator( contract.selectors.toggle );
		await expect( toggle ).toBeEnabled();
		await expect( toggle ).not.toBeChecked();

		const failedRequest = await fulfillNextToggleRequest(
			page,
			liveTrafficToggleRequest( contract.action_slug, 'Y' ),
			{
				success: false,
				data: {
					message: 'browser-live-traffic-toggle-failure',
					page_reload: false,
					is_enabled: false,
					time_remaining: 0,
				},
			}
		);

		await Promise.all( [
			failedRequest.started,
			toggle.check(),
		] );
		await failedRequest.completed;

		await expect( toggle ).toBeEnabled();
		await expect( toggle ).not.toBeChecked();

		const inspected = await fixtureApi.inspectLiveTrafficToggleFixture();
		expect( inspected.state.enable_live_log ).toBe( 'N' );
		expect( inspected.state.live_log_started_at ).toBe( 0 );
		expect( inspected.state.can_traffic_live_log ).toBe( true );
	} );
} );

test( 'live traffic toggle enables and disables through real action responses', async ( { page, fixtureApi } ) => {
	await fixtureApi.withLiveTrafficToggleFixture( async ( contract ) => {
		await openShieldRoute( page, contract.route );

		const toggle = page.locator( contract.selectors.toggle );
		await expect( toggle ).toBeEnabled();
		await expect( toggle ).not.toBeChecked();

		const enableResponse = page.waitForResponse(
			( response ) => liveTrafficToggleRequest( contract.action_slug, 'Y' )( response.request() )
		);
		const enableNavigation = waitForMainFrameNavigation( page );
		await toggle.check();
		const enablePayload = parseShieldAjaxJson( await ( await enableResponse ).text() );
		expect( enablePayload ).toHaveProperty( 'success', true );
		expect( enablePayload.data.page_reload ).toBe( true );
		await enableNavigation;

		const enabledToggle = page.locator( contract.selectors.toggle );
		await expect( enabledToggle ).toBeChecked();
		let inspected = await fixtureApi.inspectLiveTrafficToggleFixture();
		expect( inspected.state.enable_live_log ).toBe( 'Y' );
		expect( inspected.state.live_log_started_at ).toBeGreaterThan( 0 );

		const disableResponse = page.waitForResponse(
			( response ) => liveTrafficToggleRequest( contract.action_slug, 'N' )( response.request() )
		);
		const disableNavigation = waitForMainFrameNavigation( page );
		await enabledToggle.uncheck();
		const disablePayload = parseShieldAjaxJson( await ( await disableResponse ).text() );
		expect( disablePayload ).toHaveProperty( 'success', true );
		expect( disablePayload.data.page_reload ).toBe( true );
		await disableNavigation;

		await expect( page.locator( contract.selectors.toggle ) ).not.toBeChecked();
		inspected = await fixtureApi.inspectLiveTrafficToggleFixture();
		expect( inspected.state.enable_live_log ).toBe( 'N' );
		expect( inspected.state.live_log_started_at ).toBe( 0 );
	} );
} );
