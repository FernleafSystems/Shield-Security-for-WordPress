const { test } = require( './support/shield-test' );
const { expect, CYCLE_TIMEOUT, TEST_TIMEOUT } = require( './support/silentcaptcha' );
const {
	collectRuntimeErrors,
	collectShieldAjaxActionUrls,
	expectNoRuntimeErrors,
	expectShieldAjaxSuccess,
	isAdminAjaxRequest,
	requestActionSlug,
	requestPostParam,
	waitForShieldAjaxAction,
} = require( './support/security-assertions' );

const PUBLIC_VISITOR_IP = '93.184.216.34';
const NOTBOT_COOKIE_NAME = 'icwp-wpsf-notbot';
const THIRD_PARTY_AJAX_ACTION = 'shield_browser_third_party_ping';

test.setTimeout( TEST_TIMEOUT );

async function withAnonymousContext( browser, lane, publicVisitorIp, runScenario ) {
	const context = await browser.newContext( {
		baseURL: lane.baseUrl,
		extraHTTPHeaders: {
			'X-Forwarded-For': publicVisitorIp,
		},
	} );
	context.setDefaultTimeout( CYCLE_TIMEOUT );
	try {
		return await runScenario( context );
	}
	finally {
		await context.close();
	}
}

async function withAnonymousPage( browser, lane, publicVisitorIp, runScenario ) {
	return withAnonymousContext( browser, lane, publicVisitorIp, async ( context ) => {
		const page = await context.newPage();
		return runScenario( page );
	} );
}

async function mutateCaptureNotBotResponse( page, mutatePayload ) {
	const mutation = { applied: false };
	await page.route( '**/wp-admin/admin-ajax.php', async ( route ) => {
		const request = route.request();
		if ( request.method() !== 'POST' || requestActionSlug( request ) !== 'capture_not_bot' ) {
			await route.fallback();
			return;
		}

		const response = await route.fetch();
		const payload = await response.json();
		mutatePayload( payload );
		mutation.applied = true;
		await route.fulfill( { response, json: payload } );
	} );
	return mutation;
}

async function holdFirstCaptureNotBotResponse( page ) {
	let resolveRequestSeen;
	const requestSeen = new Promise( resolve => {
		resolveRequestSeen = resolve;
	} );
	let continueToServer;
	const serverAllowed = new Promise( resolve => {
		continueToServer = resolve;
	} );
	let resolveReady;
	const ready = new Promise( resolve => {
		resolveReady = resolve;
	} );
	let isHeld = false;

	await page.route( '**/wp-admin/admin-ajax.php', async ( route ) => {
		const request = route.request();
		if ( isHeld || request.method() !== 'POST' || requestActionSlug( request ) !== 'capture_not_bot' ) {
			await route.fallback();
			return;
		}

		isHeld = true;
		resolveRequestSeen();
		await serverAllowed;
		const response = await route.fetch();
		const payload = await response.json();
		const setCookieHeader = response.headers()[ 'set-cookie' ] || '';
		let releaseResponse;
		const released = new Promise( resolve => {
			releaseResponse = resolve;
		} );

		// route.fetch() can apply Set-Cookie before route.fulfill(); clear that test-harness side effect.
		await page.context().clearCookies( { name: NOTBOT_COOKIE_NAME } );
		resolveReady( {
			payload,
			setCookieHeader,
			release: releaseResponse,
		} );

		await released;
		await route.fulfill( { response, json: payload } );
	} );

	return {
		continueToServer,
		ready,
		requestSeen,
	};
}

