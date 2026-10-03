const { test, fillWordPressLoginForm } = require( './support/shield-test' );
const { expect, CYCLE_TIMEOUT, COMPLEX_CHALLENGE_TIMEOUT, FAILURE_TIMEOUT, TEST_TIMEOUT, observeRefreshTimers, timerBaseline, waitForRefreshTimer } = require( './support/silentcaptcha' );
const { requestActionSlug, collectRuntimeErrors, expectNoRuntimeErrors, expectShieldAjaxSuccess, waitForShieldAjaxAction, parseShieldAjaxJson } = require( './support/security-assertions' );
const { dismissBlockingDialogs, openShieldRoute } = require( './support/shield-browser' );
const IP = '93.184.216.84';
const keyFor = base => 'icwp-wpsf-notbot-freshness:v1:' + base.replace( /\/$/, '' ) + '/';

test.setTimeout( TEST_TIMEOUT );

async function insertForm( page ) {
	await page.evaluate( () => {
		const section = document.createElement( 'section' );
		section.innerHTML = '<form action="/" method="get"><input name="s" value="test"><button>Search</button></form>';
		document.body.append( section );
	} );
}

for ( const contextKind of [ 'ordinary', 'form', 'login' ] ) {
	test( `configured timing controls ${contextKind} through real localisation`, async ( { browser, lane, fixtureApi } ) => {
		await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
			const timing = { ordinary: 9, form: 6, login: 3 };
			await fixtureApi.setNotBotTiming( timing );
			await page.goto( contextKind === 'login' ? '/wp-login.php' : '/' );
			await success( page, key );
			expect( await page.evaluate( () => window.shield_vars_silentcaptcha.comps.silentcaptcha.config.refresh_seconds ) ).toEqual( timing );
			const first = await completion( page, key );
			if ( contextKind === 'form' ) await insertForm( page );
			await page.clock.runFor( timing[ contextKind ] * 1000 - 1 );
			await wake( page );
			expect( counts.basic ).toBe( 1 );
			await page.clock.runFor( 1 );
			await success( page, key, first );
			expect( counts.basic ).toBe( 2 );
		} );
	} );
}

for ( const timing of [ null, {}, { ordinary: 0, form: 2, login: 1 }, { ordinary: '9', form: 6, login: 3 }, { ordinary: 1, form: 2, login: 3 } ] ) {
	test( `unusable timing remains quiet without freshness: ${JSON.stringify( timing )}`, async ( { browser, lane, fixtureApi } ) => {
		await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
			await fixtureApi.setNotBotTiming( timing );
			await page.goto( '/?force_notbot=1' );
			await settled( page );
			await insertForm( page );
			await page.clock.runFor( 600000 );
			await wake( page );
			expect( counts ).toEqual( { basic: 0, altcha: 0 } );
			expect( await completion( page, key ) ).toBe( 0 );
		} );
	} );
}

test( 'cookie mode ignores configured free timings and removes populated feature storage', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts, key, start } ) => {
		await fixtureApi.setNotBotTiming( { ordinary: 3, form: 2, login: 1 } );
		await page.addInitScript( ( { key, start } ) => localStorage.setItem( key, JSON.stringify( { completed_at: start } ) ), { key, start } );
		const cycle = await startCookieCycle( page );
		await expect.poll( async () => ( await fixtureApi.inspectNotBotAltchaFixture() ).altcha_at ).toBeGreaterThan( 0 );
		expect( await completion( page, key ) ).toBe( 0 );
		await advanceTo( page, cycle.at + 14999 );
		await wake( page );
		expect( counts ).toEqual( { basic: 1, altcha: 1 } );
		const nextResponse = waitForShieldAjaxAction( page, 'capture_not_bot' );
		await page.clock.runFor( 1 );
		await expect.poll( () => counts.basic ).toBe( 2 );
		await expectShieldAjaxSuccess( await nextResponse );
	}, { allowCookies: true, mode: 'cookie' } );
} );

test( 'cookie-free setting saves through the existing configuration form', async ( { page, fixtureApi }, testInfo ) => {
	await fixtureApi.withNotBotAltchaFixture( IP, async () => {
		await openShieldRoute( page, { nav: 'zones', nav_sub: 'overview', component: 'silent_captcha', config_item: 'silentcaptcha_cookie_free' } );
		await dismissBlockingDialogs( page );
		const checkbox = page.locator( '#Opt-silentcaptcha_cookie_free' );
		await expect( checkbox ).toBeVisible();
		await expect( checkbox ).not.toBeChecked();
		await page.screenshot( { path: testInfo.outputPath( 'cookie-free-off.png' ) } );
		await checkbox.check();
		const response = waitForShieldAjaxAction( page, 'mod_options_save' );
		await checkbox.locator( 'xpath=ancestor::form' ).locator( 'button[type="submit"]' ).last().click();
		await expectShieldAjaxSuccess( await response );
		await page.reload();
		await dismissBlockingDialogs( page );
		await expect( checkbox ).toBeChecked();
		await page.screenshot( { path: testInfo.outputPath( 'cookie-free-on.png' ) } );
		const document = await page.request.get( '/' );
		await expectNoNotBotHeader( document );
	} );
} );

