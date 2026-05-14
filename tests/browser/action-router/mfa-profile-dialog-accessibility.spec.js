const { AxeBuilder, test, expect } = require( './support/shield-test' );

function requestParams( request ) {
	return new URLSearchParams( request.postData() || '' );
}

function requestMatchesPayload( request, payload ) {
	if ( request.method() !== 'POST' || !request.url().includes( '/admin-ajax.php' ) ) {
		return false;
	}

	const params = requestParams( request );
	return Object.entries( payload ).every( ( [ key, value ] ) => {
		if ( [ 'ajaxurl', '_wpnonce', '_rest_url' ].includes( key ) || typeof value === 'object' ) {
			return true;
		}
		return params.get( key ) === String( value );
	} );
}

function isMfaProfileRenderRequest( request ) {
	if ( request.method() !== 'POST' || !request.url().includes( '/admin-ajax.php' ) ) {
		return false;
	}

	const params = requestParams( request );
	return params.get( 'action' ) === 'shield_action'
		&& params.get( 'ex' ) === 'ajax_render'
		&& params.get( 'render_slug' ) === 'user_mfa_config_form';
}

function parseShieldAjaxPayload( raw ) {
	const openJsonTag = '##APTO_OPEN##';
	const closeJsonTag = '##APTO_CLOSE##';
	const openIndex = raw.indexOf( openJsonTag );
	const closeIndex = raw.lastIndexOf( closeJsonTag );
	if ( openIndex < 0 || closeIndex <= openIndex ) {
		throw new Error( 'Shield AJAX response was not wrapped.' );
	}

	return JSON.parse( raw.substring( openIndex + openJsonTag.length, closeIndex ) );
}

async function waitForMfaProfileRenderResponse( page ) {
	return page.waitForResponse( ( candidate ) => {
		return candidate.ok() && isMfaProfileRenderRequest( candidate.request() );
	}, { timeout: 20_000 } );
}

async function renderDataFromResponse( response ) {
	const payload = parseShieldAjaxPayload( await response.text() );
	const renderData = payload?.data?.render_data;
	if ( !renderData?.vars?.providers ) {
		throw new Error( 'MFA profile render response did not include provider data.' );
	}
	return renderData;
}

async function userprofileBootstrapData( page ) {
	return page.evaluate( () => window.shield_vars_userprofile.comps.userprofile );
}

async function openMfaProfile( page, fixture ) {
	const renderResponse = waitForMfaProfileRenderResponse( page );
	await page.goto( fixture.profile_path, { waitUntil: 'load' } );
	await expect( page.locator( '#ShieldMfaUserProfileForm' ) ).toBeVisible();
	const renderData = await renderDataFromResponse( await renderResponse );
	await expect( page.locator( '#ShieldUserProfileMFA' ) ).toBeVisible( { timeout: 20_000 } );
	return renderData;
}

async function openMfaEditProfile( page, fixture ) {
	await page.goto( fixture.edit_path, { waitUntil: 'load' } );
	await expect( page.locator( '.shield_user_mfa_container' ).first() ).toBeVisible();
	await expect( page.locator( '.shield_mfa_remove_all' ) ).toBeVisible( { timeout: 20_000 } );
}

async function expectConnectedReference( page, element, attribute, { visible = false } = {} ) {
	const referenceId = await element.getAttribute( attribute );
	expect( referenceId || '' ).not.toHaveLength( 0 );
	const reference = page.locator( `#${referenceId}` );
	await expect( reference ).toHaveCount( 1 );
	if ( visible ) {
		await expect( reference ).toBeVisible();
	}
	return referenceId;
}

async function expectNamedMfaDialog( page ) {
	const dialog = page.locator( '[data-shield-accessible-dialog="1"]' );
	await expect( dialog ).toBeVisible();
	await expect( dialog ).toHaveAttribute( 'role', 'dialog' );
	await expect( dialog ).toHaveAttribute( 'aria-modal', 'true' );
	await expectConnectedReference( page, dialog, 'aria-labelledby' );
	await expect( dialog ).toHaveAccessibleName( /\S/ );
	expect( await dialog.evaluate( ( node ) => node.contains( document.activeElement ) ) ).toBe( true );
	return dialog;
}