async function suppressDocumentNotBotSetCookie( page, restoreNotBotCookie = null ) {
	await page.route( '**/*', async ( route ) => {
		if ( route.request().resourceType() !== 'document' ) {
			await route.fallback();
			return;
		}

		const response = await route.fetch();
		const headers = {};
		for ( const header of response.headersArray() ) {
			if ( header.name.toLowerCase() === 'set-cookie' && header.value.includes( NOTBOT_COOKIE_NAME ) ) {
				continue;
			}
			headers[ header.name ] = header.value;
		}
		// route.fetch() can apply Set-Cookie before the filtered document response is fulfilled.
		await page.context().clearCookies( { name: NOTBOT_COOKIE_NAME } );
		if ( restoreNotBotCookie !== null ) {
			await setNotBotCookie( page.context(), restoreNotBotCookie.lane, restoreNotBotCookie.value );
			const restored = ( await page.context().cookies( restoreNotBotCookie.lane.baseUrl ) ).find( cookie => cookie.name === NOTBOT_COOKIE_NAME );
			expect( restored?.value, 'The intended cookie input must exist before document scripts run.' ).toBe( restoreNotBotCookie.value );
		}
		await route.fulfill( {
			body: await response.body(),
			headers,
			status: response.status(),
		} );
	} );
}

function waitForThirdPartyAjaxPing( page ) {
	return page.waitForResponse( ( response ) => {
		const request = response.request();
		return isAdminAjaxRequest( request )
			&& requestPostParam( request, 'action' ) === THIRD_PARTY_AJAX_ACTION;
	} );
}

function waitForShieldAjaxRequest( page, actionSlug ) {
	return page.waitForRequest( ( request ) => {
		return isAdminAjaxRequest( request )
			&& requestActionSlug( request ) === actionSlug;
	} );
}

function collectShieldAjaxActionRequests( page, actionSlug ) {
	const requests = [];
	page.on( 'request', ( request ) => {
		if ( isAdminAjaxRequest( request ) && requestActionSlug( request ) === actionSlug ) {
			requests.push( request.url() );
		}
	} );
	return requests;
}

async function triggerThirdPartyAjaxPing( page ) {
	return page.evaluate( async ( action ) => {
		const response = await fetch( '/wp-admin/admin-ajax.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest',
			},
			body: new URLSearchParams( { action } ).toString(),
		} );

		return {
			ok: response.ok,
			payload: await response.json(),
		};
	}, THIRD_PARTY_AJAX_ACTION );
}

async function responseSetCookieHeader( response ) {
	return ( await response.headerValues( 'set-cookie' ) ).join( "\n" );
}

function collectSilentCaptchaConsoleMessages( page ) {
	const messages = [];
	const patterns = [
		/silentcaptcha/i,
		/silent captcha/i,
		/fetchSilentCaptcha/,
		/Could not verify the altcha challenge data/,
		/ALTCHA/,
	];
	page.on( 'console', ( message ) => {
		const text = message.text();
		if ( patterns.some( ( pattern ) => pattern.test( text ) ) ) {
			messages.push( {
				type: message.type(),
				text,
			} );
		}
	} );
	return messages;
}

async function expectNoSilentCaptchaConsoleMessages( messages, label ) {
	await expect.poll(
		() => messages.slice(),
		{ message: `${label}: ${messages.map( ( message ) => `${message.type}: ${message.text}` ).join( '; ' )}` }
	).toEqual( [] );
}

async function readNotBotCookie( page ) {
	const cookies = await page.context().cookies();
	return cookies.find( ( cookie ) => cookie.name === NOTBOT_COOKIE_NAME ) || null;
}

async function expectNoNotBotCookie( page ) {
	expect( await readNotBotCookie( page ) ).toBeNull();
}

function parseNotBotCookieValue( value ) {
	const parts = typeof value === 'string' ? value.split( 'Z' ) : [];
	const expiryPart = parts.pop() || '';
	const expiryMatch = /^exp-([0-9]+)$/.exec( expiryPart );
	const expiresAt = expiryMatch === null ? 0 : Number( expiryMatch[ 1 ] );
	const isFresh = Number.isInteger( expiresAt ) && Math.round( Date.now() / 1000 ) < expiresAt;
	return {
		expiresAt,
		isFresh,
		signals: isFresh ? parts : [],
	};
}