async function scenario( browser, lane, fixtureApi, run, { allowCookies = false, mode = 'cookie_free', ip = IP } = {} ) {
	await fixtureApi.withNotBotAltchaFixture( ip, async () => {
		await fixtureApi.setNotBotMode( mode );
		const context = await browser.newContext( { baseURL: lane.baseUrl, extraHTTPHeaders: { 'X-Forwarded-For': ip } } );
		context.setDefaultTimeout( CYCLE_TIMEOUT );
		try {
			await context.addInitScript( allowCookies => {
				// Preserve real navigation entries: WordPress interactivity consumes them,
				// while Playwright's fake Performance object returns an empty list.
				window.nativePerformanceEntries = performance.getEntriesByType.bind( performance );
				window.cookieReads = 0;
				window.cookieWrites = 0;
				if ( !allowCookies ) Object.defineProperty( document, 'cookie', { configurable: true, get() { window.cookieReads++; throw new Error( 'Cookie access denied' ); }, set() { window.cookieWrites++; } } );
			}, allowCookies );
			const page = await context.newPage();
			// End long simulated sequences near server time, preserving real signed challenge expiry.
			const start = Date.now() - 4000000;
			await page.clock.install( { time: new Date( start ) } );
			await page.clock.pauseAt( new Date( start ) );
			await page.addInitScript( () => {
				performance.getEntriesByType = window.nativePerformanceEntries;
			} );
			const counts = { basic: 0, altcha: 0 };
			page.on( 'request', request => {
				if ( requestActionSlug( request ) === 'capture_not_bot' ) counts.basic++;
				if ( requestActionSlug( request ) === 'capture_not_bot_altcha' ) counts.altcha++;
			} );
			const errors = collectRuntimeErrors( page );
			await run( { page, context, counts, key: keyFor( lane.baseUrl ), start } );
			if ( !allowCookies ) expect( await page.evaluate( () => [ window.cookieReads, window.cookieWrites ] ) ).toEqual( [ 0, 0 ] );
			await expectNoRuntimeErrors( errors, 'cookie-independent scheduling' );
		}
		finally { await context.close(); }
	} );
}
async function completion( page, key ) {
	return page.evaluate( key => {
		try {
			const timestamp = JSON.parse( localStorage.getItem( key ) )?.completed_at;
			return typeof timestamp === 'number' && timestamp > 0 && timestamp <= Date.now() ? timestamp : 0;
		}
		catch { return 0; }
	}, key );
}
async function settled( page ) { await page.waitForLoadState( 'networkidle' ); }
async function startCookieCycle( page ) {
	await observeRefreshTimers( page );
	let baseline;
	let documentLoaded;
	const documentReady = new Promise( resolve => { documentLoaded = resolve; } );
	let held = false;
	await page.route( '**/wp-admin/admin-ajax.php', async route => {
		if ( requestActionSlug( route.request() ) !== 'capture_not_bot_altcha' || held ) return route.fallback();
		held = true;
		const response = await route.fetch();
		await documentReady;
		baseline = await timerBaseline( page );
		await route.fulfill( { response } );
	} );
	const altchaResponse = waitForShieldAjaxAction( page, 'capture_not_bot_altcha' );
	await page.goto( '/?force_notbot=1' );
	documentLoaded();
	// Cookie mode starts its solver through a zero-delay timer. Advance the
	// paused clock until that real request starts, then await its terminal timer.
	await expect.poll( async () => {
		if ( !held ) await page.clock.runFor( 1 );
		return held;
	} ).toBe( true );
	await expectShieldAjaxSuccess( await altchaResponse );
	return waitForRefreshTimer( page, baseline, 15000 );
}
async function loginReady( page ) {
	// jQuery defers ready callbacks through timers. With a paused clock, the
	// WordPress user-profile beforeunload handler can run before its form is set.
	let ready = false;
	await Promise.all( [
		page.evaluate( () => new Promise( resolve => window.jQuery( () => resolve() ) ) ).then( () => { ready = true; } ),
		expect.poll( async () => {
			await page.clock.runFor( 1 );
			return ready;
		} ).toBe( true ),
	] );
}
async function success( page, key, after = 0 ) {
	await expect.poll( () => completion( page, key ) ).toBeGreaterThan( after );
}
async function advanceTo( page, time ) {
	const remaining = time - await page.evaluate( () => Date.now() );
	expect( remaining ).toBeGreaterThanOrEqual( 0 );
	await page.clock.runFor( remaining );
}
async function wake( page ) {
	await page.evaluate( () => {
		window.dispatchEvent( new Event( 'focus' ) );
		window.dispatchEvent( new Event( 'pageshow' ) );
		window.dispatchEvent( new Event( 'storage' ) );
		document.dispatchEvent( new Event( 'visibilitychange' ) );
	} );
}

test( 'cookie-free real checks, exact ordinary boundary, reload and repeated cycles', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
		await page.goto( '/' );
		await success( page, key );
		expect( counts ).toEqual( { basic: 1, altcha: 1 } );
		const first = await completion( page, key );
		await page.reload();
		await settled( page );
		expect( counts.basic ).toBe( 1 );
		expect( await page.evaluate( () => window.shield_vars_silentcaptcha.comps.silentcaptcha.config.refresh_seconds ) ).toEqual( { ordinary: 300, form: 120, login: 60 } );
		await advanceTo( page, first + 299999 );
		await wake( page );
		expect( counts.basic ).toBe( 1 );
		await page.clock.runFor( 1 );
		await success( page, key, first );
		for ( let cycle = 2; cycle <= 12; cycle++ ) {
			const previous = await completion( page, key );
			await page.clock.runFor( 300000 );
			await success( page, key, previous );
		}
		expect( counts.basic ).toBe( 13 );
		expect( counts.altcha ).toBe( 1 );
	} );
} );

for ( const unavailable of [ 'access', 'read', 'write' ] ) {
	test( `cookie-free ${unavailable} storage failure retains successful in-document freshness`, async ( { browser, lane, fixtureApi } ) => {
		await scenario( browser, lane, fixtureApi, async ( { page, counts } ) => {
			await observeRefreshTimers( page );
			let initialBaseline;
			let documentLoaded;
			const documentReady = new Promise( resolve => { documentLoaded = resolve; } );
			await page.route( '**/wp-admin/admin-ajax.php', async route => {
				if ( requestActionSlug( route.request() ) !== 'capture_not_bot_altcha' ) return route.fallback();
				const response = await route.fetch();
				await documentReady;
				initialBaseline = await timerBaseline( page );
				await route.fulfill( { response } );
			} );
			await page.addInitScript( kind => {
				if ( kind === 'access' ) Object.defineProperty( window, 'localStorage', { get() { throw new Error( 'Storage denied' ); } } );
				else Storage.prototype[ kind === 'read' ? 'getItem' : 'setItem' ] = function () { throw new Error( 'Storage denied' ); };
			}, unavailable );
			const altchaResponse = waitForShieldAjaxAction( page, 'capture_not_bot_altcha' );
			await page.goto( '/' );
			documentLoaded();
			await expect.poll( () => counts.altcha ).toBe( 1 );
			await expectShieldAjaxSuccess( await altchaResponse );
			await waitForRefreshTimer( page, initialBaseline, 300000 );
			expect( ( await fixtureApi.inspectNotBotAltchaFixture() ).altcha_at ).toBeGreaterThan( 0 );
			// Distinguish this completed challenge from an erroneously recorded failure.
			// Success can be bypassed once for diagnostics; failure cooldown cannot.
			await page.evaluate( () => history.replaceState( null, '', '/?force_notbot=1' ) );
			const diagnosticBaseline = await timerBaseline( page );
			const diagnosticResponse = waitForShieldAjaxAction( page, 'capture_not_bot' );
			await wake( page );
			await expect.poll( () => counts.basic ).toBe( 2 );
			await expectShieldAjaxSuccess( await diagnosticResponse );
			const diagnostic = await waitForRefreshTimer( page, diagnosticBaseline, 300000 );
			await advanceTo( page, diagnostic.at + 299999 );
			await wake( page );
			expect( counts.basic ).toBe( 2 );
			const renewalResponse = waitForShieldAjaxAction( page, 'capture_not_bot' );
			const renewalBaseline = await timerBaseline( page );
			await page.clock.runFor( 1 );
			await expect.poll( () => counts.basic ).toBe( 3 );
			await expectShieldAjaxSuccess( await renewalResponse );
			await waitForRefreshTimer( page, renewalBaseline, 300000 );
			await wake( page );
			expect( counts.basic ).toBe( 3 );
		} );
	} );
}

