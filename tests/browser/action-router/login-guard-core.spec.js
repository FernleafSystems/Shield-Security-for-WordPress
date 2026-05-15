const { test, expect } = require( './support/shield-test' );

async function anonymousPage( browser, lane ) {
	const context = await browser.newContext( { baseURL: lane.baseUrl } );
	const page = await context.newPage();
	return { context, page };
}

async function submitWpLogin( page, loginPath, userLogin, userPass ) {
	await page.goto( loginPath, { waitUntil: 'domcontentloaded' } );
	await page.locator( '#user_login' ).fill( userLogin );
	await page.locator( '#user_pass' ).fill( userPass );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ).catch( () => null ),
		page.locator( '#wp-submit' ).click(),
	] );
}

async function fillOtp( page, fieldName, otp ) {
	const input = page.locator( `input[name="${ fieldName }"]` ).first();
	await input.waitFor( { state: 'attached', timeout: 20_000 } );
	const inputId = await input.getAttribute( 'id' );
	const segments = page.locator( `[data-otp-group][data-otp-target="${ inputId }"] input[data-otp]` );

	if ( await segments.first().isVisible().catch( () => false ) ) {
		for ( let index = 0; index < otp.length; index++ ) {
			await segments.nth( index ).fill( otp.charAt( index ) );
		}
	}
	else {
		await input.fill( otp, { force: true } );
	}
}

async function submitMfaForm( page ) {
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ).catch( () => null ),
		page.locator( '#mainSubmit' ).click(),
	] );
}

async function waitForInspection( fixtureApi, predicate, label ) {
	for ( let attempt = 0; attempt < 30; attempt++ ) {
		const inspected = await fixtureApi.inspectLoginGuardCoreFixture();
		if ( predicate( inspected ) ) {
			return inspected;
		}
		await new Promise( ( resolve ) => setTimeout( resolve, 500 ) );
	}

	throw new Error( `Timed out waiting for fixture inspection: ${ label }` );
}

test( 'hide-login contrasts custom path with blocked old login and disabled state', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withLoginGuardCoreFixture( 'hide-login', async ( fixture ) => {
		const { context, page } = await anonymousPage( browser, lane );
		try {
			const customResponse = await page.goto( fixture.custom_login_path, { waitUntil: 'domcontentloaded' } );
			expect( customResponse.status() ).toBeLessThan( 400 );

			const oldResponse = await page.goto( fixture.old_login_path, { waitUntil: 'domcontentloaded' } );
			expect( [ 403, 404 ] ).toContain( oldResponse.status() );

			const adminResponse = await page.goto( fixture.admin_path, { waitUntil: 'domcontentloaded' } );
			expect( adminResponse.status() ).toBeLessThan( 500 );
			expect( new URL( page.url() ).pathname ).not.toBe( fixture.admin_path );

			const inspected = await fixtureApi.inspectLoginGuardCoreFixture();
			expect( inspected.event_counts.hide_login_url ).toBeGreaterThanOrEqual( 1 );
		}
		finally {
			await context.close();
		}
	} );

	await fixtureApi.withLoginGuardCoreFixture( 'hide-login-disabled', async ( fixture ) => {
		const { context, page } = await anonymousPage( browser, lane );
		try {
			const response = await page.goto( fixture.old_login_path, { waitUntil: 'domcontentloaded' } );
			expect( response.status() ).toBeLessThan( 400 );

			const inspected = await fixtureApi.inspectLoginGuardCoreFixture();
			expect( inspected.event_counts.hide_login_url || 0 ).toBe( 0 );
		}
		finally {
			await context.close();
		}
	} );
} );