async function expectNotBotCookie( page, expectedSignals = null ) {
	const cookie = await readNotBotCookie( page );
	expect( cookie ).not.toBeNull();
	const parsed = parseNotBotCookieValue( cookie.value );
	expect( parsed.isFresh ).toBe( true );
	if ( expectedSignals !== null ) {
		expect( [ ...parsed.signals ].sort() ).toEqual( [ ...expectedSignals ].sort() );
	}
	return parsed;
}

async function expectNotBotLocalStorageUnused( page ) {
	const key = await page.evaluate( () => window.shield_vars_silentcaptcha.comps.silentcaptcha.config.storage_key );
	expect( key ).toEqual( expect.any( String ) );
	expect( key ).not.toBe( '' );
	const value = await page.evaluate( key => window.localStorage.getItem( key ), key );
	expect( value ).toBeNull();
}

async function setNotBotCookie( context, lane, value ) {
	await context.addCookies( [
		{
			name: NOTBOT_COOKIE_NAME,
			value,
			url: lane.baseUrl,
			expires: Math.floor( Date.now() / 1000 ) + 600,
		},
	] );
}

test( 'silentCAPTCHA solves ALTCHA v2, writes cookie state, and throttles refresh checks', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
		await withAnonymousPage( browser, lane, PUBLIC_VISITOR_IP, async ( page ) => {
			const runtimeErrors = collectRuntimeErrors( page );
			const consoleMessages = collectSilentCaptchaConsoleMessages( page );
			const notbotResponses = collectShieldAjaxActionUrls( page, 'capture_not_bot' );
			const altchaResponses = collectShieldAjaxActionUrls( page, 'capture_not_bot_altcha' );
			const altchaResponse = waitForShieldAjaxAction( page, 'capture_not_bot_altcha' );
			const basicResponse = waitForShieldAjaxAction( page, 'capture_not_bot' );

			await page.goto( '/?force_notbot=1', { waitUntil: 'load' } );
			const altchaPayload = await expectShieldAjaxSuccess( await altchaResponse );
			const basicPayload = await expectShieldAjaxSuccess( await basicResponse );
			expect( basicPayload.data.notbot_state ).toEqual( {
				mode: 'cookie', required: [ 'altcha' ], exchange_valid: true,
			} );
			expect( altchaPayload.data.notbot_state ).toEqual( {
				mode: 'cookie', required: [], exchange_valid: true,
			} );
			for ( const response of [ await basicResponse, await altchaResponse ] ) {
				expect( response.headers()[ 'cache-control' ] ).toMatch( /no-cache|no-store/ );
			}
			await page.waitForLoadState( 'networkidle' );

			expect( notbotResponses ).toHaveLength( 1 );
			expect( altchaResponses ).toHaveLength( 1 );
			await expectNotBotCookie( page, [ 'notbot', 'altcha' ] );
			await expectNotBotLocalStorageUnused( page );

			await expect.poll( async () => {
				const state = await fixtureApi.inspectNotBotAltchaFixture();
				return state.altcha_at;
			}, { timeout: CYCLE_TIMEOUT } ).toBeGreaterThan( 0 );

			await page.goto( '/', { waitUntil: 'load' } );
			await page.waitForLoadState( 'networkidle' );

			expect( notbotResponses ).toHaveLength( 1 );
			expect( altchaResponses ).toHaveLength( 1 );
			await expectNotBotCookie( page, [ 'notbot', 'altcha' ] );
			await expectNotBotLocalStorageUnused( page );
			await expectNoRuntimeErrors( runtimeErrors, 'ALTCHA v2 solved cookie throttle flow' );
			await expectNoSilentCaptchaConsoleMessages( consoleMessages, 'ALTCHA v2 solved cookie throttle flow' );
		} );
	} );
} );