async function expectMfaDialogTitleState( dialog, { hidden = false } = {} ) {
	const title = dialog.locator( '.shield-accessible-dialog__title' );
	await expect( dialog ).toHaveAccessibleName( /\S/ );
	if ( hidden ) {
		await expect( title ).toHaveClass( /__title--hidden/ );
	}
	else {
		await expect( title ).not.toHaveClass( /__title--hidden/ );
		await expect( title ).toBeVisible();
	}
}

async function expectMfaDialogHidden( page ) {
	const dialog = page.locator( '[data-shield-accessible-dialog="1"]' );
	await expect( dialog ).toHaveAttribute( 'aria-hidden', 'true' );
}

async function expectNoAxeViolations( page ) {
	const results = await new AxeBuilder( { page } )
	.include( '[data-shield-accessible-dialog="1"]:not([aria-hidden="true"])' )
	.analyze();

	expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
}

async function expectVisibleActionButtonsNamed( dialog, expectedCount ) {
	const buttons = dialog.locator(
		'.shield-accessible-dialog__actions button:not([hidden]):not([disabled]):not([aria-hidden="true"])'
	);

	await expect( buttons ).toHaveCount( expectedCount );
	for ( let index = 0; index < expectedCount; index++ ) {
		await expect( buttons.nth( index ) ).toBeVisible();
		await expect( buttons.nth( index ) ).toHaveAccessibleName( /\S/ );
	}
}

function installNativeDialogGuard( page ) {
	const nativeDialogs = [];
	page.on( 'dialog', async ( dialog ) => {
		nativeDialogs.push( dialog.type() );
		await dialog.dismiss().catch( () => null );
	} );
	return nativeDialogs;
}

test( 'backup-code confirm uses accessible modal and cancel does not send ajax', async ( { page, fixtureApi } ) => {
	const nativeDialogs = installNativeDialogGuard( page );
	await fixtureApi.withMfaProfileFixture( async ( fixture ) => {
		const renderData = await openMfaProfile( page, fixture );
		let matchingRequests = 0;
		page.on( 'request', ( request ) => {
			if ( requestMatchesPayload( request, renderData.vars.providers.backupcode.ajax.profile_backup_codes_gen ) ) {
				matchingRequests++;
			}
		} );

		const launcher = page.locator( '.shield-gen-backup-login-code' );
		await expect( launcher ).toBeVisible();
		await launcher.click();

		const dialog = await expectNamedMfaDialog( page );
		await expectMfaDialogTitleState( dialog );
		await expectVisibleActionButtonsNamed( dialog, 2 );
		await dialog.locator( '.shield-accessible-dialog__cancel' ).click();
		await expectMfaDialogHidden( page );
		await expect( launcher ).toBeFocused();
		expect( matchingRequests ).toBe( 0 );
		expect( nativeDialogs ).toEqual( [] );
	} );
} );

test( 'invalid yubikey alert does not expose an empty cancel action', async ( { page, fixtureApi } ) => {
	const nativeDialogs = installNativeDialogGuard( page );
	await fixtureApi.withMfaProfileFixture( async ( fixture ) => {
		await openMfaProfile( page, fixture );

		const launcher = page.locator( 'input.shield_yubi_otp' );
		await expect( launcher ).toBeVisible();
		await launcher.fill( 'bad' );
		await launcher.press( 'Enter' );

		const dialog = await expectNamedMfaDialog( page );
		await expectMfaDialogTitleState( dialog, { hidden: true } );
		await expectVisibleActionButtonsNamed( dialog, 1 );
		expect( await dialog.locator( '.shield-accessible-dialog__cancel' ).evaluate( ( button ) => {
			return button.hidden
				&& button.disabled
				&& button.getAttribute( 'aria-hidden' ) === 'true'
				&& window.getComputedStyle( button ).display === 'none';
		} ) ).toBe( true );
		await dialog.locator( '.shield-accessible-dialog__confirm' ).click();
		await expectMfaDialogHidden( page );
		await expect( launcher ).toBeFocused();
		expect( nativeDialogs ).toEqual( [] );
	} );
} );