test( 'cookie-free denied storage loses previous-document memory on an ordinary reload', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts } ) => {
		await observeRefreshTimers( page );
		await page.addInitScript( () => Object.defineProperty( window, 'localStorage', { get() { throw new Error( 'Storage denied' ); } } ) );
		// Each navigation installs an empty observer before production scripts.
		// Observe real replies without holding a fetch across document loading.
		const initialResponse = waitForShieldAjaxAction( page, 'capture_not_bot_altcha' );
		await page.goto( '/' );
		await expectShieldAjaxSuccess( await initialResponse );
		await waitForRefreshTimer( page, 0, 300000 );
		await page.clock.runFor( 1000 );
		await wake( page );
		expect( counts ).toEqual( { basic: 1, altcha: 1 } );
		// A diagnostic bypass succeeds only after successful memory freshness;
		// failure cooldown would suppress it, so quiet traffic alone is insufficient.
		const diagnostic = async expectedCount => {
			await page.evaluate( () => history.replaceState( null, '', '/?force_notbot=1' ) );
			const baseline = await timerBaseline( page );
			const response = waitForShieldAjaxAction( page, 'capture_not_bot' );
			await wake( page );
			expect( ( await expectShieldAjaxSuccess( await response ) ).data.notbot_state.required ).toEqual( [] );
			await waitForRefreshTimer( page, baseline, 300000 );
			expect( counts.basic ).toBe( expectedCount );
			await page.evaluate( () => history.replaceState( null, '', '/' ) );
		};
		await diagnostic( 2 );
		await page.clock.runFor( 1000 );
		await wake( page );
		expect( counts.basic ).toBe( 2 );
		const reloadResponse = waitForShieldAjaxAction( page, 'capture_not_bot' );
		await page.reload();
		expect( ( await expectShieldAjaxSuccess( await reloadResponse ) ).data.notbot_state.required ).toEqual( [] );
		await waitForRefreshTimer( page, 0, 300000 );
		expect( counts ).toEqual( { basic: 3, altcha: 1 } );
		await diagnostic( 4 );
		await page.clock.runFor( 1000 );
		await wake( page );
		expect( counts ).toEqual( { basic: 4, altcha: 1 } );
	} );
} );

for ( const value of [ '{', '{}', '{"completed_at":"1"}', '{"completed_at":-1}', '{"completed_at":9999999999999}' ] ) {
	test( `cookie-free ignores unusable timestamp ${value}`, async ( { browser, lane, fixtureApi } ) => {
		await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
			await page.addInitScript( ( { key, value } ) => localStorage.setItem( key, value ), { key, value } );
			await page.goto( '/' );
			await success( page, key );
			expect( counts ).toEqual( { basic: 1, altcha: 1 } );
		} );
	} );
}

for ( const fault of [ 'transport', 'missing', 'unsuccessful', 'invalid', 'unknown', 'http', 'unsupported', 'pending-notbot' ] ) {
	test( `cookie-free ${fault} cannot record success or bypass failure cooldown`, async ( { browser, lane, fixtureApi } ) => {
		await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
			let injected = 0;
			let terminalBaseline;
			let delivered = false;
			let faultActive = true;
			let documentLoaded;
			const documentReady = new Promise( resolve => { documentLoaded = resolve; } );
			await observeRefreshTimers( page );
			if ( fault === 'unsupported' ) await page.addInitScript( () => {
				window.silentCaptchaNativeCrypto = window.crypto;
				Object.defineProperty( window, 'crypto', { configurable: true, get: () => undefined } );
			} );
			await page.route( '**/wp-admin/admin-ajax.php', async route => {
				if ( !faultActive || requestActionSlug( route.request() ) !== 'capture_not_bot' ) return route.fallback();
				injected++;
				const finished = page.waitForEvent( fault === 'transport' ? 'requestfailed' : 'requestfinished', request => request === route.request() );
				if ( fault === 'transport' ) {
					await documentReady;
					terminalBaseline = await timerBaseline( page );
					await route.abort();
					await finished;
					delivered = true;
					return;
				}
				const response = await route.fetch();
				const body = parseShieldAjaxJson( await response.text() );
				if ( fault === 'missing' ) delete body.data.notbot_state;
				if ( fault === 'unsuccessful' ) body.success = false;
				if ( fault === 'invalid' ) body.data.notbot_state = { mode: 'cookie_free', required: [], exchange_valid: false };
				if ( fault === 'unknown' ) body.data.notbot_state.required = [ 'unknown' ];
				if ( fault === 'http' ) body.data.notbot_state.required = [];
				if ( fault === 'pending-notbot' ) body.data.notbot_state = { mode: 'cookie_free', required: [ 'notbot' ], exchange_valid: true };
				await documentReady;
				terminalBaseline = await timerBaseline( page );
				await route.fulfill( { response, json: body, status: fault === 'http' ? 503 : 200 } );
				await finished;
				delivered = true;
			} );
			await page.goto( '/' );
			documentLoaded();
			await expect.poll( () => delivered ).toBe( true );
			const failure = await waitForRefreshTimer( page, terminalBaseline, 300000 );
			expect( injected ).toBe( 1 );
			expect( await completion( page, key ) ).toBe( 0 );
			// An unused diagnostic override must not bypass a failed cycle's cooldown.
			await page.evaluate( () => history.replaceState( null, '', '/?force_notbot=1' ) );
			await wake( page );
			expect( counts.basic ).toBe( 1 );
			await advanceTo( page, failure.at + 299999 );
			await wake( page );
			expect( counts.basic ).toBe( 1 );
			faultActive = false;
			if ( fault === 'unsupported' ) await page.evaluate( () => Object.defineProperty( window, 'crypto', { configurable: true, value: window.silentCaptchaNativeCrypto } ) );
			await page.clock.runFor( 1 );
			await success( page, key );
			expect( counts ).toEqual( { basic: 2, altcha: 1 } );
		} );
	} );
}