test( 'server Set-Cookie overrides a stale full NotBot cookie when checks are required', async ( { browser, lane, fixtureApi } ) => {
	const future = Math.floor( Date.now() / 1000 ) + 600;

	await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
		await withAnonymousContext( browser, lane, PUBLIC_VISITOR_IP, async ( context ) => {
			await setNotBotCookie( context, lane, `notbotZaltchaZexp-${future}` );
			const page = await context.newPage();
			const runtimeErrors = collectRuntimeErrors( page );
			const consoleMessages = collectSilentCaptchaConsoleMessages( page );
			const notbotResponses = collectShieldAjaxActionUrls( page, 'capture_not_bot' );
			const altchaResponses = collectShieldAjaxActionUrls( page, 'capture_not_bot_altcha' );
			const notbotResponse = waitForShieldAjaxAction( page, 'capture_not_bot' );
			const altchaResponse = waitForShieldAjaxAction( page, 'capture_not_bot_altcha' );

			await page.goto( '/', { waitUntil: 'load' } );
			await expectShieldAjaxSuccess( await notbotResponse );
			await expectShieldAjaxSuccess( await altchaResponse );
			await page.waitForLoadState( 'networkidle' );

			expect( notbotResponses ).toHaveLength( 1 );
			expect( altchaResponses ).toHaveLength( 1 );
			await expectNotBotCookie( page, [ 'notbot', 'altcha' ] );
			await expectNotBotLocalStorageUnused( page );
			await expectNoRuntimeErrors( runtimeErrors, 'server cookie overrides stale full cookie' );
			await expectNoSilentCaptchaConsoleMessages( consoleMessages, 'server cookie overrides stale full cookie' );
		} );
	} );
} );

test( 'third-party AJAX closes stale NotBot cookie IP-change window through refreshed cookie state', async ( { browser, lane, fixtureApi } ) => {
	const future = Math.floor( Date.now() / 1000 ) + 600;
	const staleCookieValue = `notbotZaltchaZexp-${future}`;

	await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
		const seededState = await fixtureApi.inspectNotBotAltchaFixture();
		expect( seededState.ip ).toBe( PUBLIC_VISITOR_IP );
		expect( seededState.notbot_at ).toBe( 0 );
		expect( seededState.altcha_at ).toBe( 0 );

		await withAnonymousContext( browser, lane, PUBLIC_VISITOR_IP, async ( context ) => {
			await setNotBotCookie( context, lane, staleCookieValue );
			const page = await context.newPage();
			await suppressDocumentNotBotSetCookie( page, {
				lane,
				value: staleCookieValue,
			} );
			const runtimeErrors = collectRuntimeErrors( page );
			const consoleMessages = collectSilentCaptchaConsoleMessages( page );
			const notbotResponses = collectShieldAjaxActionUrls( page, 'capture_not_bot' );
			const altchaResponses = collectShieldAjaxActionUrls( page, 'capture_not_bot_altcha' );

			await page.goto( '/', { waitUntil: 'load' } );
			await page.waitForLoadState( 'networkidle' );

			expect( notbotResponses ).toEqual( [] );
			expect( altchaResponses ).toEqual( [] );
			expect( ( await readNotBotCookie( page ) )?.value ).toBe( staleCookieValue );

			const thirdPartyResponse = waitForThirdPartyAjaxPing( page );
			const notbotResponse = waitForShieldAjaxAction( page, 'capture_not_bot' );
			const altchaResponse = waitForShieldAjaxAction( page, 'capture_not_bot_altcha' );
			const thirdPartyResult = await triggerThirdPartyAjaxPing( page );
			const thirdPartyAjaxResponse = await thirdPartyResponse;
			const thirdPartySetCookie = await responseSetCookieHeader( thirdPartyAjaxResponse );

			expect( thirdPartyResult ).toEqual( {
				ok: true,
				payload: {
					ok: true,
					fixture: 'third-party-ajax',
				},
			} );
			expect( thirdPartySetCookie ).toContain( NOTBOT_COOKIE_NAME );

			await expect.poll( async () => ( await readNotBotCookie( page ) )?.value || '' ).not.toBe( staleCookieValue );
			await expectNotBotCookie( page, [] );

			await expectShieldAjaxSuccess( await notbotResponse );
			await expectShieldAjaxSuccess( await altchaResponse );
			await page.waitForLoadState( 'networkidle' );

			expect( notbotResponses ).toHaveLength( 1 );
			expect( altchaResponses ).toHaveLength( 1 );
			await expectNotBotCookie( page, [ 'notbot', 'altcha' ] );
			await expectNotBotLocalStorageUnused( page );
			await expectNoRuntimeErrors( runtimeErrors, 'third-party AJAX stale NotBot cookie refresh' );
			await expectNoSilentCaptchaConsoleMessages( consoleMessages, 'third-party AJAX stale NotBot cookie refresh' );
		} );
	} );
} );

