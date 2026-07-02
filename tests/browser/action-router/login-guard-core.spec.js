const { test, expect } = require( './support/shield-test' );

const MFA_VERIFY_PAGE_SHIELD = 'custom_shield';
const MFA_VERIFY_PAGE_WP_LOGIN = 'wp_login';

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

async function otpSegmentsValue( page, fieldName ) {
	const input = page.locator( `input[name="${ fieldName }"]` ).first();
	await input.waitFor( { state: 'attached', timeout: 20_000 } );
	const inputId = await input.getAttribute( 'id' );
	const segments = page.locator( `[data-otp-group][data-otp-target="${ inputId }"] input[data-otp]` );
	await expect( segments ).toHaveCount( 6 );
	return segments.evaluateAll( ( inputs ) => inputs.map( ( input ) => input.value ).join( '' ) );
}

async function submitMfaForm( page ) {
	const shieldSubmit = page.locator( '#mainSubmit' );
	const submit = await shieldSubmit.isVisible().catch( () => false )
		? shieldSubmit
		: page.locator( 'input[type="submit"][name="wp-submit"]' );

	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ).catch( () => null ),
		submit.click(),
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

async function clickRememberMeLabelText( page, checkbox ) {
	await expect( page.locator( 'label[for="skip_mfa"]' ) ).toBeVisible();
	await checkbox.scrollIntoViewIfNeeded();
	const checkboxBox = await checkbox.boundingBox();
	if ( checkboxBox === null ) {
		throw new Error( 'Remember-me checkbox has no clickable bounding box.' );
	}

	await page.mouse.click( checkboxBox.x + checkboxBox.width + 24, checkboxBox.y + ( checkboxBox.height / 2 ) );
}