test( 'cookie-free backward clock correction keeps one bounded failure window', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
		await observeRefreshTimers( page );
		let baseline;
		let aborted = false;
		let documentLoaded;
		const documentReady = new Promise( resolve => { documentLoaded = resolve; } );
		await page.route( '**/wp-admin/admin-ajax.php', async route => {
			if ( requestActionSlug( route.request() ) !== 'capture_not_bot' || aborted ) return route.fallback();
			await documentReady;
			const finished = page.waitForEvent( 'requestfailed', request => request === route.request() );
			baseline = await timerBaseline( page );
			await route.abort();
			await finished;
			aborted = true;
		} );
		await page.goto( '/' );
		documentLoaded();
		await expect.poll( () => aborted ).toBe( true );
		const failure = await waitForRefreshTimer( page, baseline, 300000 );
		await page.clock.setSystemTime( new Date( failure.at - 26 * 86400000 ) );
		const correctionBaseline = await timerBaseline( page );
		await page.evaluate( () => window.dispatchEvent( new Event( 'focus' ) ) );
		const corrected = await waitForRefreshTimer( page, correctionBaseline, 300000 );
		expect( await completion( page, key ) ).toBe( 0 );
		await page.clock.runFor( 1000 );
		await wake( page );
		await advanceTo( page, corrected.at + 299999 );
		await wake( page );
		expect( counts ).toEqual( { basic: 1, altcha: 0 } );
		await page.clock.runFor( 1 );
		await success( page, key );
		expect( counts ).toEqual( { basic: 2, altcha: 1 } );
	} );
} );

test( 'cookie-free pending sequence survives hidden state and competing triggers', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
		let release;
		const hold = new Promise( resolve => { release = resolve; } );
		await page.route( '**/wp-admin/admin-ajax.php', async route => {
			if ( requestActionSlug( route.request() ) !== 'capture_not_bot_altcha' ) return route.fallback();
			await hold;
			await route.continue();
		} );
		await page.goto( '/' );
		await expect.poll( () => counts.altcha ).toBe( 1 );
		expect( await completion( page, key ) ).toBe( 0 );
		await page.evaluate( () => Object.defineProperty( document, 'visibilityState', { configurable: true, value: 'hidden' } ) );
		await wake( page );
		await page.clock.runFor( 300000 );
		expect( counts.basic ).toBe( 1 );
		expect( await completion( page, key ) ).toBe( 0 );
		release();
		await success( page, key );
		const previous = await completion( page, key );
		await page.clock.runFor( 300000 );
		expect( counts.basic ).toBe( 1 );
		await page.evaluate( () => Object.defineProperty( document, 'visibilityState', { configurable: true, value: 'visible' } ) );
		await wake( page );
		await expect.poll( () => counts.basic ).toBe( 2 );
		await success( page, key, previous );
		await wake( page );
		expect( counts.basic ).toBe( 2 );
	} );
} );

test( 'cookie-free fresh tab reuse and one diagnostic override per document', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, context, counts, key, start } ) => {
		await page.goto( '/' );
		await success( page, key );
		const first = await completion( page, key );
		await page.clock.runFor( 1 );
		await page.goto( '/?force_notbot=1' );
		await success( page, key, first );
		expect( counts.basic ).toBe( 2 );
		await wake( page );
		await page.clock.runFor( 299999 );
		expect( counts.basic ).toBe( 2 );
		const other = await context.newPage();
		await other.clock.install( { time: new Date( start + 299999 ) } );
		await other.clock.pauseAt( new Date( start + 299999 ) );
		let requests = 0;
		other.on( 'request', req => { if ( requestActionSlug( req ) === 'capture_not_bot' ) requests++; } );
		await other.goto( '/' );
		await settled( other );
		expect( requests ).toBe( 0 );
	} );
} );

test( 'cookie-free retains imported freshness if storage later becomes unavailable', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts, key, start } ) => {
		await page.addInitScript( ( { key, start } ) => localStorage.setItem( key, JSON.stringify( { completed_at: start } ) ), { key, start } );
		await page.goto( '/' );
		await settled( page );
		expect( counts.basic ).toBe( 0 );
		await page.evaluate( () => { Storage.prototype.getItem = () => { throw new Error( 'Reads revoked' ); }; } );
		await wake( page );
		await page.clock.runFor( 299999 );
		expect( counts.basic ).toBe( 0 );
		const altchaResponse = waitForShieldAjaxAction( page, 'capture_not_bot_altcha' );
		await page.clock.runFor( 1 );
		await expect.poll( () => counts.altcha ).toBe( 1 );
		expect( ( await expectShieldAjaxSuccess( await altchaResponse ) ).data.notbot_state ).toEqual( { mode: 'cookie_free', required: [], exchange_valid: true } );
		await wake( page );
		expect( counts.basic ).toBe( 1 );
	} );
} );