test( 'silentCAPTCHA handles capture_not_bot transport failure without frontend noise or retry storm', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
		await withAnonymousPage( browser, lane, PUBLIC_VISITOR_IP, async ( page ) => {
			await page.route( '**/wp-admin/admin-ajax.php', async ( route ) => {
				const request = route.request();
				if ( request.method() === 'POST' && requestActionSlug( request ) === 'capture_not_bot' ) {
					await route.abort( 'failed' );
					return;
				}

				await route.fallback();
			} );

			const runtimeErrors = collectRuntimeErrors( page );
			const consoleMessages = collectSilentCaptchaConsoleMessages( page );
			const notbotRequests = collectShieldAjaxActionRequests( page, 'capture_not_bot' );
			const altchaRequests = collectShieldAjaxActionRequests( page, 'capture_not_bot_altcha' );
			const notbotRequest = waitForShieldAjaxRequest( page, 'capture_not_bot' );

			await page.goto( '/?force_notbot=1', { waitUntil: 'load' } );
			await notbotRequest;
			await page.waitForLoadState( 'networkidle' );

			expect( notbotRequests ).toHaveLength( 1 );
			expect( altchaRequests ).toEqual( [] );
			await expectNotBotLocalStorageUnused( page );
			await expectNoRuntimeErrors( runtimeErrors, 'capture_not_bot transport failure' );
			await expectNoSilentCaptchaConsoleMessages( consoleMessages, 'capture_not_bot transport failure' );
		} );
	} );
} );

test( 'silentCAPTCHA handles malformed capture_not_bot_altcha response without frontend noise or retry storm', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
		await withAnonymousPage( browser, lane, PUBLIC_VISITOR_IP, async ( page ) => {
			await page.route( '**/wp-admin/admin-ajax.php', async ( route ) => {
				const request = route.request();
				if ( request.method() === 'POST' && requestActionSlug( request ) === 'capture_not_bot_altcha' ) {
					await route.fulfill( {
						status: 200,
						contentType: 'text/html; charset=UTF-8',
						body: '<html>broken</html>',
					} );
					return;
				}

				await route.fallback();
			} );

			const runtimeErrors = collectRuntimeErrors( page );
			const consoleMessages = collectSilentCaptchaConsoleMessages( page );
			const notbotRequests = collectShieldAjaxActionRequests( page, 'capture_not_bot' );
			const altchaRequests = collectShieldAjaxActionRequests( page, 'capture_not_bot_altcha' );
			const notbotResponse = waitForShieldAjaxAction( page, 'capture_not_bot' );
			const altchaResponse = waitForShieldAjaxAction( page, 'capture_not_bot_altcha' );

			await page.goto( '/?force_notbot=1', { waitUntil: 'load' } );
			await expectShieldAjaxSuccess( await notbotResponse );
			const malformedResponse = await altchaResponse;
			expect( malformedResponse.status() ).toBe( 200 );
			await page.waitForLoadState( 'networkidle' );

			const state = await fixtureApi.inspectNotBotAltchaFixture();
			expect( state.notbot_at ).toBeGreaterThan( 0 );
			expect( state.altcha_at ).toBe( 0 );
			expect( notbotRequests ).toHaveLength( 1 );
			expect( altchaRequests ).toHaveLength( 1 );
			await expectNotBotLocalStorageUnused( page );
			await expectNoRuntimeErrors( runtimeErrors, 'malformed capture_not_bot_altcha response' );
			await expectNoSilentCaptchaConsoleMessages( consoleMessages, 'malformed capture_not_bot_altcha response' );
		} );
	} );
} );

