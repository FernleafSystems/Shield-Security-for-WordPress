const { test, expect } = require( './support/shield-test' );

const PUBLIC_VISITOR_IP = '93.184.216.34';

async function withAnonymousPage( browser, lane, publicVisitorIp, runScenario ) {
	const context = await browser.newContext( {
		baseURL: lane.baseUrl,
		extraHTTPHeaders: {
			'X-Forwarded-For': publicVisitorIp,
		},
	} );
	const page = await context.newPage();
	try {
		return await runScenario( page );
	}
	finally {
		await context.close();
	}
}

function waitForShieldAjaxAction( page, actionSlug ) {
	return page.waitForResponse( ( response ) => {
		const request = response.request();
		return request.method() === 'POST'
			&& response.url().includes( '/wp-admin/admin-ajax.php' )
			&& requestActionSlug( request ) === actionSlug;
	} );
}

async function expectShieldAjaxSuccess( response ) {
	expect( response.ok() ).toBeTruthy();
	const payload = await response.json();
	expect( payload.success ).toBe( true );
	return payload;
}

function requestActionSlug( request ) {
	return new URLSearchParams( request.postData() || '' ).get( 'ex' );
}

function collectAltchaSubmissions( page ) {
	const altchaResponses = [];
	page.on( 'response', ( response ) => {
		const request = response.request();
		if ( request.method() === 'POST' && requestActionSlug( request ) === 'capture_not_bot_altcha' ) {
			altchaResponses.push( response.url() );
		}
	} );
	return altchaResponses;
}

async function mutateNotBotAltchaChallenge( page, mutateChallenge ) {
	const mutation = { applied: false };
	await page.route( '**/wp-admin/admin-ajax.php', async ( route ) => {
		const request = route.request();
		if ( request.method() !== 'POST' || requestActionSlug( request ) !== 'capture_not_bot' ) {
			await route.fallback();
			return;
		}

		const response = await route.fetch();
		const payload = await response.json();
		const rawChallenge = payload?.data?.altcha_data?.altcha_challenge;
		if ( typeof rawChallenge === 'string' ) {
			const challenge = JSON.parse( rawChallenge );
			mutateChallenge( challenge );
			payload.data.altcha_data.altcha_challenge = JSON.stringify( challenge );
			mutation.applied = true;
		}
		await route.fulfill( { response, json: payload } );
	} );
	return mutation;
}

test( 'silentCAPTCHA solves ALTCHA v2 and records the ALTCHA signal', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
		await withAnonymousPage( browser, lane, PUBLIC_VISITOR_IP, async ( page ) => {
			const altchaResponse = waitForShieldAjaxAction( page, 'capture_not_bot_altcha' );

			await page.goto( '/?force_notbot=1', { waitUntil: 'load' } );
			await expectShieldAjaxSuccess( await altchaResponse );

			await expect.poll( async () => {
				const state = await fixtureApi.inspectNotBotAltchaFixture();
				return state.altcha_at;
			}, { timeout: 15000 } ).toBeGreaterThan( 0 );
		} );
	} );
} );

test( 'silentCAPTCHA does not downgrade to v1 when WebCrypto is unavailable', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
		await withAnonymousPage( browser, lane, PUBLIC_VISITOR_IP, async ( page ) => {
			const altchaResponses = collectAltchaSubmissions( page );
			await page.addInitScript( () => {
				try {
					Object.defineProperty( Crypto.prototype, 'subtle', {
						configurable: true,
						get: () => undefined,
					} );
				}
				catch {
				}
				try {
					Object.defineProperty( window.crypto, 'subtle', {
						configurable: true,
						value: undefined,
					} );
				}
				catch {
				}
			} );

			const notbotResponse = waitForShieldAjaxAction( page, 'capture_not_bot' );
			await page.goto( '/?force_notbot=1', { waitUntil: 'load' } );
			await expectShieldAjaxSuccess( await notbotResponse );
			await page.waitForLoadState( 'networkidle' );

			const state = await fixtureApi.inspectNotBotAltchaFixture();
			expect( state.notbot_at ).toBeGreaterThan( 0 );
			expect( state.altcha_at ).toBe( 0 );
			expect( altchaResponses ).toEqual( [] );
		} );
	} );
} );

test( 'silentCAPTCHA rejects an expired ALTCHA challenge without submitting a solution', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
		await withAnonymousPage( browser, lane, PUBLIC_VISITOR_IP, async ( page ) => {
			const altchaResponses = collectAltchaSubmissions( page );
			const mutation = await mutateNotBotAltchaChallenge( page, ( challenge ) => {
				challenge.parameters.expiresAt = Math.floor( Date.now() / 1000 ) - 60;
			} );

			const notbotResponse = waitForShieldAjaxAction( page, 'capture_not_bot' );
			await page.goto( '/?force_notbot=1', { waitUntil: 'load' } );
			await expectShieldAjaxSuccess( await notbotResponse );
			expect( mutation.applied ).toBe( true );
			await page.waitForLoadState( 'networkidle' );

			const state = await fixtureApi.inspectNotBotAltchaFixture();
			expect( state.notbot_at ).toBeGreaterThan( 0 );
			expect( state.altcha_at ).toBe( 0 );
			expect( altchaResponses ).toEqual( [] );
		} );
	} );
} );