for ( const fault of [ 'expired-challenge', 'invalid-altcha', 'malformed-altcha', 'still-required' ] ) {
	test( `cookie-free ${fault} leaves freshness absent and bounds pending work`, async ( { browser, lane, fixtureApi } ) => {
		await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
			let injected = 0;
			let challengeData;
			let terminalBaseline;
			let delivered = false;
			let faultActive = true;
			let documentLoaded;
			const documentReady = new Promise( resolve => { documentLoaded = resolve; } );
			await observeRefreshTimers( page );
			await page.route( '**/wp-admin/admin-ajax.php', async route => {
				const action = requestActionSlug( route.request() );
				if ( !faultActive || ![ 'capture_not_bot', 'capture_not_bot_altcha' ].includes( action ) ) return route.fallback();
				const response = await route.fetch();
				const body = parseShieldAjaxJson( await response.text() );
				if ( action === 'capture_not_bot' ) {
					challengeData = body.data.altcha_data;
					if ( fault === 'expired-challenge' ) {
						const challenge = JSON.parse( challengeData.altcha_challenge );
						challenge.parameters.expiresAt = 1;
						challengeData.altcha_challenge = JSON.stringify( challenge );
						injected++;
					}
				}
				else {
					injected++;
					if ( fault === 'invalid-altcha' ) body.data.notbot_state.exchange_valid = false;
					if ( fault === 'still-required' ) {
						body.data.notbot_state.required = [ 'altcha' ];
						body.data.altcha_data = challengeData;
					}
				}
				const terminal = fault === 'expired-challenge' ? action === 'capture_not_bot' : action === 'capture_not_bot_altcha' && injected === ( fault === 'still-required' ? 4 : 1 );
				const finished = terminal ? page.waitForEvent( 'requestfinished', request => request === route.request() ) : null;
				if ( terminal ) {
					await documentReady;
					terminalBaseline = await timerBaseline( page );
				}
				await route.fulfill( fault === 'malformed-altcha' && action === 'capture_not_bot_altcha' ? { response, body: '{' } : { response, json: body } );
				if ( terminal ) {
					await finished;
					delivered = true;
				}
			} );
			await page.goto( '/' );
			documentLoaded();
			await expect.poll( () => delivered, { timeout: FAILURE_TIMEOUT } ).toBe( true );
			const failure = await waitForRefreshTimer( page, terminalBaseline, 300000 );
			expect( injected ).toBe( fault === 'still-required' ? 4 : 1 );
			expect( await completion( page, key ) ).toBe( 0 );
			expect( counts.altcha ).toBe( fault === 'expired-challenge' ? 0 : fault === 'still-required' ? 4 : 1 );
			await page.evaluate( () => history.replaceState( null, '', '/?force_notbot=1' ) );
			await wake( page );
			expect( counts.basic ).toBe( 1 );
			await advanceTo( page, failure.at + 299999 );
			await wake( page );
			expect( counts.basic ).toBe( 1 );
			faultActive = false;
			await page.clock.runFor( 1 );
			await success( page, key );
			expect( counts.basic ).toBe( 2 );
		} );
	} );
}

test( 'cookie-free visible unfocused page refreshes while initially hidden page waits', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
		await page.addInitScript( () => Object.defineProperty( document, 'visibilityState', { configurable: true, value: 'hidden' } ) );
		await page.goto( '/' );
		await settled( page );
		await page.clock.runFor( 300000 );
		await wake( page );
		expect( counts.basic ).toBe( 0 );
		await page.evaluate( () => {
			Object.defineProperty( document, 'visibilityState', { configurable: true, value: 'visible' } );
			document.dispatchEvent( new Event( 'visibilitychange' ) );
			window.dispatchEvent( new Event( 'blur' ) );
		} );
		await success( page, key );
		const first = await completion( page, key );
		await page.clock.runFor( 300000 );
		await success( page, key, first );
		expect( counts.basic ).toBe( 2 );
	} );
} );

test( 'cookie-free loaded tab adopts another tab completion before its previous due time', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, context, counts, key, start } ) => {
		await page.goto( '/' );
		await success( page, key );
		await page.clock.runFor( 100000 );
		const other = await context.newPage();
		await other.clock.install( { time: new Date( start + 100000 ) } );
		await other.clock.pauseAt( new Date( start + 100000 ) );
		await other.goto( '/?force_notbot=1' );
		await success( other, key, start );
		await settled( other );
		await page.clock.runFor( 200000 );
		await settled( page );
		expect( counts.basic ).toBe( 1 );
		await page.clock.runFor( 99999 );
		expect( counts.basic ).toBe( 1 );
		await page.clock.runFor( 1 );
		await success( page, key, start + 100000 );
		expect( counts.basic ).toBe( 2 );
	} );
} );

test( 'cookie-free site key ignores and preserves other site and unrelated storage', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts, key, start } ) => {
		await page.addInitScript( ( { key, start } ) => {
			localStorage.setItem( key + 'another-site/', JSON.stringify( { completed_at: start } ) );
			localStorage.setItem( 'unrelated-preference', 'keep' );
		}, { key, start } );
		await page.goto( '/' );
		await success( page, key );
		expect( counts ).toEqual( { basic: 1, altcha: 1 } );
		expect( await page.evaluate( key => [ localStorage.getItem( key + 'another-site/' ), localStorage.getItem( 'unrelated-preference' ) ], key ) ).toEqual( [ JSON.stringify( { completed_at: start } ), 'keep' ] );
	} );
} );

async function expectNoNotBotHeader( response ) {
	const cookies = ( await response.headersArray() ).filter( header => header.name.toLowerCase() === 'set-cookie' ).map( header => header.value );
	expect( cookies.some( value => value.startsWith( 'icwp-wpsf-notbot=' ) ) ).toBe( false );
}

for ( const oldCookie of [ false, true ] ) {
	test( `cookie-free original document and AJAX headers never issue or renew NotBot (old=${oldCookie})`, async ( { browser, lane, fixtureApi } ) => {
		await scenario( browser, lane, fixtureApi, async ( { page, context, key } ) => {
			const old = { name: 'icwp-wpsf-notbot', value: 'notbotZaltchaZexp-9999999999', url: lane.baseUrl, expires: Math.floor( Date.now() / 1000 ) + 1800 };
			if ( oldCookie ) await context.addCookies( [ old ] );
			const responses = [];
			page.on( 'response', response => {
				if ( [ 'capture_not_bot', 'capture_not_bot_altcha' ].includes( requestActionSlug( response.request() ) ) ) responses.push( response );
			} );
			await expectNoNotBotHeader( await page.goto( '/' ) );
			await success( page, key );
			expect( responses ).toHaveLength( 2 );
			for ( const response of responses ) await expectNoNotBotHeader( response );
			await expectNoNotBotHeader( await context.request.post( '/wp-admin/admin-ajax.php', { form: { action: 'shield_browser_third_party_ping' } } ) );
			const login = await context.request.get( '/wp-login.php' );
			await expectNoNotBotHeader( login );
			expect( ( await login.headersArray() ).some( header => header.name.toLowerCase() === 'set-cookie' && header.value.startsWith( 'wordpress_test_cookie=' ) ) ).toBe( true );
			const stored = ( await context.cookies() ).find( cookie => cookie.name === old.name );
			if ( oldCookie ) { expect( stored.value ).toBe( old.value ); expect( stored.expires ).toBe( old.expires ); }
			else expect( stored ).toBeUndefined();
		} );
	} );
}

