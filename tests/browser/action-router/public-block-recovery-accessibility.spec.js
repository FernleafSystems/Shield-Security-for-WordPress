const { AxeBuilder, test, expect } = require( './support/shield-test' );

async function openPublicBlockPage( page, url ) {
	await page.goto( url, { waitUntil: 'load' } );
	await expect( page.locator( 'body' ) ).toBeVisible();
}

async function expectConnectedReference( page, element, attribute ) {
	const value = await element.getAttribute( attribute );
	expect( value || '' ).not.toHaveLength( 0 );
	const ids = String( value ).split( /\s+/ ).filter( Boolean );
	expect( ids.length ).toBeGreaterThan( 0 );

	for ( const id of ids ) {
		const target = page.locator( `#${id}` );
		await expect( target ).toHaveCount( 1 );
		expect( await target.evaluate( ( node ) => {
			return node.isConnected && ( node.textContent || '' ).trim().length > 0;
		} ) ).toBe( true );
	}
}

async function openRecoveryModal( page, ids ) {
	const launcher = page.locator( `#${ids.launcher}` );
	await expect( launcher ).toBeVisible();
	await expect( launcher ).toHaveAttribute( 'data-bs-target', `#${ids.dialog}` );
	await launcher.click();

	const modal = page.locator( `#${ids.dialog}` );
	await expect( modal ).toBeVisible();
	await expect( modal ).toHaveAttribute( 'role', 'dialog' );
	await expectConnectedReference( page, modal, 'aria-labelledby' );
	await expectConnectedReference( page, modal, 'aria-describedby' );

	const dismiss = modal.locator( 'button[data-bs-dismiss="modal"]' );
	await expect( dismiss ).toHaveCount( 1 );
	expect( ( await dismiss.getAttribute( 'aria-label' ) || '' ).trim().length ).toBeGreaterThan( 0 );
	return modal;
}

async function expectNoAxeViolations( page, ids ) {
	const results = await new AxeBuilder( { page } )
	.include( `#${ids.dialog}` )
	.analyze();

	expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
}

async function expectAutoRecoverControls( page, ids ) {
	const checkbox = page.locator( `#${ids.confirm}` );
	const label = page.locator( `label[for="${ids.confirm}"]` );
	const submit = page.locator( `#${ids.submit}` );

	await expect( checkbox ).toHaveAttribute( 'name', '_confirm' );
	await expect( label ).toHaveCount( 1 );
	expect( await label.evaluate( ( node ) => {
		return node.isConnected && ( node.textContent || '' ).trim().length > 0;
	} ) ).toBe( true );
	await expectConnectedReference( page, submit, 'aria-describedby' );
	await expect( submit ).toBeDisabled();
	await checkbox.check();
	await expect( submit ).toBeEnabled();
}

test( 'shield email unblock modal is named and announces send result', async ( { page, fixtureApi } ) => {
	await fixtureApi.withPublicBlockRecoveryFixture( 'shield-email', async ( fixture ) => {
		const ids = fixture.ids.ip_shield_email;
		await openPublicBlockPage( page, fixture.urls.ip_shield );
		await openRecoveryModal( page, ids );
		await expectNoAxeViolations( page, ids );

		const submit = page.locator( `#${ids.submit}` );
		const status = page.locator( `#${ids.status}` );
		await expectConnectedReference( page, submit, 'aria-describedby' );
		await expect( status ).toHaveAttribute( 'role', 'status' );
		await expect( status ).toHaveAttribute( 'aria-live', 'polite' );
		await expect( status ).toHaveAttribute( 'aria-atomic', 'true' );
		await submit.click();
		await expect.poll( async () => status.evaluate( ( node ) => ( node.textContent || '' ).trim().length ) )
			.toBeGreaterThan( 0 );
	} );
} );

test( 'shield auto recovery modal exposes stable checkbox and submit contract', async ( { page, fixtureApi } ) => {
	await fixtureApi.withPublicBlockRecoveryFixture( 'shield-auto', async ( fixture ) => {
		const ids = fixture.ids.ip_shield_auto;
		await openPublicBlockPage( page, fixture.urls.ip_shield );
		await openRecoveryModal( page, ids );
		await expectAutoRecoverControls( page, ids );
		await expectNoAxeViolations( page, ids );
	} );
} );

test( 'crowdsec auto recovery modal uses the same control contract', async ( { page, fixtureApi } ) => {
	await fixtureApi.withPublicBlockRecoveryFixture( 'crowdsec-auto', async ( fixture ) => {
		const ids = fixture.ids.ip_crowdsec_auto;
		await openPublicBlockPage( page, fixture.urls.ip_crowdsec );
		await openRecoveryModal( page, ids );
		await expectAutoRecoverControls( page, ids );
		await expectNoAxeViolations( page, ids );
	} );
} );

test( 'traffic rate limit page exposes auto recovery through producer contract', async ( { page, fixtureApi } ) => {
	await fixtureApi.withPublicBlockRecoveryFixture( 'shield-auto', async ( fixture ) => {
		const ids = fixture.ids.traffic_rate_limit_auto;
		await openPublicBlockPage( page, fixture.urls.traffic_rate_limit );
		await openRecoveryModal( page, ids );
		await expectAutoRecoverControls( page, ids );
		await expectNoAxeViolations( page, ids );
	} );
} );