test( 'force_notbot uses AJAX cookie refresh without ALTCHA when server state is fresh', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
		await withAnonymousPage( browser, lane, PUBLIC_VISITOR_IP, async ( page ) => {
			const firstAltchaResponse = waitForShieldAjaxAction( page, 'capture_not_bot_altcha' );

			await page.goto( '/?force_notbot=1', { waitUntil: 'load' } );
			await expectShieldAjaxSuccess( await firstAltchaResponse );
			await page.waitForLoadState( 'networkidle' );
			await expectNotBotCookie( page, [ 'notbot', 'altcha' ] );

			await expect.poll( async () => {
				const state = await fixtureApi.inspectNotBotAltchaFixture();
				return state.altcha_at;
			}, { timeout: CYCLE_TIMEOUT } ).toBeGreaterThan( 0 );
		} );

		await withAnonymousContext( browser, lane, PUBLIC_VISITOR_IP, async ( context ) => {
			const page = await context.newPage();
			await suppressDocumentNotBotSetCookie( page );
			const heldNotbotResponse = await holdFirstCaptureNotBotResponse( page );
			const runtimeErrors = collectRuntimeErrors( page );
			const consoleMessages = collectSilentCaptchaConsoleMessages( page );
			const notbotResponses = collectShieldAjaxActionUrls( page, 'capture_not_bot' );
			const altchaResponses = collectShieldAjaxActionUrls( page, 'capture_not_bot_altcha' );
			const forcedNotbotResponse = waitForShieldAjaxAction( page, 'capture_not_bot' );

			await expectNoNotBotCookie( page );
			await page.goto( '/?force_notbot=1', { waitUntil: 'load' } );
			await heldNotbotResponse.requestSeen;
			await expectNoNotBotCookie( page );
			heldNotbotResponse.continueToServer();
			const held = await heldNotbotResponse.ready;

			expect( held.setCookieHeader ).toContain( NOTBOT_COOKIE_NAME );
			expect( held.payload?.data?.altcha_data ).toEqual( [] );
			await expectNoNotBotCookie( page );

			held.release();
			const payload = await expectShieldAjaxSuccess( await forcedNotbotResponse );
			expect( payload?.data?.altcha_data ).toEqual( [] );
			await page.waitForLoadState( 'networkidle' );

			expect( notbotResponses ).toHaveLength( 1 );
			expect( altchaResponses ).toEqual( [] );
			await expectNotBotCookie( page, [ 'notbot', 'altcha' ] );
			await expectNotBotLocalStorageUnused( page );
			await expectNoRuntimeErrors( runtimeErrors, 'force_notbot AJAX cookie no-challenge refresh' );
			await expectNoSilentCaptchaConsoleMessages( consoleMessages, 'force_notbot AJAX cookie no-challenge refresh' );
		} );
	} );
} );