test( 'cookie-free logged-in document also suppresses the deferred init writer', async ( { page, fixtureApi } ) => {
	await fixtureApi.withNotBotAltchaFixture( IP, async () => {
		await page.context().setExtraHTTPHeaders( { 'X-Forwarded-For': IP } );
		expect( ( await page.context().cookies() ).some( cookie => cookie.name.startsWith( 'wordpress_logged_in_' ) ) ).toBe( true );
		const cookieModeResponse = await page.request.get( '/' );
		expect( cookieModeResponse.status() ).toBe( 200 );
		expect( ( await cookieModeResponse.headersArray() ).some( header =>
			header.name.toLowerCase() === 'set-cookie' && header.value.startsWith( 'icwp-wpsf-notbot=' ) ) ).toBe( true );
		await fixtureApi.setNotBotMode( 'cookie_free' );
		await expectNoNotBotHeader( await page.request.get( '/' ) );
	} );
} );

test( 'cookie-free reassesses a changed IP only when local freshness is due', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, context, counts, key } ) => {
		const secondIp = '93.184.216.85';
		await fixtureApi.addNotBotIp( secondIp );
		expect( await fixtureApi.inspectNotBotAltchaFixture() ).toEqual( { ip: IP, notbot_at: 0, altcha_at: 0 } );
		expect( await fixtureApi.inspectNotBotAltchaFixture( secondIp ) ).toEqual( { ip: secondIp, notbot_at: 0, altcha_at: 0 } );
		await page.goto( '/' );
		await success( page, key );
		const first = await completion( page, key );
		const primarySignals = await fixtureApi.inspectNotBotAltchaFixture();
		expect( primarySignals.ip ).toBe( IP );
		expect( primarySignals.altcha_at ).toBeGreaterThan( 0 );
		await context.setExtraHTTPHeaders( { 'X-Forwarded-For': secondIp } );
		await wake( page );
		await page.clock.runFor( 299999 );
		expect( counts.basic ).toBe( 1 );
		expect( ( await fixtureApi.inspectNotBotAltchaFixture( secondIp ) ).altcha_at ).toBe( 0 );
		await page.clock.runFor( 1 );
		await success( page, key, first );
		expect( counts ).toEqual( { basic: 2, altcha: 2 } );
		expect( ( await fixtureApi.inspectNotBotAltchaFixture( secondIp ) ).altcha_at ).toBeGreaterThan( 0 );
	} );
} );

for ( const secondIp of [ '93.184.216.87', '2620:0:862:ed1a::1' ] ) {
	test( `cookie-free pending challenge credits only changed IP ${secondIp} after real proof`, async ( { browser, lane, fixtureApi } ) => {
		await scenario( browser, lane, fixtureApi, async ( { page, context, counts, key } ) => {
			await fixtureApi.addNotBotIp( secondIp );
			let release;
			const hold = new Promise( resolve => { release = resolve; } );
			let minted = false;
			let firstBasic = true;
			await page.route( '**/wp-admin/admin-ajax.php', async route => {
				const action = requestActionSlug( route.request() );
				if ( action === 'capture_not_bot' && firstBasic ) {
					firstBasic = false;
					const response = await route.fetch();
					const payload = parseShieldAjaxJson( await response.text() );
					expect( payload.success ).toBe( true );
					expect( payload.data.notbot_state ).toEqual( { mode: 'cookie_free', required: [ 'altcha' ], exchange_valid: true } );
					minted = true;
					await hold;
					return route.fulfill( { response } );
				}
				return route.fallback();
			} );
			await page.goto( '/' );
			await expect.poll( () => minted ).toBe( true );
			await context.setExtraHTTPHeaders( { 'X-Forwarded-For': secondIp } );
			await wake( page );
			expect( await completion( page, key ) ).toBe( 0 );
			expect( counts ).toEqual( { basic: 1, altcha: 0 } );
			const primary = await fixtureApi.inspectNotBotAltchaFixture();
			expect( primary.notbot_at ).toBeGreaterThan( 0 );
			expect( primary.altcha_at ).toBe( 0 );
			expect( await fixtureApi.inspectNotBotAltchaFixture( secondIp ) ).toEqual( { ip: secondIp, notbot_at: 0, altcha_at: 0 } );
			const altchaResponse = waitForShieldAjaxAction( page, 'capture_not_bot_altcha' );
			release();
			const response = await altchaResponse;
			expect( await response.request().headerValue( 'x-forwarded-for' ) ).toBe( secondIp );
			expect( ( await expectShieldAjaxSuccess( response ) ).data.notbot_state ).toEqual( { mode: 'cookie_free', required: [], exchange_valid: true } );
			await success( page, key );
			expect( counts ).toEqual( { basic: 1, altcha: 1 } );
			const secondary = await fixtureApi.inspectNotBotAltchaFixture( secondIp );
			expect( secondary.notbot_at ).toBeGreaterThan( 0 );
			expect( secondary.altcha_at ).toBeGreaterThan( 0 );
			expect( await fixtureApi.inspectNotBotAltchaFixture() ).toEqual( primary );
		} );
	} );
}

test( 'cookie-free identical cached HTML serves separate IPs and stored freshness never grants server signals', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, context, counts, key } ) => {
		const secondIp = '93.184.216.86';
		await fixtureApi.addNotBotIp( secondIp );
		const original = await context.request.get( '/' );
		await expectNoNotBotHeader( original );
		const html = await original.text();
		await page.route( lane.baseUrl + '/', route => route.fulfill( { response: original, body: html } ) );
		await page.goto( '/' );
		await success( page, key );
		expect( counts.altcha ).toBe( 1 );
		const other = await browser.newContext( { baseURL: lane.baseUrl, extraHTTPHeaders: { 'X-Forwarded-For': secondIp } } );
		// Cached HTML supplies no page-load signal for this IP. Preserve the real
		// adaptive medium challenge, including its greater WebKit CPU work.
		other.setDefaultTimeout( COMPLEX_CHALLENGE_TIMEOUT );
		try {
			const visitor = await other.newPage();
			await visitor.addInitScript( key => localStorage.setItem( key, JSON.stringify( { completed_at: Date.now() } ) ), key );
			await visitor.route( lane.baseUrl + '/', route => route.fulfill( { response: original, body: html } ) );
			let ajax = 0;
			visitor.on( 'request', request => { if ( requestActionSlug( request ) === 'capture_not_bot' ) ajax++; } );
			await visitor.goto( '/' );
			await settled( visitor );
			expect( ajax ).toBe( 0 );
			expect( ( await fixtureApi.inspectNotBotAltchaFixture( secondIp ) ).altcha_at ).toBe( 0 );
			expect( ( await fixtureApi.inspectNotBotAltchaFixture( secondIp ) ).notbot_at ).toBe( 0 );
			await visitor.evaluate( key => localStorage.removeItem( key ), key );
			await wake( visitor );
			// The active document retains already-read shared freshness; a diagnostic navigation uses the same cached body.
			await visitor.route( lane.baseUrl + '/?force_notbot=1', route => route.fulfill( { response: original, body: html } ) );
			const basicResponse = waitForShieldAjaxAction( visitor, 'capture_not_bot' );
			const altchaResponse = waitForShieldAjaxAction( visitor, 'capture_not_bot_altcha' );
			await visitor.goto( '/?force_notbot=1' );
			expect( ( await expectShieldAjaxSuccess( await basicResponse ) ).data.notbot_state.required ).toEqual( [ 'altcha' ] );
			expect( ( await expectShieldAjaxSuccess( await altchaResponse ) ).data.notbot_state.required ).toEqual( [] );
			expect( ( await fixtureApi.inspectNotBotAltchaFixture( secondIp ) ).altcha_at ).toBeGreaterThan( 0 );
			expect( ajax ).toBe( 1 );
			expect( await visitor.evaluate( () => Object.keys( window.shield_vars_silentcaptcha.comps.silentcaptcha ).sort() ) ).toEqual( [ 'ajax', 'config' ] );
		}
		finally { await other.close(); }
	} );
} );