async function assertRememberMeLoginFlow( browser, lane, fixtureApi, scenario, options = {} ) {
	await fixtureApi.withLoginGuardCoreFixture( scenario, async ( fixture ) => {
		if ( options.mfaVerifyPage ) {
			expect( fixture.mfa_verify_page ).toBe( options.mfaVerifyPage );
		}

		let runtime = await anonymousPage( browser, lane );
		try {
			await submitWpLogin( runtime.page, fixture.login_path, fixture.user_login, fixture.user_pass );
			if ( options.wpReplica ) {
				await expect( runtime.page.locator( 'form.shield-2fa-wplogin' ) ).toBeVisible();
			}

			const checkbox = runtime.page.locator( 'input[name="skip_mfa"]' );
			await expect( checkbox ).toBeVisible();
			await expect( checkbox ).toBeEnabled();
			await checkbox.click();
			await expect( checkbox ).toBeChecked();

			if ( options.clickLabelText ) {
				await checkbox.click();
				await expect( checkbox ).not.toBeChecked();
				await clickRememberMeLabelText( runtime.page, checkbox );
				await expect( checkbox ).toBeChecked();
			}

			const beforeOtp = await fixtureApi.inspectLoginGuardCoreFixture();
			if ( options.mfaVerifyPage ) {
				expect( beforeOtp.option_state.mfa_verify_page ).toBe( options.mfaVerifyPage );
			}
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
}

async function submitInvalidEmailOtpAndAssertCleared( page, fixtureApi, fixture, label ) {
	await page.locator( '#ajax_intent_email_send' ).click();
	const firstMail = await waitForInspection(
		fixtureApi,
		( inspected ) => inspected.mail_count === 1 && inspected.mfa_record_counts.email === 1,
		label
	);
	const emailOtp = firstMail.latest_email_query[ fixture.email_otp_field_name ];
	expect( emailOtp ).toBeTruthy();
	const invalidEmailOtp = emailOtp === 'AAAAAA' ? 'BBBBBB' : 'AAAAAA';

	await fillOtp( page, fixture.email_otp_field_name, invalidEmailOtp );
	await submitMfaForm( page );

	await expect( page.locator( `input[name="${ fixture.email_otp_field_name }"]` ).first() ).toHaveValue( '' );
	expect( await otpSegmentsValue( page, fixture.email_otp_field_name ) ).toBe( '' );
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
	await assertRememberMeLoginFlow( browser, lane, fixtureApi, 'remember-me', {
		mfaVerifyPage: MFA_VERIFY_PAGE_SHIELD,
	} );
} );

test( 'remember-me checkbox works on the WP-login replica MFA form', async ( { browser, lane, fixtureApi } ) => {
	await assertRememberMeLoginFlow( browser, lane, fixtureApi, 'remember-me-wp-login', {
		clickLabelText: true,
		mfaVerifyPage: MFA_VERIFY_PAGE_WP_LOGIN,
		wpReplica: true,
	} );
} );

test( 'authenticator verification works from multi-provider login with email untouched', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withLoginGuardCoreFixture( 'email-plus-ga-login', async ( fixture ) => {
		const { context, page } = await anonymousPage( browser, lane );
		try {
			await submitWpLogin( page, fixture.login_path, fixture.user_login, fixture.user_pass );

			await page.locator( 'button[data-tab="ga"]' ).click();
			const inspected = await fixtureApi.inspectLoginGuardCoreFixture();
			await fillOtp( page, fixture.ga_otp_field_name, inspected.current_otp || fixture.current_ga_otp );
			await submitMfaForm( page );
			await expect( page ).toHaveURL( /\/wp-admin\// );

			const afterLogin = await fixtureApi.inspectLoginGuardCoreFixture();
			expect( afterLogin.login_intents_count ).toBe( 0 );
			expect( afterLogin.event_counts[ '2fa_success' ] ).toBeGreaterThanOrEqual( 1 );
			expect( afterLogin.event_counts[ '2fa_verify_success' ] ).toBeGreaterThanOrEqual( 1 );
		}
		finally {
			await context.close();
		}
	} );
} );

test( 'email OTP failure clears stale email input before authenticator verification', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withLoginGuardCoreFixture( 'email-plus-ga-login', async ( fixture ) => {
		const { context, page } = await anonymousPage( browser, lane );
		try {
			await submitWpLogin( page, fixture.login_path, fixture.user_login, fixture.user_pass );

			await submitInvalidEmailOtpAndAssertCleared(
				page,
				fixtureApi,
				fixture,
				'email OTP for email-plus-GA login'
			);
			await page.locator( 'button[data-tab="ga"]' ).click();
			const inspected = await fixtureApi.inspectLoginGuardCoreFixture();
			await fillOtp( page, fixture.ga_otp_field_name, inspected.current_otp || fixture.current_ga_otp );
			await submitMfaForm( page );
			await expect( page ).toHaveURL( /\/wp-admin\// );

			const afterLogin = await fixtureApi.inspectLoginGuardCoreFixture();
			expect( afterLogin.login_intents_count ).toBe( 0 );
			expect( afterLogin.event_counts[ '2fa_success' ] ).toBeGreaterThanOrEqual( 1 );
			expect( afterLogin.event_counts[ '2fa_verify_success' ] ).toBeGreaterThanOrEqual( 1 );
		}
		finally {
			await context.close();
		}
	} );
} );

test( 'email OTP failure clears stale email input before backup code verification', async ( { browser, lane, fixtureApi } ) => {
	await fixtureApi.withLoginGuardCoreFixture( 'email-plus-backup-login', async ( fixture ) => {
		const { context, page } = await anonymousPage( browser, lane );
		try {
			await submitWpLogin( page, fixture.login_path, fixture.user_login, fixture.user_pass );

			await submitInvalidEmailOtpAndAssertCleared(
				page,
				fixtureApi,
				fixture,
				'email OTP for email-plus-backup login'
			);
			await page.locator( 'button[data-tab="backupcode"]' ).click();
			await fillOtp( page, fixture.backup_otp_field_name, fixture.backup_code );
			await submitMfaForm( page );
			await expect( page ).toHaveURL( /\/wp-admin\// );

			const afterLogin = await fixtureApi.inspectLoginGuardCoreFixture();
			expect( afterLogin.login_intents_count ).toBe( 0 );
			expect( afterLogin.mfa_record_counts.backupcode || 0 ).toBe( 0 );
			expect( afterLogin.event_counts[ '2fa_success' ] ).toBeGreaterThanOrEqual( 1 );
			expect( afterLogin.event_counts[ '2fa_verify_success' ] ).toBeGreaterThanOrEqual( 1 );
		}
		finally {
			await context.close();
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