test( 'silentCAPTCHA keeps ALTCHA required when only the NotBot cookie signal is fresh', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
		await withAnonymousContext( browser, lane, PUBLIC_VISITOR_IP, async ( context ) => {
			const unsupportedPage = await context.newPage();
			const unsupportedRuntimeErrors = collectRuntimeErrors( unsupportedPage );
			const unsupportedConsoleMessages = collectSilentCaptchaConsoleMessages( unsupportedPage );
			const unsupportedAltchaResponses = collectShieldAjaxActionUrls( unsupportedPage, 'capture_not_bot_altcha' );
			await unsupportedPage.addInitScript( () => {
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

			const notbotResponse = waitForShieldAjaxAction( unsupportedPage, 'capture_not_bot' );
			await unsupportedPage.goto( '/?force_notbot=1', { waitUntil: 'load' } );
			await expectShieldAjaxSuccess( await notbotResponse );
			await unsupportedPage.waitForLoadState( 'networkidle' );

			const state = await fixtureApi.inspectNotBotAltchaFixture();
			expect( state.notbot_at ).toBeGreaterThan( 0 );
			expect( state.altcha_at ).toBe( 0 );
			expect( unsupportedAltchaResponses ).toEqual( [] );
			await expectNotBotCookie( unsupportedPage, [ 'notbot' ] );
			await expectNotBotLocalStorageUnused( unsupportedPage );
			await expectNoRuntimeErrors( unsupportedRuntimeErrors, 'ALTCHA unsupported partial cookie state' );
			await expectNoSilentCaptchaConsoleMessages( unsupportedConsoleMessages, 'ALTCHA unsupported partial cookie state' );
			await unsupportedPage.close();

			const page = await context.newPage();
			const runtimeErrors = collectRuntimeErrors( page );
			const consoleMessages = collectSilentCaptchaConsoleMessages( page );
			const notbotResponses = collectShieldAjaxActionUrls( page, 'capture_not_bot' );
			const altchaResponses = collectShieldAjaxActionUrls( page, 'capture_not_bot_altcha' );
			const altchaResponse = waitForShieldAjaxAction( page, 'capture_not_bot_altcha' );

			await page.goto( '/', { waitUntil: 'load' } );
			await expectShieldAjaxSuccess( await altchaResponse );
			await page.waitForLoadState( 'networkidle' );

			expect( notbotResponses ).toHaveLength( 1 );
			expect( altchaResponses ).toHaveLength( 1 );
			await expect.poll( async () => {
				const updatedState = await fixtureApi.inspectNotBotAltchaFixture();
				return updatedState.altcha_at;
			}, { timeout: CYCLE_TIMEOUT } ).toBeGreaterThan( 0 );
			await expectNotBotCookie( page, [ 'notbot', 'altcha' ] );
			await expectNotBotLocalStorageUnused( page );
			await expectNoRuntimeErrors( runtimeErrors, 'ALTCHA required after partial cookie state' );
			await expectNoSilentCaptchaConsoleMessages( consoleMessages, 'ALTCHA required after partial cookie state' );
		} );
	} );
} );

test( 'silentCAPTCHA ignores expired or malformed NotBot cookies and refreshes through the existing flow', async ( { browser, lane, fixtureApi } ) => {
	const past = Math.floor( Date.now() / 1000 ) - 60;
	const invalidCookieValues = [
		`notbotZaltchaZexp-${past}`,
		'notbotZaltchaZexp-nope',
		'notbotZaltcha',
	];

	for ( const cookieValue of invalidCookieValues ) {
		await test.step( cookieValue, async () => {
			await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
				await withAnonymousContext( browser, lane, PUBLIC_VISITOR_IP, async ( context ) => {
					await setNotBotCookie( context, lane, cookieValue );
					const page = await context.newPage();
					await suppressDocumentNotBotSetCookie( page, { lane, value: cookieValue } );
					const runtimeErrors = collectRuntimeErrors( page );
					const consoleMessages = collectSilentCaptchaConsoleMessages( page );
					const notbotResponses = collectShieldAjaxActionUrls( page, 'capture_not_bot' );
					const altchaResponses = collectShieldAjaxActionUrls( page, 'capture_not_bot_altcha' );
					const altchaResponse = waitForShieldAjaxAction( page, 'capture_not_bot_altcha' );
					const basicRequest = waitForShieldAjaxRequest( page, 'capture_not_bot' );

					await page.goto( '/', { waitUntil: 'load' } );
					await basicRequest;
					await expectShieldAjaxSuccess( await altchaResponse );
					await page.waitForLoadState( 'networkidle' );

					expect( notbotResponses ).toHaveLength( 1 );
					expect( altchaResponses ).toHaveLength( 1 );
					const refreshedCookie = await readNotBotCookie( page );
					expect( refreshedCookie.value ).not.toBe( cookieValue );
					await expectNotBotCookie( page, [ 'notbot', 'altcha' ] );
					await expectNotBotLocalStorageUnused( page );
					await expectNoRuntimeErrors( runtimeErrors, `invalid NotBot cookie ${cookieValue}` );
					await expectNoSilentCaptchaConsoleMessages( consoleMessages, `invalid NotBot cookie ${cookieValue}` );
				} );
			} );
		} );
	}
} );