test( 'backup-code confirm sends expected action payload', async ( { page, fixtureApi } ) => {
	const nativeDialogs = installNativeDialogGuard( page );
	await fixtureApi.withMfaProfileFixture( async ( fixture ) => {
		const renderData = await openMfaProfile( page, fixture );
		const payload = renderData.vars.providers.backupcode.ajax.profile_backup_codes_gen;

		await page.locator( '.shield-gen-backup-login-code' ).click();
		const dialog = await expectNamedMfaDialog( page );
		const request = page.waitForRequest( ( req ) => requestMatchesPayload( req, payload ), { timeout: 20_000 } );
		await dialog.locator( '.shield-accessible-dialog__confirm' ).click();
		await request;
		expect( nativeDialogs ).toEqual( [] );
	} );
} );

test( 'yubikey label prompt is labelled and validates inline', async ( { page, fixtureApi } ) => {
	const nativeDialogs = installNativeDialogGuard( page );
	await fixtureApi.withMfaProfileFixture( async ( fixture ) => {
		const renderData = await openMfaProfile( page, fixture );
		let matchingRequests = 0;
		page.on( 'request', ( request ) => {
			if ( requestMatchesPayload( request, renderData.vars.providers.yubi.ajax.profile_yubikey_toggle ) ) {
				matchingRequests++;
			}
		} );

		const launcher = page.locator( 'input.shield_yubi_otp' );
		await expect( launcher ).toBeVisible();
		await launcher.fill( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' );
		await launcher.press( 'Enter' );

		const dialog = await expectNamedMfaDialog( page );
		const input = dialog.locator( '#ShieldMfaDialogInput' );
		const inputLabel = dialog.locator( 'label[for="ShieldMfaDialogInput"]' );
		await expect( inputLabel ).toHaveCount( 1 );
		await expect( input ).toHaveAccessibleName( /\S/ );

		await input.fill( 'bad!' );
		await dialog.locator( '.shield-accessible-dialog__confirm' ).click();
		await expect( input ).toHaveAttribute( 'aria-invalid', 'true' );
		const validationId = await expectConnectedReference( page, input, 'aria-describedby', { visible: true } );
		await expect( page.locator( `#${validationId}` ) ).toHaveAttribute( 'role', 'alert' );

		await dialog.locator( '.shield-accessible-dialog__cancel' ).click();
		await expectMfaDialogHidden( page );
		await expect( launcher ).toBeFocused();
		expect( matchingRequests ).toBe( 0 );
		expect( nativeDialogs ).toEqual( [] );
	} );
} );

test( 'remove-all confirm on user-edit profile uses accessible modal', async ( { page, fixtureApi } ) => {
	const nativeDialogs = installNativeDialogGuard( page );
	await fixtureApi.withMfaProfileFixture( async ( fixture ) => {
		await openMfaEditProfile( page, fixture );
		const data = await userprofileBootstrapData( page );
		let matchingRequests = 0;
		page.on( 'request', ( request ) => {
			if ( requestMatchesPayload( request, data.ajax.mfa_remove_all ) ) {
				matchingRequests++;
			}
		} );

		const launcher = page.locator( '.shield_mfa_remove_all' );
		await launcher.click();

		const dialog = await expectNamedMfaDialog( page );
		await dialog.locator( '.shield-accessible-dialog__cancel' ).click();
		await expectMfaDialogHidden( page );
		await expect( launcher ).toBeFocused();
		expect( matchingRequests ).toBe( 0 );
		expect( nativeDialogs ).toEqual( [] );
	} );
} );

test( 'active MFA profile dialog has no scoped axe violations', async ( { page, fixtureApi } ) => {
	const nativeDialogs = installNativeDialogGuard( page );
	await fixtureApi.withMfaProfileFixture( async ( fixture ) => {
		await openMfaProfile( page, fixture );

		await page.locator( '.shield-gen-backup-login-code' ).click();
		const dialog = await expectNamedMfaDialog( page );
		await expectNoAxeViolations( page );
		await dialog.locator( '.shield-accessible-dialog__cancel' ).click();
		await expectMfaDialogHidden( page );
		expect( nativeDialogs ).toEqual( [] );
	} );
} );
