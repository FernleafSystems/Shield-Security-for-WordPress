const { test, expect } = require( './support/shield-test' );
const {
	expectAutoRecoverControls,
	expectConnectedNonEmptyReference,
	expectNoAxeViolationsInDialog,
	expectStatusLiveRegion,
	openBlockRecoveryModal,
	openPublicBlockPage,
} = require( './support/security-assertions' );

test( 'shield email unblock modal is named and announces send result', async ( { page, fixtureApi } ) => {
	await fixtureApi.withPublicBlockRecoveryFixture( 'shield-email', async ( fixture ) => {
		const ids = fixture.ids.ip_shield_email;
		await openPublicBlockPage( page, fixture.urls.ip_shield );
		await openBlockRecoveryModal( page, ids );
		await expectNoAxeViolationsInDialog( page, ids );

		const submit = page.locator( `#${ids.submit}` );
		const status = page.locator( `#${ids.status}` );
		await expectConnectedNonEmptyReference( page, submit, 'aria-describedby' );
		await expectStatusLiveRegion( status );
		await submit.click();
		await expect.poll( async () => status.evaluate( ( node ) => ( node.textContent || '' ).trim().length ) )
			.toBeGreaterThan( 0 );
	} );
} );

test( 'shield auto recovery modal exposes stable checkbox and submit contract', async ( { page, fixtureApi } ) => {
	await fixtureApi.withPublicBlockRecoveryFixture( 'shield-auto', async ( fixture ) => {
		const ids = fixture.ids.ip_shield_auto;
		await openPublicBlockPage( page, fixture.urls.ip_shield );
		await openBlockRecoveryModal( page, ids );
		await expectAutoRecoverControls( page, ids );
		await expectNoAxeViolationsInDialog( page, ids );
	} );
} );

test( 'crowdsec auto recovery modal uses the same control contract', async ( { page, fixtureApi } ) => {
	await fixtureApi.withPublicBlockRecoveryFixture( 'crowdsec-auto', async ( fixture ) => {
		const ids = fixture.ids.ip_crowdsec_auto;
		await openPublicBlockPage( page, fixture.urls.ip_crowdsec );
		await openBlockRecoveryModal( page, ids );
		await expectAutoRecoverControls( page, ids );
		await expectNoAxeViolationsInDialog( page, ids );
	} );
} );

test( 'traffic rate limit page exposes auto recovery through producer contract', async ( { page, fixtureApi } ) => {
	await fixtureApi.withPublicBlockRecoveryFixture( 'shield-auto', async ( fixture ) => {
		const ids = fixture.ids.traffic_rate_limit_auto;
		await openPublicBlockPage( page, fixture.urls.traffic_rate_limit );
		await openBlockRecoveryModal( page, ids );
		await expectAutoRecoverControls( page, ids );
		await expectNoAxeViolationsInDialog( page, ids );
	} );
} );