test( 'remember-me checkbox creates skip state and suppresses the next login intent', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withLoginGuardCoreFixture( 'remember-me', async ( fixture ) => {
		let runtime = await anonymousPage( browser, lane );
		try {
			await submitWpLogin( runtime.page, fixture.login_path, fixture.user_login, fixture.user_pass );
			const checkbox = runtime.page.locator( 'input[name="skip_mfa"]' );
			await expect( checkbox ).toBeVisible();
			await checkbox.check();
			await expect( checkbox ).toBeChecked();

			const beforeOtp = await fixtureApi.inspectLoginGuardCoreFixture();
			await fillOtp( runtime.page, fixture.otp_field_name, beforeOtp.current_otp || fixture.current_otp );
			await submitMfaForm( runtime.page );
			await expect( runtime.page ).toHaveURL( /\/wp-admin\// );

			const afterFirstLogin = await fixtureApi.inspectLoginGuardCoreFixture();
			expect( afterFirstLogin.hash_loginmfa_count ).toBe( 1 );
			expect( afterFirstLogin.login_intents_count ).toBe( 0 );
			expect( afterFirstLogin.event_counts[ '2fa_success' ] ).toBeGreaterThanOrEqual( 1 );
			expect( afterFirstLogin.event_counts[ '2fa_verify_success' ] ).toBeGreaterThanOrEqual( 1 );
		}
		finally {
			await runtime.context.close();
		}

		runtime = await anonymousPage( browser, lane );
		try {
			await submitWpLogin( runtime.page, fixture.login_path, fixture.user_login, fixture.user_pass );
			await expect( runtime.page ).toHaveURL( /\/wp-admin\// );

			const afterSecondLogin = await fixtureApi.inspectLoginGuardCoreFixture();
			expect( afterSecondLogin.hash_loginmfa_count ).toBe( 1 );
			expect( afterSecondLogin.login_intents_count ).toBe( 0 );
		}
		finally {
			await runtime.context.close();
		}
	} );
} );

test( 'email authentication sends local OTP, invalidates resend, and validates through login form', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withLoginGuardCoreFixture( 'email-auth-login', async ( fixture ) => {
		const { context, page } = await anonymousPage( browser, lane );
		try {
			await submitWpLogin( page, fixture.login_path, fixture.user_login, fixture.user_pass );

			const firstMail = await waitForInspection(
				fixtureApi,
				( inspected ) => inspected.mail_count === 1 && inspected.mfa_record_counts.email === 1,
				'first email OTP'
			);
			const firstOtp = firstMail.latest_email_query[ firstMail.email_otp_field_name ];

			await page.locator( '#ajax_intent_email_send' ).click();
			const secondMail = await waitForInspection(
				fixtureApi,
				( inspected ) => inspected.mail_count === 2 && inspected.mfa_record_counts.email === 1,
				'second email OTP'
			);
			const latestQuery = secondMail.latest_email_query;
			const latestOtp = latestQuery[ secondMail.email_otp_field_name ];

			expect( secondMail.mail_recipients ).toContain( `${ fixture.user_login }@example.test` );
			expect( latestQuery.action ).toBe( 'shield_action' );
			expect( latestQuery.ex ).toBe( 'mfa_email_auto_login' );
			expect( latestQuery.login_nonce ).toBeTruthy();
			expect( latestQuery.user_id ).toBe( String( fixture.user_id ) );
			expect( latestQuery ).toHaveProperty( 'redirect_to' );
			expect( latestOtp ).toBeTruthy();
			expect( latestOtp ).not.toBe( firstOtp );

			await fillOtp( page, fixture.otp_field_name, latestOtp );
			await submitMfaForm( page );
			await expect( page ).toHaveURL( /\/wp-admin\// );

			const inspected = await fixtureApi.inspectLoginGuardCoreFixture();
			expect( inspected.login_intents_count ).toBe( 0 );
			expect( inspected.event_counts[ '2fa_success' ] ).toBeGreaterThanOrEqual( 1 );
			expect( inspected.event_counts[ '2fa_verify_success' ] ).toBeGreaterThanOrEqual( 1 );
		}
		finally {
			await context.close();
		}
	} );
} );
