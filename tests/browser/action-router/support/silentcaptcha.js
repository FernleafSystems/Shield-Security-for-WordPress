const { expect: baseExpect } = require( './shield-test' );

const CYCLE_TIMEOUT = 30_000;
const COMPLEX_CHALLENGE_TIMEOUT = 90_000;
const FAILURE_TIMEOUT = 90_000;
const TEST_TIMEOUT = 120_000;
const expect = baseExpect.configure( { timeout: CYCLE_TIMEOUT } );

async function observeRefreshTimers( page ) {
	await page.addInitScript( () => {
		const nativeSetTimeout = window.setTimeout;
		const records = [];
		function observedSetTimeout( ...args ) {
			records.push( { sequence: records.length + 1, delay: Number( args[ 1 ] || 0 ), at: Date.now() } );
			return Reflect.apply( nativeSetTimeout, this, args );
		}
		window.setTimeout = observedSetTimeout;
		window.silentCaptchaTimerObservation = { records, wrapper: observedSetTimeout };
	} );
}

async function timerBaseline( page ) {
	return page.evaluate( () => {
		const observation = window.silentCaptchaTimerObservation;
		if ( !observation || window.setTimeout !== observation.wrapper ) {
			throw new Error( 'The refresh timer observer must wrap the active browser timer.' );
		}
		return observation.records.length;
	} );
}

async function waitForRefreshTimer( page, baseline, windowMs, timeout = CYCLE_TIMEOUT ) {
	const observations = () => page.evaluate( ( { baseline, windowMs } ) =>
		window.silentCaptchaTimerObservation.records.filter( record => record.sequence > baseline && record.delay >= windowMs ),
	{ baseline, windowMs } );
	await expect.poll( observations, { timeout } ).not.toEqual( [] );
	const records = await observations();
	expect( records, 'A terminal exchange must have one unambiguous refresh timer.' ).toHaveLength( 1 );
	expect( records[ 0 ].delay ).toBe( windowMs );
	return records[ 0 ];
}

module.exports = { expect, CYCLE_TIMEOUT, COMPLEX_CHALLENGE_TIMEOUT, FAILURE_TIMEOUT, TEST_TIMEOUT, observeRefreshTimers, timerBaseline, waitForRefreshTimer };