test( 'cookie-free forged local freshness cannot authorize native login but real proof can', async ( { browser, lane, fixtureApi } ) => {
	const ip = '93.184.216.88';
	await fixtureApi.withLoginGuardCoreFixture( 'silentcaptcha-login', async fixture => {
		await scenario( browser, lane, fixtureApi, async ( { page, context, counts, key, start } ) => {
			await page.addInitScript( ( { key, start } ) => {
				if ( localStorage.getItem( key ) === null ) localStorage.setItem( key, JSON.stringify( { completed_at: start } ) );
			}, { key, start } );
			await page.goto( fixture.login_path + '?redirect_to=' + encodeURIComponent( lane.baseUrl + '/' ) );
			await settled( page );
			expect( counts ).toEqual( { basic: 0, altcha: 0 } );
			expect( await fixtureApi.inspectNotBotAltchaFixture() ).toEqual( { ip, notbot_at: 0, altcha_at: 0 } );
			const submitCredentials = async () => {
				await loginReady( page );
				await fillWordPressLoginForm( page.locator( '#loginform' ), fixture.user_login, fixture.user_pass );
				await Promise.all( [ page.waitForNavigation(), page.locator( '#wp-submit' ).click() ] );
			};
			await submitCredentials();
			expect( ( await fixtureApi.inspectLoginGuardCoreFixture() ).event_counts.login_block ).toBe( 1 );
			expect( ( await context.cookies() ).some( cookie => cookie.name.startsWith( 'wordpress_logged_in_' ) ) ).toBe( false );
			expect( counts ).toEqual( { basic: 0, altcha: 0 } );
			expect( await fixtureApi.inspectNotBotAltchaFixture() ).toEqual( { ip, notbot_at: 0, altcha_at: 0 } );
			await loginReady( page );
			await page.clock.runFor( 1 );
			await page.evaluate( () => history.replaceState( null, '', '/wp-login.php?force_notbot=1' ) );
			await wake( page );
			await success( page, key, start );
			expect( counts ).toEqual( { basic: 1, altcha: 1 } );
			const signals = await fixtureApi.inspectNotBotAltchaFixture();
			expect( signals.notbot_at ).toBeGreaterThan( 0 );
			expect( signals.altcha_at ).toBeGreaterThan( 0 );
			await submitCredentials();
			expect( ( await context.cookies() ).some( cookie => cookie.name.startsWith( 'wordpress_logged_in_' ) ) ).toBe( true );
			expect( ( await fixtureApi.inspectLoginGuardCoreFixture() ).event_counts.login_block ).toBe( 1 );
			expect( ( await context.cookies() ).some( cookie => cookie.name === 'icwp-wpsf-notbot' ) ).toBe( false );
		}, { allowCookies: true, ip } );
	} );
} );

test( 'cookie-free adopts cookie mode and clears only feature freshness', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
		await page.goto( '/' );
		await success( page, key );
		await page.evaluate( () => localStorage.setItem( 'unrelated', 'keep' ) );
		await fixtureApi.setNotBotMode( 'cookie' );
		await page.clock.runFor( 300000 );
		await expect.poll( () => page.evaluate( key => localStorage.getItem( key ), key ) ).toBeNull();
		await settled( page );
		expect( counts.basic ).toBe( 2 );
		await wake( page );
		await page.clock.runFor( 300000 );
		expect( counts.basic ).toBe( 2 );
		expect( await page.evaluate( () => localStorage.getItem( 'unrelated' ) ) ).toBe( 'keep' );
	}, { allowCookies: true } );
} );

test( 'cookie mode adopts cookie-free after old-cycle cleanup without completing the mismatched response', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
		const cycle = await startCookieCycle( page );
		await expect.poll( async () => ( await fixtureApi.inspectNotBotAltchaFixture() ).altcha_at ).toBeGreaterThan( 0 );
		expect( counts ).toEqual( { basic: 1, altcha: 1 } );
		await fixtureApi.setNotBotMode( 'cookie_free' );
		let release;
		const hold = new Promise( resolve => { release = resolve; } );
		let matchingCycle = 0;
		await page.route( '**/wp-admin/admin-ajax.php', async route => {
			if ( requestActionSlug( route.request() ) !== 'capture_not_bot' ) return route.fallback();
			if ( ++matchingCycle === 2 ) await hold;
			await route.continue();
		} );
		await advanceTo( page, cycle.at + 15000 );
		await expect.poll( () => counts.basic ).toBe( 3 );
		expect( await completion( page, key ) ).toBe( 0 );
		await wake( page );
		expect( counts.basic ).toBe( 3 );
		release();
		await success( page, key );
		await page.clock.runFor( 299999 );
		await wake( page );
		expect( counts.basic ).toBe( 3 );
	}, { allowCookies: true, mode: 'cookie' } );
} );