test( 'silentCAPTCHA handles malformed AJAX payload envelopes without frontend errors', async ( { browser, lane, fixtureApi } ) => {
	const malformedPayloads = [
		{
			label: 'missing data',
			mutate: ( payload ) => delete payload.data,
		},
		{
			label: 'null data',
			mutate: ( payload ) => {
				payload.data = null;
			},
		},
		{
			label: 'array data',
			mutate: ( payload ) => {
				payload.data = [];
			},
		},
		{
			label: 'string data',
			mutate: ( payload ) => {
				payload.data = 'not-object';
			},
		},
		{
			label: 'number data',
			mutate: ( payload ) => {
				payload.data = 7;
			},
		},
	];

	for ( const { label, mutate } of malformedPayloads ) {
		await test.step( label, async () => {
			await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
				await withAnonymousPage( browser, lane, PUBLIC_VISITOR_IP, async ( page ) => {
					const runtimeErrors = collectRuntimeErrors( page );
					const consoleMessages = collectSilentCaptchaConsoleMessages( page );
					const mutation = await mutateCaptureNotBotResponse( page, mutate );
					const notbotResponse = waitForShieldAjaxAction( page, 'capture_not_bot' );

					await page.goto( '/?force_notbot=1', { waitUntil: 'load' } );
					await expectShieldAjaxSuccess( await notbotResponse );
					await page.waitForLoadState( 'networkidle' );

					expect( mutation.applied ).toBe( true );
					await expectNotBotLocalStorageUnused( page );
					await expectNoRuntimeErrors( runtimeErrors, `malformed AJAX payload ${label}` );
					await expectNoSilentCaptchaConsoleMessages( consoleMessages, `malformed AJAX payload ${label}` );
				} );
			} );
		} );
	}
} );

test( 'silentCAPTCHA recoverable failures do not touch console methods', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
		await withAnonymousPage( browser, lane, PUBLIC_VISITOR_IP, async ( page ) => {
			const runtimeErrors = collectRuntimeErrors( page );
			await page.addInitScript( () => {
				[ 'log', 'warn', 'error' ].forEach( ( method ) => {
					console[ method ] = () => {
						throw new Error( `console.${method} blocked for test.` );
					};
				} );
			} );
			await mutateCaptureNotBotResponse( page, ( payload ) => {
				delete payload.data;
			} );

			const notbotResponse = waitForShieldAjaxAction( page, 'capture_not_bot' );
			await page.goto( '/?force_notbot=1', { waitUntil: 'load' } );
			await expectShieldAjaxSuccess( await notbotResponse );
			await page.waitForLoadState( 'networkidle' );

			await expectNotBotLocalStorageUnused( page );
			await expectNoRuntimeErrors( runtimeErrors, 'console method failure during recoverable silentCAPTCHA error' );
		} );
	} );
} );

test( 'silentCAPTCHA rejects an expired ALTCHA challenge without submitting a solution', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withNotBotAltchaFixture( PUBLIC_VISITOR_IP, async () => {
		await withAnonymousPage( browser, lane, PUBLIC_VISITOR_IP, async ( page ) => {
			const runtimeErrors = collectRuntimeErrors( page );
			const consoleMessages = collectSilentCaptchaConsoleMessages( page );
			const altchaResponses = collectShieldAjaxActionUrls( page, 'capture_not_bot_altcha' );
			const mutation = await mutateCaptureNotBotResponse( page, ( payload ) => {
				const challenge = JSON.parse( payload.data.altcha_data.altcha_challenge );
				challenge.parameters.expiresAt = Math.floor( Date.now() / 1000 ) - 60;
				payload.data.altcha_data.altcha_challenge = JSON.stringify( challenge );
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
			await expectNotBotLocalStorageUnused( page );
			await expectNoRuntimeErrors( runtimeErrors, 'expired ALTCHA challenge' );
			await expectNoSilentCaptchaConsoleMessages( consoleMessages, 'expired ALTCHA challenge' );
		} );
	} );
} );