for ( const contextKind of [ 'form', 'parser-late-form', 'native-login', 'renamed-login' ] ) {
	test( `adaptive ${contextKind} exact boundary and fresh reload`, async ( { browser, lane, fixtureApi } ) => {
		const run = async () => scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
			const isForm = contextKind.endsWith( 'form' );
			if ( contextKind === 'parser-late-form' ) await page.addInitScript( () => {
				const nativeFetch = window.fetch.bind( window );
				window.basicStarted = 0;
				window.fetch = ( ...args ) => {
					if ( String( args[ 1 ]?.body ).includes( 'capture_not_bot' ) ) window.basicStarted++;
					return nativeFetch( ...args );
				};
			} );
			if ( isForm ) await page.route( '**/', async route => {
				const response = await route.fetch();
				const form = '<form method="get"><input name="log"><input name="pwd" type="password"></form>';
				let body = await response.text();
				if ( contextKind === 'parser-late-form' ) {
					const script = /<script\b[^>]*src=['"][^'"]*silentcaptcha[^'"]*['"][^>]*><\/script>/;
					expect( body ).toMatch( script );
					body = body.replace( script, tag => tag.replace( /\s(?:defer|async)(?:=['"][^'"]*['"])?/g, '' )
						+ '<script>window.beforeLateForm = {forms: document.forms.length, requests: window.basicStarted};</script>' + form );
				}
				else body = body.replace( /(<body[^>]*>)/, '$1' + form );
				await route.fulfill( { response, body } );
			} );
			await page.goto( isForm ? '/' : contextKind === 'native-login' ? '/wp-login.php' : '/shield-browser-login' );
			if ( !isForm ) await loginReady( page );
			await success( page, key );
			if ( contextKind === 'parser-late-form' ) expect( await page.evaluate( () => window.beforeLateForm ) ).toEqual( { forms: 0, requests: 1 } );
			expect( await page.evaluate( () => window.shield_vars_silentcaptcha.comps.silentcaptcha.config.is_login ) ).toBe( !isForm );
			const first = await completion( page, key );
			await page.reload();
			if ( !isForm ) await loginReady( page );
			await settled( page );
			expect( counts.basic ).toBe( 1 );
			const interval = isForm ? 120000 : 60000;
			await advanceTo( page, first + interval - 1 );
			await wake( page );
			expect( counts.basic ).toBe( 1 );
			await page.clock.runFor( 1 );
			await success( page, key, first );
			expect( counts.basic ).toBe( 2 );
		} );
		if ( contextKind === 'renamed-login' ) await fixtureApi.withLoginGuardCoreFixture( 'hide-login', run );
		else await run();
	} );
}

test( 'adaptive inserted subtree becomes due without resetting completion and form removal stays latched', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
		await page.goto( '/' );
		await success( page, key );
		const first = await completion( page, key );
		await page.clock.runFor( 180000 );
		expect( counts.basic ).toBe( 1 );
		await insertForm( page );
		await success( page, key, first );
		expect( counts.basic ).toBe( 2 );
		const second = await completion( page, key );
		await page.evaluate( () => document.querySelectorAll( 'form' ).forEach( form => form.remove() ) );
		await page.clock.runFor( 119999 );
		await wake( page );
		expect( counts.basic ).toBe( 2 );
		await page.clock.runFor( 1 );
		await success( page, key, second );
		expect( counts.basic ).toBe( 3 );
	} );
} );

test( 'adaptive form detection shortens failure cooldown without resetting its timestamp', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
		await observeRefreshTimers( page );
		let attempts = 0;
		let baseline;
		let aborted = false;
		let documentLoaded;
		const documentReady = new Promise( resolve => { documentLoaded = resolve; } );
		await page.route( '**/wp-admin/admin-ajax.php', async route => {
			if ( requestActionSlug( route.request() ) === 'capture_not_bot' && ++attempts === 1 ) {
				await documentReady;
				const finished = page.waitForEvent( 'requestfailed', request => request === route.request() );
				baseline = await timerBaseline( page );
				await route.abort();
				await finished;
				aborted = true;
				return;
			}
			return route.continue();
		} );
		await page.goto( '/' );
		documentLoaded();
		await expect.poll( () => aborted ).toBe( true );
		const failure = await waitForRefreshTimer( page, baseline, 300000 );
		await advanceTo( page, failure.at + 30000 );
		await insertForm( page );
		await wake( page );
		expect( counts.basic ).toBe( 1 );
		await page.clock.runFor( 89999 );
		await wake( page );
		expect( counts.basic ).toBe( 1 );
		expect( await completion( page, key ) ).toBe( 0 );
		await page.clock.runFor( 1 );
		await success( page, key );
		expect( counts ).toEqual( { basic: 2, altcha: 1 } );
	} );
} );

test( 'adaptive form pending work and hidden resume keep a single sequence', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts, key } ) => {
		let release;
		const hold = new Promise( resolve => { release = resolve; } );
		await page.route( '**/wp-admin/admin-ajax.php', async route => {
			if ( requestActionSlug( route.request() ) === 'capture_not_bot_altcha' ) await hold;
			await route.continue();
		} );
		await page.goto( '/' );
		await expect.poll( () => counts.altcha ).toBe( 1 );
		await insertForm( page );
		await page.clock.runFor( 120000 );
		await wake( page );
		expect( counts.basic ).toBe( 1 );
		expect( await completion( page, key ) ).toBe( 0 );
		release();
		await success( page, key );
		const first = await completion( page, key );
		await page.evaluate( () => Object.defineProperty( document, 'visibilityState', { configurable: true, value: 'hidden' } ) );
		await wake( page );
		await page.clock.runFor( 120000 );
		expect( counts.basic ).toBe( 1 );
		await page.evaluate( () => Object.defineProperty( document, 'visibilityState', { configurable: true, value: 'visible' } ) );
		await wake( page );
		await success( page, key, first );
		expect( counts.basic ).toBe( 2 );
	} );
} );

test( 'adaptive forms submit while silentCAPTCHA remains pending', async ( { browser, lane, fixtureApi } ) => {
	await scenario( browser, lane, fixtureApi, async ( { page, counts } ) => {
		let release;
		const hold = new Promise( resolve => { release = resolve; } );
		await page.route( '**/wp-admin/admin-ajax.php', async route => {
			if ( requestActionSlug( route.request() ) === 'capture_not_bot' ) await hold;
			await route.continue().catch( () => {} );
		} );
		try {
			await page.goto( '/' );
			await expect.poll( () => counts.basic ).toBe( 1 );
			await insertForm( page );
			await Promise.all( [ page.waitForURL( '**/?s=test' ), page.getByRole( 'button', { name: 'Search', exact: true } ).click() ] );
			expect( new URL( page.url() ).searchParams.get( 's' ) ).toBe( 'test' );
		}
		finally { release(); }
	} );
} );
